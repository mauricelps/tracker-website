<?php
// api/start_job.php

require_once '../db.php';

header('Content-Type: application/json');

// Daten vom Request Body lesen
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Überprüfen, ob alle notwendigen Daten vorhanden sind
$required_fields = ['game', 'driverSteamId', 'truck', 'cargo', 'sourceCity', 'sourceCompany', 'destinationCity', 'destinationCompany', 'plannedDistanceKm', 'truckLicensePlate', 'truckLicensePlateCountry', 'trailerLicensePlate', 'trailerLicensePlateCountry', 'truckLicensePlateCountryId', 'trailerLicensePlateCountryId'];
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

$sql = "INSERT INTO jobs (game, driver_steam_id, truck, cargo, source_city, source_company, destination_city, destination_company, planned_distance_km, start_time, status, truckLicensePlate, truckLPlateCountry, trailerLicensePlate, trailerLPlateCountry, truckLPlateCountryId, trailerLPlateCountryId, truckLPlateCountryIdBase, trailerLPlateCountryIdBase) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'Started', ?, ?, ?, ?, ?, ?, ?, ?)";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $data['game'],
        $data['driverSteamId'],
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
		$data['trailerLicensePlateCountryId']
    ]);

    $jobId = $pdo->lastInsertId();

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
?>