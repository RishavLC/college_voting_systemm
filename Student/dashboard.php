<?php
session_start();
require_once '../Database/db_connect.php';

// Redirect if not verified
if (!isset($_SESSION['student_id']) || !isset($_SESSION['student_verified'])) {
    header("Location: student_verify.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// Fetch student details
$stmt = $conn->prepare("SELECT student_name, student_batch, student_faculty, student_semester, voting_status FROM student WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
if (!$student) {
    session_destroy();
    header("Location: student_verify.php");
    exit();
}

// Check if already voted
if ($student['voting_status'] == 1) {
    $already_voted = true;
} else {
    $already_voted = false;
}

// Find active election for this student's batch/faculty/semester
$election_query = "SELECT election_id, election_name, election_date FROM election 
                   WHERE election_batch = ? AND election_faculty = ? AND election_semester = ? 
                   AND election_status = 'active' LIMIT 1";
$stmt = $conn->prepare($election_query);
$stmt->bind_param("ssi", $student['student_batch'], $student['student_faculty'], $student['student_semester']);
$stmt->execute();
$election = $stmt->get_result()->fetch_assoc();

if (!$election) {
    $no_election = true;
} else {
    $no_election = false;
    $election_id = $election['election_id'];
    // Fetch candidates for this election (join with student to get names)
    $candidates_query = "SELECT c.candidate_id, s.student_id, s.student_name, s.student_phone, c.candidate_photo 
                         FROM candidate c 
                         JOIN student s ON c.student_id = s.student_id 
                         WHERE c.election_id = ?";
    $stmt = $conn->prepare($candidates_query);
    $stmt->bind_param("i", $election_id);
    $stmt->execute();
    $candidates = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Voting Dashboard · HDCVotes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/custom.css">
    <link rel="icon" href="../assets/img/logo.png">
</head>
<body class="vote-body">

<div class="vote-hero">
    <img src="../assets/img/logo.png" alt="Himalaya Darshan College" class="brand-logo brand-logo-lg">
    <h2><i class="bi bi-check2-circle me-1"></i> Welcome, <?= htmlspecialchars($student['student_name']) ?></h2>
    <p>Cast your vote for your batch's student representative</p>
    <div class="vote-meta-pill">
        <span><i class="bi bi-mortarboard-fill me-1"></i>Batch: <?= htmlspecialchars($student['student_batch']) ?></span>
        <span><i class="bi bi-building me-1"></i>Faculty: <?= htmlspecialchars($student['student_faculty']) ?></span>
        <span><i class="bi bi-journal-bookmark-fill me-1"></i>Semester: <?= htmlspecialchars($student['student_semester']) ?></span>
    </div>
</div>

<div class="vote-content">
    <?php if ($no_election): ?>
        <div class="no-election text-center">
            <h4><i class="bi bi-exclamation-triangle-fill text-warning"></i> No Active Election</h4>
            <p class="mb-3">There is no active election for your batch, faculty, and semester right now.</p>
            <a href="student_verify.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>Go Back</a>
        </div>
    <?php elseif ($already_voted): ?>
        <div class="already-voted text-center">
            <h4><i class="bi bi-check-circle-fill text-success"></i> You have already voted!</h4>
            <p class="mb-2">Thank you for participating.</p>
            <p class="text-muted">Redirecting to the login page in <span id="redirectTimer" class="fw-bold">5</span> seconds…</p>
        </div>
        <script>
            let seconds = 5;
            const timer = setInterval(() => {
                seconds--;
                document.getElementById('redirectTimer').textContent = seconds;
                if (seconds <= 0) {
                    clearInterval(timer);
                    window.location.href = 'student_verify.php';
                }
            }, 1000);
        </script>
    <?php else: ?>
        <!-- Election info & timer -->
        <div class="election-banner">
            <h5 class="mb-1"><?= htmlspecialchars($election['election_name']) ?></h5>
            <p class="text-muted mb-3"><i class="bi bi-calendar-event"></i> <?= date('d M Y', strtotime($election['election_date'])) ?></p>
            <div class="timer-value" id="countdown">30</div>
            <p class="text-muted mb-0">seconds left to vote</p>
        </div>

        <!-- Candidate grid -->
        <div class="row" id="candidateGrid">
            <?php while ($candidate = $candidates->fetch_assoc()): ?>
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="card card-candidate shadow-sm h-100">
                        <div class="card-body text-center">
                            <?php
                            // Dummy avatar: use UI Avatars with student name
                            $avatar_url = "https://ui-avatars.com/api/?name=" . urlencode($candidate['student_name']) . "&size=80&background=random&color=fff&font-size=0.5&rounded=true";
                            ?>
                            <img src="<?= $avatar_url ?>" alt="Candidate" class="candidate-img mb-3">
                            <h6 class="card-title mb-1"><?= htmlspecialchars($candidate['student_name']) ?></h6>
                            <p class="card-text mb-3"><small class="text-muted">ID: <?= $candidate['student_id'] ?></small></p>
                            <button class="btn btn-primary vote-btn w-100" data-candidate-id="<?= $candidate['candidate_id'] ?>" data-student-id="<?= $student_id ?>">
                                <i class="bi bi-check-circle"></i> Vote
                            </button>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Hidden input to store election_id for JS -->
<input type="hidden" id="electionId" value="<?= $election_id ?? '' ?>">
<input type="hidden" id="studentId" value="<?= $student_id ?>">

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
<?php if (!$no_election && !$already_voted): ?>
    // Timer: 30 seconds countdown
    let timeLeft = 30;
    const timerDisplay = document.getElementById('countdown');
    const timerInterval = setInterval(() => {
        timeLeft--;
        timerDisplay.textContent = timeLeft;
        if (timeLeft <= 10) { timerDisplay.style.color = '#e11d48'; }
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            // Time's up -> redirect to verify
            window.location.href = 'student_verify.php';
        }
    }, 1000);

    // Vote button click handler
    const voteButtons = document.querySelectorAll('.vote-btn');
    voteButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            // Prevent multiple clicks
            if (this.disabled) return;
            const candidateId = this.dataset.candidateId;
            const studentId = document.getElementById('studentId').value;
            const electionId = document.getElementById('electionId').value;

            // Disable all buttons
            voteButtons.forEach(b => b.disabled = true);
            // Show loading state
            this.innerHTML = '<i class="bi bi-hourglass-split"></i> Voting...';

            // Send vote via AJAX
            fetch('vote.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `candidate_id=${candidateId}&student_id=${studentId}&election_id=${electionId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mark this button as "Voted" and style it
                    this.innerHTML = '<i class="bi bi-check-circle-fill"></i> Voted';
                    this.classList.remove('btn-primary');
                    this.classList.add('btn-success');
                    this.closest('.card-candidate').style.borderColor = '#16a34a';
                    // Show success message
                    alert(data.message);
                    // Redirect after 3 seconds
                    setTimeout(() => {
                        window.location.href = 'student_verify.php';
                    }, 3000);
                } else {
                    alert(data.message);
                    // Re-enable buttons? but if error like already voted, redirect anyway
                    if (data.already_voted) {
                        setTimeout(() => {
                            window.location.href = 'student_verify.php';
                        }, 2000);
                    } else {
                        // Re-enable if not fatal (optional)
                        voteButtons.forEach(b => b.disabled = false);
                        this.innerHTML = '<i class="bi bi-check-circle"></i> Vote';
                    }
                }
            })
            .catch(error => {
                alert('An error occurred. Please try again.');
                console.error(error);
                voteButtons.forEach(b => b.disabled = false);
                this.innerHTML = '<i class="bi bi-check-circle"></i> Vote';
            });
        });
    });
<?php endif; ?>
</script>
</body>
</html>
