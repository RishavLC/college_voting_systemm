<?php
session_start();
if (!isset($_SESSION['admin_id'])) header("Location: login.php");
require_once '../Database/db_connect.php';

// Fetch filters + search
$batch = $_GET['batch'] ?? '';
$faculty = $_GET['faculty'] ?? '';
$semester = $_GET['semester'] ?? '';
$search = $_GET['search'] ?? '';

// Modified query to include candidate status with roles
$sql = "SELECT s.*, 
        CASE 
            WHEN c.candidate_id IS NOT NULL THEN 'candidate'
            WHEN cs1.candidate_id IS NOT NULL THEN 'supporter1'
            WHEN cs2.candidate_id IS NOT NULL THEN 'supporter2'
            WHEN cp.candidate_id IS NOT NULL THEN 'proposer'
            ELSE 'none'
        END as candidate_role
        FROM student s
        LEFT JOIN candidate c ON s.student_id = c.student_id
        LEFT JOIN candidate cs1 ON s.student_id = cs1.supporter1
        LEFT JOIN candidate cs2 ON s.student_id = cs2.supporter2
        LEFT JOIN candidate cp ON s.student_id = cp.proposer
        WHERE 1=1";

if ($batch) $sql .= " AND s.student_batch = '$batch'";
if ($faculty) $sql .= " AND s.student_faculty = '$faculty'";
if ($semester) $sql .= " AND s.student_semester = '$semester'";
if ($search) {
    $searchEsc = $conn->real_escape_string($search);
    $sql .= " AND (s.student_name LIKE '%$searchEsc%' 
                  OR s.student_email LIKE '%$searchEsc%' 
                  OR s.student_id = '$searchEsc')";
}
// Group by to avoid duplicates
$sql .= " GROUP BY s.student_id";
$result = $conn->query($sql);
?>

<style>
    /* Sticky header for the table */
    .sticky-header thead th {
        position: sticky;
        top: 0;
        background: #f7f7fc;
        z-index: 10;
        box-shadow: inset 0 -2px 0 var(--border, #e7e6f3);
    }
    .table-scroll {
        max-height: 500px;
        overflow-y: auto;
        border-radius: var(--radius-md, 8px);
        border: 1px solid var(--border, #e7e6f3);
    }
    .table-scroll table {
        margin-bottom: 0;
    }
    .table-scroll .table thead th {
        background: #f7f7fc;
    }
    /* Status badge styles */
    .role-badge {
        font-size: 0.7rem;
        padding: 0.25rem 0.5rem;
    }
    
    /* Photo preview animations */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: scale(0.9);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }
    
    /* Photo preview container styling */
    #photoPreviewContainer .border {
        transition: all 0.3s ease;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    }
    
    #photoPreview {
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        max-width: 200px;
        max-height: 200px;
        object-fit: cover;
        border-radius: 8px;
        border: 2px solid #198754;
    }
    
    #photoPreview:hover {
        transform: scale(1.02);
        box-shadow: 0 4px 16px rgba(0,0,0,0.2);
    }
    
    /* Placeholder styling */
    #photoPlaceholder {
        transition: all 0.3s ease;
        background: #f8f9fa;
    }
    
    #photoPlaceholder i {
        font-size: 3rem;
        color: #adb5bd;
    }
    
    /* File input styling */
    #candidate_photo {
        cursor: pointer;
    }
    
    #candidate_photo:hover {
        border-color: #198754;
    }
    
    /* Clear button */
    #clearPhotoBtn {
        transition: all 0.3s ease;
    }
    
    #clearPhotoBtn:hover {
        background-color: #dc3545;
        color: white;
        border-color: #dc3545;
    }

    /* ===== FIXED MODAL STYLES - NON SCROLLABLE ===== */
    .modal-content {
        max-height: 95vh;
        overflow: hidden;
        border-radius: 12px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }

    .modal-header {
        flex-shrink: 0;
        padding: 1rem 1.5rem;
        border-bottom: 2px solid rgba(255,255,255,0.1);
    }

    .modal-body {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: 1.5rem;
        max-height: calc(95vh - 140px);
        /* Custom scrollbar for better appearance */
        scrollbar-width: thin;
        scrollbar-color: #c1c7cd transparent;
    }

    .modal-body::-webkit-scrollbar {
        width: 6px;
    }

    .modal-body::-webkit-scrollbar-track {
        background: transparent;
    }

    .modal-body::-webkit-scrollbar-thumb {
        background-color: #c1c7cd;
        border-radius: 3px;
    }

    .modal-body::-webkit-scrollbar-thumb:hover {
        background-color: #a0a7ae;
    }

    .modal-footer {
        flex-shrink: 0;
        padding: 1rem 1.5rem;
        border-top: 1px solid #dee2e6;
        background: #f8f9fa;
        border-radius: 0 0 12px 12px;
    }

    /* Modal dialog positioning */
    .modal-dialog {
        margin: 1.75rem auto;
        max-width: 700px;
        display: flex;
        align-items: center;
        min-height: calc(100% - 3.5rem);
    }

    /* Candidate info alert styling */
    #candidateInfo {
        margin-bottom: 1.25rem;
        padding: 0.75rem 1rem;
        border-radius: 8px;
        background: #e7f3ff;
        border-color: #b6d4fe;
        color: #084298;
        font-size: 0.95rem;
    }

    /* Form spacing */
    #candidateForm .mb-3 {
        margin-bottom: 1.25rem !important;
    }

    /* Responsive adjustments */
    @media (max-width: 576px) {
        .modal-dialog {
            margin: 0.5rem;
            min-height: calc(100% - 1rem);
        }
        .modal-body {
            padding: 1rem;
            max-height: calc(95vh - 120px);
        }
        .modal-header {
            padding: 0.75rem 1rem;
        }
        .modal-footer {
            padding: 0.75rem 1rem;
            flex-wrap: wrap;
            justify-content: center;
        }
        .modal-footer .btn {
            flex: 1;
            min-width: 80px;
        }
    }

    /* Mobile landscape */
    @media (max-height: 600px) and (orientation: landscape) {
        .modal-body {
            max-height: calc(100vh - 160px);
        }
        #photoPreview {
            max-width: 120px;
            max-height: 120px;
        }
        .modal-dialog {
            margin: 0.25rem auto;
        }
    }

    /* Ensure buttons are properly sized */
    .modal-footer .btn {
        padding: 0.5rem 1.25rem;
        font-weight: 500;
    }

    /* Loading state */
    .spinner-border-sm {
        width: 1rem;
        height: 1rem;
        border-width: 0.2em;
    }

    /* Feedback messages */
    #candidateFeedback .alert {
        border-radius: 8px;
        margin-top: 0.5rem;
        padding: 0.75rem 1rem;
    }
</style>

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
        <!-- Filters + Search -->
        <form method="GET" action="home.php" class="row g-2 mb-3">
            <input type="hidden" name="section" value="students">
            <div class="col-md-3">
                <input type="text" name="batch" class="form-control" placeholder="Batch" value="<?= htmlspecialchars($batch) ?>">
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
            <!-- Search row -->
            <div class="col-12 mt-2">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search by name, email, or student ID" value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
            </div>
        </form>

        <!-- Table -->
        <div class="table-scroll">
            <table class="table table-bordered table-striped table-hover align-middle sticky-header">
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
                    <?php 
                    // Determine if student is already a candidate, supporter, or proposer
                    $isCandidate = $row['is_candidate'] == 1;
                    $candidateRole = $row['candidate_role'] ?? 'none';
                    
                    // Check if student is involved in any election role
                    $isInvolved = ($candidateRole != 'none');
                    
                    // Get role label for display
                    $roleLabel = '';
                    $roleBadgeClass = '';
                    switch($candidateRole) {
                        case 'candidate':
                            $roleLabel = 'Candidate';
                            $roleBadgeClass = 'bg-primary';
                            break;
                        case 'supporter1':
                        case 'supporter2':
                            $roleLabel = 'Supporter';
                            $roleBadgeClass = 'bg-info';
                            break;
                        case 'proposer':
                            $roleLabel = 'Proposer';
                            $roleBadgeClass = 'bg-warning text-dark';
                            break;
                        default:
                            $roleLabel = '';
                            $roleBadgeClass = '';
                    }
                    ?>
                    <tr>
                        <td><?= $row['student_id'] ?></td>
                        <td class="fw-semibold">
                            <?= htmlspecialchars($row['student_name']) ?>
                            <?php if ($isInvolved): ?>
                                <span class="badge <?= $roleBadgeClass ?> role-badge ms-1"><?= $roleLabel ?></span>
                            <?php endif; ?>
                        </td>
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
                                
                                <?php 
                                // Only show "Mark as Candidate" button if student is NOT involved in any election role
                                if (!$isInvolved && !$row['is_candidate']): 
                                ?>
                                    <button type="button" class="btn btn-sm btn-success" title="Mark as Candidate" 
                                            data-bs-toggle="modal" data-bs-target="#candidateModal" 
                                            data-student-id="<?= $row['student_id'] ?>"
                                            data-student-name="<?= htmlspecialchars($row['student_name']) ?>"
                                            data-student-faculty="<?= htmlspecialchars($row['student_faculty']) ?>"
                                            data-student-batch="<?= htmlspecialchars($row['student_batch']) ?>"
                                            data-student-semester="<?= $row['student_semester'] ?>">
                                        <i class="bi bi-star-fill"></i>
                                    </button>
                                <?php else: ?>
                                    <!-- Show disabled state with tooltip -->
                                    <button type="button" class="btn btn-sm btn-secondary" title="Already involved in election" disabled>
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

<!-- ====== CANDIDATE MODAL WITH PHOTO PREVIEW - FIXED NON-SCROLLABLE ====== -->
<div class="modal fade" id="candidateModal" tabindex="-1" aria-labelledby="candidateModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="candidateModalLabel"><i class="bi bi-star-fill"></i> Mark as Candidate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Candidate Info -->
                <div class="alert alert-info" id="candidateInfo">
                    <strong>Candidate:</strong> <span id="candidateName">—</span><br>
                    <strong>Faculty:</strong> <span id="candidateFaculty">—</span> &middot;
                    <strong>Batch:</strong> <span id="candidateBatch">—</span> &middot;
                    <strong>Semester:</strong> <span id="candidateSemester">—</span>
                </div>

                <form id="candidateForm" enctype="multipart/form-data">
                    <input type="hidden" name="student_id" id="candidate_student_id">
                    <input type="hidden" name="election_id" id="election_id" value="2">

                    <!-- Photo Upload with Preview -->
                    <div class="mb-3">
                        <label for="candidate_photo" class="form-label">Candidate Photo <span class="text-danger">*</span></label>
                        
                        <!-- Photo Preview Container -->
                        <div id="photoPreviewContainer" class="mb-2 text-center" style="display: none;">
                            <div class="p-3 border rounded bg-light">
                                <img id="photoPreview" src="#" alt="Candidate Photo Preview">
                                <div class="mt-2">
                                    <span id="photoFileName" class="text-muted small"></span>
                                    <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="removePhoto()">
                                        <i class="bi bi-x-circle"></i> Remove
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- File Input -->
                        <div class="input-group">
                            <input type="file" class="form-control" name="candidate_photo" id="candidate_photo" accept="image/*" required>
                            <button class="btn btn-outline-secondary" type="button" id="clearPhotoBtn" onclick="removePhoto()">
                                <i class="bi bi-x"></i> Clear
                            </button>
                        </div>
                        <div class="form-text">Upload a photo of the candidate (required). Supported formats: JPG, PNG, GIF, WEBP (Max 5MB)</div>
                        
                        <!-- Photo Preview Placeholder -->
                        <div id="photoPlaceholder" class="text-center p-3 border rounded bg-light mt-2">
                            <i class="bi bi-image display-6 text-muted"></i>
                            <p class="text-muted mb-0">No photo selected</p>
                            <small class="text-muted">Click "Choose File" to upload a candidate photo</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="supporter1" class="form-label">Supporter 1 <span class="text-danger">*</span></label>
                            <select class="form-select" name="supporter1" id="supporter1" required>
                                <option value="">Select supporter…</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="supporter2" class="form-label">Supporter 2 <span class="text-danger">*</span></label>
                            <select class="form-select" name="supporter2" id="supporter2" required>
                                <option value="">Select supporter…</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="proposer" class="form-label">Proposer <span class="text-danger">*</span></label>
                            <select class="form-select" name="proposer" id="proposer" required>
                                <option value="">Select proposer…</option>
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
    const proposer = document.getElementById('proposer');
    const feedback = document.getElementById('candidateFeedback');
    const submitBtn = document.getElementById('submitCandidateBtn');
    let photoInput = document.getElementById('candidate_photo');
    
    // Photo preview elements
    const photoPreviewContainer = document.getElementById('photoPreviewContainer');
    const photoPreview = document.getElementById('photoPreview');
    const photoFileName = document.getElementById('photoFileName');
    const photoPlaceholder = document.getElementById('photoPlaceholder');

    const candName = document.getElementById('candidateName');
    const candFaculty = document.getElementById('candidateFaculty');
    const candBatch = document.getElementById('candidateBatch');
    const candSemester = document.getElementById('candidateSemester');

    let allStudents = [];
    let candidateId = 0;

    // ===== PHOTO PREVIEW FUNCTIONS =====
    function previewPhoto(file) {
        console.log('Previewing photo:', file);
        
        if (!file) {
            console.log('No file provided');
            hidePhotoPreview();
            return;
        }

        // Validate file type
        const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
        if (!validTypes.includes(file.type)) {
            console.log('Invalid file type:', file.type);
            feedback.innerHTML = `<div class="alert alert-danger">Please upload a valid image file (JPG, PNG, GIF, WEBP, BMP).</div>`;
            photoInput.value = '';
            hidePhotoPreview();
            return;
        }

        // Validate file size (max 5MB)
        const maxSize = 5 * 1024 * 1024; // 5MB
        if (file.size > maxSize) {
            console.log('File too large:', file.size);
            feedback.innerHTML = `<div class="alert alert-danger">File size exceeds 5MB limit. Please compress your image.</div>`;
            photoInput.value = '';
            hidePhotoPreview();
            return;
        }

        console.log('Reading file with FileReader...');
        const reader = new FileReader();
        reader.onload = function(e) {
            console.log('File loaded successfully');
            // Show preview
            photoPreview.src = e.target.result;
            photoPreviewContainer.style.display = 'block';
            photoPlaceholder.style.display = 'none';
            photoFileName.textContent = file.name + ' (' + formatFileSize(file.size) + ')';
            feedback.innerHTML = ''; // Clear any previous errors
            
            // Add success animation
            photoPreview.style.animation = 'fadeIn 0.5s ease';
            console.log('Preview displayed');
        };
        reader.onerror = function() {
            console.error('Error reading file:', reader.error);
            feedback.innerHTML = `<div class="alert alert-danger">Error reading file. Please try again.</div>`;
        };
        reader.readAsDataURL(file);
    }

    function hidePhotoPreview() {
        console.log('Hiding photo preview');
        photoPreviewContainer.style.display = 'none';
        photoPlaceholder.style.display = 'block';
        photoPreview.src = '#';
        photoFileName.textContent = '';
    }

    // Make removePhoto globally accessible for onclick
    window.removePhoto = function() {
        console.log('Removing photo');
        photoInput.value = '';
        hidePhotoPreview();
        feedback.innerHTML = '';
        // Reset the file input
        const newInput = photoInput.cloneNode(true);
        photoInput.parentNode.replaceChild(newInput, photoInput);
        // Re-attach event listener
        newInput.addEventListener('change', handlePhotoChange);
        document.getElementById('clearPhotoBtn').onclick = window.removePhoto;
        // Update the reference
        photoInput = newInput;
    };

    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    // ===== PHOTO INPUT EVENT HANDLER =====
    function handlePhotoChange() {
        console.log('Photo input changed');
        console.log('Files:', this.files);
        
        if (this.files && this.files.length > 0) {
            const file = this.files[0];
            console.log('Selected file:', file.name, file.type, file.size);
            previewPhoto(file);
        } else {
            console.log('No file selected');
            hidePhotoPreview();
        }
    }

    // Attach event listener to photo input
    photoInput.addEventListener('change', handlePhotoChange);
    console.log('Photo input listener attached');

    // ===== DROPDOWN FUNCTIONS =====
    function rebuildDropdowns() {
        const s1 = supporter1;
        const s2 = supporter2;
        const prop = proposer;
        const currentS1 = s1.value;
        const currentS2 = s2.value;
        const currentProp = prop.value;

        function buildOptions(excludeIds, selectedValue) {
            let html = '<option value="">Select …</option>';
            allStudents.forEach(s => {
                const idStr = s.student_id.toString();
                if (excludeIds.includes(idStr)) return;
                const selected = (idStr === selectedValue) ? ' selected' : '';
                html += `<option value="${idStr}"${selected}>${s.student_name} (${s.student_id})</option>`;
            });
            return html;
        }

        const excludeS1 = [candidateId.toString()];
        if (currentS2) excludeS1.push(currentS2);
        if (currentProp) excludeS1.push(currentProp);
        s1.innerHTML = buildOptions(excludeS1, currentS1);

        const excludeS2 = [candidateId.toString()];
        if (currentS1) excludeS2.push(currentS1);
        if (currentProp) excludeS2.push(currentProp);
        s2.innerHTML = buildOptions(excludeS2, currentS2);

        const excludeProp = [candidateId.toString()];
        if (currentS1) excludeProp.push(currentS1);
        if (currentS2) excludeProp.push(currentS2);
        prop.innerHTML = buildOptions(excludeProp, currentProp);
    }

    supporter1.addEventListener('change', rebuildDropdowns);
    supporter2.addEventListener('change', rebuildDropdowns);
    proposer.addEventListener('change', rebuildDropdowns);

    // ===== MODAL SHOW EVENT =====
    modal.addEventListener('show.bs.modal', function(event) {
        console.log('Modal showing');
        const button = event.relatedTarget;
        const studentId = button.getAttribute('data-student-id');
        candidateId = studentId;
        studentIdInput.value = studentId;

        candName.textContent = button.getAttribute('data-student-name') || '—';
        candFaculty.textContent = button.getAttribute('data-student-faculty') || '—';
        candBatch.textContent = button.getAttribute('data-student-batch') || '—';
        candSemester.textContent = button.getAttribute('data-student-semester') || '—';

        // Reset photo preview
        photoInput.value = '';
        hidePhotoPreview();
        
        // Reset dropdowns
        supporter1.innerHTML = '<option value="">Select supporter…</option>';
        supporter2.innerHTML = '<option value="">Select supporter…</option>';
        proposer.innerHTML = '<option value="">Select proposer…</option>';
        feedback.innerHTML = '';

        fetch('get_supporters.php?student_id=' + studentId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    allStudents = data.supporters;
                    rebuildDropdowns();
                } else {
                    feedback.innerHTML = `<div class="alert alert-warning">${data.message}</div>`;
                }
            })
            .catch(err => {
                feedback.innerHTML = `<div class="alert alert-danger">Error loading supporters.</div>`;
            });
    });

    // ===== MODAL HIDDEN EVENT =====
    modal.addEventListener('hidden.bs.modal', function() {
        console.log('Modal hidden');
        // Reset everything when modal is closed
        photoInput.value = '';
        hidePhotoPreview();
        feedback.innerHTML = '';
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Mark as Candidate';
        
        // Reset the file input
        const newInput = photoInput.cloneNode(true);
        photoInput.parentNode.replaceChild(newInput, photoInput);
        newInput.addEventListener('change', handlePhotoChange);
        document.getElementById('clearPhotoBtn').onclick = window.removePhoto;
        photoInput = newInput;
    });

    // ===== VALIDATION =====
    function validateForm() {
        // Check photo
        if (!photoInput.files || photoInput.files.length === 0) {
            feedback.innerHTML = `<div class="alert alert-danger">Please upload a candidate photo.</div>`;
            photoInput.focus();
            return false;
        }
        
        // Check file type and size
        const file = photoInput.files[0];
        const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
        if (!validTypes.includes(file.type)) {
            feedback.innerHTML = `<div class="alert alert-danger">Please upload a valid image file (JPG, PNG, GIF, WEBP, BMP).</div>`;
            return false;
        }
        if (file.size > 5 * 1024 * 1024) {
            feedback.innerHTML = `<div class="alert alert-danger">File size exceeds 5MB limit.</div>`;
            return false;
        }

        // Check supporters and proposer
        const selects = [supporter1, supporter2, proposer];
        const selectNames = ['Supporter 1', 'Supporter 2', 'Proposer'];
        for (let i = 0; i < selects.length; i++) {
            if (!selects[i].value) {
                feedback.innerHTML = `<div class="alert alert-danger">Please select a ${selectNames[i]}.</div>`;
                selects[i].focus();
                return false;
            }
        }
        return true;
    }

    // ===== SUBMIT =====
    submitBtn.addEventListener('click', function() {
        feedback.innerHTML = '';
        if (!validateForm()) return;

        const form = document.getElementById('candidateForm');
        const formData = new FormData(form);

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing…';

        fetch('mark_candidate.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            // Check if response is OK
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error('HTTP ' + response.status + ': ' + text.substring(0, 200));
                });
            }
            return response.text();
        })
        .then(text => {
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('Raw response:', text);
                throw new Error('Server returned invalid JSON. Please check the console for details.');
            }
            return data;
        })
        .then(data => {
            if (data.success) {
                feedback.innerHTML = `<div class="alert alert-success">${data.message}</div>`;
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                let msg = data.message || 'Something went wrong.';
                if (data.debug) msg += `<br><small class="text-muted">${data.debug}</small>`;
                feedback.innerHTML = `<div class="alert alert-danger">${msg}</div>`;
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Mark as Candidate';
            }
        })
        .catch(error => {
            console.error('Error details:', error);
            feedback.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Mark as Candidate';
        });
    });
});
</script>