<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../Database/db_connect.php';

$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($student_id > 0) {
    $stmt = $conn->prepare("UPDATE student SET is_present = 0 WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Student marked as absent.";
    } else {
        $_SESSION['error_message'] = "Failed to mark as absent: " . $conn->error;
    }
} else {
    $_SESSION['error_message'] = "Invalid student ID.";
}

header("Location: home.php?section=students");
exit();
?>
