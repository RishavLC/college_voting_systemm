<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../Database/db_connect.php';

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
if ($student_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
    exit();
}

// Get candidate's faculty, batch, semester
$stmt = $conn->prepare("SELECT student_faculty, student_batch, student_semester FROM student WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Student not found']);
    exit();
}
$student = $result->fetch_assoc();

// Fetch other students with same faculty, batch, semester, excluding self and already candidates
$query = "SELECT student_id, student_name FROM student 
          WHERE student_faculty = ? 
          AND student_batch = ? 
          AND student_semester = ? 
          AND student_id != ? 
          AND is_candidate = 0 
          ORDER BY student_name";
$stmt = $conn->prepare($query);
$stmt->bind_param("ssii", $student['student_faculty'], $student['student_batch'], $student['student_semester'], $student_id);
$stmt->execute();
$supporters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode(['success' => true, 'supporters' => $supporters]);
exit();
?>