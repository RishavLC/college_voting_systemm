<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../Database/db_connect.php';

// Define program options
$programs = ['BCA', 'BIM/BITM', 'BBS', 'Bsc. Csit', 'BHM'];

// Define semester options for each program
$semesterOptions = [
    'BCA' => [1,2,3,4,5,6,7,8],
    'BIM/BITM' => [1,2,3,4,5,6,7,8],
    'BBS' => [1,2,3,4],
    'Bsc. Csit' => [1,2,3,4,5,6,7,8],
    'BHM' => [1,2,3,4,5,6,7,8]
];

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
    $email = $email === '' ? null : $email;
    $voting_status = isset($_POST['voting_status']) ? 1 : 0;
    $is_candidate = isset($_POST['is_candidate']) ? 1 : 0;

    // Validate
    if (empty($name) || empty($batch) || empty($faculty) || $semester < 1) {
        $error = "All required fields (Name, Batch, Faculty, Semester) must be filled correctly.";
    } elseif (!in_array($faculty, $programs)) {
        $error = "Please select a valid program.";
    } elseif (isset($semesterOptions[$faculty]) && !in_array($semester, $semesterOptions[$faculty])) {
        $error = "Invalid semester for the selected program.";
    } elseif ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check if email already used by another student
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
        <form method="POST" id="editStudentForm">
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
                    <label>Program *</label>
                    <select name="student_faculty" id="programSelect" class="form-control" required>
                        <option value="">Select Program...</option>
                        <?php foreach ($programs as $program): ?>
                            <option value="<?= $program ?>" 
                                <?= ($student['student_faculty'] == $program) ? 'selected' : '' ?>>
                                <?= $program ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Semester/Year *</label>
                    <select name="student_semester" id="semesterSelect" class="form-control" required>
                        <option value="">Select Semester/Year...</option>
                        <?php 
                        $currentSemester = $student['student_semester'];
                        $defaultSemesters = [1,2,3,4,5,6,7,8];
                        foreach ($defaultSemesters as $sem): 
                        ?>
                            <option value="<?= $sem ?>" 
                                <?= ($currentSemester == $sem) ? 'selected' : '' ?>>
                                <?= $sem ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const programSelect = document.getElementById('programSelect');
    const semesterSelect = document.getElementById('semesterSelect');

    // Semester options for each program
    const semesterOptions = {
        'BCA': [1, 2, 3, 4, 5, 6, 7, 8],
        'BIM/BITM': [1, 2, 3, 4, 5, 6, 7, 8],
        'BBS': [1, 2, 3, 4],
        'Bsc. Csit': [1, 2, 3, 4, 5, 6, 7, 8],
        'BHM': [1, 2, 3, 4, 5, 6, 7, 8]
    };

    // Store current semester value
    const currentSemester = '<?= $student['student_semester'] ?>';

    // Update semester dropdown based on selected program
    function updateSemesterDropdown(program) {
        // Clear current options
        semesterSelect.innerHTML = '<option value="">Select Semester...</option>';
        
        // Get semester list for selected program
        const semesters = semesterOptions[program] || [];
        
        if (semesters.length === 0) {
            semesterSelect.disabled = true;
            return;
        }

        semesterSelect.disabled = false;
        
        // Add options
        semesters.forEach(sem => {
            const option = document.createElement('option');
            option.value = sem;
            option.textContent = sem;
            // Preserve selected value if it's valid for this program
            if (currentSemester == sem && semesters.includes(parseInt(currentSemester))) {
                option.selected = true;
            }
            semesterSelect.appendChild(option);
        });
    }

    // Event listener for program change
    programSelect.addEventListener('change', function() {
        const selectedProgram = this.value;
        updateSemesterDropdown(selectedProgram);
    });

    // Validate on form submit
    document.getElementById('editStudentForm').addEventListener('submit', function(e) {
        const program = programSelect.value;
        const semester = semesterSelect.value;
        
        if (!program) {
            alert('Please select a program');
            e.preventDefault();
            return false;
        }
        
        if (!semester) {
            alert('Please select a semester');
            e.preventDefault();
            return false;
        }
        
        // Validate semester range for selected program
        const validSemesters = semesterOptions[program] || [];
        if (!validSemesters.includes(parseInt(semester))) {
            alert('Invalid semester for the selected program. Please select a valid semester.');
            e.preventDefault();
            return false;
        }
        
        return true;
    });

    // Trigger initial update with current program
    if (programSelect.value) {
        updateSemesterDropdown(programSelect.value);
    } else {
        semesterSelect.disabled = true;
    }
});
</script>

<?php include 'footer.php'; ?>