<?php

header('Content-Type: application/json');

// --- SCHRITT 1: DATEN AUSLESEN ---
$json_str = file_get_contents('php://input');
$data = json_decode($json_str, true);

if ($data === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON format.']);
    exit();
}

// --- SCHRITT 2: DATEN VALIDIEREN ---
// Diese Felder MÜSSEN vom Java-Client gesendet werden.
// Beachte die Namen: `transportType`, `sourceName`, `destinationName`
$required_fields = ['jobId', 'steamid', 'transportType', 'source', 'destination', 'game', 'amount'];
foreach ($required_fields as $field) {
    if (!array_key_exists($field, $data)) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

// Die Daten in Variablen speichern
$job_id = $data['jobId'];
$steam_id = $data['steamid']; // Wichtig zur Verifizierung!
$transport_type = $data['transportType']; // z.B. 'ferry' oder 'train'
$source_name = $data['source'];
$destination_name = $data['destination'];
$game = $data['game'];
$amount = $data['amount'];

// --- SCHRITT 3: DATENBANKVERBINDUNG (PDO!) ---
require_once '../db.php'; // Stellt die $pdo Variable bereit

try {
    // --- SCHRITT 4: VERIFIZIERUNG (Sicherheits-Check) ---
    // Wir prüfen, ob der Job existiert UND dem übergebenen User gehört.
    $check_sql = "SELECT id FROM jobs WHERE id = ? AND driver_steam_id = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$job_id, $steam_id]);
    
    if ($check_stmt->fetch() === false) {
        http_response_code(404);
        echo json_encode(['error' => 'Job not found or access denied.']);
        exit();
    }

    // --- SCHRITT 5: EVENT IN DIE `job_transports`-TABELLE SCHREIBEN ---
    // Das SQL passt jetzt exakt zu deiner Tabellenstruktur.
    $sql = "INSERT INTO job_transports 
                (job_id, transport_type, source_name, destination_name, timestamp, game, amount, drv_steam_id) 
            VALUES 
                (?, ?, ?, ?, NOW(), ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    
    // Führe die Anweisung mit den Daten aus.
    $success = $stmt->execute([
        $job_id,
        $transport_type,
        $source_name,
        $destination_name,
		$game,
		$amount,
		$steam_id
    ]);

    // --- SCHRITT 6: ANTWORT SENDEN ---
    if ($success) {
        http_response_code(201); // 201 Created
        echo json_encode([
            'success' => true, 
            'message' => 'Transport event recorded successfully.',
            'transport_id' => $pdo->lastInsertId() // Gibt die ID des neuen Eintrags zurück
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to record transport event.']);
    }

} catch (PDOException $e) {
    // Fängt alle Datenbankfehler ab und gibt eine saubere Fehlermeldung aus.
    http_response_code(500);
    echo json_encode(['error' => 'Database error.', 'details' => $e->getMessage()]);
}
?>