<?php
session_start();
session_destroy(); 
session_start();

require_once '../Database/db_connect.php';

$message = '';
$error = '';
$mobile = '';
$success_otp = ''; // will hold OTP if generated

// Mock SMS function
function sendSMS($phone, $otp) {
    $log = "OTP for $phone: $otp\n";
    file_put_contents('sms_log.txt', $log, FILE_APPEND);
    return true;
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
    $mobile = trim($_POST['mobile']);
    if (empty($mobile)) {
        $error = "Please enter your mobile number.";
    } else {
        $stmt = $conn->prepare("SELECT student_id, student_name, is_present FROM student WHERE student_phone = ?");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

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
                        $sms_sent = sendSMS($mobile, $otp);
                        if ($sms_sent) {
                            // Log the request in otp_requests (source of truth
                            // for the "one OTP per student" rule and what the
                            // admin looks up to manually give a student their
                            // OTP if they can't request it again).
                            $log = $conn->prepare("INSERT INTO otp_requests (student_id, mobile, otp, status) VALUES (?, ?, ?, 'sent')");
                            $log->bind_param("iss", $student_id, $mobile, $otp);
                            $log->execute();

                            $_SESSION['verify_student_id'] = $student_id;
                            $_SESSION['verify_mobile'] = $mobile;
                            // Store OTP in session to display on this page
                            $_SESSION['generated_otp'] = $otp;
                            $success_otp = $otp; // for immediate display
                        } else {
                            $error = "Failed to send SMS. Please try again.";
                        }
                    } else {
                        $error = "Database error. Please try again.";
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
    <?php if ($success_otp): ?>
        <!-- Auto-redirect after 3 seconds -->
        <meta http-equiv="refresh" content="3;url=verify_otp.php">
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

            <?php if ($success_otp): ?>
                <div class="alert alert-success text-center">
                    <strong>OTP generated successfully!</strong><br>
                    Your OTP is: <span class="fw-bold fs-3 d-inline-block mt-1" style="letter-spacing:.3em;"><?= $success_otp ?></span><br>
                    <small class="text-muted">Redirecting to the verification page in 3 seconds…</small>
                </div>
            <?php endif; ?>

            <?php if (!$success_otp && $error !== 'already_requested'): // hide form once the student's one OTP request has been used ?>
            <form method="POST">
                <div class="mb-3">
                    <label for="mobile">Registered mobile number</label>
                    <input type="tel" id="mobile" name="mobile" class="form-control" 
                           placeholder="e.g., 9876543210" value="<?= htmlspecialchars($mobile) ?>" required>
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
