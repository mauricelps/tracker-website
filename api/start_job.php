<?php
// api/start_job.php

require_once '../db.php';

header('Content-Type: application/json');

// Daten vom Request Body lesen
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Überprüfen, ob alle notwendigen Daten vorhanden sind
$required_fields = ['game', 'userid', 'truck', 'cargo', 'sourceCity', 'sourceCompany', 'destinationCity', 'destinationCompany', 'plannedDistanceKm', 'truckLicensePlate', 'truckLicensePlateCountry', 'trailerLicensePlate', 'trailerLicensePlateCountry', 'truckLicensePlateCountryId', 'trailerLicensePlateCountryId', 'trailerBodyType'];
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

$sql = "INSERT INTO tracker_jobs (game, driver_steam_id, truck, cargo, source_city, source_company, destination_city, destination_company, planned_distance_km, start_time, status, truckLicensePlate, truckLPlateCountry, trailerLicensePlate, trailerLPlateCountry, truckLPlateCountryId, trailerLPlateCountryId, truckLPlateCountryIdBase, trailerLPlateCountryIdBase, trailer_type) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'Started', ?, ?, ?, ?, ?, ?, ?, ?, ?)";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $data['game'],
        $data['userid'],
        $data['truck'],
        $data['cargo'],
        $data['sourceCity'],
        $data['sourceCompany'],
        $data['destinationCity'],
        $data['destinationCompany'],
        $data['plannedDistanceKm'],
		$data['truckLicensePlate'],
		$data['truckLicensePlateCountry'],
		$data['trailerLicensePlate'],
		$data['trailerLicensePlateCountry'],
		getLicensePlateCode($data['truckLicensePlateCountryId']),
		getLicensePlateCode($data['trailerLicensePlateCountryId']),
		$data['truckLicensePlateCountryId'],
		$data['trailerLicensePlateCountryId'],
		$data['trailerBodyType']
    ]);

    $jobId = $pdo->lastInsertId();
	
	$ws_host = '88.198.12.152';   // <-- Ziel-IP hier anpassen
    $ws_port = 9996;          // <-- Ziel-Port hier anpassen
    $ws_path = '/';           // <-- WebSocket-Pfad, z.B. '/ws' falls benötigt

	$payload = json_encode(['jobId' => $jobId, 'token' => "SQh4VWjY8Py7Dl8QZU7ljJkmxul4sdr2V3k2ThCx0jWItF7Xwak27ocppFBA1zBz8oggyoHoGv1R4ODkABPfAH9WyRGVy7XjqE3JlhDI2YKbFYdvLg9Bjmhgge104hwi", 'mode' => "startJob"]);

    // Non-blocking send: Fehler werden geloggt, aber die API-Antwort bleibt erfolgreich
    try {
        $sent = ws_send($ws_host, (int)$ws_port, $ws_path, $payload, 2.0);
        if (!$sent) {
            error_log("finish_job.php: WebSocket notify failed for job {$job_id} to {$ws_host}:{$ws_port}");
        }
    } catch (Throwable $t) {
        error_log("finish_job.php: WebSocket exception: " . $t->getMessage());
    }

    echo json_encode(['jobId' => $jobId]);

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to start job.', 'details' => $e->getMessage()]);
}

function getLicensePlateCode(string $countryId): string {
    return match (strtolower($countryId)) {
        // Basisspiel
        'austria' => 'A',
        'belgium' => 'B',
        'czech' => 'CZ',
        'france' => 'F',
        'germany' => 'D',
        'hungary' => 'H',
        'italy' => 'I',
        'luxembourg' => 'L',
        'netherlands' => 'NL',
        'poland' => 'PL',
        'slovakia' => 'SK',
        'switzerland' => 'CH',
        'uk' => 'GB',
        
        // Going East! DLC
        'estonia' => 'EST',
        'latvia' => 'LV',
        'lithuania' => 'LT',
        
        // Scandinavia DLC
        'denmark' => 'DK',
        'norway' => 'N',
        'sweden' => 'S',
        
        // Road to the Black Sea DLC
        'bulgaria' => 'BG',
        'romania' => 'RO',
        'turkey' => 'TR', // Oft als 'turkiye' in neueren Spieldaten
        'turkiye' => 'TR',
        
        // Iberia DLC
        'spain' => 'E',
        'portugal' => 'P',
        
        // Beyond the Baltic Sea DLC
        'finland' => 'FIN',
        'russia' => 'RUS',
        
        // West Balkans DLC
        'albania' => 'AL',
        'bosnia' => 'BIH',
        'croatia' => 'HR',
        'kosovo' => 'RKS',
        'montenegro' => 'MNE',
        'north_macedonia' => 'NMK',
        'serbia' => 'SRB',
        'slovenia' => 'SLO',

        // Standard-Fallback: Wenn das Land nicht bekannt ist, gib einen Platzhalter zurück.
        default => '??',
    };
}

function ws_send(string $host, int $port, string $path, string $payload, float $timeout = 3.0): bool {
    $errNo = 0; $errStr = '';
    $fp = @stream_socket_client("tcp://{$host}:{$port}", $errNo, $errStr, $timeout);
    if (!$fp) {
        error_log("ws_send: connection failed: $errNo $errStr");
        return false;
    }

    // Create Sec-WebSocket-Key
    try {
        $key = base64_encode(random_bytes(16));
    } catch (Throwable $t) {
        $key = base64_encode(uniqid('', true));
    }

    $headers = "GET {$path} HTTP/1.1\r\n" .
               "Host: {$host}:{$port}\r\n" .
               "Upgrade: websocket\r\n" .
               "Connection: Upgrade\r\n" .
               "Sec-WebSocket-Key: {$key}\r\n" .
               "Sec-WebSocket-Version: 13\r\n" .
               "\r\n";

    stream_set_timeout($fp, (int)$timeout);
    fwrite($fp, $headers);

    // Read response (simple check, don't require full validation)
    $response = fread($fp, 1024);
    if ($response === false || stripos($response, '101') === false) {
        // still proceed in some environments, but log
        error_log("ws_send: handshake response invalid or missing for {$host}:{$port} - resp: " . substr($response ?? '', 0, 200));
        // optionally return false here if strict
    }

    // Build a masked text frame (client MUST mask)
    $data = (string)$payload;
    $len = strlen($data);
    $frameHead = '';
    $b1 = 0x81; // FIN + text opcode
    $frameHead .= chr($b1);

    if ($len <= 125) {
        $frameHead .= chr(0x80 | $len); // mask bit set + length
    } elseif ($len <= 0xFFFF) {
        $frameHead .= chr(0x80 | 126) . pack('n', $len);
    } else {
        // 64bit length
        $frameHead .= chr(0x80 | 127) . pack('J', $len);
    }

    // generate mask
    try {
        $mask = random_bytes(4);
    } catch (Throwable $t) {
        $mask = substr(md5(uniqid((string)microtime(true), true), true), 0, 4);
    }

    $frame = $frameHead . $mask;

    // mask payload
    $maskedPayload = '';
    for ($i = 0; $i < $len; $i++) {
        $maskedPayload .= $data[$i] ^ $mask[$i % 4];
    }

    fwrite($fp, $frame . $maskedPayload);

    // give server a short time to receive
    usleep(150000);

    fclose($fp);
    return true;
}
?>