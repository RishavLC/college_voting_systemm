<?php
session_start();
if (!isset($_SESSION['admin_id'])) die("Unauthorized");
require_once '../Database/db_connect.php';
$id = $_GET['id'] ?? 0;
if ($id) $conn->query("DELETE FROM student WHERE student_id = $id");
header("Location: home.php?section=students");
exit();
?>