<?php
require_once '../Database/db_connect.php';
require_once '../Database/sms_config.php';

$error = '';
$mobile = '';
$otp_sent = false;
$otp_already_exists = false;
$generated_otp = '';
$sms_response = '';

// Normalize phone
function normalizePhone($input) {
    $p = preg_replace('/[^0-9]/', '', trim($input));
    if (strlen($p) === 13 && str_starts_with($p, '977')) {
        $p = substr($p, 3);
    }
    return $p;
}

// Validate Nepali mobile
function isValidNepaliMobile($p) {
    return (bool) preg_match('/^9[78]\d{8}$/', $p);
}

// Generate unique OTP
function generateUniqueOTP($conn) {
    do {
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $stmt = $conn->prepare("SELECT student_id FROM student WHERE student_otp = ?");
        $stmt->bind_param("s", $otp);
        $stmt->execute();
        $result = $stmt->get_result();
    } while ($result->num_rows > 0);
    return $otp;
}

// Send SMS using VerifiedSMS API
function sendOTPviaSMS($phone, $otp, $student_name) {
    $message = "Hello $student_name, your OTP for voting is: $otp. Please use this code to verify your identity and cast your vote. - Himalaya Darshan College";
    
    $postData = [
        'key' => 'VERIFIEDSMScddc0f3ad2d8929d2d06159333295c0d',
        'destination' => '977' . $phone,
        'message' => $message
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, SMS_API_URL);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    error_log("SMS API Response: " . $response);
    error_log("HTTP Code: " . $httpCode);
    
    if ($curlError) {
        return ['success' => false, 'error' => "CURL Error: $curlError"];
    }
    
    $responseData = json_decode($response, true);
    
    if ($httpCode == 200 && isset($responseData['status']) && $responseData['status'] == 'success') {
        return ['success' => true, 'response' => $responseData];
    } else {
        $errorMsg = isset($responseData['message']) ? $responseData['message'] : 
                   (isset($responseData['error']) ? $responseData['error'] : 'Unknown error');
        return ['success' => false, 'error' => $errorMsg, 'response' => $responseData];
    }
}

// ---- Process form ----
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify'])) {
    $mobileRaw = trim($_POST['mobile']);
    if (empty($mobileRaw)) {
        $error = "Please enter your mobile number.";
    } else {
        $mobile = normalizePhone($mobileRaw);

        if (!isValidNepaliMobile($mobile)) {
            $error = "Please enter a valid 10-digit mobile number starting with 97 or 98 (e.g., 98XXXXXXXX).";
        } else {
            $stmt = $conn->prepare("SELECT student_id, student_name, is_present, student_otp, voting_status FROM student WHERE student_phone = ?");
            $stmt->bind_param("s", $mobile);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 0) {
                $error = "not_registered";
            } else {
                $student = $result->fetch_assoc();
                $student_id = $student['student_id'];
                $student_name = $student['student_name'];
                $existing_otp = $student['student_otp'];
                $voting_status = $student['voting_status'];
                $is_present = $student['is_present'];

                if (!$is_present) {
                    $error = "not_present";
                } elseif ($voting_status == 1) {
                    $error = "already_voted";
                } elseif (!empty($existing_otp)) {
                    // OTP already exists - NO RESEND! Show Contact Admin message
                    $otp_already_exists = true;
                    $error = "otp_exists";
                    $generated_otp = $existing_otp;
                    
                    // Log this attempt for admin tracking
                    error_log("Student $student_id ($student_name) attempted to get OTP again at " . date('Y-m-d H:i:s'));
                } else {
                    // No OTP – generate and send via SMS (ONLY FIRST TIME)
                    $otp = generateUniqueOTP($conn);
                    $generated_otp = $otp;
                    
                    $update = $conn->prepare("UPDATE student SET student_otp = ? WHERE student_id = ?");
                    $update->bind_param("si", $otp, $student_id);
                    
                    if ($update->execute()) {
                        $smsResult = sendOTPviaSMS($mobile, $otp, $student_name);
                        
                        if ($smsResult['success']) {
                            $otp_sent = true;
                            if (SMS_DEBUG) {
                                $sms_response = "OTP sent successfully via SMS";
                            }
                        } else {
                            // If SMS fails, delete the OTP from database so user can try again
                            $delete = $conn->prepare("UPDATE student SET student_otp = NULL WHERE student_id = ?");
                            $delete->bind_param("i", $student_id);
                            $delete->execute();
                            
                            $error = "sms_failed";
                            if (SMS_DEBUG) {
                                $sms_response = $smsResult['error'] ?? 'Unknown SMS error';
                                if (isset($smsResult['response'])) {
                                    $sms_response .= " | Full response: " . json_encode($smsResult['response']);
                                }
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
            <?php elseif ($error === 'already_voted'): ?>
                <div class="alert alert-warning"><i class="bi bi-check-circle-fill me-1"></i>
                    You have already voted. Thank you for participating!
                </div>
            <?php elseif ($error === 'otp_exists'): ?>
                <div class="alert alert-info text-center" style="border-left: 4px solid #0dcaf0;">
                    <i class="bi bi-info-circle-fill fs-2 d-block mb-2" style="color: #0dcaf0;"></i>
                    <h5><strong>OTP Already Generated</strong></h5>
                    <p class="mb-2">An OTP has already been sent to your registered mobile number.</p>
                    <div class="alert alert-secondary text-center" style="background-color: #f8f9fa;">
                        <i class="bi bi-person-badge me-1"></i>
                        <strong>Please contact the Admin Desk</strong> to get your OTP.
                    </div>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-clock-history me-1"></i>
                        For security reasons, OTP cannot be resent. 
                        The admin will verify your identity and provide the OTP.
                    </p>
                </div>
                <div class="text-center mt-3">
                    <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#contactAdminModal">
                        <i class="bi bi-person-badge me-1"></i>Contact Admin
                    </button>
                </div>
            <?php elseif ($error === 'sms_failed'): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i>
                    <strong>Failed to send OTP.</strong> 
                    <?php if (SMS_DEBUG && !empty($sms_response)): ?>
                        <br><small class="text-muted">Error: <?= htmlspecialchars($sms_response) ?></small>
                    <?php else: ?>
                        Please try again later or contact the admin desk.
                    <?php endif; ?>
                </div>
                <div class="text-center mt-3">
                    <a href="student_verify.php" class="btn btn-primary"><i class="bi bi-arrow-repeat me-1"></i>Try Again</a>
                </div>
            <?php elseif ($error && $error != 'not_registered'): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($otp_sent && $error !== 'sms_failed'): ?>
                <div class="alert alert-success text-center">
                    <i class="bi bi-check-circle-fill fs-3 d-block mb-1"></i>
                    <strong>OTP Sent Successfully!</strong><br>
                    A 6-digit OTP has been sent to your registered mobile number.<br>
                    <small class="text-muted">Please check your SMS inbox.</small>
                    <?php if (SMS_DEBUG && !empty($sms_response)): ?>
                        <br><small class="text-muted">[DEBUG] <?= htmlspecialchars($sms_response) ?></small>
                    <?php endif; ?>
                    <?php if (SMS_DEBUG && isset($generated_otp)): ?>
                        <br><small class="text-muted">[DEBUG] OTP: <?= $generated_otp ?></small>
                    <?php endif; ?>
                </div>
                <div class="alert alert-info text-center">
                    <i class="bi bi-info-circle me-1"></i>
                    <strong>Next Step:</strong> Use the OTP sent to your phone to verify your identity.
                </div>
            <?php endif; ?>

            <?php if (!$otp_sent && $error !== 'already_voted' && $error !== 'not_present' && $error !== 'otp_exists' && $error !== 'sms_failed'): ?>
            <form method="POST">
                <div class="mb-3">
                    <label for="mobile">Registered mobile number</label>
                    <input type="tel" id="mobile" name="mobile" class="form-control"
                           placeholder="e.g., 98XXXXXXXX" value="<?= htmlspecialchars($mobile) ?>" required>
                </div>
                <button type="submit" name="verify" class="btn btn-primary w-100"><i class="bi bi-send-fill me-1"></i>Request OTP</button>
            </form>
            <p class="auth-footnote">You will receive a 6-digit OTP via SMS on your registered mobile number.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal for "Mobile not registered" -->
    <div class="modal fade" id="notRegisteredModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Mobile Number Not Registered</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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

    <!-- Modal for "Contact Admin" -->
    <div class="modal fade" id="contactAdminModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="bi bi-person-badge me-2"></i>Contact Admin for OTP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="bi bi-shield-lock fs-1" style="color: #0dcaf0;"></i>
                    </div>
                    <h6>Why can't I get a new OTP?</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> One OTP per student for security</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Prevents multiple OTP requests</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Ensures secure voting process</li>
                    </ul>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Visit the Admin Desk</strong> with your student ID to get your OTP.
                    </div>
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