<?php
session_start();
header('Content-Type: application/json');

require_once '../Database/db_connect.php';

// Check login
if (
    !isset($_SESSION['student_id']) ||
    !isset($_SESSION['student_verified'])
) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.'
    ]);
    exit();
}

$student_id   = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
$candidate_id = isset($_POST['candidate_id']) ? (int)$_POST['candidate_id'] : 0;
$election_id  = isset($_POST['election_id']) ? (int)$_POST['election_id'] : 0;

// Validate input
if ($student_id <= 0 || $candidate_id <= 0 || $election_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request.'
    ]);
    exit();
}

// Prevent spoofing
if ($_SESSION['student_id'] != $student_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid student.'
    ]);
    exit();
}

try {

    // Check voting status
    $stmt = $conn->prepare("
        SELECT voting_status
        FROM student
        WHERE student_id = ?
    ");

    $stmt->bind_param("i", $student_id);
    $stmt->execute();

    $student = $stmt->get_result()->fetch_assoc();

    if (!$student) {
        echo json_encode([
            'success' => false,
            'message' => 'Student not found.'
        ]);
        exit();
    }

    if ($student['voting_status'] == 1) {
        echo json_encode([
            'success' => false,
            'already_voted' => true,
            'message' => 'You have already voted.'
        ]);
        exit();
    }

    // Verify candidate
    $stmt = $conn->prepare("
        SELECT candidate_id
        FROM candidate
        WHERE candidate_id = ?
        AND election_id = ?
    ");

    $stmt->bind_param("ii", $candidate_id, $election_id);
    $stmt->execute();

    if ($stmt->get_result()->num_rows == 0) {

        echo json_encode([
            'success' => false,
            'message' => 'Invalid candidate.'
        ]);
        exit();
    }

    // Prevent duplicate vote
    $stmt = $conn->prepare("
        SELECT vote_id
        FROM vote
        WHERE election_id = ?
        AND voter_id = ?
    ");

    $stmt->bind_param("ii", $election_id, $student_id);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {

        echo json_encode([
            'success' => false,
            'already_voted' => true,
            'message' => 'You have already voted in this election.'
        ]);
        exit();
    }

    // Begin transaction
    $conn->begin_transaction();

    // Insert vote
    $stmt = $conn->prepare("
        INSERT INTO vote
        (election_id, candidate_id, voter_id)
        VALUES (?, ?, ?)
    ");

    $stmt->bind_param(
        "iii",
        $election_id,
        $candidate_id,
        $student_id
    );

    if (!$stmt->execute()) {
        throw new Exception("Failed to save vote.");
    }

    // Update voting status
    $stmt = $conn->prepare("
        UPDATE student
        SET voting_status = 1
        WHERE student_id = ?
    ");

    $stmt->bind_param("i", $student_id);

    if (!$stmt->execute()) {
        throw new Exception("Failed to update voting status.");
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Vote submitted successfully.'
    ]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>