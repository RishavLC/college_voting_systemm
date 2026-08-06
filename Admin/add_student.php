<?php
session_start();
if (!isset($_SESSION['admin_id'])) header("Location: login.php");
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
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['student_id'];
    $name = $_POST['student_name'];
    $batch = $_POST['student_batch'];
    $faculty = $_POST['student_faculty'];
    $semester = $_POST['student_semester'];
    $phone = $_POST['student_phone'];
    $email = trim($_POST['student_email']);
    $email = $email === '' ? null : $email;

    // Validate semester based on program
    if (isset($semesterOptions[$faculty]) && !in_array((int)$semester, $semesterOptions[$faculty])) {
        $error = "Invalid semester for the selected program.";
    } else {
        $stmt = $conn->prepare("INSERT INTO student (student_id, student_name, student_batch, student_faculty, student_semester, student_phone, student_email) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssiss", $id, $name, $batch, $faculty, $semester, $phone, $email);
        if ($stmt->execute()) {
            header("Location: home.php?section=students");
            exit();
        } else {
            $error = "Error: " . $conn->error;
        }
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
        <form method="POST" id="studentForm">
            <div class="row g-3">
                <div class="col-md-6">
                    <label>Student ID</label>
                    <input type="number" name="student_id" class="form-control" 
                           value="<?= isset($_POST['student_id']) ? htmlspecialchars($_POST['student_id']) : '' ?>" required>
                </div>
                <div class="col-md-6">
                    <label>Name</label>
                    <input type="text" name="student_name" class="form-control" 
                           value="<?= isset($_POST['student_name']) ? htmlspecialchars($_POST['student_name']) : '' ?>" required>
                </div>
                <div class="col-md-6">
                    <label>Batch</label>
                    <input type="text" name="student_batch" class="form-control" 
                           value="<?= isset($_POST['student_batch']) ? htmlspecialchars($_POST['student_batch']) : '' ?>" 
                           placeholder="e.g., 2079" required>
                </div>
                <div class="col-md-6">
                    <label>Program</label>
                    <select name="student_faculty" id="programSelect" class="form-control" required>
                        <option value="">Select Program...</option>
                        <?php foreach ($programs as $program): ?>
                            <option value="<?= $program ?>" 
                                <?= (isset($_POST['student_faculty']) && $_POST['student_faculty'] == $program) ? 'selected' : '' ?>>
                                <?= $program ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Semester/Year</label>
                    <select name="student_semester" id="semesterSelect" class="form-control" required>
                        <option value="">Select Semester/Year...</option>
                        <?php 
                        $defaultSemesters = [1,2,3,4,5,6,7,8];
                        $selectedSemester = isset($_POST['student_semester']) ? $_POST['student_semester'] : '';
                        foreach ($defaultSemesters as $sem): 
                        ?>
                            <option value="<?= $sem ?>" 
                                <?= ($selectedSemester == $sem) ? 'selected' : '' ?>>
                                <?= $sem ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Phone</label>
                    <input type="text" name="student_phone" class="form-control" 
                           value="<?= isset($_POST['student_phone']) ? htmlspecialchars($_POST['student_phone']) : '' ?>">
                </div>
                <div class="col-12">
                    <label>Email <span class="text-muted" style="text-transform:none;font-weight:500;">(optional)</span></label>
                    <input type="email" name="student_email" class="form-control" 
                           value="<?= isset($_POST['student_email']) ? htmlspecialchars($_POST['student_email']) : '' ?>">
                </div>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
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

    // Update semester dropdown based on selected program
    function updateSemesterDropdown(program) {
        // Clear current options
        semesterSelect.innerHTML = '<option value="">Select Semester...</option>';
        
        // Get semester list for selected program
        const semesters = semesterOptions[program] || [];
        
        if (semesters.length === 0) {
            // If no program selected
            semesterSelect.disabled = true;
            return;
        }

        semesterSelect.disabled = false;
        
        // Get previously selected semester (if any)
        const currentValue = '<?= isset($_POST['student_semester']) ? $_POST['student_semester'] : '' ?>';
        
        // Add options
        semesters.forEach(sem => {
            const option = document.createElement('option');
            option.value = sem;
            option.textContent = sem;
            if (currentValue == sem) {
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
    document.getElementById('studentForm').addEventListener('submit', function(e) {
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

    // Trigger initial update if program is pre-selected
    if (programSelect.value) {
        updateSemesterDropdown(programSelect.value);
    } else {
        // If no program selected, disable semester select
        semesterSelect.disabled = true;
    }
});
</script>

<?php include 'footer.php'; ?>