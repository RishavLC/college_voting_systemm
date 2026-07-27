<?php
session_start();
require_once '../Database/db_connect.php';


// Check if student is logged in (via session)
if (!isset($_SESSION['student_id']) || !isset($_SESSION['student_verified'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$student_id = intval($_POST['student_id'] ?? 0);
$candidate_id = intval($_POST['candidate_id'] ?? 0);
$election_id = intval($_POST['election_id'] ?? 0);

// Validate that session student_id matches the posted one
if ($student_id != $_SESSION['student_id']) {
    echo json_encode(['success' => false, 'message' => 'Invalid student']);
    exit();
}

// Check if student already voted
$check_vote = $conn->prepare("SELECT voting_status FROM student WHERE student_id = ?");
$check_vote->bind_param("i", $student_id);
$check_vote->execute();
$result = $check_vote->get_result();
$student = $result->fetch_assoc();
if ($student['voting_status'] == 1) {
    echo json_encode(['success' => false, 'message' => 'You have already voted!', 'already_voted' => true]);
    exit();
}

// Verify that the candidate belongs to the election
$verify_candidate = $conn->prepare("SELECT candidate_id FROM candidate WHERE candidate_id = ? AND election_id = ?");
$verify_candidate->bind_param("ii", $candidate_id, $election_id);
$verify_candidate->execute();
if ($verify_candidate->get_result()->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid candidate for this election']);
    exit();
}

// Insert vote into vote table
$insert_vote = $conn->prepare("INSERT INTO vote (election_id, candidate_id, voter_id) VALUES (?, ?, ?)");
$insert_vote->bind_param("iii", $election_id, $candidate_id, $student_id);
if ($insert_vote->execute()) {
    // Update student voting_status
    $update = $conn->prepare("UPDATE student SET voting_status = 1 WHERE student_id = ?");
    $update->bind_param("i", $student_id);
    $update->execute();
    echo json_encode(['success' => true, 'message' => 'Vote recorded successfully!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}
?>