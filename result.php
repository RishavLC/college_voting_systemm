<?php
// Hardcoded election data (faculty, batch, semester => election details + candidates)
// Batches follow the Nepali academic calendar (B.S.) used by the college: 2080-2083.
$election_data = [
    'BCA_2081_5' => [
        'name' => 'BCA 5th Semester Election',
        'date' => '2081-06-15',
        'candidates' => [
            ['name' => 'John Doe', 'votes' => 45],
            ['name' => 'Jane Smith', 'votes' => 38],
            ['name' => 'Emily Johnson', 'votes' => 52],
            ['name' => 'Michael Brown', 'votes' => 29],
        ]
    ],
    'BIM_2080_3' => [
        'name' => 'BIM 3rd Semester Election',
        'date' => '2080-06-20',
        'candidates' => [
            ['name' => 'Alice Wonder', 'votes' => 30],
            ['name' => 'Bob Builder', 'votes' => 25],
            ['name' => 'Carol Danvers', 'votes' => 40],
            ['name' => 'David Beckham', 'votes' => 12],
        ]
    ],
    'BCA_2080_7' => [
        'name' => 'BCA 7th Semester Election',
        'date' => '2080-05-10',
        'candidates' => [
            ['name' => 'Sarah Connor', 'votes' => 60],
            ['name' => 'James Bond', 'votes' => 55],
            ['name' => 'Harry Potter', 'votes' => 70],
        ]
    ],
    'BBS_2082_2' => [
        'name' => 'BBS 2nd Semester Election',
        'date' => '2082-07-01',
        'candidates' => [
            ['name' => 'Tony Stark', 'votes' => 48],
            ['name' => 'Steve Rogers', 'votes' => 43],
            ['name' => 'Bruce Banner', 'votes' => 21],
            ['name' => 'Natasha Romanoff', 'votes' => 35],
        ]
    ],
    'BITM_2081_4' => [
        'name' => 'BITM 4th Semester Election',
        'date' => '2081-04-18',
        'candidates' => [
            ['name' => 'Peter Parker', 'votes' => 33],
            ['name' => 'Wade Wilson', 'votes' => 41],
            ['name' => 'Diana Prince', 'votes' => 37],
        ]
    ],
    'BHM_2083_1' => [
        'name' => 'BHM 1st Semester Election',
        'date' => '2083-02-05',
        'candidates' => [
            ['name' => 'Clark Kent', 'votes' => 22],
            ['name' => 'Bruce Wayne', 'votes' => 27],
            ['name' => 'Barry Allen', 'votes' => 19],
        ]
    ],
];

$faculties = ['BCA', 'BIM', 'BITM', 'Bsc. CSIT', 'BHM', 'BBS'];
$batches = ['2080', '2081', '2082', '2083'];
$semesters = range(1, 8);

// Get filter inputs
$faculty = isset($_GET['faculty']) ? trim($_GET['faculty']) : '';
$batch = isset($_GET['batch']) ? trim($_GET['batch']) : '';
$semester = isset($_GET['semester']) ? intval($_GET['semester']) : 0;

$election = null;
$candidates = [];
$total_votes = 0;
$winner = null;

if ($faculty && $batch && $semester) {
    $election_key = $faculty . '_' . $batch . '_' . $semester;
    if (isset($election_data[$election_key])) {
        $election = $election_data[$election_key];
        $candidates = $election['candidates'];
        $total_votes = array_sum(array_column($candidates, 'votes'));
        if ($total_votes > 0) {
            $max_votes = max(array_column($candidates, 'votes'));
            foreach ($candidates as $c) {
                if ($c['votes'] == $max_votes) { $winner = $c; break; }
            }
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
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Election Results · HDCVotes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="assets/css/custom.css">
    <link rel="icon" href="assets/img/logo.png">
</head>
<body>
<div class="container" style="max-width:1100px;">

    <!-- ============ Header: logo | election details | filter ============ -->
    <div class="results-header animate-card">
        <div class="rh-logo">
            <img src="assets/img/logo.png" alt="Himalaya Darshan College" class="brand-logo brand-logo-lg">
        </div>

        <div class="rh-details">
            <h6 class="text-muted mb-1" style="letter-spacing:.05em; text-transform:uppercase; font-size:.72rem;">Himalaya Darshan College &middot; HDCVotes</h6>
            <?php if ($election): ?>
                <h3><?= htmlspecialchars($election['name']) ?></h3>
                <p class="text-muted mb-2"><i class="bi bi-calendar-event"></i> <?= htmlspecialchars($election['date']) ?> B.S.</p>
                <span class="election-badge"><i class="bi bi-people"></i> Total Votes: <?= $total_votes ?></span>
                <span class="badge bg-secondary ms-1"><?= htmlspecialchars($faculty) ?></span>
                <span class="badge bg-secondary ms-1">Batch <?= htmlspecialchars($batch) ?></span>
                <span class="badge bg-secondary ms-1">Sem <?= $semester ?></span>
            <?php elseif ($faculty && $batch && $semester): ?>
                <h3 class="placeholder-text"><i class="bi bi-exclamation-triangle text-warning"></i> No election found</h3>
                <p class="text-muted mb-0">No results match this faculty, batch and semester.</p>
            <?php else: ?>
                <h3 class="placeholder-text"><i class="bi bi-bar-chart-fill text-primary"></i> Election Results</h3>
                <p class="text-muted mb-0">Choose your batch, faculty and semester to view results.</p>
            <?php endif; ?>
        </div>

        <div class="rh-filter">
            <form method="GET" class="row">
                <div class="col-12">
                    <label for="batch">Batch</label>
                    <select id="batch" name="batch" class="form-select" required>
                        <option value="">Select batch</option>
                        <?php foreach ($batches as $b): ?>
                            <option value="<?= $b ?>" <?= $batch === $b ? 'selected' : '' ?>><?= $b ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label for="faculty">Faculty</label>
                    <select id="faculty" name="faculty" class="form-select" required>
                        <option value="">Select faculty</option>
                        <?php foreach ($faculties as $f): ?>
                            <option value="<?= htmlspecialchars($f) ?>" <?= $faculty === $f ? 'selected' : '' ?>><?= htmlspecialchars($f) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label for="semester">Semester</label>
                    <select id="semester" name="semester" class="form-select" required>
                        <option value="">Select semester</option>
                        <?php foreach ($semesters as $s): ?>
                            <option value="<?= $s ?>" <?= $semester === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> View Results</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($election && count($candidates) > 0): ?>
        <!-- ============ Staged reveal: chart first, then list ============ -->
        <div class="results-stage" id="resultsStage">

            <div class="stage-chart" id="stageChart">
                <div class="result-card animate-card">
                    <h4><i class="bi bi-graph-up-arrow text-success"></i> Vote Distribution</h4>
                    <div class="chart-container">
                        <canvas id="voteChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="stage-list" id="stageList">
                <div class="result-card">
                    <h4><i class="bi bi-table text-info"></i> Candidate-wise Breakdown</h4>
                    <div class="table-responsive">
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
                                <tr>
                                    <td><?= $rank++ ?></td>
                                    <td class="candidate-label">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="cand-avatar" style="background:<?= $color ?>;"><?= cand_initials($cand['name']) ?></div>
                                            <span>
                                                <?= htmlspecialchars($cand['name']) ?>
                                                <?php if ($is_winner): ?>
                                                    <span class="winner-badge ms-1"><i class="bi bi-trophy"></i> Winner</span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="vote-count-badge"><?= $cand['votes'] ?></span>
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
    <?php elseif ($faculty && $batch && $semester): ?>
        <div class="result-card no-data animate-card">
            <i class="bi bi-info-circle display-3"></i>
            <h4>No results available</h4>
            <p class="text-muted">Either the election wasn't found or no candidates have been assigned yet.</p>
        </div>
    <?php else: ?>
        <div class="result-card text-center animate-card">
            <i class="bi bi-funnel display-3 text-secondary"></i>
            <h4>Please select batch, faculty and semester</h4>
            <p class="text-muted">Use the filters above to view election results.</p>
        </div>
    <?php endif; ?>
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
                <div class="crown"><i class="bi bi-award-fill"></i></div>
                <div class="winner-photo"><?= cand_initials($winner['name']) ?></div>
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
    <?php if ($election && count($candidates) > 0): ?>
        // ---- Chart.js bar chart ----
        const ctx = document.getElementById('voteChart').getContext('2d');
        const labels = <?= json_encode(array_column($candidates, 'name')) ?>;
        const data = <?= json_encode(array_column($candidates, 'votes')) ?>;
        const colors = <?= json_encode(array_map(fn($i) => cand_color($i, $palette), array_keys($candidates))) ?>;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Votes',
                    data: data,
                    backgroundColor: colors,
                    borderColor: '#fff',
                    borderWidth: 2,
                    borderRadius: 8,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 1800, easing: 'easeOutBounce' },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
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

        function animateTableBars() {
            document.querySelectorAll('.bar-fill').forEach(bar => {
                bar.style.width = parseFloat(bar.dataset.width) + '%';
            });
        }

        // ---- Staged reveal sequence ----
        // Stage 1: chart shows full-width first (default state, no action needed).
        const stageChart = document.getElementById('stageChart');
        const stageList = document.getElementById('stageList');

        // Stage 2: once the chart has finished its own animation, slide it to the left.
        setTimeout(() => { stageChart.classList.add('is-left'); }, 2100);

        // Stage 3: candidate list fades in across the full row.
        setTimeout(() => {
            stageList.classList.add('is-visible');
            animateTableBars();
        }, 2800);

        // Stage 4: list also settles into the left column, next to/under the chart.
        setTimeout(() => { stageList.classList.add('is-left'); }, 4600);

        // Stage 5: winner popup.
        <?php if ($winner): ?>
        setTimeout(() => {
            const wm = new bootstrap.Modal(document.getElementById('winnerModal'));
            wm.show();
        }, 5400);
        <?php endif; ?>
    <?php endif; ?>
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>