<?php
header('Content-Type: application/json');

$json_str = file_get_contents('php://input');
$data = json_decode($json_str, true);

if ($data === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON format.']);
    exit();
}

$required_fields = ['jobId', 'steamid', 'transportType', 'source', 'destination', 'game', 'amount'];
foreach ($required_fields as $field) {
    if (!array_key_exists($field, $data)) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

$job_id = $data['jobId'];
$steam_id = $data['steamid'];
$transport_type = $data['transportType'];
$source_name = $data['source'];
$destination_name = $data['destination'];
$game = $data['game'];
$amount = (int)$data['amount'];

require_once '../db.php';

try {
    $check_sql = "SELECT id FROM jobs WHERE id = ? AND driver_steam_id = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$job_id, $steam_id]);

    if ($check_stmt->fetch() === false) {
        http_response_code(404);
        echo json_encode(['error' => 'Job not found or access denied.']);
        exit();
    }

    $sql = "INSERT INTO job_transports 
                (job_id, transport_type, source_name, destination_name, timestamp, game, amount, drv_steam_id) 
            VALUES 
                (?, ?, ?, ?, NOW(), ?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([
        $job_id,
        $transport_type,
        $source_name,
        $destination_name,
        $game,
        $amount,
        $steam_id
    ]);

    if ($success) {
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Transport event recorded successfully.',
            'transport_id' => $pdo->lastInsertId()
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to record transport event.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error.', 'details' => $e->getMessage()]);
}
?>