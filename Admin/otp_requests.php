<?php
// Admin: OTP Requests lookup.
// Lets an admin find a student's OTP (e.g. when a student calls in
// after being blocked from requesting a second OTP) and see whether
// it has already been used to verify.

$search = trim($_GET['search'] ?? '');

$sql = "SELECT r.otp_id, r.student_id, s.student_name, r.mobile, r.otp,
               r.requested_at, r.is_used, r.used_at, r.status, s.is_present
        FROM otp_requests r
        JOIN student s ON s.student_id = r.student_id
        WHERE 1=1";

if ($search !== '') {
    $searchEsc = $conn->real_escape_string($search);
    $sql .= " AND (s.student_name LIKE '%$searchEsc%' OR r.mobile LIKE '%$searchEsc%' OR r.student_id = '$searchEsc')";
}
$sql .= " ORDER BY r.requested_at DESC";
$result = $conn->query($sql);
?>
<div class="card shadow">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-shield-lock-fill me-2"></i>OTP Requests</span>
    </div>
    <div class="card-body">
        <p class="text-muted small">
            Every student can request an OTP only once. If a student contacts you saying they can't request
            an OTP again, look them up here and read them their OTP.
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
                    <th>Student ID</th><th>Name</th><th>Mobile</th><th>OTP</th>
                    <th>Present?</th><th>Requested At</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= (int)$row['student_id'] ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars($row['student_name']) ?></td>
                    <td><?= htmlspecialchars($row['mobile']) ?></td>
                    <td><span class="fw-bold" style="letter-spacing:.15em;"><?= htmlspecialchars($row['otp']) ?></span></td>
                    <td>
                        <?php if ($row['is_present']): ?>
                            <span class="badge bg-success">Present</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Absent</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($row['requested_at']) ?></td>
                    <td>
                        <?php if ($row['status'] === 'verified'): ?>
                            <span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> Verified</span>
                        <?php elseif ($row['status'] === 'expired'): ?>
                            <span class="badge bg-secondary">Expired</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Sent / Not used</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-inbox display-6 d-block mb-2"></i>No OTP requests found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
