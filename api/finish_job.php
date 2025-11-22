<?php

header('Content-Type: application/json');

// --- SCHRITT 1: DATEN AUSLESEN (unverändert) ---
$json_str = file_get_contents('php://input');
file_put_contents('debug_log.txt', "--- FINISH JOB REQUEST ---\n" . $json_str . "\n\n", FILE_APPEND);
$data = json_decode($json_str, true);

if ($data === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON format.']);
    exit();
}

// --- SCHRITT 2: DATEN VALIDIEREN (unverändert) ---
$required_fields = ['jobid', 'steamid', 'status', 'driven_km', 'market', 'income', 'wearTruckCabin', 'wearTruckChassis', 'wearTruckTransmission', 'wearTruckWheels', 'wearTrailerChassis', 'wearTrailerWheels', 'wearTrailerBody', 'cargoDamage', 'cargoMass', 'wearTruckEngine', 'maxspeed', 'xp', 'autoLoad', 'autoPark', 'usedDiesel'];
foreach ($required_fields as $field) {
    if (!array_key_exists($field, $data)) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

// --- Daten in Variablen speichern (unverändert) ---
$job_id = $data['jobid'];
$steam_id = $data['steamid'];
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

// --- Berechnungen (unverändert) ---
$wearTruck = [$wearTruckCabin, $wearTruckChassis, $wearTruckTransmission, $wearTruckWheels, $wearTruckEngine];
$avgTruckWear = count($wearTruck) > 0 ? array_sum($wearTruck) / count($wearTruck) : 0;
$wearTrailer = [$wearTrailerChassis, $wearTrailerWheels, $wearTrailerBody];
$avgTrailerWear = count($wearTrailer) > 0 ? array_sum($wearTrailer) / count($wearTrailer) : 0;

// --- SCHRITT 3: DATENBANKVERBINDUNG HERSTELLEN (unverändert) ---
require_once '../db.php'; // Stellt die $pdo Variable bereit

try {
    // --- SCHRITT 4: JOB AKTUALISIEREN (JETZT KORREKTER PDO-CODE) ---
    $sql = "UPDATE jobs SET 
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
				usedDiesel = ?
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
        $job_id,
        $steam_id
    ]);

    // KORREKTUR: Die Anzahl der betroffenen Zeilen wird mit rowCount() ermittelt.
    if ($success && $stmt->rowCount() > 0) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Job ' . $job_id . ' updated successfully.']);

        // --- DATEN FÜR WEBHOOK ABFRAGEN (JETZT KORREKTER PDO-CODE) ---
        $query_job_data = $pdo->prepare("
            SELECT 
                j.*, u.username, 
                TIMESTAMPDIFF(SECOND, j.start_time, j.end_time) as duration_seconds
            FROM jobs j
            JOIN users u ON j.driver_steam_id = u.steamId
            WHERE j.id = ?
        ");
        
        $query_job_data->execute([$job_id]);
        // KORREKTUR: Das Ergebnis wird mit fetch() geholt, nicht mit get_result().
        $job_data = $query_job_data->fetch(PDO::FETCH_ASSOC);

        if ($job_data) {
            // --- DISCORD WEBHOOK LOGIK (unverändert, sollte jetzt funktionieren) ---
            $whurl = "https://discord.com/api/webhooks/1434216294810648838/MjMc_m-nzLG4P_4IXJLFMkdV0wvyfdNPloYpTHH70oKrQIWeJmKdG4AEt6ioFTddMMlF";
            $title = "Job " . ucfirst($job_status);

            $hookObj = json_encode([
                "username" => "JobTracker", "avatar_url" => "https://gtracker.kitsoft.at/data/avatar.png",
                "embeds" => [
                    [
                        "title" => $title, "type" => "rich", "color" => hexdec("FFEF00"),
                        "author" => ["name" => $job_data['username']],
                        "description" => "**From - To** \n " . $job_data['source_city'] . " (" . $job_data['source_company'] . ") - " . $job_data['destination_city'] . " (" . $job_data['destination_company'] . ")",
                        "fields" => [
                            ["name" => "Game", "value" => translateGame($job_data['game']), "inline" => true],
                            ["name" => "Cargo", "value" => $job_data['cargo'] . " (" . ($job_data['cargoMass'] / 1000) . " t)", "inline" => false],
                            ["name" => "Experience", "value" => $xp . " XP", "inline" => true],
							["name" => "Distance", "value" => round($driven_km, 2) . " km", "inline" => true],
                            ["name" => "IRL Time Duration", "value" => formatDuration($job_data['duration_seconds']), "inline" => true],
                            ["name" => "Income", "value" => number_format($income) . " €", "inline" => true],
                            ["name" => "Damage", "value" => "Truck: " . number_format($avgTruckWear * 100, 2) . " % \nTrailer: " . number_format($avgTrailerWear * 100, 2) . " % \nCargo: " . number_format($cargoDamage, 2) . " %", "inline" => true],
                            ["name" => "Truck & Trailer", "value" => "Truck: " . $job_data['truck'] . ", " . $job_data['truckLPlateCountryId'] . " " . $job_data['truckLicensePlate'] . "\nTrailer: " . translateTrailerBody($job_data['trailerBodyType']) . ", " .$job_data['trailerLPlateCountryId'] . " " . $job_data['trailerLicensePlate'], "inline" => true]
                        ]
                    ]
                ]
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $ch = curl_init();
            curl_setopt_array($ch, [CURLOPT_URL => $whurl, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $hookObj, CURLOPT_HTTPHEADER => ["Content-Type: application/json"]]);
            curl_exec($ch);
            curl_close($ch);
        }

    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Job not found or user does not match.']);
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
?>