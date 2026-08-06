<?php
session_start();
if (!isset($_SESSION['admin_id'])) header("Location: Admin/login.php");
require_once 'Database/db_connect.php';

// ------------------------------------------------------------------
// Dynamic data source: results are now pulled live from the database
// (election / candidate / student / vote tables) instead of a
// hardcoded array. Nothing below this block — markup, CSS classes,
// JS, animations — was changed. This block still hands the template
// the exact same $election / $candidates / $total_votes / $winner /
// $faculties / $batches / $semesters shapes it always expected.
// ------------------------------------------------------------------

// Filter dropdown options, built from whatever elections actually exist
$faculties = [];
$fRes = $conn->query("SELECT DISTINCT election_faculty FROM election ORDER BY election_faculty");
while ($fRes && $row = $fRes->fetch_assoc()) { $faculties[] = $row['election_faculty']; }

$batches = [];
$bRes = $conn->query("SELECT DISTINCT election_batch FROM election ORDER BY election_batch DESC");
while ($bRes && $row = $bRes->fetch_assoc()) { $batches[] = $row['election_batch']; }

$semesters = range(1, 8); // fixed valid domain (matches the `election_semester` CHECK constraint)

// Get filter inputs
$faculty = isset($_GET['faculty']) ? trim($_GET['faculty']) : '';
$batch = isset($_GET['batch']) ? trim($_GET['batch']) : '';
$semester = isset($_GET['semester']) ? intval($_GET['semester']) : 0;

$election = null;
$election_pending = false; // true when the election exists but hasn't been closed by the admin yet
$candidates = [];
$total_votes = 0;
$winner = null;

if ($faculty && $batch && $semester) {
    // Find the matching election for this faculty/batch/semester combo.
    // If the same batch/semester election is ever run again in a later
    // year, the most recent one wins.
    $stmt = $conn->prepare("SELECT election_id, election_name, election_date, election_status
                             FROM election
                             WHERE election_faculty = ? AND election_batch = ? AND election_semester = ?
                             ORDER BY election_date DESC LIMIT 1");
    $stmt->bind_param("ssi", $faculty, $batch, $semester);
    $stmt->execute();
    $electionRow = $stmt->get_result()->fetch_assoc();

    if ($electionRow) {
        // Results are only published once the admin has closed the
        // election (Admin > Elections > Status = Closed).
        if ($electionRow['election_status'] === 'closed') {
            $election_id = $electionRow['election_id'];
            $election = [
                'name' => $electionRow['election_name'],
                'date' => $electionRow['election_date'],
            ];

            // Candidates for this specific election
            $candStmt = $conn->prepare("SELECT c.candidate_id, s.student_name AS name, c.candidate_photo AS photo
                                         FROM candidate c
                                         JOIN student s ON s.student_id = c.student_id
                                         WHERE c.election_id = ?
                                         ORDER BY c.candidate_id");
            $candStmt->bind_param("i", $election_id);
            $candStmt->execute();
            $candRes = $candStmt->get_result();

            // Vote counts per candidate, tallied from the vote table
            $voteCounts = [];
            $voteStmt = $conn->prepare("SELECT candidate_id, COUNT(*) AS votes FROM vote WHERE election_id = ? GROUP BY candidate_id");
            $voteStmt->bind_param("i", $election_id);
            $voteStmt->execute();
            $voteRes = $voteStmt->get_result();
            while ($vrow = $voteRes->fetch_assoc()) {
                $voteCounts[$vrow['candidate_id']] = (int) $vrow['votes'];
            }

            while ($crow = $candRes->fetch_assoc()) {
                $candidates[] = [
                    'name' => $crow['name'],
                    'photo' => $crow['photo'] ?? '',
                    'votes' => $voteCounts[$crow['candidate_id']] ?? 0,
                ];
            }

            $total_votes = array_sum(array_column($candidates, 'votes'));
            if ($total_votes > 0) {
                $max_votes = max(array_column($candidates, 'votes'));
                foreach ($candidates as $c) {
                    if ($c['votes'] == $max_votes) { $winner = $c; break; }
                }
            }
        } else {
            $election_pending = true;
        }
    }
}

// Deterministic color per candidate, drawn from the brand palette
$palette = ['#1e4d92', '#f4820a', '#3f74b8', '#c96600', '#16a34a', '#e11d48', '#6f42c1'];
function cand_color($i, $palette) { return $palette[$i % count($palette)]; }
function cand_initials($name) {
    $parts = preg_split('/\s+/', trim($name));
    $initials = strtoupper(substr($parts[0], 0, 1));
    if (count($parts) > 1) $initials .= strtoupper(substr(end($parts), 0, 1));
    return $initials;
}
?>
<!DOCTYPE html>
<html class="results-no-scroll">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Election Results · HDCVotes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.2/dist/confetti.browser.js"></script>
    <link rel="stylesheet" href="assets/css/result.css">
    <link rel="icon" href="assets/img/logo.png">
</head>
<body class="results-no-scroll">
<div class="results-page" id="resultsPage">

    <!-- ============ Header: logo + election details + filter, single fixed row ============ -->
    <header class="results-header-bar">
        <div class="rhb-logo">
            <img src="assets/img/logo.png" alt="Himalaya Darshan College" class="brand-logo brand-logo-lg">
        </div>

        <div class="rhb-info">
            <span class="rhb-eyebrow">Himalaya Darshan College &middot; HDCVotes</span>
            <?php if ($election): ?>
                <h3><?= htmlspecialchars($election['name']) ?></h3>
                <div class="rhb-badges">
                    <span class="badge-chip"><i class="bi bi-calendar-event"></i> <?= htmlspecialchars($election['date']) ?> B.S.</span>
                    <span class="badge-chip chip-success"><i class="bi bi-people"></i> Total Votes: <span class="count-up" data-target="<?= $total_votes ?>">0</span></span>
                    <span class="badge-chip"><?= htmlspecialchars($faculty) ?></span>
                    <span class="badge-chip">Batch <?= htmlspecialchars($batch) ?></span>
                    <span class="badge-chip">Sem <?= $semester ?></span>
                </div>
            <?php elseif ($election_pending): ?>
                <h3 class="placeholder-text"><i class="bi bi-hourglass-split text-warning"></i> Results not yet available</h3>
                <p class="rhb-sub">Voting for this election hasn't closed yet. Please check back once the admin closes it.</p>
            <?php elseif ($faculty && $batch && $semester): ?>
                <h3 class="placeholder-text"><i class="bi bi-exclamation-triangle text-warning"></i> No election found</h3>
                <p class="rhb-sub">No results match this faculty, batch and semester.</p>
            <?php else: ?>
                <h3 class="placeholder-text"><i class="bi bi-bar-chart-fill text-primary"></i> Election Results</h3>
                <p class="rhb-sub">Choose your batch, faculty and semester to view results.</p>
            <?php endif; ?>
        </div>

        <form method="GET" class="rhb-filter-form">
            <div class="rhb-field">
                <label for="batch">Batch</label>
                <select id="batch" name="batch" class="form-select form-select-sm" required>
                    <option value="">Select</option>
                    <?php foreach ($batches as $b): ?>
                        <option value="<?= $b ?>" <?= $batch === $b ? 'selected' : '' ?>><?= $b ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="rhb-field">
                <label for="faculty">Faculty</label>
                <select id="faculty" name="faculty" class="form-select form-select-sm" required>
                    <option value="">Select</option>
                    <?php foreach ($faculties as $f): ?>
                        <option value="<?= htmlspecialchars($f) ?>" <?= $faculty === $f ? 'selected' : '' ?>><?= htmlspecialchars($f) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="rhb-field">
                <label for="semester">Semester</label>
                <select id="semester" name="semester" class="form-select form-select-sm" required>
                    <option value="">Select</option>
                    <?php foreach ($semesters as $s): ?>
                        <option value="<?= $s ?>" <?= $semester === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="rhb-field rhb-field-btn">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> View</button>
            </div>
        </form>
    </header>

    <!-- ============ Body: fills remaining viewport, never scrolls the page itself ============ -->
    <main class="results-body">

        <?php if ($election && count($candidates) > 0): ?>
            <div class="results-stage">

                <!-- Chart: starts off-screen right, slides left over 3s, freezes in this left column -->
                <div class="col-left">
                    <div class="stage-chart" id="stageChart">
                        <div class="result-card full-height chart-card">
                            <h4><i class="bi bi-graph-up-arrow text-success"></i> Vote Distribution</h4>
                            <div class="chart-container">
                                <canvas id="voteChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Candidate breakdown: fades in on the right once the chart has settled -->
                <div class="col-right">
                    <div class="stage-list" id="stageList">
                        <div class="result-card full-height">
                            <h4><i class="bi bi-table text-info"></i> Candidate-wise Breakdown</h4>
                            <div class="table-responsive stage-list-scroll">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Candidate</th>
                                            <th class="text-center">Votes</th>
                                            <th>Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody id="candidateTableBody">
                                        <?php
                                        $max_votes = max(array_column($candidates, 'votes')) ?: 1;
                                        $rank = 1;
                                        foreach ($candidates as $i => $cand):
                                            $percent = $total_votes > 0 ? round(($cand['votes'] / $total_votes) * 100, 1) : 0;
                                            $bar_width = $total_votes > 0 ? ($cand['votes'] / $total_votes) * 100 : 0;
                                            $is_winner = ($cand['votes'] == $max_votes && $max_votes > 0);
                                            $color = cand_color($i, $palette);
                                        ?>
                                        <!--
                                            IMPORTANT: we deliberately do NOT render `is-winner-row` here even
                                            though $is_winner is known server-side. If we did, the winner
                                            highlight/pulse and the trophy badge below would already be sitting
                                            in the DOM before the reveal animation runs, spoiling the result
                                            the instant the row fades in. Instead we stash the fact in
                                            data-winner and let JS add `is-winner-row` (and reveal the badge)
                                            only once that candidate's bar has actually finished animating to
                                            its real value — see runParallelReveal()/animateOneCandidate() below.
                                        -->
                                        <tr <?= $is_winner ? 'data-winner="true"' : '' ?>>
                                            <td><?= $rank++ ?></td>
                                            <td class="candidate-label">
                                                <div class="d-flex align-items-center gap-2">
                                                    <?php if (!empty($cand['photo'])): ?>
                                                        <div class="cand-avatar" style="background:<?= $color ?>; padding:0; overflow:hidden;">
                                                            <img src="<?= htmlspecialchars($cand['photo']) ?>" alt="<?= htmlspecialchars($cand['name']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" onerror="this.parentElement.textContent='<?= cand_initials($cand['name']) ?>'; this.parentElement.style.padding='';">
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="cand-avatar" style="background:<?= $color ?>;"><?= cand_initials($cand['name']) ?></div>
                                                    <?php endif; ?>
                                                    <span>
                                                        <?= htmlspecialchars($cand['name']) ?>
                                                        <?php if ($is_winner): ?>
                                                            <!--
                                                                Rendered but kept fully inert (invisible, non-
                                                                interactive, hidden from the accessibility tree)
                                                                via .badge-pending + aria-hidden until JS reveals
                                                                it at the true reveal moment.
                                                            -->
                                                            <span class="winner-badge ms-1 badge-pending" aria-hidden="true"><i class="bi bi-trophy"></i> Winner</span>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="vote-count-badge"><span class="count-up" data-target="<?= $cand['votes'] ?>">0</span></span>
                                            </td>
                                            <td style="width: 40%;">
                                                <div class="candidate-bar-container">
                                                    <div class="bar-wrapper">
                                                        <div class="bar-fill" style="background: linear-gradient(90deg, <?= $color ?>, <?= $color ?>dd);" data-width="<?= $bar_width ?>"></div>
                                                        <span class="bar-text"><?= $percent ?>%</span>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        <?php elseif ($election_pending): ?>
            <div class="result-card no-data centered-state">
                <i class="bi bi-hourglass-split display-3 text-warning"></i>
                <h4>Voting is still open</h4>
                <p class="text-muted">Results will be published here once the admin closes this election.</p>
            </div>
        <?php elseif ($faculty && $batch && $semester): ?>
            <div class="result-card no-data centered-state">
                <i class="bi bi-info-circle display-3"></i>
                <h4>No results available</h4>
                <p class="text-muted">Either the election wasn't found or no candidates have been assigned yet.</p>
            </div>
        <?php else: ?>
            <div class="result-card centered-state">
                <i class="bi bi-funnel display-3 text-secondary"></i>
                <h4>Please select batch, faculty and semester</h4>
                <p class="text-muted">Use the filters above to view election results.</p>
            </div>
        <?php endif; ?>

    </main>
</div>

<!-- ============ Winner popup modal ============ -->
<?php if ($winner): ?>
<div class="modal fade winner-modal" id="winnerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header themed">
                <h5 class="modal-title"><i class="bi bi-trophy-fill me-1"></i> Election Winner</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <img src="assets/img/logo.png" alt="Himalaya Darshan College" class="winner-modal-logo">
                <div class="crown"><i class="bi bi-award-fill"></i></div>
                <?php if (!empty($winner['photo'])): ?>
                    <div class="winner-photo" style="padding:0;overflow:hidden;">
                        <img src="<?= htmlspecialchars($winner['photo']) ?>" alt="<?= htmlspecialchars($winner['name']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" onerror="this.parentElement.textContent='<?= cand_initials($winner['name']) ?>'; this.parentElement.style.padding='';">
                    </div>
                <?php else: ?>
                    <div class="winner-photo"><?= cand_initials($winner['name']) ?></div>
                <?php endif; ?>
                <h3><?= htmlspecialchars($winner['name']) ?></h3>
                <p class="winner-sub"><?= htmlspecialchars($election['name']) ?></p>
                <span class="vote-count-badge"><?= $winner['votes'] ?> votes</span>
                <span class="badge bg-secondary ms-1"><?= $total_votes > 0 ? round(($winner['votes'] / $total_votes) * 100, 1) : 0 ?>% of total</span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($winner): ?>
    // ---- Fully hide the results page (not just fade it) behind the winner
    // modal the instant it opens, and bring it back the instant it closes —
    // tied directly to Bootstrap's own show/hide events so there's no extra
    // lag layered on top of the modal's own open/close timing. ----
    const resultsPageEl = document.getElementById('resultsPage');
    const winnerModalEl = document.getElementById('winnerModal');
    winnerModalEl.addEventListener('show.bs.modal', () => {
        resultsPageEl.classList.add('is-behind-modal');
    });
    winnerModalEl.addEventListener('hidden.bs.modal', () => {
        resultsPageEl.classList.remove('is-behind-modal');
    });
    <?php endif; ?>

    <?php if ($election && count($candidates) > 0): ?>
        // ---- Chart.js bar chart ----
        // Built with zeroed-out data on purpose: if we animated to the real
        // values immediately, the bars would finish growing while the card
        // is still off-screen sliding in, and the animation would look like
        // it never happened. We grow the bars for real once the card is
        // actually visible (see revealResults() below).
        const ctx = document.getElementById('voteChart').getContext('2d');
        const labels = <?= json_encode(array_column($candidates, 'name')) ?>;
        const realData = <?= json_encode(array_column($candidates, 'votes')) ?>;
        const colors = <?= json_encode(array_map(fn($i) => cand_color($i, $palette), array_keys($candidates))) ?>;
        const zeroData = realData.map(() => 0);

        // Bar-grow animation, KBC-style — but with the standings kept secret
        // for almost the whole animation:
        //
        // Phase 1 — SUSPENSE: every bar wobbles near a shared, roughly equal
        //           "decoy" height (based on the average vote count, not each
        //           candidate's real value), so all candidates look neck-and-
        //           neck the whole time. The wobble is random per candidate
        //           and has nothing to do with real standings, so there's no
        //           way to read who's ahead from it.
        // Phase 2 — REVEAL: right at the end, every bar smoothly (with one
        //           quick settling bounce) moves from wherever the decoy
        //           wobble left it to its real value, all at once — this is
        //           the only moment the true standings become visible.
        //
        // All candidates share the same SUSPENSE_MS + REVEAL_MS timeline, so
        // nobody locks in early and gives the result away ahead of everyone
        // else. The winner badge / row highlight are likewise only ever
        // attached to the DOM once a candidate's own reveal has completed
        // (see animateOneCandidate below), so the markup itself can't give
        // anything away early either.
        const SUSPENSE_MS = 3200;   // how long bars dance around the shared decoy height
        const REVEAL_MS = 1000;     // final stretch where bars settle onto their true values
        const BAR_ANIM_MS = SUSPENSE_MS + REVEAL_MS;
        const MAX_STAGGER_MS = 150; // tiny random head-start per candidate, purely cosmetic

        const SUSPENSE_PERIOD_MS = 260;   // how fast the decoy wobble oscillates
        const SUSPENSE_JITTER = 0.28;     // how far the decoy wobble can drift from the shared baseline

        const REVEAL_OSCILLATIONS = 2.5;  // quick settle-bounce once the real value is revealed
        const REVEAL_PERIOD_MS = REVEAL_MS / REVEAL_OSCILLATIONS;
        const REVEAL_DECAY = 5;

        // A shared, roughly-equal decoy baseline every bar wobbles around
        // during the suspense phase — the AVERAGE vote count, not any
        // individual candidate's real value, and the same equal-share width
        // for every bar, so there's nothing to read into it.
        const decoyVotes = realData.length ? realData.reduce((a, b) => a + b, 0) / realData.length : 0;
        const decoyWidth = realData.length ? 100 / realData.length : 0;

        // Random (not standings-based) wobble parameters, one set per
        // candidate, generated once so each bar's dance looks distinct but
        // conveys nothing about who's actually winning.
        const wobbleSeeds = realData.map(() => ({
            phase1: Math.random() * Math.PI * 2,
            phase2: Math.random() * Math.PI * 2,
        }));

        // Value the decoy wobble is at for a given elapsed time — oscillates
        // around decoyBase using two blended sine waves so it drifts rather
        // than snapping, without ever trending toward the real value.
        function decoyValueAt(elapsedMs, decoyBase, seed) {
            const wobble = 0.6 * Math.sin((2 * Math.PI * elapsedMs) / SUSPENSE_PERIOD_MS + seed.phase1)
                         + 0.4 * Math.sin((2 * Math.PI * elapsedMs) / (SUSPENSE_PERIOD_MS * 1.7) + seed.phase2);
            return Math.max(0, decoyBase * (1 + SUSPENSE_JITTER * wobble));
        }

        // Eases (with one quick settling bounce) from `start` to `target` as
        // elapsedMs goes from 0 to REVEAL_MS.
        function revealValueAt(elapsedMs, start, target) {
            if (elapsedMs <= 0) return start;
            if (elapsedMs >= REVEAL_MS) return target;
            const t = elapsedMs / REVEAL_MS;
            const envelope = Math.pow(2, -REVEAL_DECAY * t);
            const factor = envelope * Math.sin((elapsedMs - REVEAL_PERIOD_MS / 4) * (2 * Math.PI) / REVEAL_PERIOD_MS) + 1;
            return start + (target - start) * factor;
        }

        const voteChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Votes',
                    data: zeroData,
                    backgroundColor: colors,
                    borderColor: '#fff',
                    borderWidth: 2,
                    borderRadius: 8,
                    hoverBorderWidth: 3,
                    hoverBorderColor: '#fff',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                // Chart.js's own animation system is turned off — we drive the
                // bar heights ourselves every frame (see runParallelReveal below)
                // so we can control bounce speed, bounce count, and sequencing.
                animation: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 },
                        // extra headroom so both the decoy wobble and the final
                        // reveal bounce have room without getting clipped
                        suggestedMax: Math.max(Math.max(...realData) * 1.4, decoyVotes * (1 + SUSPENSE_JITTER) * 1.4) + 1
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let total = <?= $total_votes ?>;
                                let percentage = total > 0 ? ((context.raw / total) * 100).toFixed(1) : 0;
                                return context.raw + ' votes (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Animates ONE candidate's chart bar and its matching table row
        // together: wobbles near the shared decoy height for SUSPENSE_MS,
        // then eases (with one settling bounce) onto its real value over the
        // final REVEAL_MS, then calls onDone. Every other chart bar's value
        // in dataArray is left untouched, so candidates who haven't started
        // yet stay at 0 and candidates who are mid-animation keep updating
        // independently.
        //
        // `isWinner` is passed in from runParallelReveal (computed from the
        // REAL data, not from any pre-rendered class) and is only acted on
        // once elapsed >= BAR_ANIM_MS — i.e. only once this candidate's bar
        // has actually landed on its true, final value. That's the single
        // point where `.is-winner-row` gets added to the row and the trophy
        // badge is flipped from `.badge-pending` to `.badge-revealed`, so
        // there is no way to read the outcome before that moment.
        function animateOneCandidate(chart, dataArray, index, row, targetVotes, targetWidth, seed, isWinner, onDone) {
            const startTime = performance.now();
            if (row) row.classList.add('row-in');
            const bar = row ? row.querySelector('.bar-fill') : null;
            const countEl = row ? row.querySelector('.vote-count-badge .count-up') : null;
            let revealStartVotes = decoyVotes;
            let revealStartWidth = decoyWidth;

            function frame(now) {
                const elapsed = now - startTime;

                if (elapsed >= BAR_ANIM_MS) {
                    dataArray[index] = targetVotes;
                    chart.data.datasets[0].data = dataArray;
                    chart.update('none');
                    if (bar) bar.style.width = targetWidth + '%';
                    if (countEl) countEl.textContent = targetVotes;

                    // ---- Reveal winner cues, but only right here, right
                    // now — after the real value has actually landed. ----
                    if (isWinner && row) {
                        row.classList.add('is-winner-row');
                        const badge = row.querySelector('.winner-badge');
                        if (badge) {
                            badge.classList.remove('badge-pending');
                            badge.classList.add('badge-revealed');
                            badge.removeAttribute('aria-hidden');
                        }
                    }

                    onDone();
                    return;
                }

                if (elapsed < SUSPENSE_MS) {
                    // ---- Phase 1: suspense — wobble near the shared decoy
                    // height, telling the audience nothing about who's ahead.
                    const votes = decoyValueAt(elapsed, decoyVotes, seed);
                    const width = decoyValueAt(elapsed, decoyWidth, seed);
                    dataArray[index] = votes;
                    chart.data.datasets[0].data = dataArray;
                    chart.update('none');
                    if (bar) bar.style.width = Math.max(0, Math.min(width, 100)) + '%';
                    if (countEl) countEl.textContent = Math.round(votes);

                    // Remember exactly where the wobble left off, so phase 2
                    // eases from this point rather than jumping.
                    revealStartVotes = votes;
                    revealStartWidth = width;
                } else {
                    // ---- Phase 2: reveal — ease from wherever the wobble
                    // left off onto the real value, with one quick settle.
                    const revealElapsed = elapsed - SUSPENSE_MS;
                    const votes = revealValueAt(revealElapsed, revealStartVotes, targetVotes);
                    const width = revealValueAt(revealElapsed, revealStartWidth, targetWidth);
                    dataArray[index] = Math.max(0, votes);
                    chart.data.datasets[0].data = dataArray;
                    chart.update('none');
                    if (bar) bar.style.width = Math.max(0, Math.min(width, 100)) + '%';
                    if (countEl) countEl.textContent = Math.round(Math.max(0, votes));
                }

                requestAnimationFrame(frame);
            }
            requestAnimationFrame(frame);
        }

        // Kicks off every candidate's animation at (almost) the same moment
        // — each with its own random decoy-wobble seed and a tiny random
        // head start, purely cosmetic — and calls onAllDone only once every
        // single candidate has locked onto its real value. Because everyone
        // wobbles near the same shared decoy height for the whole suspense
        // phase and only reveals their true value in the last stretch,
        // together, there's no point before that where the standings can be
        // read off the screen. `isWinner` is computed here from the real
        // vote totals (never from a pre-rendered DOM class) and handed to
        // animateOneCandidate so it can gate the winner reveal correctly.
        function runParallelReveal(chart, targets, rows, onAllDone) {
            const dataArray = targets.map(() => 0);
            const n = targets.length;
            let doneCount = 0;
            const maxVotes = targets.length ? Math.max(...targets) : 0;

            targets.forEach((target, index) => {
                const row = rows[index];
                const bar = row ? row.querySelector('.bar-fill') : null;
                const targetWidth = bar ? parseFloat(bar.dataset.width) || 0 : 0;
                const startDelay = Math.random() * MAX_STAGGER_MS;
                const seed = wobbleSeeds[index];
                const isWinner = maxVotes > 0 && target === maxVotes;

                setTimeout(() => {
                    animateOneCandidate(chart, dataArray, index, row, target, targetWidth, seed, isWinner, () => {
                        doneCount++;
                        if (doneCount === n) onAllDone();
                    });
                }, startDelay);
            });
        }

        // ---- Count-up animation for any numeric badge ----
        function animateCount(el, target, duration = 1100, delay = 0) {
            setTimeout(() => {
                const start = performance.now();
                function tick(now) {
                    const progress = Math.min((now - start) / duration, 1);
                    const eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
                    el.textContent = Math.round(target * eased);
                    if (progress < 1) requestAnimationFrame(tick);
                    else el.textContent = target;
                }
                requestAnimationFrame(tick);
            }, delay);
        }

        // Header "Total Votes" counts up right away.
        document.querySelectorAll('.rhb-badges .count-up').forEach(el => {
            animateCount(el, parseInt(el.dataset.target, 10) || 0, 1200, 300);
        });

        // (Chart-bar and table-row bouncing for each candidate is now handled
        // together, all in parallel, by animateOneCandidate/runParallelReveal above.)

        // ---- Colorful confetti covering the whole page, fired the moment
        // the winner modal is visible. Since the results page underneath is
        // now fully hidden, confetti is the only thing moving on screen, so
        // it's made denser and spread across the full width/height rather
        // than concentrated near the center. ----
        function fireConfetti() {
            if (typeof confetti !== 'function') return;
            const confettiColors = ['#1e4d92', '#f4820a', '#16a34a', '#e11d48', '#6f42c1', '#eab308', '#06b6d4', '#ec4899'];

            // Big center burst
            confetti({
                particleCount: 180,
                spread: 130,
                startVelocity: 50,
                origin: { y: 0.4 },
                colors: confettiColors,
                zIndex: 2000
            });

            // A handful of extra bursts scattered across the top of the
            // page so confetti rains down over the entire width, not just
            // the middle.
            [0.08, 0.28, 0.5, 0.72, 0.92].forEach((x, i) => {
                setTimeout(() => {
                    confetti({
                        particleCount: 60,
                        spread: 80,
                        startVelocity: 40,
                        origin: { x, y: -0.05 },
                        colors: confettiColors,
                        zIndex: 2000
                    });
                }, i * 120);
            });

            // Side cannons streaming in from both edges for a few seconds
            const end = Date.now() + 3200;
            (function frame() {
                confetti({ particleCount: 6, angle: 60, spread: 70, origin: { x: 0, y: 0.7 }, colors: confettiColors, zIndex: 2000 });
                confetti({ particleCount: 6, angle: 120, spread: 70, origin: { x: 1, y: 0.7 }, colors: confettiColors, zIndex: 2000 });
                confetti({ particleCount: 3, angle: 90, spread: 100, startVelocity: 30, origin: { x: Math.random(), y: -0.05 }, colors: confettiColors, zIndex: 2000 });
                if (Date.now() < end) requestAnimationFrame(frame);
            })();
        }

        // ---- Staged reveal sequence ----
        const stageChart = document.getElementById('stageChart');
        const stageList = document.getElementById('stageList');

        // Stage 1: chart starts off-screen to the right (see CSS default transform).
        // Trigger the slide-in on the next frame so the transition actually runs.
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                stageChart.classList.add('is-in');
            });
        });

        // Stage 2: once the 3s slide finishes, BOTH cards reveal together —
        // the bars grow for real (now that they're visible) and the
        // candidate breakdown fades in with its own staggered bars/counters.
        // The winner modal only fires after every candidate has fully
        // finished animating (see runParallelReveal above).
        let revealed = false;
        function revealResults() {
            if (revealed) return;
            revealed = true;

            stageChart.classList.add('settled');

            // Grow the chart bars now that the card is actually on screen —
            // every candidate wobbles near the same shared decoy height
            // together, then all reveal their true value together right at
            // the very end, so the standings stay a mystery until then.
            const rows = Array.from(document.querySelectorAll('#candidateTableBody tr'));

            // Reveal the candidate breakdown card alongside the chart (the
            // rows themselves fade in as their bounce starts).
            stageList.classList.add('is-visible');

            runParallelReveal(voteChart, realData, rows, () => {
                <?php if ($winner): ?>
                winnerModalEl.addEventListener('shown.bs.modal', fireConfetti);
                const wm = new bootstrap.Modal(winnerModalEl);
                wm.show();
                <?php endif; ?>
            });
        }

        // Fire the moment the slide's transform transition ends...
        stageChart.addEventListener('transitionend', (e) => {
            if (e.propertyName === 'transform') revealResults();
        });
        // ...with a safety-net timer in case the transitionend event is missed
        // (e.g. the tab was backgrounded mid-animation).
        setTimeout(revealResults, 3400);
    <?php endif; ?>
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>