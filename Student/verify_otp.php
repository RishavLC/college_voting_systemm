<?php
session_start();
require_once '../Database/db_connect.php';

// Check if we have a student_id in session (from previous step)
if (!isset($_SESSION['verify_student_id']) || !isset($_SESSION['verify_mobile'])) {
    header("Location: student_verify.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_otp'])) {
    $entered_otp = trim($_POST['otp']);
    $student_id = $_SESSION['verify_student_id'];
    
    // Fetch stored OTP from DB
    $stmt = $conn->prepare("SELECT student_otp FROM student WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row && $row['student_otp'] == $entered_otp) {
        // OTP matched – mark as verified (update voting_status or create session)

        // Record in otp_requests that this OTP has now been used/verified.
        $mark = $conn->prepare("UPDATE otp_requests SET is_used = 1, used_at = NOW(), status = 'verified' WHERE student_id = ? AND otp = ?");
        $mark->bind_param("is", $student_id, $entered_otp);
        $mark->execute();

        $_SESSION['student_verified'] = true;
        $_SESSION['student_id'] = $student_id;
        $_SESSION['student_mobile'] = $_SESSION['verify_mobile'];
        unset($_SESSION['verify_student_id']);
        unset($_SESSION['verify_mobile']);
        $success = "OTP verified successfully! You are now logged in.";
        // Redirect to dashboard or voting page after 2 seconds
        header("refresh:2;url=dashboard.php"); // placeholder – you can create dashboard later
    } else {
        $error = "Invalid OTP. Please try again.";
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
        <div class="auth-card-head" >
            <div class="auth-icon"><img src="../assets/img/logo.png" alt="Himalaya Darshan College" class="brand-logo brand-logo-md"></div>
            <h4>Enter OTP</h4>
            <p>One-time code sent to your phone</p>
        </div>
        <div class="auth-card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($success) ?></div>
            <?php else: ?>
                <p class="text-muted small">A 6-digit OTP has been sent to your mobile number (<strong><?= htmlspecialchars($_SESSION['verify_mobile']) ?></strong>).</p>
                <form method="POST">
                    <div class="mb-3">
                        <label for="otp">OTP</label>
                        <input type="text" id="otp" name="otp" class="form-control otp-input" maxlength="6" inputmode="numeric" autocomplete="one-time-code" required>
                    </div>
                    <button type="submit" name="verify_otp" class="btn btn-success w-100"><i class="bi bi-unlock-fill me-1"></i>Verify OTP</button>
                </form>
                <p class="auth-footnote"><a href="student_verify.php"><i class="bi bi-arrow-left"></i> Go back</a></p>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
