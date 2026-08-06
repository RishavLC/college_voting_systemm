<?php

if (!isset($_SESSION['admin_id'])) header("Location: login.php");
// Admin: OTP Lookup from student table.
// Shows the current OTP for each student, and whether it has been used (voting_status).
// No separate otp_requests table needed.

$search = trim($_GET['search'] ?? '');

// Query from student table directly
$sql = "SELECT student_id, student_name, student_phone, student_otp, is_present, voting_status
        FROM student
        WHERE 1=1";

if ($search !== '') {
    $searchEsc = $conn->real_escape_string($search);
    $sql .= " AND (student_name LIKE '%$searchEsc%' 
                OR student_phone LIKE '%$searchEsc%' 
                OR student_id = '$searchEsc')";
}
$sql .= " ORDER BY student_id ASC";
$result = $conn->query($sql);
?>
<div class="card shadow">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-shield-lock-fill me-2"></i>OTP Lookup</span>
    </div>
    <div class="card-body">
        <p class="text-muted small">
            View OTPs for all students. If a student cannot request a new OTP, you can read it here and help them complete verification.
        </p>

        <form method="GET" action="home.php" class="row g-2 mb-3">
            <input type="hidden" name="section" value="otp_requests">
            <div class="col-md-6">
                <input type="text" name="search" class="form-control" placeholder="Search by name, mobile, or student ID" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-secondary"><i class="bi bi-search"></i> Search</button>
                <a href="home.php?section=otp_requests" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
        <table class="table table-bordered table-striped table-hover align-middle">
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Mobile</th>
                    <th>OTP</th>
                    <th>Present?</th>
                    <th>Voted?</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= (int)$row['student_id'] ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars($row['student_name']) ?></td>
                    <td><?= htmlspecialchars($row['student_phone']) ?></td>
                    <td>
                        <?php if (!empty($row['student_otp'])): ?>
                            <span class="fw-bold" style="letter-spacing:.15em; color:#0d6efd;"><?= htmlspecialchars($row['student_otp']) ?></span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($row['is_present']): ?>
                            <span class="badge bg-success">Present</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Absent</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($row['voting_status']): ?>
                            <span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> Voted</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-clock"></i> Not Voted</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox display-6 d-block mb-2"></i>No students found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>