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

    // Validation
    if (empty($name) || empty($date) || empty($batch) || empty($faculty) || $semester < 1) {
        $error = "All required fields must be filled correctly.";
    } elseif (!in_array($faculty, $programs)) {
        $error = "Please select a valid program.";
    } elseif (isset($semesterOptions[$faculty]) && !in_array($semester, $semesterOptions[$faculty])) {
        $error = "Invalid semester for the selected program.";
    } else {
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
        <form method="POST" id="editEventForm">
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
                    <label>Batch *</label>
                    <input type="text" name="election_batch" class="form-control" value="<?= htmlspecialchars($event['election_batch']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label>Program *</label>
                    <select name="election_faculty" id="programSelect" class="form-control" required>
                        <option value="">Select Program...</option>
                        <?php foreach ($programs as $program): ?>
                            <option value="<?= $program ?>" 
                                <?= ($event['election_faculty'] == $program) ? 'selected' : '' ?>>
                                <?= $program ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Semester/Year *</label>
                    <select name="election_semester" id="semesterSelect" class="form-control" required>
                        <option value="">Select Semester/Year...</option>
                        <?php 
                        $currentSemester = $event['election_semester'];
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
            </div>
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Update Election</button>
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

    // Store current semester value
    const currentSemester = '<?= $event['election_semester'] ?>';

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
    document.getElementById('editEventForm').addEventListener('submit', function(e) {
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