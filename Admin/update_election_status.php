<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../Database/db_connect.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($id <= 0 || !in_array($action, ['start', 'close'])) {
    $_SESSION['error_message'] = "Invalid request.";
    header("Location: home.php?section=events");
    exit();
}

// Map action to status
$newStatus = ($action === 'start') ? 'active' : 'closed';

// Update the election status
$stmt = $conn->prepare("UPDATE election SET election_status = ? WHERE election_id = ?");
$stmt->bind_param("si", $newStatus, $id);
if ($stmt->execute()) {
    $_SESSION['success_message'] = "Election status updated to " . ucfirst($newStatus) . ".";
} else {
    $_SESSION['error_message'] = "Failed to update election status: " . $conn->error;
}

header("Location: home.php?section=events");
exit();
?>