<?php
session_start();
if (!isset($_SESSION['admin_id'])) die("Unauthorized");
require_once '../Database/db_connect.php';

$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM election WHERE election_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
}
header("Location: home.php?section=events");
exit();
?>
