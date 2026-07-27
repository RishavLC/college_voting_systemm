<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../Database/db_connect.php';

$error = '';
$success = '';
$event = null;

$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($event_id <= 0) {
    header("Location: home.php?section=events");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM election WHERE election_id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    header("Location: home.php?section=events");
    exit();
}
$event = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['election_name']);
    $alias = trim($_POST['alias']);
    $date = $_POST['election_date'];
    $batch = trim($_POST['election_batch']);
    $faculty = trim($_POST['election_faculty']);
    $semester = intval($_POST['election_semester']);
    $status = $_POST['election_status'];

    if (empty($name) || empty($date) || empty($batch) || empty($faculty) || $semester < 1 || $semester > 8) {
        $error = "All required fields must be filled correctly.";
    } else {
        // FIXED: type string must match 8 variables
        $update = $conn->prepare("UPDATE election SET 
            election_name = ?, 
            alias = ?, 
            election_date = ?, 
            election_batch = ?, 
            election_faculty = ?, 
            election_semester = ?, 
            election_status = ? 
            WHERE election_id = ?");
        $update->bind_param("sssssisi", $name, $alias, $date, $batch, $faculty, $semester, $status, $event_id);        
        if ($update->execute()) {
            $success = "Election updated successfully!";
            // Refresh data
            $stmt = $conn->prepare("SELECT * FROM election WHERE election_id = ?");
            $stmt->bind_param("i", $event_id);
            $stmt->execute();
            $event = $stmt->get_result()->fetch_assoc();
        } else {
            $error = "Database error: " . $conn->error;
        }
    }
}

$pageTitle = 'Edit Election';
$pageSubtitle = 'Election #' . $event_id;
include 'header.php';
?>
<div class="card shadow" style="max-width:720px;">
    <div class="card-header bg-success text-white"><i class="bi bi-calendar-check-fill me-2"></i>Edit Election</div>
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
                    <label>Election ID</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($event['election_id']) ?>" disabled>
                    <small class="text-muted">ID cannot be changed.</small>
                </div>
                <div class="col-md-6">
                    <label>Status</label>
                    <select name="election_status" class="form-select">
                        <option value="upcoming" <?= $event['election_status'] == 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                        <option value="active" <?= $event['election_status'] == 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="closed" <?= $event['election_status'] == 'closed' ? 'selected' : '' ?>>Closed</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <label>Election Name *</label>
                    <input type="text" name="election_name" class="form-control" value="<?= htmlspecialchars($event['election_name']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label>Alias (short name)</label>
                    <input type="text" name="alias" class="form-control" value="<?= htmlspecialchars($event['alias']) ?>">
                </div>
                <div class="col-md-6">
                    <label>Election Date *</label>
                    <input type="date" name="election_date" class="form-control" value="<?= htmlspecialchars($event['election_date']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label>Semester *</label>
                    <input type="number" name="election_semester" class="form-control" min="1" max="8" value="<?= htmlspecialchars($event['election_semester']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label>Batch *</label>
                    <input type="text" name="election_batch" class="form-control" value="<?= htmlspecialchars($event['election_batch']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label>Faculty *</label>
                    <input type="text" name="election_faculty" class="form-control" value="<?= htmlspecialchars($event['election_faculty']) ?>" required>
                </div>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Update Election</button>
                <a href="home.php?section=events" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php include 'footer.php'; ?>
