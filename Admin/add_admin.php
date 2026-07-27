<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in as an admin to do this.']);
    exit();
}

require_once '../Database/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit();
}
if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
    exit();
}
if ($password !== $confirm) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
    exit();
}

// Check for duplicate email
$check = $conn->prepare("SELECT id FROM admin WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'An admin with this email already exists.']);
    exit();
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$insert = $conn->prepare("INSERT INTO admin (email, password) VALUES (?, ?)");
$insert->bind_param("ss", $email, $hash);

if ($insert->execute()) {
    echo json_encode(['success' => true, 'message' => 'New admin "' . htmlspecialchars($email) . '" was added successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}
