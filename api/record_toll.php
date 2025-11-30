<?php
header('Content-Type: application/json');

$json_str = file_get_contents('php://input');
$data = json_decode($json_str, true);

if ($data === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON format.']);
    exit();
}

$required_fields = ['jobId', 'userid', 'game', 'amount'];
foreach ($required_fields as $field) {
    if (!array_key_exists($field, $data)) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

$job_id = $data['jobId'];
$steam_id = $data['userid'];
$game = $data['game'];
$amount = (float)$data['amount'];

require_once '../db.php';

try {
    // Prüfen, ob Job existiert und zum Fahrer gehört
    $check_sql = "SELECT id FROM tracker_jobs WHERE id = ? AND driver_steam_id = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$job_id, $steam_id]);

    if ($check_stmt->fetch() === false) {
        http_response_code(404);
        echo json_encode(['error' => 'Job not found or access denied.']);
        exit();
    }

    // Eintrag in Tabelle 'tolls' (falls Schema abweicht, bitte Spalten anpassen)
    $sql = "INSERT INTO tracker_tolls (job_id, amount, game, created_at, user_id) VALUES (?, ?, ?, NOW(), ?)";
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([
        $job_id,
        $amount,
        $game,
        $steam_id
    ]);

    if ($success) {
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Toll recorded successfully.',
            'toll_id' => (int)$pdo->lastInsertId()
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to record toll.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error.', 'details' => $e->getMessage()]);
}
?>