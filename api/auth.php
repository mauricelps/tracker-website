<?php
// api/authenticate.php
// Verifiziere einen Installations-Code (core_tokens.token) und speichere den vom Tracker gelieferten tracker_id.
// Eingabe (POST, JSON oder form-data):
// - token (string) required  -- der Installations-Code aus core_tokens.token
// - tracker_id (string) required -- vom Tracker erzeugter Token/ID (z. B. UUID)
// Optional:
// - force (bool) optional -- wenn true, überschreibt bereits gesetztes tracker_id

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

// require DB (fix path)
require_once __DIR__ . '/../db.php';

// sanity check for $pdo
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    error_log('authenticate.php: $pdo not available after require');
    echo json_encode(['success' => false, 'error' => 'Server configuration error (database).']);
    exit;
}

// read input (JSON or form)
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
    $input = $_POST;
}

$token = isset($input['token']) ? trim((string)$input['token']) : '';
$trackerId = isset($input['tracker_id']) ? trim((string)$input['tracker_id']) : '';
$force = (!empty($input['force']) && ($input['force'] === true || $input['force'] === '1' || $input['force'] === 'true')) ? true : false;

$errors = [];
if ($token === '') $errors[] = 'token is required';
if ($trackerId === '') $errors[] = 'tracker_id is required';
if ($token !== '' && mb_strlen($token) > 255) $errors[] = 'token too long';
if ($trackerId !== '' && mb_strlen($trackerId) > 255) $errors[] = 'tracker_id too long';
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// optional: validate tracker_id format (basic)
if (!preg_match('/^[A-Za-z0-9\-_:.]{8,255}$/', $trackerId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'tracker_id has invalid format']);
    exit;
}

try {
    // Atomare Prüfung + ggf. Update
    $pdo->beginTransaction();

    // Lock the row to avoid race conditions
    $stmt = $pdo->prepare('SELECT token, user_id, hardware_id FROM core_tokens WHERE token = ? LIMIT 1 FOR UPDATE');
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $pdo->rollBack();
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid token']);
        exit;
    }

    // Wenn bereits ein tracker_id gesetzt ist und kein force, dann ablehnen
    if (!empty($row['tracker_id']) && !$force) {
        // Wenn die gleiche tracker_id bereits hinterlegt ist, ist das idempotent -> success
        if ($row['tracker_id'] === $trackerId) {
            $pdo->commit();
            echo json_encode(['success' => true, 'user_id' => (int)$row['user_id'], 'note' => 'already_registered']);
            exit;
        } else {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Install token already used by another tracker. Use force to override.']);
            exit;
        }
    }

    // Setze tracker_id und updated_at
    $upd = $pdo->prepare('UPDATE core_tokens SET hardware_id = ?, updated_at = NOW() WHERE token = ?');
    $upd->execute([$trackerId, $token]);

    $pdo->commit();

    echo json_encode(['success' => true, 'user_id' => (int)$row['user_id']]);
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    error_log('authenticate.php DB error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    error_log('authenticate.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
    exit;
}
?>