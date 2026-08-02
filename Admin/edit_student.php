<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../Database/db_connect.php';

$error = '';
$success = '';
$student = null;

$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($student_id <= 0) {
    header("Location: home.php?section=students");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM student WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    header("Location: home.php?section=students");
    exit();
}
$student = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['student_name']);
    $batch = trim($_POST['student_batch']);
    $faculty = trim($_POST['student_faculty']);
    $semester = intval($_POST['student_semester']);
    $phone = trim($_POST['student_phone']);
    $email = trim($_POST['student_email']);
    $email = $email === '' ? null : $email; // optional — store NULL if blank
    $voting_status = isset($_POST['voting_status']) ? 1 : 0;
    $is_candidate = isset($_POST['is_candidate']) ? 1 : 0;

    // Validate
    if (empty($name) || empty($batch) || empty($faculty) || $semester < 1 || $semester > 8) {
        $error = "All required fields (Name, Batch, Faculty, Semester) must be filled correctly.";
    } elseif ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check if email already used by another student (only matters
        // if an email was actually entered — NULLs never conflict)
        $emailTaken = false;
        if ($email !== null) {
            $check = $conn->prepare("SELECT student_id FROM student WHERE student_email = ? AND student_id != ?");
            $check->bind_param("si", $email, $student_id);
            $check->execute();
            $emailTaken = $check->get_result()->num_rows > 0;
        }
        if ($emailTaken) {
            $error = "This email is already registered by another student.";
        } else {
            // ✅ FIXED: correct type string: "sssissiii" (9 specifiers)
            $update = $conn->prepare("UPDATE student SET 
                student_name = ?, 
                student_batch = ?, 
                student_faculty = ?, 
                student_semester = ?, 
                student_phone = ?, 
                student_email = ?, 
                voting_status = ?, 
                is_candidate = ? 
                WHERE student_id = ?");
            $update->bind_param("sssissiii", $name, $batch, $faculty, $semester, $phone, $email, $voting_status, $is_candidate, $student_id);
            if ($update->execute()) {
                $success = "Student updated successfully!";
                // Refresh student data
                $stmt = $conn->prepare("SELECT * FROM student WHERE student_id = ?");
                $stmt->bind_param("i", $student_id);
                $stmt->execute();
                $student = $stmt->get_result()->fetch_assoc();
            } else {
                $error = "Database error: " . $conn->error;
            }
        }
    }
}

$pageTitle = 'Edit Student';
$pageSubtitle = 'Student #' . $student_id;
include 'header.php';
?>
<div class="card shadow" style="max-width:720px;">
    <div class="card-header bg-primary text-white"><i class="bi bi-person-lines-fill me-2"></i>Edit Student</div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label>Student ID</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($student['student_id']) ?>" disabled>
                    <small class="text-muted">ID cannot be changed.</small>
                </div>
                <div class="col-md-6">
                    <label>Full Name *</label>
                    <input type="text" name="student_name" class="form-control" value="<?= htmlspecialchars($student['student_name']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label>Batch *</label>
                    <input type="text" name="student_batch" class="form-control" value="<?= htmlspecialchars($student['student_batch']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label>Faculty *</label>
                    <input type="text" name="student_faculty" class="form-control" value="<?= htmlspecialchars($student['student_faculty']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label>Semester *</label>
                    <input type="number" name="student_semester" class="form-control" min="1" max="8" value="<?= htmlspecialchars($student['student_semester']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label>Phone</label>
                    <input type="text" name="student_phone" class="form-control" value="<?= htmlspecialchars($student['student_phone']) ?>">
                </div>
                <div class="col-12">
                    <label>Email <span class="text-muted" style="text-transform:none;font-weight:500;">(optional)</span></label>
                    <input type="email" name="student_email" class="form-control" value="<?= htmlspecialchars($student['student_email'] ?? '') ?>">
                </div>
            </div>
            <div class="d-flex flex-wrap gap-4 mt-3">
                <div class="form-check">
                    <input type="checkbox" name="voting_status" class="form-check-input" id="votingStatus" <?= $student['voting_status'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="votingStatus">Has already voted</label>
                </div>
                <div class="form-check">
                    <input type="checkbox" name="is_candidate" class="form-check-input" id="isCandidate" <?= $student['is_candidate'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="isCandidate">Is a candidate</label>
                </div>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Update Student</button>
                <a href="home.php?section=students" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php include 'footer.php'; ?>