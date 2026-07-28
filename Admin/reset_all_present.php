<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../Database/db_connect.php';

// Bulk reset: mark every student as absent again. Useful at the start of
// a new voting day/session so yesterday's check-ins don't carry over.
// This does NOT touch voting_status or OTP records — it only resets
// attendance.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reset'])) {
    if ($conn->query("UPDATE student SET is_present = 0")) {
        $affected = $conn->affected_rows;
        $_SESSION['success_message'] = "Attendance reset for all students ($affected updated to absent).";
    } else {
        $_SESSION['error_message'] = "Failed to reset attendance: " . $conn->error;
    }
} else {
    $_SESSION['error_message'] = "Invalid reset request.";
}

header("Location: home.php?section=students");
exit();
?>
