<?php
// Never let a PHP warning/fatal error leak raw HTML into what's supposed
// to be a JSON response — that's what turns into "Network error" on the
// frontend (the browser's fetch().json() call chokes on non-JSON output).
error_reporting(E_ALL);
ini_set('display_errors', '0'); // don't print raw PHP errors into the response
header('Content-Type: application/json');

function json_fail($message, $debug = null) {
    $out = ['success' => false, 'message' => $message];
    if ($debug !== null) $out['debug'] = $debug;
    echo json_encode($out);
    exit();
}

// Catch fatal errors (e.g. a mysqli exception, undefined function, etc.)
// that would otherwise produce an HTML error page instead of JSON.
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Server error while marking candidate.',
            'debug' => $err['message'] . ' in ' . $err['file'] . ' on line ' . $err['line'],
        ]);
    }
});

session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../Database/db_connect.php';

$response = ['success' => false, 'message' => ''];

try {

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = intval($_POST['student_id'] ?? 0);
    $supporter1 = intval($_POST['supporter1'] ?? 0);
    $supporter2 = intval($_POST['supporter2'] ?? 0);

    if ($student_id <= 0) {
        json_fail('Invalid student ID.');
    }

    // ----- 1. Fetch student details -----
    $student_stmt = $conn->prepare("SELECT student_batch, student_faculty, student_semester FROM student WHERE student_id = ?");
    if (!$student_stmt) json_fail('Query error (student lookup).', $conn->error);
    $student_stmt->bind_param("i", $student_id);
    $student_stmt->execute();
    $student_result = $student_stmt->get_result();
    if ($student_result->num_rows == 0) {
        json_fail('Student not found.');
    }
    $student = $student_result->fetch_assoc();
    $batch = $student['student_batch'];
    $faculty = $student['student_faculty'];
    $semester = $student['student_semester'];

    // ----- 2. Validate supporters (must be different from candidate and each other) -----
    if ($supporter1 == $student_id || $supporter2 == $student_id) {
        json_fail('A supporter cannot be the candidate themselves.');
    }
    if ($supporter1 && $supporter1 == $supporter2) {
        json_fail('Supporter 1 and Supporter 2 must be different.');
    }

    // Verify supporters exist and share the same faculty/batch/semester
    $supporters = [];
    if ($supporter1) $supporters[] = $supporter1;
    if ($supporter2) $supporters[] = $supporter2;
    if (!empty($supporters)) {
        $placeholders = implode(',', array_fill(0, count($supporters), '?'));
        $check_query = "SELECT student_id FROM student WHERE student_id IN ($placeholders) 
                        AND student_faculty = ? AND student_batch = ? AND student_semester = ?";
        $stmt = $conn->prepare($check_query);
        if (!$stmt) json_fail('Query error (supporter check).', $conn->error);
        $types = str_repeat('i', count($supporters)) . 'ssi';
        $params = array_merge($supporters, [$faculty, $batch, $semester]);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $valid_count = $stmt->get_result()->num_rows;
        if ($valid_count != count($supporters)) {
            json_fail('One or both supporters are not eligible (must be same faculty/batch/semester).');
        }
    }

    // ----- 3. Find an election (upcoming or active) -----
    $election_query = "SELECT election_id FROM election 
                       WHERE election_batch = ? AND election_faculty = ? AND election_semester = ?
                       AND election_status IN ('upcoming', 'active') LIMIT 1";
    $stmt = $conn->prepare($election_query);
    if (!$stmt) json_fail('Query error (election lookup).', $conn->error);
    $stmt->bind_param("ssi", $batch, $faculty, $semester);
    $stmt->execute();
    $election = $stmt->get_result()->fetch_assoc();
    if (!$election) {
        json_fail("No upcoming or active election found for this student's batch/faculty/semester.");
    }
    $election_id = $election['election_id'];

    // ----- 4. Handle photo upload -----
    $photo_path = null;
    if (isset($_FILES['candidate_photo']) && $_FILES['candidate_photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../assets/uploads/candidates/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true) && !is_dir($upload_dir)) {
                json_fail('Could not create upload directory. Check folder permissions on assets/uploads/candidates/.');
            }
        }
        $ext = pathinfo($_FILES['candidate_photo']['name'], PATHINFO_EXTENSION);
        $filename = 'candidate_' . $student_id . '_' . time() . '.' . $ext;
        $target = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['candidate_photo']['tmp_name'], $target)) {
            $photo_path = 'assets/uploads/candidates/' . $filename;
        } else {
            json_fail('Failed to upload photo. Check that assets/uploads/candidates/ exists and is writable.');
        }
    }

    // ----- 5. Insert candidate -----
    $insert = $conn->prepare("INSERT INTO candidate (student_id, election_id, candidate_photo, supporter1, supporter2) VALUES (?, ?, ?, ?, ?)");
    if (!$insert) json_fail('Query error (candidate insert).', $conn->error);
    $insert->bind_param("iisii", $student_id, $election_id, $photo_path, $supporter1, $supporter2);
    if ($insert->execute()) {
        $conn->query("UPDATE student SET is_candidate = 1 WHERE student_id = $student_id");
        $response['success'] = true;
        $response['message'] = 'Candidate marked successfully!';
    } else {
        $response['message'] = 'Database error: ' . $conn->error;
    }
} else {
    $response['message'] = 'Invalid request method.';
}

} catch (Throwable $e) {
    json_fail('Unexpected server error while marking candidate.', $e->getMessage());
}

echo json_encode($response);
exit();
?>