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
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['election_name'] ?? '');
    $alias = trim($_POST['alias'] ?? '');
    $date = $_POST['election_date'] ?? '';
    $batch = trim($_POST['election_batch'] ?? '');
    $faculty = trim($_POST['election_faculty'] ?? '');
    $semester = intval($_POST['election_semester'] ?? 0);
    $status = $_POST['election_status'] ?? 'upcoming';

    // Validation
    if (empty($name)) {
        $error = "Event name is required.";
    } elseif (empty($date)) {
        $error = "Date is required.";
    } elseif (empty($batch)) {
        $error = "Batch is required.";
    } elseif (!in_array($faculty, $programs)) {
        $error = "Please select a valid program.";
    } elseif ($semester < 1) {
        $error = "Please select a valid semester.";
    } elseif (isset($semesterOptions[$faculty]) && !in_array($semester, $semesterOptions[$faculty])) {
        $error = "Invalid semester for the selected program.";
    } else {
        $stmt = $conn->prepare("INSERT INTO election (election_name, alias, election_date, election_batch, election_faculty, election_semester, election_status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssis", $name, $alias, $date, $batch, $faculty, $semester, $status);
        if ($stmt->execute()) {
            $success = "Election created successfully!";
            // Clear form data
            $_POST = array();
        } else {
            $error = "Database error: " . $conn->error;
        }
    }
}

$pageTitle = 'Add Election';
$pageSubtitle = 'Create a new voting event';
include 'header.php';
?>

<div class="card shadow" style="max-width:720px;">
    <div class="card-header bg-success text-white"><i class="bi bi-calendar-plus-fill me-2"></i>Add Election</div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <form method="POST" id="electionForm">
            <div class="row g-3">
                <div class="col-md-8">
                    <label>Event Name</label>
                    <input type="text" name="election_name" class="form-control" 
                           value="<?= isset($_POST['election_name']) ? htmlspecialchars($_POST['election_name']) : '' ?>" required>
                </div>
                <div class="col-md-4">
                    <label>Alias</label>
                    <input type="text" name="alias" class="form-control" 
                           value="<?= isset($_POST['alias']) ? htmlspecialchars($_POST['alias']) : '' ?>">
                </div>
                <div class="col-md-6">
                    <label>Date</label>
                    <input type="date" name="election_date" class="form-control" 
                           value="<?= isset($_POST['election_date']) ? htmlspecialchars($_POST['election_date']) : '' ?>" required>
                </div>
                <div class="col-md-6">
                    <label>Batch</label>
                    <input type="text" name="election_batch" class="form-control" 
                           value="<?= isset($_POST['election_batch']) ? htmlspecialchars($_POST['election_batch']) : '' ?>" 
                           placeholder="e.g., 2079" required>
                </div>
                <div class="col-md-6">
                    <label>Program</label>
                    <select name="election_faculty" id="programSelect" class="form-select" required>
                        <option value="">Select Program...</option>
                        <?php foreach ($programs as $program): ?>
                            <option value="<?= $program ?>" 
                                <?= (isset($_POST['election_faculty']) && $_POST['election_faculty'] == $program) ? 'selected' : '' ?>>
                                <?= $program ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Semester/Year</label>
                    <select name="election_semester" id="semesterSelect" class="form-select" required>
                        <option value="">Select Semester/Year...</option>
                        <?php 
                        $defaultSemesters = [1,2,3,4,5,6,7,8];
                        $selectedSemester = isset($_POST['election_semester']) ? $_POST['election_semester'] : '';
                        foreach ($defaultSemesters as $sem): 
                        ?>
                            <option value="<?= $sem ?>" 
                                <?= ($selectedSemester == $sem) ? 'selected' : '' ?>>
                                <?= $sem ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label>Status</label>
                    <select name="election_status" class="form-select">
                        <option value="upcoming" <?= (isset($_POST['election_status']) && $_POST['election_status'] == 'upcoming') ? 'selected' : '' ?>>Upcoming</option>
                        <option value="active" <?= (isset($_POST['election_status']) && $_POST['election_status'] == 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="closed" <?= (isset($_POST['election_status']) && $_POST['election_status'] == 'closed') ? 'selected' : '' ?>>Closed</option>
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
        const currentValue = '<?= isset($_POST['election_semester']) ? $_POST['election_semester'] : '' ?>';
        
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

    // Also validate on form submit
    document.getElementById('electionForm').addEventListener('submit', function(e) {
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