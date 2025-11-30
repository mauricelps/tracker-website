<?php
header('Content-Type: application/json');

$json_str = file_get_contents('php://input');
$data = json_decode($json_str, true);

if ($data === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON format.']);
    exit();
}

// --- SCHRITT 2: DATEN VALIDIEREN (unverändert) ---
$required_fields = ['jobid', 'userid', 'status', 'driven_km', 'market', 'income', 'wearTruckCabin', 'wearTruckChassis', 'wearTruckTransmission', 'wearTruckWheels', 'wearTrailerChassis', 'wearTrailerWheels', 'wearTrailerBody', 'cargoDamage', 'cargoMass', 'wearTruckEngine', 'maxspeed', 'xp', 'autoLoad', 'autoPark', 'usedDiesel', 'usedAdblue'];
foreach ($required_fields as $field) {
    if (!array_key_exists($field, $data)) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

// --- Daten in Variablen speichern (unverändert) ---
$job_id = $data['jobid'];
$steam_id = $data['userid'];
$job_status = $data['status'];
$driven_km = $data['driven_km'];
$income = $data['income'];
$market = $data['market'];
$wearTruckCabin = $data['wearTruckCabin'];
$wearTruckChassis = $data['wearTruckChassis'];
$wearTruckTransmission = $data['wearTruckTransmission'];
$wearTruckWheels = $data['wearTruckWheels'];
$wearTruckEngine = $data['wearTruckEngine'];
$wearTrailerChassis = $data['wearTrailerChassis'];
$wearTrailerWheels = $data['wearTrailerWheels'];
$wearTrailerBody = $data['wearTrailerBody'];
$cargoDamage = $data['cargoDamage'];
$cargoMass = $data['cargoMass'];
$maxSpeed = $data['maxspeed'];
$xp = $data['xp'];
$autoPark = $data['autoPark'];
$autoLoad = $data['autoLoad'];
$usedDiesel = $data['usedDiesel'];
$usedAdblue = $data['usedAdblue'];

// --- Berechnungen (unverändert) ---
$wearTruck = [$wearTruckCabin, $wearTruckChassis, $wearTruckTransmission, $wearTruckWheels, $wearTruckEngine];
$avgTruckWear = count($wearTruck) > 0 ? array_sum($wearTruck) / count($wearTruck) : 0;
$wearTrailer = [$wearTrailerChassis, $wearTrailerWheels, $wearTrailerBody];
$avgTrailerWear = count($wearTrailer) > 0 ? array_sum($wearTrailer) / count($wearTrailer) : 0;

// --- SCHRITT 3: DATENBANKVERBINDUNG HERSTELLEN (unverändert) ---
require_once '../db.php'; // Stellt die $pdo Variable bereit

try {
    // --- SCHRITT 4: JOB AKTUALISIEREN (JETZT KORREKTER PDO-CODE) ---
    $sql = "UPDATE tracker_jobs SET 
                status = ?, 
                driven_distance_km = ?, 
                end_time = NOW(),
                income = ?,
                market = ?,
                wear_trailer_body = ?,
                wear_trailer_chassis = ?,
                wear_trailer_wheels = ?,
                wear_truck_cabin = ?,
                wear_truck_chassis = ?,
                wear_truck_engine = ?,
                wear_truck_transmission = ?,
                wear_truck_wheels = ?,
                cargoDamage = ?,
                cargoMass = ?,
                maxSpeed = ?,
                xp = ?,
				autoParkUsed = ?,
				autoLoadUsed = ?,
				usedDiesel = ?,
				usedAdblue = ?
            WHERE 
                id = ? AND driver_steam_id = ?";

    $stmt = $pdo->prepare($sql);

    $success = $stmt->execute([
        $job_status,
        $driven_km,
        $income,
        $market,
        $wearTrailerBody,
        $wearTrailerChassis,
        $wearTrailerWheels,
        $wearTruckCabin,
        $wearTruckChassis,
        $wearTruckEngine,
        $wearTruckTransmission,
        $wearTruckWheels,
        $cargoDamage,
        $cargoMass,
        $maxSpeed,
        $xp,               // <- xp jetzt gesetzt
		$autoPark,
		$autoLoad,
		$usedDiesel,
		$usedAdblue,
        $job_id,
        $steam_id
    ]);

    // KORREKTUR: Die Anzahl der betroffenen Zeilen wird mit rowCount() ermittelt.
    if ($success && $stmt->rowCount() > 0) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Job ' . $job_id . ' updated successfully.']);

        // --- WEBSOCKET-NOTIFY: jobId an konfigurierbare IP:PORT senden (neu) ---
        // Konfiguration anpassen: Zielhost, Port und Pfad (falls nötig)
        $ws_host = '88.198.12.152';   // <-- Ziel-IP hier anpassen
        $ws_port = 9996;          // <-- Ziel-Port hier anpassen
        $ws_path = '/';           // <-- WebSocket-Pfad, z.B. '/ws' falls benötigt

        $payload = json_encode(['jobId' => $job_id]);

        // Non-blocking send: Fehler werden geloggt, aber die API-Antwort bleibt erfolgreich
        try {
            $sent = ws_send($ws_host, (int)$ws_port, $ws_path, $payload, 2.0);
            if (!$sent) {
                error_log("finish_job.php: WebSocket notify failed for job {$job_id} to {$ws_host}:{$ws_port}");
            }
        } catch (Throwable $t) {
            error_log("finish_job.php: WebSocket exception: " . $t->getMessage());
        }
    }
} catch (PDOException $e) {
    // Fängt alle PDO-bezogenen Fehler ab und gibt eine saubere Fehlermeldung aus.
    http_response_code(500);
    echo json_encode(['error' => 'Database error.', 'details' => $e->getMessage()]);
}

// --- Funktionsdefinitionen (unverändert) ---
function formatDuration(int $totalSeconds): string {
    return ($totalSeconds < 3600) ? gmdate('i:s', $totalSeconds) : gmdate('H:i:s', $totalSeconds);
}

function translateGame(string $gameName): string {
    return match ($gameName) {
        "eut2" => "Euro Truck Simulator 2",
        "ats" => "American Truck Simulator",
        default => $gameName,
    };
}
				
function translateTrailerBody(string $trlbdy): string {
	return match($trlbdy) {
		"flatbed" => "Flatbed",
		"container" => "Container",
		"curtainsider" => "Curtainsider",
		default => $trlbdy . "_def"
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