<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}
require_once '../Database/db_connect.php';

// Section switch (Students / Events) - defaults to students
$section = $_GET['section'] ?? 'students';
if (!in_array($section, ['students', 'events', 'otp_requests'])) {
    $section = 'students';
}

// Lightweight stats for the dashboard cards
$total_students = $conn->query("SELECT COUNT(*) AS c FROM student")->fetch_assoc()['c'] ?? 0;
$total_elections = $conn->query("SELECT COUNT(*) AS c FROM election")->fetch_assoc()['c'] ?? 0;
$active_elections = $conn->query("SELECT COUNT(*) AS c FROM election WHERE election_status = 'active'")->fetch_assoc()['c'] ?? 0;
$total_votes = $conn->query("SELECT COUNT(*) AS c FROM vote")->fetch_assoc()['c'] ?? 0;

$pageTitle = 'Dashboard';
$pageSubtitle = 'Overview of students, elections and voting activity';
include 'header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon violet"><i class="bi bi-people-fill"></i></div>
            <div>
                <div class="stat-value"><?= (int)$total_students ?></div>
                <div class="stat-label">Total Students</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon teal"><i class="bi bi-calendar-event-fill"></i></div>
            <div>
                <div class="stat-value"><?= (int)$total_elections ?></div>
                <div class="stat-label">Total Elections</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-lightning-charge-fill"></i></div>
            <div>
                <div class="stat-value"><?= (int)$active_elections ?></div>
                <div class="stat-label">Active Elections</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon rose"><i class="bi bi-check2-square"></i></div>
            <div>
                <div class="stat-value"><?= (int)$total_votes ?></div>
                <div class="stat-label">Votes Cast</div>
            </div>
        </div>
    </div>
</div>

<div class="section-tabs">
    <a href="home.php?section=students" class="section-tab <?= $section === 'students' ? 'active' : '' ?>">
        <i class="bi bi-people-fill me-1"></i> Students
    </a>
    <a href="home.php?section=events" class="section-tab <?= $section === 'events' ? 'active' : '' ?>">
        <i class="bi bi-calendar-event-fill me-1"></i> Elections
    </a>
    <a href="home.php?section=otp_requests" class="section-tab <?= $section === 'otp_requests' ? 'active' : '' ?>">
        <i class="bi bi-shield-lock-fill me-1"></i> OTP Requests
    </a>
</div>

<?php if ($section === 'students'): ?>
    <?php include 'students.php'; ?>
<?php elseif ($section === 'otp_requests'): ?>
    <?php include 'otp_requests.php'; ?>
<?php else: ?>
    <?php include 'events.php'; ?>
<?php endif; ?>

<?php include 'footer.php'; ?>
