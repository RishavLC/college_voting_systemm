<?php
// Include SMS configuration
require_once 'Database/sms_config.php';

// ---------- Helper Functions ----------
function callAPI($endpoint, $params = [], $method = 'GET') {
    $url = 'https://verifiedsms.com/api/v1/' . $endpoint;
    if ($method === 'GET' && !empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['success' => false, 'error' => "cURL error: $curlError"];
    }
    $data = json_decode($response, true);
    if (!is_array($data)) {
        return ['success' => false, 'error' => "Invalid JSON response (HTTP $httpCode): " . substr($response, 0, 200)];
    }
    $data['http_code'] = $httpCode;
    return $data;
}

function normalizePhone($input) {
    $p = preg_replace('/[^0-9]/', '', trim($input));
    // If it's 10 digits and starts with 97/98, it's a local Nepali number
    if (strlen($p) === 10 && preg_match('/^9[78]\d{8}$/', $p)) {
        return '+977' . $p;
    }
    // If it already has +977 and is 13 digits
    if (strlen($p) === 13 && substr($p, 0, 3) === '977') {
        return '+' . $p;
    }
    return $p; // return as-is (maybe already with +)
}

// ---------- Process Form Submission ----------
$send_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_sms'])) {
    $raw_number = trim($_POST['number']);
    $message = trim($_POST['message']);
    $error = '';

    if (empty($raw_number)) {
        $error = "Please enter a phone number.";
    } elseif (empty($message)) {
        $error = "Please enter a message.";
    } else {
        $destination = normalizePhone($raw_number);
        // Validate after normalization
        if (!preg_match('/^\+9779[78]\d{8}$/', $destination)) {
            $error = "Invalid number. Must be a 10-digit Nepali number starting with 97 or 98.";
        } elseif (strlen($message) > 1600) {
            $error = "Message too long (max 1600 characters).";
        } else {
            // Send SMS via API
            $params = [
                'key' => SMS_API_KEY,
                'destination' => $destination,
                'message' => $message,
                // 'type' => 3 // optional, you can uncomment if you want OTP type
            ];
            $send_result = callAPI('send.php', $params, 'POST');
        }
    }
    if (!empty($error)) {
        $send_result = ['success' => false, 'error' => $error];
    }
}

// ---------- Fetch Balance & Validate Key ----------
$balance = null;
$key_status = null;
$key_valid = false;

$validate = callAPI('validate_key.php', ['key' => SMS_API_KEY]);
if (isset($validate['status']) && $validate['status'] === 'success') {
    $key_valid = true;
    $key_status = 'Valid';
    // Also fetch balance
    $bal = callAPI('balance.php', ['key' => SMS_API_KEY]);
    if (isset($bal['status']) && $bal['status'] === 'success') {
        $balance = $bal['data']['balance'] ?? 'N/A';
    }
} else {
    $key_status = $validate['message'] ?? 'Invalid or error';
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SMS Test · HDCVotes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f4f5fb; padding: 30px; }
        .card { border-radius: 18px; box-shadow: 0 8px 24px rgba(30,27,46,0.08); border: 1px solid #e7e6f3; }
        .card-header { border-radius: 18px 18px 0 0 !important; font-weight: 700; }
        .form-control, .form-select { border-radius: 10px; border: 1.5px solid #e7e6f3; }
        .btn { border-radius: 10px; font-weight: 600; }
        .status-badge { font-size: 0.9rem; padding: 0.4rem 1rem; }
        .pre-wrap { white-space: pre-wrap; word-break: break-all; background: #f8f9fa; padding: 15px; border-radius: 10px; }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h2 class="mb-4"><i class="bi bi-send-check text-primary me-2"></i>SMS Test Console</h2>

            <!-- Status Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="card-subtitle text-muted"><i class="bi bi-key"></i> API Key</h6>
                            <p class="card-text fs-5">
                                <?php if ($key_valid): ?>
                                    <span class="badge bg-success status-badge"><i class="bi bi-check-circle-fill"></i> Valid</span>
                                <?php else: ?>
                                    <span class="badge bg-danger status-badge"><i class="bi bi-x-circle-fill"></i> Invalid</span>
                                    <br><small class="text-muted"><?= htmlspecialchars($key_status) ?></small>
                                <?php endif; ?>
                            </p>
                            <small class="text-muted">Key: <?= substr(SMS_API_KEY, 0, 20) . '…' ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="card-subtitle text-muted"><i class="bi bi-wallet2"></i> Balance</h6>
                            <p class="card-text fs-3 fw-bold <?= ($balance !== null && $balance > 0) ? 'text-success' : 'text-danger' ?>">
                                <?= $balance !== null ? $balance : '—' ?>
                            </p>
                            <small class="text-muted">Credits available</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Send SMS Form -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-chat-dots me-2"></i>Send SMS
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="number" class="form-label">Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-phone"></i></span>
                                <input type="tel" class="form-control" id="number" name="number"
                                       placeholder="e.g., 9862887116" required>
                            </div>
                            <div class="form-text">Enter a 10‑digit number starting with 97 or 98. We'll add +977 automatically.</div>
                        </div>
                        <div class="mb-3">
                            <label for="message" class="form-label">Message</label>
                            <textarea class="form-control" id="message" name="message" rows="3"
                                      placeholder="Your SMS text (max 1600 chars)" required></textarea>
                            <div class="form-text" id="charCount">0 / 1600</div>
                        </div>
                        <button type="submit" name="send_sms" class="btn btn-primary w-100">
                            <i class="bi bi-send-fill me-1"></i> Send SMS
                        </button>
                    </form>

                    <?php if ($send_result !== null): ?>
                        <hr>
                        <h6><i class="bi bi-info-circle"></i> API Response</h6>
                        <?php if (isset($send_result['success']) && $send_result['success'] === false): ?>
                            <div class="alert alert-danger">
                                <strong>Error:</strong> <?= htmlspecialchars($send_result['error'] ?? 'Unknown') ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success">
                                <strong>Status:</strong> <?= $send_result['status'] ?? '?' ?><br>
                                <strong>Message:</strong> <?= $send_result['message'] ?? '' ?>
                                <?php if (isset($send_result['data'])): ?>
                                    <hr>
                                    <div class="pre-wrap"><?= json_encode($send_result['data'], JSON_PRETTY_PRINT) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($send_result['http_code'])): ?>
                            <small class="text-muted">HTTP Code: <?= $send_result['http_code'] ?></small>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Raw Debug (only if you want) -->
            <div class="mt-3 text-muted small">
                <details>
                    <summary>Raw API Debug (click to expand)</summary>
                    <pre class="pre-wrap mt-2"><?php
                        $debug = [
                            'key' => SMS_API_KEY,
                            'balance' => $balance,
                            'key_valid' => $key_valid,
                            'validate_response' => $validate ?? null,
                            'last_send' => $send_result ?? null,
                        ];
                        echo htmlspecialchars(json_encode($debug, JSON_PRETTY_PRINT));
                    ?></pre>
                </details>
            </div>

        </div>
    </div>
</div>

<script>
    // Character counter
    document.getElementById('message').addEventListener('input', function() {
        const len = this.value.length;
        document.getElementById('charCount').textContent = len + ' / 1600';
        if (len > 1600) {
            document.getElementById('charCount').classList.add('text-danger');
        } else {
            document.getElementById('charCount').classList.remove('text-danger');
        }
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>