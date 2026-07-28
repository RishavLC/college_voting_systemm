<?php
session_start();
session_destroy(); 
session_start();

require_once '../Database/db_connect.php';
require_once '../Database/sms_config.php';

$message = '';
$error = '';
$mobile = '';
$otp_sent = false; // true once an OTP has actually been sent via SMS (never shown on screen)

// Normalize whatever the student typed (+9779800000000, 9779800000000,
// 9800000000 ...) down to the plain 10-digit local number that is stored
// in the `student` table, e.g. 9800000000.
function normalizePhone($input) {
    $p = preg_replace('/[^0-9]/', '', trim($input)); // strip spaces, +, dashes etc.
    if (strlen($p) === 13 && str_starts_with($p, '977')) {
        $p = substr($p, 3); // 9779800000000 -> 9800000000
    }
    return $p;
}

// Nepali mobile numbers: 10 digits, starting with 97 or 98 (e.g. 9800000000, 9700000000)
function isValidNepaliMobile($p) {
    return (bool) preg_match('/^9[78]\d{8}$/', $p);
}

// Send a real OTP SMS via the VerifiedSMS API.
// $phone_local must already be the normalized 10-digit local number.
// Returns ['success' => bool, 'reason' => string] — reason is only for
// logs / debug display, never shown to students directly.
function sendSMS($phone_local, $otp) {
    $destination = '+977' . $phone_local;
    $message = "Your HDCVotes OTP is $otp. Do not share this code with anyone.";

    $ch = curl_init(SMS_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'key'         => SMS_API_KEY,
            'destination' => $destination,
            'message'     => $message,
            'type'        => 3, // 3 = OTP message type per VerifiedSMS docs
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Keep a debug/audit trail of send attempts. The OTP itself is logged
    // here for admin troubleshooting only — this file is never shown to
    // students and is not the source of truth (otp_requests table is).
    $logLine = date('Y-m-d H:i:s') . " | dest=$destination | otp=$otp | http=$httpCode | " .
               ($curlErr ? "curl_error=$curlErr" : "response=$response") . "\n";
    file_put_contents('sms_log.txt', $logLine, FILE_APPEND);

    if ($curlErr) {
        // cURL couldn't even reach the API — usually a network/firewall
        // problem, missing php-curl extension, or DNS/SSL issue on the
        // server, not a problem with the phone number.
        return ['success' => false, 'reason' => "Network error calling SMS API: $curlErr"];
    }

    $data = json_decode($response, true);

    if (!is_array($data)) {
        // The API didn't return valid JSON at all (e.g. an HTML error
        // page, or the URL/endpoint is wrong).
        return ['success' => false, 'reason' => "Unexpected response (HTTP $httpCode): " . substr((string)$response, 0, 200)];
    }

    if (isset($data['status']) && $data['status'] === 'success') {
        return ['success' => true, 'reason' => 'sent'];
    }

    // API responded but rejected the request — key invalid, no balance,
    // bad phone format, rate-limited, etc. $data['message'] has the
    // specific reason straight from VerifiedSMS.
    $apiMessage = $data['message'] ?? 'Unknown API error';
    return ['success' => false, 'reason' => "API rejected request (HTTP $httpCode): $apiMessage"];
}

// Generate unique OTP
function generateUniqueOTP($conn) {
    do {
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $check = $conn->query("SELECT student_id FROM student WHERE student_otp = '$otp'");
    } while ($check->num_rows > 0);
    return $otp;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify'])) {
    $mobileRaw = trim($_POST['mobile']);
    if (empty($mobileRaw)) {
        $error = "Please enter your mobile number.";
    } else {
        $mobile = normalizePhone($mobileRaw);

        if (!isValidNepaliMobile($mobile)) {
            $error = "Please enter a valid 10-digit mobile number starting with 97 or 98 (e.g., 98XXXXXXXX or 97XXXXXXXX).";
        } else {
        $stmt = $conn->prepare("SELECT student_id, student_name, is_present FROM student WHERE student_phone = ?");
        $stmt->bind_param("s", $mobile);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows == 0) {
            $error = "not_registered";
        } else {
            $student = $result->fetch_assoc();
            $student_id = $student['student_id'];

            // Rule 1: student must be marked PRESENT by the admin before an
            // OTP can be requested. This prevents fake / remote votes from
            // students who never actually showed up.
            if (!$student['is_present']) {
                $error = "not_present";
            } else {
                // Rule 2: a student can only ever request an OTP once. If a
                // row already exists for them in otp_requests, block the
                // request and tell them to see the admin instead.
                $check = $conn->prepare("SELECT otp_id FROM otp_requests WHERE student_id = ?");
                $check->bind_param("i", $student_id);
                $check->execute();
                $already = $check->get_result();

                if ($already->num_rows > 0) {
                    $error = "already_requested";
                    // Still set up the verify session so the student can go
                    // enter the OTP that the admin gives them manually,
                    // without us generating/displaying a new one here.
                    $_SESSION['verify_student_id'] = $student_id;
                    $_SESSION['verify_mobile'] = $mobile;
                } else {
                    $otp = generateUniqueOTP($conn);

                    $update = $conn->prepare("UPDATE student SET student_otp = ? WHERE student_id = ?");
                    $update->bind_param("si", $otp, $student_id);
                    if ($update->execute()) {
                        $sms_result = sendSMS($mobile, $otp);
                        if ($sms_result['success']) {
                            // Log the request in otp_requests (source of truth
                            // for the "one OTP per student" rule and what the
                            // admin looks up to manually give a student their
                            // OTP if they can't request it again).
                            $log = $conn->prepare("INSERT INTO otp_requests (student_id, mobile, otp, status) VALUES (?, ?, ?, 'sent')");
                            $log->bind_param("iss", $student_id, $mobile, $otp);
                            $log->execute();

                            $_SESSION['verify_student_id'] = $student_id;
                            $_SESSION['verify_mobile'] = $mobile;
                            $otp_sent = true; // OTP was texted to the student's phone — never displayed here
                        } else {
                            // SMS failed to send: roll back the OTP we wrote to
                            // `student` so a retry can generate a fresh one,
                            // and do NOT log to otp_requests (the student never
                            // actually received a code, so this shouldn't count
                            // against their one-time allowance).
                            $conn->query("UPDATE student SET student_otp = NULL WHERE student_id = $student_id");
                            $error = "We couldn't send the SMS right now. Please try again in a moment, or contact an admin.";
                            if (defined('SMS_DEBUG') && SMS_DEBUG) {
                                $error .= " [DEBUG: " . $sms_result['reason'] . "]";
                            }
                        }
                    } else {
                        $error = "Database error. Please try again.";
                    }
                }
            }
        }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Verification · HDCVotes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/custom.css">
    <link rel="icon" href="../assets/img/logo.png">
    <?php if ($otp_sent): ?>
        <!-- Auto-redirect after 2 seconds -->
        <meta http-equiv="refresh" content="2;url=verify_otp.php">
    <?php endif; ?>
</head>
<body class="auth-body">
    <div class="auth-card">
        <div class="auth-card-head">
            <div class="auth-icon"><img src="../assets/img/logo.png" alt="Himalaya Darshan College" class="brand-logo brand-logo-md"></div>
            <h4>Phone Verification</h4>
            <p>Himalaya Darshan College &middot; Verify your number to access your ballot</p>
        </div>
        <div class="auth-card-body">
            <?php if ($error === 'not_present'): ?>
                <div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill me-1"></i>
                    You have not been marked <strong>present</strong> yet. Please check in at the admin desk
                    before requesting your OTP.
                </div>
            <?php elseif ($error === 'already_requested'): ?>
                <div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill me-1"></i>
                    Contact admins for OTP
                </div>
            <?php elseif ($error && $error != 'not_registered'): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($otp_sent): ?>
                <div class="alert alert-success text-center">
                    <i class="bi bi-check-circle-fill fs-3 d-block mb-1"></i>
                    <strong>OTP sent!</strong><br>
                    We've texted a 6-digit code to <strong><?= htmlspecialchars($mobile) ?></strong>.<br>
                    <small class="text-muted">Redirecting to the verification page in a moment…</small>
                </div>
            <?php endif; ?>

            <?php if (!$otp_sent && $error !== 'already_requested'): // hide form once the student's one OTP request has been used ?>
            <form method="POST">
                <div class="mb-3">
                    <label for="mobile">Registered mobile number</label>
                    <input type="tel" id="mobile" name="mobile" class="form-control"
                           placeholder="e.g., 98XXXXXXXX or 97XXXXXXXX" value="<?= htmlspecialchars($mobile) ?>" required>
                </div>
                <button type="submit" name="verify" class="btn btn-primary w-100"><i class="bi bi-send-fill me-1"></i>Verify & Send OTP</button>
            </form>
            <p class="auth-footnote">You will receive a 6-digit OTP via SMS. You can only request an OTP once — if you already have, please contact an admin.</p>
            <?php elseif ($error === 'already_requested'): ?>
                <p class="auth-footnote">Already have your OTP? <a href="verify_otp.php">Enter it here</a>.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal for "Mobile not registered" -->
    <div class="modal fade" id="notRegisteredModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Mobile Number Not Registered</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>The mobile number you entered is not registered in our system.</p>
                    <p>Please contact your admin to add your details.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($error == 'not_registered'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modal = new bootstrap.Modal(document.getElementById('notRegisteredModal'));
            modal.show();
        });
    </script>
    <?php endif; ?>
</body>
</html>
