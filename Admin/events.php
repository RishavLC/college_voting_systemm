<?php
session_start();
if (!isset($_SESSION['admin_id'])) header("Location: login.php");
require_once '../Database/db_connect.php';

$batch = $_GET['batch'] ?? '';
$faculty = $_GET['faculty'] ?? '';
$semester = $_GET['semester'] ?? '';

$sql = "SELECT * FROM election WHERE 1=1";
if ($batch) $sql .= " AND election_batch = '$batch'";
if ($faculty) $sql .= " AND election_faculty = '$faculty'";
if ($semester) $sql .= " AND election_semester = '$semester'";
$result = $conn->query($sql);

function statusBadge($status) {
    $map = ['active' => 'success', 'upcoming' => 'warning', 'closed' => 'secondary'];
    $cls = $map[$status] ?? 'secondary';
    return "<span class=\"badge bg-$cls\">" . ucfirst(htmlspecialchars($status)) . "</span>";
}
?>
<div class="card shadow">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-calendar-event-fill me-2"></i>Elections (Events)</span>
        <a href="add_event.php" class="btn btn-sm btn-light"><i class="bi bi-plus-lg"></i> Add Event</a>
    </div>
    <div class="card-body">
        <form method="GET" action="home.php" class="row g-2 mb-3">
            <input type="hidden" name="section" value="events">
            <div class="col-md-3"><input type="text" name="batch" class="form-control" placeholder="Batch" value="<?= htmlspecialchars($batch) ?>"></div>
            <div class="col-md-3"><input type="text" name="faculty" class="form-control" placeholder="Faculty" value="<?= htmlspecialchars($faculty) ?>"></div>
            <div class="col-md-2"><input type="number" name="semester" class="form-control" placeholder="Semester" value="<?= htmlspecialchars($semester) ?>"></div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-secondary"><i class="bi bi-funnel"></i> Filter</button>
                <a href="home.php?section=events" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
        <div class="table-responsive">
        <table class="table table-bordered table-striped table-hover align-middle">
            <thead><tr><th>ID</th><th>Name</th><th>Alias</th><th>Date</th><th>Batch</th><th>Faculty</th><th>Semester</th><th>Status</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['election_id'] ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars($row['election_name']) ?></td>
                    <td><?= htmlspecialchars($row['alias']) ?></td>
                    <td><?= htmlspecialchars($row['election_date']) ?></td>
                    <td><?= htmlspecialchars($row['election_batch']) ?></td>
                    <td><?= htmlspecialchars($row['election_faculty']) ?></td>
                    <td><?= $row['election_semester'] ?></td>
                    <td><?= statusBadge($row['election_status']) ?></td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end flex-wrap">
                            <!-- Status action buttons -->
                            <?php if ($row['election_status'] === 'upcoming'): ?>
                                <a href="update_election_status.php?id=<?= $row['election_id'] ?>&action=start" 
                                   class="btn btn-sm btn-success" 
                                   title="Start Election"
                                   onclick="return confirm('Are you sure you want to start this election? It will become active and students can vote.')">
                                    <i class="bi bi-play-fill"></i> Start
                                </a>
                            <?php elseif ($row['election_status'] === 'active'): ?>
                                <a href="update_election_status.php?id=<?= $row['election_id'] ?>&action=close" 
                                   class="btn btn-sm btn-danger" 
                                   title="Close Election"
                                   onclick="return confirm('Are you sure you want to close this election? Voting will be disabled.')">
                                    <i class="bi bi-stop-fill"></i> Close
                                </a>
                            <?php endif; ?>
                            <!-- Edit -->
                            <a href="edit_event.php?id=<?= $row['election_id'] ?>" class="btn btn-sm btn-warning" title="Edit"><i class="bi bi-pencil-fill"></i></a>
                            <!-- Delete -->
                            <a href="delete_event.php?id=<?= $row['election_id'] ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Delete this election?')"><i class="bi bi-trash-fill"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="9" class="text-center text-muted py-4"><i class="bi bi-inbox display-6 d-block mb-2"></i>No elections found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>