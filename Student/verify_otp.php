<?php
require_once '../Database/db_connect.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_otp'])) {
    $entered_otp = trim($_POST['otp']);

    if (empty($entered_otp)) {
        $error = "Please enter the OTP.";
    } else {
        // Fetch student by OTP
        $stmt = $conn->prepare("SELECT student_id, student_phone, student_otp, voting_status FROM student WHERE student_otp = ?");
        $stmt->bind_param("s", $entered_otp);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $error = "Invalid OTP. Please try again.";
        } else {
            $row = $result->fetch_assoc();
            if ($row['voting_status'] == 1) {
                $error = "You have already voted.";
            } else {
                // OTP matched – log in the student (start session)
                session_start();
                $_SESSION['student_id'] = $row['student_id'];
                $_SESSION['student_verified'] = true;
                $_SESSION['student_mobile'] = $row['student_phone'];
                $success = "OTP verified successfully! Redirecting to dashboard...";
                header("refresh:2;url=dashboard.php");
                exit();
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
    <title>Verify OTP · HDCVotes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/custom.css">
    <link rel="icon" href="../assets/img/logo.png">
</head>
<body class="auth-body">
    <div class="auth-card">
        <div class="auth-card-head">
            <div class="auth-icon"><img src="../assets/img/logo.png" alt="Himalaya Darshan College" class="brand-logo brand-logo-md"></div>
            <h4>Enter OTP</h4>
            <p>Enter the 6-digit code sent to your phone</p>
        </div>
        <div class="auth-card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($success) ?></div>
            <?php else: ?>
                <form method="POST">
                    <div class="mb-4">
                        <label for="otp" class="form-label">OTP Code</label>
                        <input type="text" class="form-control otp-input text-center" id="otp" name="otp"
                               maxlength="6" inputmode="numeric" placeholder="Enter 6-digit OTP" 
                               required autofocus style="font-size: 24px; letter-spacing: 8px;">
                        <div class="form-text text-center mt-2">Enter the OTP you received on your registered phone number.</div>
                    </div>
                    <button type="submit" name="verify_otp" class="btn btn-success w-100"><i class="bi bi-unlock-fill me-1"></i>Verify OTP</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>