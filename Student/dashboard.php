<?php
// ============================================================
// Suppress PHP errors from being displayed (they go to logs)
// ============================================================
error_reporting(0);
ini_set('display_errors', 0);
ob_start(); // catch any accidental output

session_start();

// ============================================================
// BACKEND: student authentication, election & candidate data
// ============================================================

require_once '../Database/db_connect.php';

// Redirect if not verified
if (!isset($_SESSION['student_id']) || !isset($_SESSION['student_verified'])) {
    header("Location: verify_otp.php");
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

$hasVoted = (bool) $student['voting_status'];

// Find active election for this student's batch/faculty/semester
$election_query = "SELECT election_id, election_name, election_date FROM election 
                   WHERE election_batch = ? AND election_faculty = ? AND election_semester = ? 
                   AND election_status = 'active' LIMIT 1";
$stmt = $conn->prepare($election_query);
$stmt->bind_param("ssi", $student['student_batch'], $student['student_faculty'], $student['student_semester']);
$stmt->execute();
$election = $stmt->get_result()->fetch_assoc();

$no_election = !$election;

if (!$no_election) {
    $election_id = $election['election_id'];
    // Fetch candidates
    $candidates_query = "SELECT c.candidate_id, s.student_id, s.student_name, c.candidate_photo 
                         FROM candidate c 
                         JOIN student s ON c.student_id = s.student_id 
                         WHERE c.election_id = ?";
    $stmt = $conn->prepare($candidates_query);
    $stmt->bind_param("i", $election_id);
    $stmt->execute();
    $candidates_result = $stmt->get_result();
    $candidates = [];
    while ($row = $candidates_result->fetch_assoc()) {
        $candidates[] = [
            'id' => $row['candidate_id'],
            'student_id' => $row['student_id'],
            'name' => $row['student_name'],
            'photo_url' => $row['candidate_photo'] ?? ''
        ];
    }
} else {
    $candidates = [];
}

// If already voted, fetch voted candidate
$votedCandidate = null;
if ($hasVoted && !$no_election) {
    $vote_query = "SELECT s.student_name, c.candidate_photo, v.voted_at 
                   FROM vote v
                   JOIN candidate c ON v.candidate_id = c.candidate_id
                   JOIN student s ON c.student_id = s.student_id
                   WHERE v.voter_id = ? AND v.election_id = ?";
    $stmt = $conn->prepare($vote_query);
    $stmt->bind_param("ii", $student_id, $election_id);
    $stmt->execute();
    $votedCandidate = $stmt->get_result()->fetch_assoc();
}
$votedCandidate = $votedCandidate ?: ['student_name' => '', 'candidate_photo' => '', 'voted_at' => ''];

// ============================================================
// VOTE-CASTING HANDLER (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['candidate_id'])) {
    ob_clean();
    header('Content-Type: application/json');

    try {
        if (!isset($_SESSION['student_id']) || !isset($_SESSION['student_verified'])) {
            echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $student_id = $_SESSION['student_id'];
        $candidate_id = intval($_POST['candidate_id']);

        // 1. Check voting_status
        $check_vote = $conn->prepare("SELECT voting_status FROM student WHERE student_id = ?");
        $check_vote->bind_param("i", $student_id);
        if (!$check_vote->execute()) throw new Exception('DB error: ' . $conn->error);
        $student_row = $check_vote->get_result()->fetch_assoc();
        if ($student_row['voting_status'] == 1) {
            echo json_encode(['ok' => false, 'message' => 'You have already voted.']);
            exit;
        }

        // 2. Find active election
        $election_query = "SELECT election_id FROM election 
                           WHERE election_batch = ? AND election_faculty = ? AND election_semester = ? 
                           AND election_status = 'active' LIMIT 1";
        $stmt = $conn->prepare($election_query);
        $stmt->bind_param("ssi", $student['student_batch'], $student['student_faculty'], $student['student_semester']);
        if (!$stmt->execute()) throw new Exception('DB error: ' . $conn->error);
        $election_row = $stmt->get_result()->fetch_assoc();
        if (!$election_row) {
            echo json_encode(['ok' => false, 'message' => 'No active election found.']);
            exit;
        }
        $election_id = $election_row['election_id'];

        // 3. Delete any existing vote for this student+election (if any)
        $delete = $conn->prepare("DELETE FROM vote WHERE voter_id = ? AND election_id = ?");
        $delete->bind_param("ii", $student_id, $election_id);
        $delete->execute();

        // 4. Verify candidate belongs to this election
        $verify_candidate = $conn->prepare("SELECT candidate_id FROM candidate WHERE candidate_id = ? AND election_id = ?");
        $verify_candidate->bind_param("ii", $candidate_id, $election_id);
        if (!$verify_candidate->execute()) throw new Exception('DB error: ' . $conn->error);
        if ($verify_candidate->get_result()->num_rows == 0) {
            echo json_encode(['ok' => false, 'message' => 'Invalid candidate for this election.']);
            exit;
        }

        // 5. Insert new vote
        $insert_vote = $conn->prepare("INSERT INTO vote (election_id, candidate_id, voter_id) VALUES (?, ?, ?)");
        $insert_vote->bind_param("iii", $election_id, $candidate_id, $student_id);
        if (!$insert_vote->execute()) throw new Exception('DB error: ' . $conn->error);

        // 6. Update student voting_status
        $update = $conn->prepare("UPDATE student SET voting_status = 1 WHERE student_id = ?");
        $update->bind_param("i", $student_id);
        if (!$update->execute()) throw new Exception('DB error: ' . $conn->error);

        session_destroy();
        echo json_encode(['ok' => true]);
        exit;

    } catch (Exception $e) {
        error_log("Vote error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
        echo json_encode(['ok' => false, 'message' => 'Server error. Please try again.']);
        exit;
    }
}

// ============================================================
// Helper for initials
// ============================================================
function initials_of($name) {
    $parts = preg_split('/\s+/', trim($name));
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cast Your Vote — HDC Votes</title>
    <link rel="stylesheet" href="../assets/css/vote.css">
    <style>
        /* same styles – keep unchanged */
        body.kiosk{
            --navy:      #142F63;
            --navy-2:    #1C6FA0;
            --navy-tint: #DCEEFA;
            --gold:      #C97A1E;
            --gold-2:    #E0954A;
            --gold-tint: #FBE5C8;
            --paper:     #F3F8FC;
            --paper-dim: #E3F0F8;
            --line:      #AFD2E9;
            --line-soft: #C9E2F0;
            --ink-soft:  #43506B;
        }
        body.kiosk .civic-bg{
            background:
                radial-gradient(circle at 1px 1px, rgba(20,47,99,.045) 1px, transparent 0) 0 0/22px 22px,
                radial-gradient(1100px 520px at 14% -8%, rgba(201,122,30,.12), transparent 60%),
                radial-gradient(900px 480px at 100% 0%, rgba(28,111,160,.14), transparent 55%),
                linear-gradient(180deg, var(--paper) 0%, var(--paper-dim) 100%);
        }
        body.kiosk .btn-gold{
            --btn-bg: linear-gradient(155deg, var(--gold-2) 0%, var(--gold) 55%, #7A4A0F 100%);
        }
        body.kiosk .candidate-card{
            border-width: 2px;
            border-color: var(--line);
        }
        body.kiosk .candidate-avatar{
            box-shadow: 0 0 0 6px var(--accent), var(--shadow);
        }
        .no-election-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 60vh;
            text-align: center;
            padding: 2rem;
        }
        .no-election-state .btn {
            margin-top: 1.5rem;
        }
    </style>
</head>
<body class="kiosk">
<div class="civic-bg">
    <div class="ribbon"></div>

    <!-- Minimal brand -->
    <div class="kiosk-brand">
        <img src="../assets/img/logo.png" alt="Himalaya Darshan College"
             onerror="this.replaceWith(Object.assign(document.createElement('span'),{textContent:'HDC',style:'font-family:Fraunces,serif;font-weight:700;color:var(--navy);padding:0 4px;'}))">
    </div>

    <!-- ============ NO ELECTION STATE ============ -->
    <?php if ($no_election): ?>
        <div class="no-election-state">
            <h2 style="color: var(--navy);">No Active Election</h2>
            <p>There is no active election for your batch, faculty, and semester right now.</p>
            <a href="verify_otp.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i> Go Back</a>
        </div>
    <?php else: ?>
        <!-- ============ BALLOT (pre-vote) ============ -->
        <div class="vote-wrap" id="ballotState" style="<?= $hasVoted ? 'display:none;' : '' ?>">
            <div class="vote-wrap-head">
                <h2><?= htmlspecialchars($election['election_name']) ?></h2>
                <p>Choose your candidate, then press Vote to cast your ballot.</p>
                <div style="font-size:0.85rem; color:var(--ink-soft); margin-top:0.5rem;">
                    <span>Batch: <?= htmlspecialchars($student['student_batch']) ?></span> &middot;
                    <span>Faculty: <?= htmlspecialchars($student['student_faculty']) ?></span> &middot;
                    <span>Semester: <?= htmlspecialchars($student['student_semester']) ?></span>
                </div>
            </div>

            <div class="candidate-grid stagger" id="candidateGrid">
                <?php foreach ($candidates as $c): ?>
                <?php
                    $name    = $c['name'];
                    $photo   = $c['photo_url'] ?? '';
                    $initial = initials_of($name);
                ?>
                <div class="candidate-card" data-name="<?= htmlspecialchars($name) ?>" data-id="<?= (int) $c['id'] ?>" data-photo="<?= htmlspecialchars($photo) ?>">
                    <div class="candidate-avatar">
                        <?php if ($photo): ?>
                            <img src="../<?= htmlspecialchars($photo) ?>" alt="<?= htmlspecialchars($name) ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="avatar-fallback"><?= htmlspecialchars($initial) ?></div>
                        <?php else: ?>
                            <div class="avatar-fallback" style="display:flex;"><?= htmlspecialchars($initial) ?></div>
                        <?php endif; ?>
                    </div>
                    <h3><?= htmlspecialchars($name) ?></h3>
                    <button class="btn btn-gold btn-block btn-sm candidate-vote-btn" onclick="requestVote(this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="10"/></svg>
                        Vote
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============ ALREADY VOTED STATE – ALWAYS RENDERED ============ -->
    <div class="voted-state" id="votedState" style="<?= $hasVoted ? 'display:flex;' : 'display:none;' ?>">
        <div class="voted-card reveal-pop">
            <div class="voted-photo-wrap">
                <div class="voted-photo" id="votedPhoto">
                    <?php $votedPhoto = $votedCandidate['candidate_photo'] ?? ''; ?>
                    <?php $votedInitial = initials_of($votedCandidate['student_name'] ?? ''); ?>
                    <img src="<?= $votedPhoto ? '../' . htmlspecialchars($votedPhoto) : '' ?>" alt="<?= htmlspecialchars($votedCandidate['student_name']) ?>" id="votedPhotoImg" style="<?= $votedPhoto ? '' : 'display:none;' ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="avatar-fallback" id="votedPhotoFallback" style="<?= $votedPhoto ? 'display:none;' : 'display:flex;' ?>"><?= htmlspecialchars($votedInitial) ?></div>
                </div>
                <div class="voted-check-badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                </div>
            </div>
            <div class="badge badge-gold" style="margin:14px 0 10px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" width="12" height="12"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Vote recorded
            </div>
            <h2>Your vote is recorded</h2>
            <p>Thanks for participating — your ballot for <strong id="votedForName"><?= htmlspecialchars($votedCandidate['student_name']) ?></strong> has been securely cast and cannot be changed.</p>
            <div class="voted-divider"></div>
            <div class="voted-meta-row">
                <div class="voted-meta-item">
                    <span class="lbl">Election</span>
                    <span class="val"><?= htmlspecialchars($election['election_name'] ?? '—') ?></span>
                </div>
                <div class="voted-meta-item">
                    <span class="lbl">Voter</span>
                    <span class="val"><?= htmlspecialchars($student['student_name'] . ' · ' . $student['student_faculty'] . ' · Sem ' . $student['student_semester']) ?></span>
                </div>
                <div class="voted-meta-item">
                    <span class="lbl">Cast at</span>
                    <span class="val mono" id="votedTimestamp"><?= htmlspecialchars($votedCandidate['voted_at'] ?? '—') ?></span>
                </div>
            </div>
            <p class="hint" style="margin-top:20px;">Please step away from the booth now. This screen will reset for the next voter in <span class="mono" id="kioskRedirectCount">10</span>s.</p>
        </div>
    </div>

</div>

<!-- ============ CONFIRM MODAL ============ -->
<div class="modal-backdrop" id="confirmModal">
    <div class="modal">
        <div class="modal-head">
            <h3>Confirm your vote</h3>
            <button class="modal-close" onclick="closeModal('confirmModal')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <p>You're about to vote for:</p>
            <div class="card" style="padding:16px;display:flex;align-items:center;gap:14px;box-shadow:none;">
                <div class="candidate-avatar" style="margin:0;width:52px;height:52px;" id="modalAvatar">
                    <img src="" alt="" id="modalAvatarImg" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="avatar-fallback" id="modalAvatarFallback" style="font-size:16px;"></div>
                </div>
                <div>
                    <div class="cell-name" id="modalName" style="font-size:15.5px;"></div>
                    <div class="cell-sub">This action is final and cannot be undone.</div>
                </div>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-outline" onclick="closeModal('confirmModal')">Cancel</button>
            <form id="castVoteForm" action="" method="POST" style="margin:0;">
                <input type="hidden" name="candidate_id" id="candidateIdField">
                <button type="submit" class="btn btn-gold" id="finalConfirmBtn">
                    <span class="btn-label">Confirm &amp; Vote</span>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    // ============================================================
    // Shared helpers
    // ============================================================
    function openModal(id) {
        document.getElementById(id).classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
        document.body.style.overflow = '';
    }

    function showToast(message, duration) {
        duration = duration || 3200;
        let stack = document.querySelector('.toast-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'toast-stack';
            document.body.appendChild(stack);
        }
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.textContent = message;
        stack.appendChild(toast);
        setTimeout(function () {
            toast.style.transition = 'opacity .25s ease, transform .25s ease';
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-6px)';
            setTimeout(function () { toast.remove(); }, 250);
        }, duration);
    }

    function fireConfetti(container) {
        if (!container) return;
        const colors = ['#B98A2E', '#D9AE55', '#14213D', '#2F7A4F', '#7C8DC7'];
        const rect = container.getBoundingClientRect();
        const layer = document.createElement('div');
        layer.style.cssText = 'position:fixed;left:0;top:0;width:100%;height:100%;pointer-events:none;z-index:9999;';
        document.body.appendChild(layer);

        const originX = rect.left + rect.width / 2;
        const originY = rect.top + Math.min(80, rect.height * 0.2);

        for (let i = 0; i < 60; i++) {
            const piece = document.createElement('span');
            const size = 6 + Math.random() * 6;
            const color = colors[Math.floor(Math.random() * colors.length)];
            const dx = (Math.random() - 0.5) * 500;
            const dy = 300 + Math.random() * 300;
            const rot = Math.random() * 720 - 360;
            const delay = Math.random() * 150;
            const dur = 900 + Math.random() * 700;

            piece.style.cssText =
                'position:absolute;left:' + originX + 'px;top:' + originY + 'px;' +
                'width:' + size + 'px;height:' + size * 0.6 + 'px;background:' + color + ';' +
                'border-radius:2px;opacity:0.95;';
            layer.appendChild(piece);

            piece.animate(
                [
                    { transform: 'translate(0,0) rotate(0deg)', opacity: 1 },
                    { transform: 'translate(' + dx + 'px,' + dy + 'px) rotate(' + rot + 'deg)', opacity: 0 }
                ],
                { duration: dur, delay: delay, easing: 'cubic-bezier(.2,.7,.3,1)', fill: 'forwards' }
            );
        }
        setTimeout(function () { layer.remove(); }, 2200);
    }

    document.addEventListener('click', function(e) {
        if (e.target.classList && e.target.classList.contains('modal-backdrop')) {
            e.target.classList.remove('open');
            document.body.style.overflow = '';
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-backdrop.open').forEach(function(el) {
                el.classList.remove('open');
            });
            document.body.style.overflow = '';
        }
    });

    // ============================================================
    // Ballot sizing
    // ============================================================
    (function () {
        const grid = document.getElementById('candidateGrid');
        if (!grid) return;
        const count = grid.children.length;
        if (!count) return;

        let cols;
        if (count <= 3) cols = count;
        else if (count <= 4) cols = 2;
        else if (count <= 6) cols = 3;
        else if (count <= 9) cols = 3;
        else if (count <= 12) cols = 4;
        else cols = Math.min(6, Math.ceil(Math.sqrt(count * 1.4)));

        grid.style.gridTemplateColumns = 'repeat(' + cols + ', minmax(0,1fr))';
        const rows = Math.ceil(count / cols);

        const availableHeight = grid.clientHeight || window.innerHeight * 0.5;
        const gapPx = parseFloat(getComputedStyle(grid).rowGap) || 16;
        const perRow = Math.max(60, (availableHeight - gapPx * (rows - 1)) / rows);

        function applySizes(scale) {
            const avatarSize = Math.max(28, Math.min(200, Math.round(perRow * 0.48 * scale)));
            const padSize = Math.max(6, Math.min(32, Math.round(perRow * 0.055 * scale)));
            const nameSize = Math.max(10, Math.min(19, Math.round(perRow * 0.085 * scale)));
            grid.style.setProperty('--avatar-max', avatarSize + 'px');
            grid.style.setProperty('--avatar-vh', avatarSize + 'px');
            grid.style.setProperty('--pad-max', padSize + 'px');
            grid.style.setProperty('--pad-vh', padSize + 'px');
            grid.style.setProperty('--name-max', nameSize + 'px');
            grid.style.setProperty('--name-vh', nameSize + 'px');
        }

        let scale = 1;
        applySizes(scale);
        for (let pass = 0; pass < 5; pass++) {
            const overflow = grid.scrollHeight - grid.clientHeight;
            if (overflow <= 1) break;
            scale *= Math.max(0.6, (grid.clientHeight / grid.scrollHeight) * 0.96);
            applySizes(scale);
        }
    })();

    // ============================================================
    // Vote flow
    // ============================================================
    let selected = null;

    function requestVote(btn) {
        const card = btn.closest('.candidate-card');
        selected = card;

        const initials = card.dataset.name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
        document.getElementById('modalName').textContent = card.dataset.name;
        const accent = getComputedStyle(card).getPropertyValue('--accent').trim() || '#DD8B33';
        document.getElementById('modalAvatar').style.setProperty('--accent', accent);
        const modalImg = document.getElementById('modalAvatarImg');
        modalImg.style.display = card.dataset.photo ? '' : 'none';
        modalImg.src = card.dataset.photo ? '../' + card.dataset.photo : '';
        modalImg.alt = card.dataset.name;
        document.getElementById('modalAvatarFallback').textContent = initials;
        document.getElementById('modalAvatarFallback').style.display = card.dataset.photo ? 'none' : 'flex';
        document.getElementById('candidateIdField').value = card.dataset.id;
        openModal('confirmModal');
    }

    document.getElementById('castVoteForm').addEventListener('submit', function (e) {
        e.preventDefault();
        if (!selected) return;
        const btn = document.getElementById('finalConfirmBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span><span>Casting…</span>';

        const formData = new FormData(this);

        fetch(this.action, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(res => {
                if (!res.ok) throw new Error('Server error: ' + res.status);
                return res.json();
            })
            .then(data => {
                if (!data || data.ok !== true) {
                    throw new Error(data.message || 'Could not record your vote.');
                }

                closeModal('confirmModal');
                document.getElementById('ballotState').style.display = 'none';

                // Now update the voted state (elements always exist)
                const votedState = document.getElementById('votedState');
                if (votedState) {
                    votedState.style.display = 'flex';
                    document.getElementById('votedForName').textContent = selected.dataset.name;

                    const initials = selected.dataset.name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
                    const votedImg = document.getElementById('votedPhotoImg');
                    if (votedImg) {
                        votedImg.style.display = selected.dataset.photo ? '' : 'none';
                        votedImg.src = selected.dataset.photo ? '../' + selected.dataset.photo : '';
                        votedImg.alt = selected.dataset.name;
                    }
                    const fallback = document.getElementById('votedPhotoFallback');
                    if (fallback) {
                        fallback.textContent = initials;
                        fallback.style.display = selected.dataset.photo ? 'none' : 'flex';
                    }
                    const ts = document.getElementById('votedTimestamp');
                    if (ts) {
                        ts.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    }
                    fireConfetti(votedState);
                }

                showToast('Vote cast successfully');

                let n = 10;
                const countEl = document.getElementById('kioskRedirectCount');
                const iv = setInterval(() => {
                    n--;
                    if (countEl) countEl.textContent = n;
                    if (n <= 0) {
                        clearInterval(iv);
                        window.location.href = 'verify_otp.php';
                    }
                }, 1000);
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<span class="btn-label">Confirm &amp; Vote</span>';
                showToast(err.message || 'Something went wrong. Please try again.');
            });
    });
</script>
</body>
</html>