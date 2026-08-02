<?php
session_start();
header('Content-Type: application/json');
require_once '../Database/db_connect.php';

function respond($arr) {
    echo json_encode($arr);
    exit();
}

function normalizePhone($input) {
    $p = preg_replace('/[^0-9]/', '', trim($input));
    if (strlen($p) === 13 && str_starts_with($p, '977')) {
        $p = substr($p, 3);
    }
    return $p;
}

function isValidNepaliMobile($p) {
    return (bool) preg_match('/^9[78]\d{8}$/', $p);
}

$mobileRaw = $_POST['mobile'] ?? '';
$mobile = normalizePhone($mobileRaw);

if (!isValidNepaliMobile($mobile)) {
    respond(['eligible' => false, 'reason' => 'invalid_format', 'message' => 'Please enter a valid 10-digit mobile number starting with 97 or 98.']);
}

$stmt = $conn->prepare("SELECT student_id, is_present, voting_status FROM student WHERE student_phone = ?");
$stmt->bind_param("s", $mobile);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    respond(['eligible' => false, 'reason' => 'not_registered', 'message' => 'This mobile number is not registered. Please contact an admin.']);
}

$student = $result->fetch_assoc();
$student_id = $student['student_id'];

if ($student['voting_status'] == 1) {
    respond(['eligible' => false, 'reason' => 'already_voted', 'message' => 'You have already voted. Thank you for participating!']);
}

if (!$student['is_present']) {
    respond(['eligible' => false, 'reason' => 'not_present', 'message' => "You haven't been marked present yet. Please check in at the admin desk first."]);
}

$check = $conn->prepare("SELECT otp_id FROM otp_requests WHERE student_id = ?");
$check->bind_param("i", $student_id);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    respond(['eligible' => false, 'reason' => 'already_requested', 'message' => 'Contact admins for OTP']);
}

// Eligible — log the request now (before the SMS actually goes out via
// Firebase) so the one-time-request rule is enforced even if the
// student never completes verification. The real OTP itself is
// generated and known only to Firebase/Google, never our server, so we
// store a placeholder in the `otp` column purely to satisfy the schema.
$log = $conn->prepare("INSERT INTO otp_requests (student_id, mobile, otp, status) VALUES (?, ?, 'FIREBASE', 'sent')");
$log->bind_param("is", $student_id, $mobile);
$log->execute();

respond(['eligible' => true, 'student_id' => $student_id, 'mobile' => $mobile]);
