<?php
session_start();
if (!isset($_SESSION['admin_id'])) header("Location: login.php");
require_once '../Database/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['election_name'];
    $alias = $_POST['alias'];
    $date = $_POST['election_date'];
    $batch = $_POST['election_batch'];
    $faculty = $_POST['election_faculty'];
    $semester = $_POST['election_semester'];
    $status = $_POST['election_status'];
    $stmt = $conn->prepare("INSERT INTO election (election_name, alias, election_date, election_batch, election_faculty, election_semester, election_status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssis", $name, $alias, $date, $batch, $faculty, $semester, $status);
    if ($stmt->execute()) {
        header("Location: home.php?section=events");
        exit();
    }
}

$pageTitle = 'Add Election';
$pageSubtitle = 'Create a new voting event';
include 'header.php';
?>
<div class="card shadow" style="max-width:720px;">
    <div class="card-header bg-success text-white"><i class="bi bi-calendar-plus-fill me-2"></i>Add Election</div>
    <div class="card-body">
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-8"><label>Event Name</label><input type="text" name="election_name" class="form-control" required></div>
                <div class="col-md-4"><label>Alias</label><input type="text" name="alias" class="form-control"></div>
                <div class="col-md-6"><label>Date</label><input type="date" name="election_date" class="form-control" required></div>
                <div class="col-md-6"><label>Semester</label><input type="number" name="election_semester" class="form-control" min="1" max="8" required></div>
                <div class="col-md-6"><label>Batch</label><input type="text" name="election_batch" class="form-control" required></div>
                <div class="col-md-6"><label>Faculty</label><input type="text" name="election_faculty" class="form-control" required></div>
                <div class="col-12">
                    <label>Status</label>
                    <select name="election_status" class="form-select">
                        <option value="upcoming">Upcoming</option>
                        <option value="active">Active</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Save</button>
                <a href="home.php?section=events" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php include 'footer.php'; ?>
