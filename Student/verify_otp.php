<?php
require_once '../Database/db_connect.php';

// Normalize phone number (strip non-digits, remove 977 if present)
function normalizePhone($input) {
    $p = preg_replace('/[^0-9]/', '', trim($input));
    if (strlen($p) === 13 && substr($p, 0, 3) === '977') {
        $p = substr($p, 3);
    }
    return $p;
}

// Get phone from GET (link) or POST (form)
$phone = isset($_GET['phone']) ? trim($_GET['phone']) : (isset($_POST['phone']) ? trim($_POST['phone']) : '');
$phone_normalized = !empty($phone) ? normalizePhone($phone) : '';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_otp'])) {
    $entered_otp = trim($_POST['otp']);
    $phone_raw = trim($_POST['phone']);
    $phone_normalized = normalizePhone($phone_raw);

    if (empty($phone_normalized) || empty($entered_otp)) {
        $error = "Please provide both phone number and OTP.";
    } else {
        // Fetch student by phone
        $stmt = $conn->prepare("SELECT student_id, student_otp, voting_status FROM student WHERE student_phone = ?");
        $stmt->bind_param("s", $phone_normalized);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows == 0) {
            $error = "Phone number not registered.";
        } else {
            $row = $result->fetch_assoc();
            if ($row['voting_status'] == 1) {
                $error = "You have already voted.";
            } elseif ($row['student_otp'] == $entered_otp) {
                // OTP matched – log in the student (start session)
                session_start();
                $_SESSION['student_id'] = $row['student_id'];
                $_SESSION['student_verified'] = true;
                $_SESSION['student_mobile'] = $phone_normalized;
                $success = "OTP verified successfully! Redirecting to dashboard...";
                header("refresh:2;url=dashboard.php");
                exit();
            } else {
                $error = "Invalid OTP. Please try again.";
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
            <p>Verify your phone number</p>
        </div>
        <div class="auth-card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($success) ?></div>
            <?php else: ?>
                <form method="POST">
                    <div class="mb-3">
                        <label for="phone">Phone number</label>
                        <input type="tel" class="form-control" id="phone" name="phone"
                               placeholder="e.g., 9862887116" value="<?= htmlspecialchars($phone_normalized) ?>" required>
                        <div class="form-text">Enter the 10‑digit number you used to request the OTP.</div>
                    </div>
                    <div class="mb-3">
                        <label for="otp">OTP</label>
                        <input type="text" class="form-control otp-input" id="otp" name="otp"
                               maxlength="6" inputmode="numeric" placeholder="6-digit code" required>
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