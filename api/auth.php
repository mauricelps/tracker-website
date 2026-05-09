<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once '../db.php'; // erwartet: $pdo (PDO)

function respond(int $httpStatus, array $payload): void
{
    http_response_code($httpStatus);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Hardcoded App-ID (muss 1:1 mit der App übereinstimmen)
 * WICHTIG: Das ist nur ein "App-Check", keine echte Security.
 */
const APP_ID = 'EnJGIcxU4WFffpa-lgJ<nb]j>pSYhFQK';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    respond(400, ['ok' => false, 'error' => 'invalid_json']);
}

$appId = $data['appId'] ?? null;
$userToken = $data['userToken'] ?? null;

if (!is_string($appId) || $appId === '' || !is_string($userToken) || $userToken === '') {
    respond(400, [
        'ok' => false,
        'error' => 'missing_parameters',
        'required' => ['appId', 'userToken'],
    ]);
}

if (!hash_equals(APP_ID, $appId)) {
    // App-ID falsch -> nicht deine App
    respond(401, ['ok' => false, 'error' => 'auth_failed']);
}

try {
    /**
     * Erwartete Spalten in core_users:
     * - id
     * - name
     * - isActive (0/1)
     * - userToken (String)  <-- neu
     */
    $sql = "
        SELECT id, name, isActive
        FROM core_users
        WHERE userToken = :token
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':token' => $userToken]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        respond(401, ['ok' => false, 'error' => 'auth_failed']);
    }

    if ((int)($row['isActive'] ?? 0) !== 1) {
        respond(403, ['ok' => false, 'error' => 'user_inactive']);
    }

    $validUntil = time() + 24 * 60 * 60; // now + 24h

    respond(200, [
        'ok' => true,
        'userId' => (int)$row['id'],
        'name' => (string)$row['name'],
        'validUntil' => (int)$validUntil,
    ]);
} catch (Throwable $e) {
    // In Produktion: loggen statt Details ausgeben
    respond(500, ['ok' => false, 'error' => 'server_error']);
}