<?php
require_once '../Database/db_connect.php';

// Fetch filters
$batch = $_GET['batch'] ?? '';
$faculty = $_GET['faculty'] ?? '';
$semester = $_GET['semester'] ?? '';

$sql = "SELECT * FROM student WHERE 1=1";
if ($batch) $sql .= " AND student_batch = '$batch'";
if ($faculty) $sql .= " AND student_faculty = '$faculty'";
if ($semester) $sql .= " AND student_semester = '$semester'";
$result = $conn->query($sql);
?>
<div class="card shadow">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-people-fill me-2"></i>Students</span>
        <div class="d-flex gap-2">
            <a href="add_student.php" class="btn btn-sm btn-light"><i class="bi bi-plus-lg"></i> Add</a>
            <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#importModal"><i class="bi bi-file-earmark-arrow-up"></i> Import Excel</button>
            <button type="button" class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#resetAttendanceModal"><i class="bi bi-arrow-counterclockwise"></i> Reset All Attendance</button>
        </div>
    </div>
    <div class="card-body">
        <!-- Filters -->
        <form method="GET" action="home.php" class="row g-2 mb-3">
            <input type="hidden" name="section" value="students">
            <div class="col-md-3">
                <input type="text" name="batch" class="form-control" placeholder="Batch (e.g., 2022-2026)" value="<?= htmlspecialchars($batch) ?>">
            </div>
            <div class="col-md-3">
                <input type="text" name="faculty" class="form-control" placeholder="Faculty" value="<?= htmlspecialchars($faculty) ?>">
            </div>
            <div class="col-md-2">
                <input type="number" name="semester" class="form-control" placeholder="Semester" value="<?= htmlspecialchars($semester) ?>">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-secondary"><i class="bi bi-funnel"></i> Filter</button>
                <a href="home.php?section=students" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
        <table class="table table-bordered table-striped table-hover align-middle">
            <thead>
                <tr>
                    <th>ID</th><th>Name</th><th>Batch</th><th>Faculty</th><th>Semester</th>
                    <th>Phone</th><th>Email</th><th>Voted</th><th>Candidate</th><th>Present</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['student_id'] ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars($row['student_name']) ?></td>
                    <td><?= htmlspecialchars($row['student_batch']) ?></td>
                    <td><?= htmlspecialchars($row['student_faculty']) ?></td>
                    <td><?= $row['student_semester'] ?></td>
                    <td><?= htmlspecialchars($row['student_phone']) ?></td>
                    <td><?= htmlspecialchars($row['student_email']) ?></td>
                    <td><span class="badge bg-<?= $row['voting_status'] ? 'success' : 'secondary' ?>"><?= $row['voting_status'] ? 'Yes' : 'No' ?></span></td>
                    <td><span class="badge bg-<?= $row['is_candidate'] ? 'primary' : 'light text-dark' ?>"><?= $row['is_candidate'] ? 'Yes' : 'No' ?></span></td>
                    <td>
                        <?php if ($row['is_present']): ?>
                            <span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> Present</span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><i class="bi bi-clock"></i> Absent</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end flex-wrap">
                            <?php if (!$row['is_present']): ?>
                                <a href="mark_present.php?id=<?= $row['student_id'] ?>" class="btn btn-sm btn-success" title="Mark Present"><i class="bi bi-check2-circle"></i></a>
                            <?php else: ?>
                                <a href="mark_absent.php?id=<?= $row['student_id'] ?>" class="btn btn-sm btn-secondary" title="Mark Absent" onclick="return confirm('Mark this student as absent again?')"><i class="bi bi-x-circle"></i></a>
                            <?php endif; ?>
                            <a href="edit_student.php?id=<?= $row['student_id'] ?>" class="btn btn-sm btn-warning" title="Edit"><i class="bi bi-pencil-fill"></i></a>
                            <a href="delete_student.php?id=<?= $row['student_id'] ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Delete this student?')"><i class="bi bi-trash-fill"></i></a>
                            <?php if (!$row['is_candidate']): ?>
                                <!-- Trigger modal -->
                                <button type="button" class="btn btn-sm btn-success" title="Mark as Candidate" 
                                        data-bs-toggle="modal" data-bs-target="#candidateModal" 
                                        data-student-id="<?= $row['student_id'] ?>">
                                    <i class="bi bi-star-fill"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="11" class="text-center text-muted py-4"><i class="bi bi-inbox display-6 d-block mb-2"></i>No students found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- ====== RESET ALL ATTENDANCE MODAL ====== -->
<div class="modal fade" id="resetAttendanceModal" tabindex="-1" aria-labelledby="resetAttendanceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="resetAttendanceModalLabel"><i class="bi bi-exclamation-triangle-fill"></i> Reset All Attendance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>This will mark <strong>every student</strong> as <strong>absent</strong> again.</p>
                <p class="text-muted small mb-0">It only resets check-in/attendance status — it does not affect votes already cast or OTPs already issued.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="reset_all_present.php">
                    <input type="hidden" name="confirm_reset" value="1">
                    <button type="submit" class="btn btn-warning"><i class="bi bi-arrow-counterclockwise"></i> Reset All to Absent</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ====== CANDIDATE MODAL ====== -->
<div class="modal fade" id="candidateModal" tabindex="-1" aria-labelledby="candidateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="candidateModalLabel"><i class="bi bi-star-fill"></i> Mark as Candidate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="candidateForm" enctype="multipart/form-data">
                    <input type="hidden" name="student_id" id="candidate_student_id">
                    
                    <div class="mb-3">
                        <label for="candidate_photo" class="form-label">Candidate Photo</label>
                        <input type="file" class="form-control" name="candidate_photo" id="candidate_photo" accept="image/*">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="supporter1" class="form-label">Supporter 1</label>
                            <select class="form-select" name="supporter1" id="supporter1">
                                <option value="">Select supporter…</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="supporter2" class="form-label">Supporter 2</label>
                            <select class="form-select" name="supporter2" id="supporter2">
                                <option value="">Select supporter…</option>
                            </select>
                        </div>
                    </div>

                    <div id="candidateFeedback" class="mt-2"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="submitCandidateBtn">
                    <i class="bi bi-check-circle"></i> Mark as Candidate
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('candidateModal');
    const studentIdInput = document.getElementById('candidate_student_id');
    const supporter1 = document.getElementById('supporter1');
    const supporter2 = document.getElementById('supporter2');
    const feedback = document.getElementById('candidateFeedback');
    const submitBtn = document.getElementById('submitCandidateBtn');

    // When modal is about to show, load supporters via AJAX
    modal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget; // Button that triggered the modal
        const studentId = button.getAttribute('data-student-id');
        studentIdInput.value = studentId;

        // Reset form
        supporter1.innerHTML = '<option value="">Select supporter…</option>';
        supporter2.innerHTML = '<option value="">Select supporter…</option>';
        feedback.innerHTML = '';
        document.getElementById('candidate_photo').value = '';

        // Fetch supporters
        fetch('get_supporters.php?student_id=' + studentId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const options = data.supporters.map(s => 
                        `<option value="${s.student_id}">${s.student_name} (${s.student_id})</option>`
                    ).join('');
                    supporter1.innerHTML += options;
                    supporter2.innerHTML += options;
                } else {
                    feedback.innerHTML = `<div class="alert alert-warning">${data.message}</div>`;
                }
            })
            .catch(err => {
                feedback.innerHTML = `<div class="alert alert-danger">Error loading supporters.</div>`;
            });
    });

    // Submit form via AJAX
    submitBtn.addEventListener('click', function() {
        const form = document.getElementById('candidateForm');
        const formData = new FormData(form);

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing…';

        fetch('mark_candidate.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                feedback.innerHTML = `<div class="alert alert-success">${data.message}</div>`;
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                feedback.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
            }
        })
        .catch(error => {
            feedback.innerHTML = `<div class="alert alert-danger">Network error. Please try again.</div>`;
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Mark as Candidate';
        });
    });
});
</script>