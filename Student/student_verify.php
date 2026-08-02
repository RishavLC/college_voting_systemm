<?php
require_once '../Database/db_connect.php';

$error = '';
$mobile = '';
$otp_sent = false;
$otp_exists = false;

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
        $check = $conn->query("SELECT student_id FROM student WHERE student_otp = '$otp'");
    } while ($check->num_rows > 0);
    return $otp;
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
                $existing_otp = $student['student_otp'];
                $voting_status = $student['voting_status'];
                $is_present = $student['is_present'];

                // Rule 1: Must be marked present
                if (!$is_present) {
                    $error = "not_present";
                } elseif ($voting_status == 1) {
                    $error = "already_voted";
                } elseif (!empty($existing_otp)) {
                    // OTP already exists – show message, do nothing else
                    $otp_exists = true;
                    $error = "otp_exists," . $existing_otp; // trigger custom message
                } else {
                    // No OTP – generate and store (mock SMS)
                    $otp = generateUniqueOTP($conn);
                    $update = $conn->prepare("UPDATE student SET student_otp = ? WHERE student_id = ?");
                    $update->bind_param("si", $otp, $student_id);
                    if ($update->execute()) {
                        $otp_sent = true; // mock sent
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
                <div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill me-1"></i>
                    <strong>OTP already exists.(<?php echo $otp; ?>)</strong> Please contact the admin desk for your OTP.
                </div>
            <?php elseif ($error && $error != 'not_registered'): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($otp_sent): ?>
                <div class="alert alert-success text-center">
                    <i class="bi bi-check-circle-fill fs-3 d-block mb-1"></i>
                    <strong>OTP sent!</strong><br>
                    We've generated a 6-digit OTP and stored it in the system.(<?php echo $otp; ?>)<br>
                    <small class="text-muted">(Mock SMS – no real message was sent.)</small>
                </div>
            <?php endif; ?>

            <?php if (!$otp_sent && $error !== 'otp_exists' && $error !== 'already_voted' && $error !== 'not_present'): ?>
            <form method="POST">
                <div class="mb-3">
                    <label for="mobile">Registered mobile number</label>
                    <input type="tel" id="mobile" name="mobile" class="form-control"
                           placeholder="e.g., 98XXXXXXXX" value="<?= htmlspecialchars($mobile) ?>" required>
                </div>
                <button type="submit" name="verify" class="btn btn-primary w-100"><i class="bi bi-send-fill me-1"></i>Verify & Send OTP</button>
            </form>
            <p class="auth-footnote">You will receive a 6-digit OTP (simulated).</p>
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