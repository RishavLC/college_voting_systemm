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

// Find the active election for this batch/faculty/semester
$election_query = "SELECT election_id FROM election 
                   WHERE election_faculty = ? AND election_batch = ? AND election_semester = ? 
                   AND election_status IN ('upcoming', 'active') LIMIT 1";
$stmt = $conn->prepare($election_query);
$stmt->bind_param("ssi", $student['student_faculty'], $student['student_batch'], $student['student_semester']);
$stmt->execute();
$election_result = $stmt->get_result();
if ($election_result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'No election found for this batch/faculty/semester.']);
    exit();
}
$election = $election_result->fetch_assoc();
$election_id = $election['election_id'];

// Fetch students who are NOT:
//   - already candidates in this election
//   - already supporter1 in this election
//   - already supporter2 in this election
//   - already proposer in this election
// And share the same faculty/batch/semester, excluding the candidate themselves.
$query = "
    SELECT s.student_id, s.student_name
    FROM student s
    WHERE s.student_faculty = ? 
      AND s.student_batch = ? 
      AND s.student_semester = ?
      AND s.student_id != ?
      AND s.student_id NOT IN (
          SELECT student_id FROM candidate WHERE election_id = ?
      )
      AND s.student_id NOT IN (
          SELECT supporter1 FROM candidate WHERE election_id = ? AND supporter1 IS NOT NULL
      )
      AND s.student_id NOT IN (
          SELECT supporter2 FROM candidate WHERE election_id = ? AND supporter2 IS NOT NULL
      )
      AND s.student_id NOT IN (
          SELECT proposer FROM candidate WHERE election_id = ? AND proposer IS NOT NULL
      )
    ORDER BY s.student_name
";
$stmt = $conn->prepare($query);
$stmt->bind_param(
    "ssiiiiii",
    $student['student_faculty'],
    $student['student_batch'],
    $student['student_semester'],
    $student_id,
    $election_id,
    $election_id,
    $election_id,
    $election_id
);
$stmt->execute();
$supporters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode(['success' => true, 'supporters' => $supporters]);
exit();
?>