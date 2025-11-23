<?php
// record_toll.php
// API endpoint to record tolls

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

// load DB (expects $pdo)
// NOTE: fix path - add '/../' between __DIR__ and filename
require_once __DIR__ . '/../db.php';

// sanity check for $pdo
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    error_log('record_toll.php: $pdo not available after require');
    echo json_encode(['success' => false, 'error' => 'Server configuration error (database).']);
    exit;
}

// fetch raw input (support JSON or form data)
$input = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
if (stripos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
        exit;
    }
    $input = $decoded;
} else {
    // form encoded or multipart
    $input = $_POST;
}

// helper to get param
$get = function($key) use ($input) {
    return isset($input[$key]) ? $input[$key] : null;
};

// Auth token: header Bearer, X-Auth-Token or POST field
$authToken = null;
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null);
if ($authHeader) {
    if (stripos($authHeader, 'bearer ') === 0) {
        $authToken = substr($authHeader, 7);
    } else {
        $authToken = $authHeader;
    }
}
if (empty($authToken) && !empty($_SERVER['HTTP_X_AUTH_TOKEN'])) {
    $authToken = $_SERVER['HTTP_X_AUTH_TOKEN'];
}
if (empty($authToken) && !empty($input['auth_token'])) {
    $authToken = $input['auth_token'];
}

// Validate required fields
$game = trim((string)$get('game'));
$amount = $get('amount');
$jobId = $get('jobId') ?? $get('job_id');
$steamId = (string) $get('steamId');

$errors = [];
if ($game === '') $errors[] = 'game is required';
if ($amount === null || !is_numeric($amount)) $errors[] = 'amount is required and must be numeric';
if ($jobId === null || !is_numeric($jobId) || intval($jobId) <= 0) $errors[] = 'jobId is required and must be a positive integer';
if ($steamId === '' || !preg_match('/^[0-9]{6,20}$/', $steamId)) $errors[] = 'steamId is required and must be numeric (SteamID64 recommended)';

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

if (empty($authToken)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Missing auth token']);
    exit;
}

try {
    // find user by steamid and auth_token
    $stmt = $pdo->prepare('SELECT id, status FROM users WHERE steamid = ? AND auth_token = ? LIMIT 1');
    $stmt->execute([$steamId, $authToken]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid auth token or steamId']);
        exit;
    }
    if ($user['status'] !== 'active') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'User account not active']);
        exit;
    }

    $userId = (int)$user['id'];
    $jobIdInt = (int)$jobId;
    $amountVal = (float)$amount;

    // verify job exists; select both possible owner columns
    $j = $pdo->prepare('SELECT id, user_id, driver_steam_id FROM jobs WHERE id = ? LIMIT 1');
    $j->execute([$jobIdInt]);
    $job = $j->fetch(PDO::FETCH_ASSOC);
    if (!$job) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Job not found']);
        exit;
    }

    // if jobs.user_id exists, check that first, otherwise fallback to driver_steam_id comparison
    if (isset($job['user_id']) && $job['user_id'] !== null) {
        if ((int)$job['user_id'] !== $userId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Job does not belong to authenticated user']);
            exit;
        }
    } else {
        // fall back: check driver_steam_id string
        $driverSteam = (string)($job['driver_steam_id'] ?? '');
        if ($driverSteam === '' || $driverSteam !== $steamId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Job does not belong to authenticated user (steamId mismatch)']);
            exit;
        }
    }

    // insert into tolls table
    $ins = $pdo->prepare('INSERT INTO tolls (job_id, user_id, game, amount, created_at) VALUES (?, ?, ?, ?, ?)');
    $now = date('Y-m-d H:i:s');
    $ins->execute([$jobIdInt, $userId, $game, $amountVal, $now]);
    $tollId = $pdo->lastInsertId();

    echo json_encode(['success' => true, 'toll_id' => (int)$tollId]);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    error_log('record_toll.php DB error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    error_log('record_toll.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
    exit;
} catch (Throwable $e) {
    // catch PHP Errors (e.g. call on null) and others
    http_response_code(500);
    error_log('record_toll.php throwable: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
    exit;
}
?>