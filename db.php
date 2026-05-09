<?php
// api/db.php

$host = '88.198.12.152';       // z.B. 'localhost' oder eine IP
$dbname = 'jobtracker';
$user = 'root';
$pass = 'LotusGC2024';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Im Fehlerfall eine generische Fehlermeldung senden
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed.']);
    exit;
}
?>