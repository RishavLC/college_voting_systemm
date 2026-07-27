<?php
session_start();
if (!isset($_SESSION['admin_id'])) header("Location: login.php");
require_once '../Database/db_connect.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['student_id'];
    $name = $_POST['student_name'];
    $batch = $_POST['student_batch'];
    $faculty = $_POST['student_faculty'];
    $semester = $_POST['student_semester'];
    $phone = $_POST['student_phone'];
    $email = $_POST['student_email'];

    $stmt = $conn->prepare("INSERT INTO student (student_id, student_name, student_batch, student_faculty, student_semester, student_phone, student_email) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssiss", $id, $name, $batch, $faculty, $semester, $phone, $email);
    if ($stmt->execute()) {
        header("Location: home.php?section=students");
        exit();
    } else {
        $error = "Error: " . $conn->error;
    }
}

$pageTitle = 'Add Student';
$pageSubtitle = 'Register a new student in the system';
include 'header.php';
?>
<div class="card shadow" style="max-width:720px;">
    <div class="card-header bg-primary text-white"><i class="bi bi-person-plus-fill me-2"></i>Add Student</div>
    <div class="card-body">
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6"><label>Student ID</label><input type="number" name="student_id" class="form-control" required></div>
                <div class="col-md-6"><label>Name</label><input type="text" name="student_name" class="form-control" required></div>
                <div class="col-md-6"><label>Batch</label><input type="text" name="student_batch" class="form-control" required></div>
                <div class="col-md-6"><label>Faculty</label><input type="text" name="student_faculty" class="form-control" required></div>
                <div class="col-md-6"><label>Semester</label><input type="number" name="student_semester" class="form-control" min="1" max="8" required></div>
                <div class="col-md-6"><label>Phone</label><input type="text" name="student_phone" class="form-control"></div>
                <div class="col-12"><label>Email</label><input type="email" name="student_email" class="form-control" required></div>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
                <a href="home.php?section=students" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php include 'footer.php'; ?>
