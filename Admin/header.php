<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' · ' : '' ?>HDCVotes Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/custom.css">
    <link rel="icon" href="../assets/img/logo.png">
</head>
<body>
<?php
    // Determine active nav item (based on current script + optional ?section=)
    $current = basename($_SERVER['PHP_SELF']);
    $activeSection = $_GET['section'] ?? 'students';
    $adminEmail = $_SESSION['admin_email'] ?? 'Admin';
    $adminInitial = strtoupper(substr($adminEmail, 0, 1));
?>
<div class="admin-shell">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <span class="brand-icon"><img src="../assets/img/logo.png" alt="Himalaya Darshan College" class="brand-logo brand-logo-sm"></span>
            <br>
            <span>Himalaya Darshan College</span>
        </div>
        <nav class="sidebar-nav">
            <div class="sidebar-section-label">Overview</div>
            <a href="home.php" class="sidebar-link <?= ($current === 'home.php') ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>

            <div class="sidebar-section-label">Manage</div>
            <a href="home.php?section=students" class="sidebar-link <?= ($current === 'home.php' && $activeSection === 'students') || in_array($current, ['add_student.php','edit_student.php']) ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i> Students
            </a>
            <a href="home.php?section=events" class="sidebar-link <?= ($current === 'home.php' && $activeSection === 'events') || in_array($current, ['add_event.php','edit_event.php']) ? 'active' : '' ?>">
                <i class="bi bi-calendar-event-fill"></i> Elections
            </a>
            <a href="home.php?section=otp_requests" class="sidebar-link <?= ($current === 'home.php' && $activeSection === 'otp_requests') ? 'active' : '' ?>">
                <i class="bi bi-shield-lock-fill"></i> OTP Requests
            </a>
            <button type="button" class="sidebar-link" data-bs-toggle="modal" data-bs-target="#importModal">
                <i class="bi bi-file-earmark-arrow-up-fill"></i> Import Students
            </button>

            <div class="sidebar-section-label">Admin</div>
            <button type="button" class="sidebar-link" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                <i class="bi bi-person-fill-add"></i> Add Admin
            </button>

            <div class="sidebar-section-label">Public</div>
            <a href="../result.php" target="_blank" class="sidebar-link">
                <i class="bi bi-bar-chart-line-fill"></i> View Results
            </a>
        </nav>
        <div class="sidebar-foot">
            <a href="logout.php" class="btn btn-outline-light btn-sm w-100">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </aside>

    <div class="main-area">
        <div class="topbar">
            <div class="d-flex align-items-center gap-2">
                <button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
                <div>
                    <h1 class="topbar-title"><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard' ?>
                        <?php if (isset($pageSubtitle)): ?><span class="sub"><?= htmlspecialchars($pageSubtitle) ?></span><?php endif; ?>
                    </h1>
                </div>
            </div>
            <div class="topbar-right">
                <button type="button" class="btn-add-admin" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                    <i class="bi bi-person-fill-add"></i> <span class="d-none d-sm-inline">Add Admin</span>
                </button>
                <span class="text-muted small d-none d-md-inline">Welcome, <?= htmlspecialchars($adminEmail) ?></span>
                <div class="admin-avatar"><?= $adminInitial ?></div>
            </div>
        </div>
        <div class="content-wrapper">
