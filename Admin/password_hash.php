<?php
$hash = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $plain = $_POST['password'];
    $hash = password_hash($plain, PASSWORD_DEFAULT);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Generate Password Hash · HDCVotes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/custom.css">
    <link rel="icon" href="../assets/img/logo.png">
</head>
<body class="auth-body">
    <div class="auth-card" style="max-width:480px;">
        <div class="auth-card-head" style="background:linear-gradient(120deg,var(--secondary-dark),#0d2547);">
            <div class="auth-icon"><img src="../assets/img/logo.png" alt="Himalaya Darshan College" class="brand-logo brand-logo-md"></div>
            <h4>Generate Password Hash</h4>
            <p>Create a bcrypt hash for the admin table</p>
        </div>
        <div class="auth-card-body">
            <form method="POST">
                <div class="mb-3">
                    <label>Enter plain password</label>
                    <input type="text" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-secondary w-100"><i class="bi bi-magic me-1"></i> Generate</button>
            </form>
            <?php if ($hash): ?>
                <div class="mt-3">
                    <label>Hash (copy this into your database)</label>
                    <textarea class="form-control" rows="2" onclick="this.select()"><?= $hash ?></textarea>
                </div>
            <?php endif; ?>
            <p class="auth-footnote"><a href="login.php"><i class="bi bi-arrow-left"></i> Back to Login</a></p>
        </div>
    </div>
</body>
</html>
