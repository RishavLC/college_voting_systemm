<?php
session_start();
header('Content-Type: application/json');
require_once '../Database/db_connect.php';
require_once '../Database/firebase_config.php';

function respond($arr) {
    echo json_encode($arr);
    exit();
}

$idToken = $_POST['idToken'] ?? '';
$student_id = intval($_POST['student_id'] ?? 0);
$mobile = preg_replace('/[^0-9]/', '', $_POST['mobile'] ?? '');

if (!$idToken || !$student_id || !$mobile) {
    respond(['success' => false, 'message' => 'Missing verification data.']);
}

// Verify the ID token directly with Firebase/Google — never trust a
// client-side "success" alone, since JS in the browser can be tampered
// with. This call confirms Google actually issued this token and tells
// us the phone number it was issued for.
$ch = curl_init("https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=" . FIREBASE_API_KEY);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['idToken' => $idToken]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
]);
$response = curl_exec($ch);
$curlErr = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    respond(['success' => false, 'message' => 'Could not reach Firebase to verify: ' . $curlErr]);
}

$data = json_decode($response, true);
$verifiedPhone = $data['users'][0]['phoneNumber'] ?? null; // e.g. "+9779800000000"

if (!$verifiedPhone) {
    respond(['success' => false, 'message' => 'Firebase could not verify this token.']);
}

// The verified phone must match the number this specific student
// requested an OTP for (strip everything but digits, then compare the
// last 10 digits so +977 vs 977 formatting differences don't matter).
$verifiedDigits = preg_replace('/[^0-9]/', '', $verifiedPhone);
$verifiedLocal = substr($verifiedDigits, -10);
$expectedLocal = substr($mobile, -10);

if ($verifiedLocal !== $expectedLocal) {
    respond(['success' => false, 'message' => 'Verified phone number does not match.']);
}

// Re-check eligibility server-side in case anything changed since the
// request was first logged (defense in depth against race conditions).
$stmt = $conn->prepare("SELECT is_present, voting_status FROM student WHERE student_id = ? AND student_phone = ?");
$stmt->bind_param("is", $student_id, $expectedLocal);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    respond(['success' => false, 'message' => 'Student record not found.']);
}
if ($student['voting_status'] == 1) {
    respond(['success' => false, 'message' => 'You have already voted.']);
}
if (!$student['is_present']) {
    respond(['success' => false, 'message' => 'You are not marked present.']);
}

// All good — mark the request verified and start the session.
$mark = $conn->prepare("UPDATE otp_requests SET is_used = 1, used_at = NOW(), status = 'verified' WHERE student_id = ?");
$mark->bind_param("i", $student_id);
$mark->execute();

$_SESSION['student_verified'] = true;
$_SESSION['student_id'] = $student_id;
$_SESSION['student_mobile'] = $expectedLocal;

respond(['success' => true]);
