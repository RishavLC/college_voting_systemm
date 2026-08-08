<?php
// ============================================================
// Suppress PHP errors from being displayed (they go to logs)
// ============================================================
error_reporting(0);
ini_set('display_errors', 0);
ob_start(); // catch any accidental output

session_start();

// ============================================================
// BACKEND: student authentication, election & candidate data
// ============================================================

require_once '../Database/db_connect.php';

// Redirect if not verified
if (!isset($_SESSION['student_id']) || !isset($_SESSION['student_verified'])) {
    header("Location: verify_otp.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// Fetch student details
$stmt = $conn->prepare("SELECT student_name, student_batch, student_faculty, student_semester, voting_status FROM student WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
if (!$student) {
    session_destroy();
    header("Location: student_verify.php");
    exit();
}

$hasVoted = (bool) $student['voting_status'];

// Find active election for this student's batch/faculty/semester
$election_query = "SELECT election_id, election_name, election_date FROM election 
                   WHERE election_batch = ? AND election_faculty = ? AND election_semester = ? 
                   AND election_status = 'active' LIMIT 1";
$stmt = $conn->prepare($election_query);
$stmt->bind_param("ssi", $student['student_batch'], $student['student_faculty'], $student['student_semester']);
$stmt->execute();
$election = $stmt->get_result()->fetch_assoc();

$no_election = !$election;

if (!$no_election) {
    $election_id = $election['election_id'];
    // Fetch candidates
    $candidates_query = "SELECT c.candidate_id, s.student_id, s.student_name, c.candidate_photo 
                         FROM candidate c 
                         JOIN student s ON c.student_id = s.student_id 
                         WHERE c.election_id = ?";
    $stmt = $conn->prepare($candidates_query);
    $stmt->bind_param("i", $election_id);
    $stmt->execute();
    $candidates_result = $stmt->get_result();
    $candidates = [];
    while ($row = $candidates_result->fetch_assoc()) {
        $candidates[] = [
            'id' => $row['candidate_id'],
            'student_id' => $row['student_id'],
            'name' => $row['student_name'],
            'photo_url' => $row['candidate_photo'] ?? ''
        ];
    }
} else {
    $candidates = [];
}

// If already voted, fetch voted candidate
$votedCandidate = null;
if ($hasVoted && !$no_election) {
    $vote_query = "SELECT s.student_name, c.candidate_photo, v.voted_at 
                   FROM vote v
                   JOIN candidate c ON v.candidate_id = c.candidate_id
                   JOIN student s ON c.student_id = s.student_id
                   WHERE v.voter_id = ? AND v.election_id = ?";
    $stmt = $conn->prepare($vote_query);
    $stmt->bind_param("ii", $student_id, $election_id);
    $stmt->execute();
    $votedCandidate = $stmt->get_result()->fetch_assoc();
}
$votedCandidate = $votedCandidate ?: ['student_name' => '', 'candidate_photo' => '', 'voted_at' => ''];

// ============================================================
// VOTE-CASTING HANDLER (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['candidate_id'])) {
    ob_clean();
    header('Content-Type: application/json');

    try {
        if (!isset($_SESSION['student_id']) || !isset($_SESSION['student_verified'])) {
            echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $student_id = $_SESSION['student_id'];
        $candidate_id = intval($_POST['candidate_id']);

        // 1. Check voting_status
        $check_vote = $conn->prepare("SELECT voting_status FROM student WHERE student_id = ?");
        $check_vote->bind_param("i", $student_id);
        if (!$check_vote->execute()) throw new Exception('DB error: ' . $conn->error);
        $student_row = $check_vote->get_result()->fetch_assoc();
        if ($student_row['voting_status'] == 1) {
            echo json_encode(['ok' => false, 'message' => 'You have already voted.']);
            exit;
        }

        // 2. Find active election
        $election_query = "SELECT election_id FROM election 
                           WHERE election_batch = ? AND election_faculty = ? AND election_semester = ? 
                           AND election_status = 'active' LIMIT 1";
        $stmt = $conn->prepare($election_query);
        $stmt->bind_param("ssi", $student['student_batch'], $student['student_faculty'], $student['student_semester']);
        if (!$stmt->execute()) throw new Exception('DB error: ' . $conn->error);
        $election_row = $stmt->get_result()->fetch_assoc();
        if (!$election_row) {
            echo json_encode(['ok' => false, 'message' => 'No active election found.']);
            exit;
        }
        $election_id = $election_row['election_id'];

        // 3. Delete any existing vote for this student+election (if any)
        $delete = $conn->prepare("DELETE FROM vote WHERE voter_id = ? AND election_id = ?");
        $delete->bind_param("ii", $student_id, $election_id);
        $delete->execute();

        // 4. Verify candidate belongs to this election
        $verify_candidate = $conn->prepare("SELECT candidate_id FROM candidate WHERE candidate_id = ? AND election_id = ?");
        $verify_candidate->bind_param("ii", $candidate_id, $election_id);
        if (!$verify_candidate->execute()) throw new Exception('DB error: ' . $conn->error);
        if ($verify_candidate->get_result()->num_rows == 0) {
            echo json_encode(['ok' => false, 'message' => 'Invalid candidate for this election.']);
            exit;
        }

        // 5. Insert new vote
        $insert_vote = $conn->prepare("INSERT INTO vote (election_id, candidate_id, voter_id) VALUES (?, ?, ?)");
        $insert_vote->bind_param("iii", $election_id, $candidate_id, $student_id);
        if (!$insert_vote->execute()) throw new Exception('DB error: ' . $conn->error);

        // 6. Update student voting_status
        $update = $conn->prepare("UPDATE student SET voting_status = 1 WHERE student_id = ?");
        $update->bind_param("i", $student_id);
        if (!$update->execute()) throw new Exception('DB error: ' . $conn->error);

        session_destroy();
        echo json_encode(['ok' => true]);
        exit;

    } catch (Exception $e) {
        error_log("Vote error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
        echo json_encode(['ok' => false, 'message' => 'Server error. Please try again.']);
        exit;
    }
}

// ============================================================
// Helper for initials
// ============================================================
function initials_of($name) {
    $parts = preg_split('/\s+/', trim($name));
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cast Your Vote — HDC Votes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* ============================================
           MODERN VOTING DASHBOARD - COMPLETE REDESIGN
           ============================================ */
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #1a237e;
            --primary-dark: #0d1445;
            --primary-light: #283593;
            --secondary: #d4a843;
            --secondary-light: #f0d080;
            --secondary-dark: #b8860b;
            --gradient-gold: linear-gradient(135deg, #d4a843 0%, #b8860b 100%);
            --gradient-primary: linear-gradient(135deg, #1a237e 0%, #283593 100%);
            --white: #ffffff;
            --gray-50: #f8f9fa;
            --gray-100: #f1f3f5;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-800: #343a40;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 12px 40px rgba(0,0,0,0.12);
            --shadow-xl: 0 20px 60px rgba(0,0,0,0.15);
            --radius: 16px;
            --radius-sm: 10px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--gray-50);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            margin: 0;
        }

        /* Background Pattern */
        .bg-pattern {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(ellipse at 10% 20%, rgba(212, 168, 67, 0.08) 0%, transparent 50%),
                radial-gradient(ellipse at 90% 80%, rgba(26, 35, 126, 0.06) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 50%, rgba(26, 35, 126, 0.03) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        .container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 1200px;
        }

        /* ============================================
           HEADER
           ============================================ */
        .header {
            background: var(--gradient-primary);
            border-radius: var(--radius);
            padding: 20px 32px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow-xl);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-gold);
        }

        .header::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(212, 168, 67, 0.05) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
            position: relative;
            z-index: 1;
        }

        .header-logo {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--gradient-gold);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 20px;
            color: var(--primary-dark);
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(212, 168, 67, 0.3);
            overflow: hidden;
        }

        .header-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .header-logo .logo-text {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
        }

        .header-brand h1 {
            color: var(--white);
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }

        .header-brand p {
            color: rgba(255,255,255,0.7);
            font-size: 13px;
            font-weight: 400;
        }

        .header-brand p i {
            margin-right: 4px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
            z-index: 1;
        }

        .header-student {
            color: var(--white);
            text-align: right;
        }

        .header-student .name {
            font-weight: 600;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: flex-end;
        }

        .header-student .name i {
            color: var(--secondary);
            font-size: 14px;
        }

        .header-student .details {
            font-size: 12px;
            color: rgba(255,255,255,0.6);
        }

        .header-badge {
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            background: rgba(212, 168, 67, 0.15);
            color: var(--secondary-light);
            border: 1px solid rgba(212, 168, 67, 0.2);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .header-badge i {
            font-size: 12px;
        }

        /* ============================================
           ELECTION INFO
           ============================================ */
        .election-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 24px 32px;
            margin-bottom: 32px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            transition: var(--transition);
        }

        .election-card:hover {
            box-shadow: var(--shadow-lg);
            border-color: var(--secondary);
        }

        .election-info-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .election-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: rgba(212, 168, 67, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--secondary);
            font-size: 22px;
            flex-shrink: 0;
        }

        .election-info-left .title h2 {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-dark);
            margin: 0;
        }

        .election-info-left .title p {
            color: var(--gray-600);
            font-size: 14px;
            margin: 2px 0 0 0;
        }

        .election-meta {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
        }

        .election-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--gray-600);
            font-size: 13px;
            font-weight: 500;
        }

        .election-meta-item i {
            color: var(--secondary);
            font-size: 16px;
        }

        /* ============================================
           CANDIDATES SECTION - DYNAMIC GRID
           ============================================ */
        .candidates-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .candidates-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .candidates-header h3 i {
            color: var(--secondary);
        }

        .candidates-count {
            font-size: 13px;
            color: var(--gray-600);
            background: var(--gray-100);
            padding: 6px 16px;
            border-radius: 50px;
            font-weight: 500;
        }

        /* Dynamic Grid - adjusts based on number of candidates */
        .candidates-grid {
            display: grid;
            gap: 24px;
        }

        /* 1 candidate - full width */
        .candidates-grid.count-1 {
            grid-template-columns: 1fr;
            max-width: 400px;
            margin: 0 auto;
        }

        /* 2 candidates - 50% each */
        .candidates-grid.count-2 {
            grid-template-columns: repeat(2, 1fr);
            max-width: 800px;
            margin: 0 auto;
        }

        /* 3 candidates - 33.33% each */
        .candidates-grid.count-3 {
            grid-template-columns: repeat(3, 1fr);
        }

        /* 4 candidates - 25% each */
        .candidates-grid.count-4 {
            grid-template-columns: repeat(4, 1fr);
        }

        /* 5+ candidates - responsive grid */
        .candidates-grid.count-5,
        .candidates-grid.count-6 {
            grid-template-columns: repeat(3, 1fr);
        }

        .candidates-grid.count-7,
        .candidates-grid.count-8,
        .candidates-grid.count-9 {
            grid-template-columns: repeat(3, 1fr);
        }

        .candidates-grid.count-10-plus {
            grid-template-columns: repeat(4, 1fr);
        }

        .candidate-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 28px 24px 24px;
            text-align: center;
            box-shadow: var(--shadow-md);
            border: 2px solid var(--gray-200);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }

        .candidate-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-gold);
            opacity: 0;
            transition: var(--transition);
        }

        .candidate-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-xl);
            border-color: var(--secondary);
        }

        .candidate-card:hover::before {
            opacity: 1;
        }

        .candidate-card .number {
            position: absolute;
            top: 12px;
            right: 16px;
            font-size: 12px;
            font-weight: 700;
            color: var(--gray-400);
            background: var(--gray-100);
            padding: 2px 10px;
            border-radius: 50px;
        }

        .candidate-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 16px;
            background: var(--gray-100);
            border: 4px solid var(--gray-200);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            position: relative;
        }

        .candidate-card:hover .candidate-avatar {
            border-color: var(--secondary);
            box-shadow: 0 0 0 6px rgba(212, 168, 67, 0.12);
            transform: scale(1.02);
        }

        .candidate-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .candidate-avatar .fallback {
            font-size: 36px;
            font-weight: 700;
            color: var(--primary-dark);
            text-transform: uppercase;
        }

        .candidate-card h3 {
            font-size: 17px;
            font-weight: 700;
            color: var(--primary-dark);
            margin: 0 0 4px 0;
        }

        .candidate-card .subtitle {
            font-size: 13px;
            color: var(--gray-500);
            margin-bottom: 16px;
        }

        .btn-vote {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 32px;
            border-radius: 50px;
            background: var(--gradient-gold);
            color: var(--white);
            border: none;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 4px 16px rgba(212, 168, 67, 0.3);
            width: 100%;
            justify-content: center;
        }

        .btn-vote:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(212, 168, 67, 0.4);
        }

        .btn-vote:active {
            transform: translateY(0) scale(0.98);
        }

        .btn-vote i {
            font-size: 18px;
        }

        /* ============================================
           VOTED STATE
           ============================================ */
        .voted-state {
            background: var(--white);
            border-radius: var(--radius);
            padding: 48px 40px;
            text-align: center;
            box-shadow: var(--shadow-md);
            border: 2px solid var(--secondary);
            position: relative;
            overflow: hidden;
        }

        .voted-state::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--gradient-gold);
        }

        .voted-state .success-icon {
            font-size: 64px;
            color: var(--secondary);
            margin-bottom: 12px;
            display: block;
        }

        .voted-state h2 {
            font-size: 26px;
            color: var(--primary-dark);
            margin: 0 0 4px 0;
        }

        .voted-state .sub-text {
            color: var(--gray-600);
            font-size: 15px;
            margin-bottom: 24px;
        }

        .voted-candidate-display {
            display: inline-flex;
            align-items: center;
            gap: 16px;
            background: var(--gray-50);
            padding: 16px 28px;
            border-radius: var(--radius-sm);
            margin-bottom: 24px;
            border: 1px solid var(--gray-200);
        }

        .voted-candidate-display .avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: var(--gray-200);
            overflow: hidden;
            flex-shrink: 0;
        }

        .voted-candidate-display .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .voted-candidate-display .avatar .fallback {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            color: var(--primary-dark);
        }

        .voted-candidate-display .info {
            text-align: left;
        }

        .voted-candidate-display .info .name {
            font-weight: 600;
            color: var(--primary-dark);
            font-size: 16px;
        }

        .voted-candidate-display .info .time {
            font-size: 13px;
            color: var(--gray-500);
        }

        .voted-meta-grid {
            display: flex;
            justify-content: center;
            gap: 40px;
            flex-wrap: wrap;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--gray-200);
        }

        .voted-meta-grid .item {
            text-align: center;
        }

        .voted-meta-grid .item .label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray-400);
            font-weight: 600;
        }

        .voted-meta-grid .item .value {
            font-size: 14px;
            font-weight: 600;
            color: var(--primary-dark);
            margin-top: 2px;
        }

        .redirect-timer {
            margin-top: 24px;
            font-size: 14px;
            color: var(--gray-600);
        }

        .redirect-timer strong {
            color: var(--primary-dark);
            font-size: 20px;
            font-weight: 800;
        }

        /* ============================================
           NO ELECTION STATE
           ============================================ */
        .no-election-state {
            background: var(--white);
            border-radius: var(--radius);
            padding: 60px 40px;
            text-align: center;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
        }

        .no-election-state i {
            font-size: 64px;
            color: var(--gray-300);
            margin-bottom: 16px;
        }

        .no-election-state h2 {
            font-size: 24px;
            color: var(--primary-dark);
            margin: 0 0 8px 0;
        }

        .no-election-state p {
            color: var(--gray-500);
            max-width: 400px;
            margin: 0 auto 24px;
        }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            border-radius: 50px;
            background: var(--gray-200);
            color: var(--gray-700);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }

        .btn-secondary:hover {
            background: var(--gray-300);
            transform: translateY(-2px);
        }

        /* ============================================
           MODAL
           ============================================ */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            background: var(--white);
            border-radius: var(--radius);
            max-width: 440px;
            width: 100%;
            padding: 36px 32px 32px;
            box-shadow: var(--shadow-xl);
            animation: modalIn 0.3s ease;
        }

        @keyframes modalIn {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(16px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-dark);
            margin: 0;
        }

        .modal-close {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            background: var(--gray-100);
            color: var(--gray-600);
            font-size: 18px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            background: var(--gray-200);
            transform: rotate(90deg);
        }

        .modal-body {
            text-align: center;
        }

        .modal-body p {
            color: var(--gray-600);
            font-size: 14px;
            margin-bottom: 16px;
        }

        .modal-candidate {
            display: flex;
            align-items: center;
            gap: 16px;
            background: var(--gray-50);
            padding: 16px 20px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--gray-200);
            margin-bottom: 24px;
            text-align: left;
        }

        .modal-candidate .avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: var(--gray-200);
            overflow: hidden;
            flex-shrink: 0;
        }

        .modal-candidate .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .modal-candidate .avatar .fallback {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            color: var(--primary-dark);
        }

        .modal-candidate .info .name {
            font-weight: 600;
            color: var(--primary-dark);
            font-size: 16px;
        }

        .modal-candidate .info .hint {
            font-size: 12px;
            color: var(--gray-500);
        }

        .modal-actions {
            display: flex;
            gap: 12px;
        }

        .modal-actions button {
            flex: 1;
            padding: 14px;
            border-radius: var(--radius-sm);
            border: none;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-cancel {
            background: var(--gray-100);
            color: var(--gray-700);
        }

        .btn-cancel:hover {
            background: var(--gray-200);
        }

        .btn-confirm {
            background: var(--gradient-gold);
            color: var(--white);
            box-shadow: 0 4px 16px rgba(212, 168, 67, 0.3);
        }

        .btn-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(212, 168, 67, 0.4);
        }

        .btn-confirm:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.6s linear infinite;
            margin-right: 8px;
            vertical-align: middle;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* ============================================
           TOAST NOTIFICATIONS
           ============================================ */
        .toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 2000;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .toast {
            padding: 14px 24px;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 500;
            box-shadow: var(--shadow-lg);
            animation: slideUp 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--primary-dark);
            color: var(--white);
            min-width: 280px;
        }

        .toast i {
            font-size: 20px;
        }

        .toast.success {
            background: #0d6e3a;
            border-left: 4px solid #4ade80;
        }

        .toast.success i {
            color: #4ade80;
        }

        .toast.error {
            background: #8b1a1a;
            border-left: 4px solid #f87171;
        }

        .toast.error i {
            color: #f87171;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ============================================
           RESPONSIVE
           ============================================ */
        @media (max-width: 992px) {
            .candidates-grid.count-3 {
                grid-template-columns: repeat(2, 1fr);
            }
            .candidates-grid.count-4 {
                grid-template-columns: repeat(2, 1fr);
            }
            .candidates-grid.count-5,
            .candidates-grid.count-6 {
                grid-template-columns: repeat(2, 1fr);
            }
            .candidates-grid.count-7,
            .candidates-grid.count-8,
            .candidates-grid.count-9 {
                grid-template-columns: repeat(2, 1fr);
            }
            .candidates-grid.count-10-plus {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 16px;
            }

            .header {
                flex-direction: column;
                align-items: stretch;
                gap: 16px;
                padding: 20px 24px;
            }

            .header-left {
                justify-content: center;
            }

            .header-right {
                justify-content: space-between;
                flex-wrap: wrap;
            }

            .header-student {
                text-align: left;
            }

            .header-student .name {
                justify-content: flex-start;
            }

            .election-card {
                flex-direction: column;
                align-items: stretch;
                padding: 20px;
            }

            .election-info-left {
                flex-direction: column;
                text-align: center;
            }

            .election-meta {
                justify-content: center;
            }

            .candidates-grid.count-2,
            .candidates-grid.count-3,
            .candidates-grid.count-4 {
                grid-template-columns: repeat(2, 1fr);
            }

            .candidates-grid.count-5,
            .candidates-grid.count-6,
            .candidates-grid.count-7,
            .candidates-grid.count-8,
            .candidates-grid.count-9 {
                grid-template-columns: repeat(2, 1fr);
            }

            .candidates-grid.count-10-plus {
                grid-template-columns: repeat(2, 1fr);
            }

            .voted-state {
                padding: 32px 20px;
            }

            .voted-meta-grid {
                gap: 20px;
                flex-direction: column;
            }

            .modal {
                padding: 24px 20px;
            }

            .modal-actions {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .candidates-grid.count-1 {
                max-width: 100%;
            }
            
            .candidates-grid.count-2,
            .candidates-grid.count-3,
            .candidates-grid.count-4 {
                grid-template-columns: 1fr;
            }

            .candidates-grid.count-5,
            .candidates-grid.count-6,
            .candidates-grid.count-7,
            .candidates-grid.count-8,
            .candidates-grid.count-9 {
                grid-template-columns: 1fr;
            }

            .candidates-grid.count-10-plus {
                grid-template-columns: 1fr 1fr;
            }

            .header-brand h1 {
                font-size: 17px;
            }

            .election-info-left .title h2 {
                font-size: 17px;
            }

            .header {
                padding: 16px 20px;
            }

            .toast {
                min-width: auto;
                width: calc(100vw - 40px);
            }

            .voted-candidate-display {
                flex-direction: column;
                text-align: center;
                padding: 16px;
            }

            .voted-candidate-display .info {
                text-align: center;
            }
        }

        @media (max-width: 360px) {
            .candidate-card {
                padding: 20px 16px;
            }

            .candidate-avatar {
                width: 80px;
                height: 80px;
            }

            .btn-vote {
                padding: 10px 20px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>

<div class="bg-pattern"></div>

<div class="container">
    <!-- ==========================================
         HEADER
         ========================================== -->
    <header class="header">
        <div class="header-left">
            <div class="header-logo">
                <img src="../assets/img/logo.png" alt="HDC" onerror="this.style.display='none'; this.parentElement.innerHTML='<div class=\'logo-text\'>HDC</div>';">
                <div class="logo-text" style="display:none;">HDC</div>
            </div>
            <div class="header-brand">
                <h1>Himalaya Darshan College</h1>
                <p><i class="fas fa-vote-yea"></i> Student Voting System</p>
            </div>
        </div>
        <div class="header-right">
            <div class="header-student">
                <div class="name">
                    <i class="fas fa-user-circle"></i>
                    <?= htmlspecialchars($student['student_name']) ?>
                </div>
                <div class="details">
                    <?= htmlspecialchars($student['student_faculty']) ?> · Sem <?= htmlspecialchars($student['student_semester']) ?>
                </div>
            </div>
            <span class="header-badge">
                <i class="fas fa-check-circle"></i> Verified
            </span>
        </div>
    </header>

    <?php if ($no_election): ?>
        <!-- ==========================================
             NO ELECTION
             ========================================== -->
        <div class="no-election-state">
            <i class="fas fa-inbox"></i>
            <h2>No Active Election</h2>
            <p>There is currently no active election for your batch, faculty, and semester.</p>
            <a href="verify_otp.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Go Back
            </a>
        </div>

    <?php elseif ($hasVoted): ?>
        <!-- ==========================================
             VOTED STATE
             ========================================== -->
        <div class="voted-state">
            <i class="fas fa-check-circle success-icon"></i>
            <h2>Thank You for Voting!</h2>
            <p class="sub-text">Your vote has been securely recorded and cannot be changed.</p>
            
            <div class="voted-candidate-display">
                <div class="avatar">
                    <?php if ($votedCandidate['candidate_photo']): ?>
                        <img src="../<?= htmlspecialchars($votedCandidate['candidate_photo']) ?>" alt="<?= htmlspecialchars($votedCandidate['student_name']) ?>">
                    <?php else: ?>
                        <div class="fallback"><?= htmlspecialchars(initials_of($votedCandidate['student_name'])) ?></div>
                    <?php endif; ?>
                </div>
                <div class="info">
                    <div class="name"><?= htmlspecialchars($votedCandidate['student_name']) ?></div>
                    <div class="time"><i class="far fa-clock"></i> <?= date('h:i A', strtotime($votedCandidate['voted_at'])) ?></div>
                </div>
            </div>

            <div class="voted-meta-grid">
                <div class="item">
                    <div class="label">Election</div>
                    <div class="value"><?= htmlspecialchars($election['election_name']) ?></div>
                </div>
                <div class="item">
                    <div class="label">Voter</div>
                    <div class="value"><?= htmlspecialchars($student['student_name']) ?></div>
                </div>
                <div class="item">
                    <div class="label">Status</div>
                    <div class="value" style="color: #0d6e3a;">
                        <i class="fas fa-check-circle"></i> Confirmed
                    </div>
                </div>
            </div>

            <div class="redirect-timer">
                <i class="fas fa-clock"></i> Redirecting in <strong id="countdown">10</strong> seconds
            </div>
        </div>

        <script>
            let seconds = 10;
            const countdownEl = document.getElementById('countdown');
            const timer = setInterval(() => {
                seconds--;
                countdownEl.textContent = seconds;
                if (seconds <= 0) {
                    clearInterval(timer);
                    window.location.href = 'verify_otp.php';
                }
            }, 1000);
        </script>

    <?php else: ?>
        <!-- ==========================================
             ELECTION INFO
             ========================================== -->
        <div class="election-card">
            <div class="election-info-left">
                <div class="election-icon">
                    <i class="fas fa-megaphone"></i>
                </div>
                <div class="title">
                    <h2><?= htmlspecialchars($election['election_name']) ?></h2>
                    <p>Choose your candidate, then press Vote to cast your ballot.</p>
                </div>
            </div>
            <div class="election-meta">
                <div class="election-meta-item">
                    <i class="fas fa-calendar-alt"></i>
                    <?= date('F j, Y', strtotime($election['election_date'])) ?>
                </div>
                <div class="election-meta-item">
                    <i class="fas fa-users"></i>
                    <?= count($candidates) ?> Candidates
                </div>
                <div class="election-meta-item">
                    <i class="fas fa-tag"></i>
                    <?= htmlspecialchars($student['student_batch']) ?>
                </div>
            </div>
        </div>

        <!-- ==========================================
             CANDIDATES - DYNAMIC GRID
             ========================================== -->
        <div class="candidates-header">
            <h3><i class="fas fa-user-tie"></i> Choose Your Candidate</h3>
            <span class="candidates-count">
                <i class="fas fa-user"></i> <?= count($candidates) ?> candidates
            </span>
        </div>

        <?php 
        $candidateCount = count($candidates);
        // Determine grid class based on number of candidates
        $gridClass = 'count-' . $candidateCount;
        if ($candidateCount >= 10) {
            $gridClass = 'count-10-plus';
        }
        ?>

        <div class="candidates-grid <?= $gridClass ?>">
            <?php 
            $index = 1;
            foreach ($candidates as $c): 
                $name = $c['name'];
                $photo = $c['photo_url'] ?? '';
                $initial = initials_of($name);
            ?>
            <div class="candidate-card" 
                 data-id="<?= (int) $c['id'] ?>" 
                 data-name="<?= htmlspecialchars($name) ?>" 
                 data-photo="<?= htmlspecialchars($photo) ?>">
                <span class="number">#<?= $index++ ?></span>
                <div class="candidate-avatar">
                    <?php if ($photo): ?>
                        <img src="../<?= htmlspecialchars($photo) ?>" alt="<?= htmlspecialchars($name) ?>">
                    <?php else: ?>
                        <div class="fallback"><?= htmlspecialchars($initial) ?></div>
                    <?php endif; ?>
                </div>
                <h3><?= htmlspecialchars($name) ?></h3>
                <div class="subtitle">Candidate</div>
                <button class="btn-vote" onclick="openVoteModal(this.closest('.candidate-card'))">
                    <i class="fas fa-check-circle"></i> Vote
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ==========================================
     CONFIRM MODAL
     ========================================== -->
<div class="modal-overlay" id="voteModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-check-circle" style="color: var(--secondary);"></i> Confirm Your Vote</h3>
            <button class="modal-close" onclick="closeVoteModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p>You are about to cast your vote for:</p>
            <div class="modal-candidate">
                <div class="avatar" id="modalAvatar">
                    <img src="" alt="" id="modalImg" style="display:none;">
                    <div class="fallback" id="modalFallback"></div>
                </div>
                <div class="info">
                    <div class="name" id="modalName">Candidate Name</div>
                    <div class="hint"><i class="fas fa-exclamation-circle"></i> This action is final and cannot be undone</div>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeVoteModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button class="btn-confirm" id="confirmBtn" onclick="castVote()">
                    <i class="fas fa-check"></i> Confirm & Vote
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================
     TOAST CONTAINER
     ========================================== -->
<div class="toast-container" id="toastContainer"></div>

<!-- ==========================================
     SCRIPTS
     ========================================== -->
<script>
    let selectedCandidate = null;

    function openVoteModal(card) {
        selectedCandidate = card;
        const name = card.dataset.name;
        const photo = card.dataset.photo;
        const initial = name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();

        document.getElementById('modalName').textContent = name;
        const img = document.getElementById('modalImg');
        const fallback = document.getElementById('modalFallback');

        if (photo) {
            img.src = '../' + photo;
            img.style.display = 'block';
            fallback.style.display = 'none';
        } else {
            img.style.display = 'none';
            fallback.textContent = initial;
            fallback.style.display = 'flex';
        }

        document.getElementById('voteModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeVoteModal() {
        document.getElementById('voteModal').classList.remove('active');
        document.body.style.overflow = '';
        selectedCandidate = null;
    }

    function castVote() {
        if (!selectedCandidate) return;

        const btn = document.getElementById('confirmBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Casting...';

        const formData = new FormData();
        formData.append('candidate_id', selectedCandidate.dataset.id);

        fetch('', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.ok) {
                showToast('Vote cast successfully!', 'success');
                closeVoteModal();
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showToast(data.message || 'Failed to cast vote', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check"></i> Confirm & Vote';
            }
        })
        .catch(err => {
            showToast('Server error. Please try again.', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> Confirm & Vote';
        });
    }

    function showToast(message, type) {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast ${type || ''}`;
        const icon = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
        toast.innerHTML = `<i class="${icon}"></i> ${message}`;
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(20px)';
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    }

    // Close modal on overlay click
    document.getElementById('voteModal').addEventListener('click', function(e) {
        if (e.target === this) closeVoteModal();
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeVoteModal();
    });
</script>

</body>
</html>