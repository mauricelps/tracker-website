<?php
// job.php - Job detail page (pretty URL /job/<id>)
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/db.php';

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    http_response_code(404);
    echo "404 - Job Not Found";
    exit;
}
$job_id = (int)$_GET['id'];

// Fetch job and linked user
try {
    $sql = "SELECT j.*, u.id AS user_id, u.username, TIMESTAMPDIFF(SECOND, j.start_time, j.end_time) AS duration_seconds
            FROM jobs j
            LEFT JOIN users u ON j.driver_steam_id = u.steamId
            WHERE j.id = :jobId
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':jobId' => $job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    // transports
    $transport_stmt = $pdo->prepare("SELECT * FROM job_transports WHERE job_id = :jobId ORDER BY timestamp ASC");
    $transport_stmt->execute([':jobId' => $job_id]);
    $transports = $transport_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "<div style='color:#ffb3b3;padding:12px;border-radius:6px;background:#3a1b1b;'>Database error: " . htmlspecialchars($e->getMessage()) . "</div>";
    exit;
}

if (!$job) {
    http_response_code(404);
    echo "404 - Job Not Found";
    exit;
}

$page_title = 'Job #' . $job['id'];
include __DIR__ . '/includes/header.php';

function formatDuration(int $totalSeconds): string { return ($totalSeconds < 3600) ? gmdate('i:s', $totalSeconds) : gmdate('H:i:s', $totalSeconds); }
function translateGame(string $gameName): string { return match ($gameName) { "eut2" => "Euro Truck Simulator 2", "ats" => "American Truck Simulator", default => $gameName }; }

?>
<h1>Job Details: #<?php echo htmlspecialchars($job['id']); ?></h1>

<div style="max-width:900px;margin:20px auto;display:grid;grid-template-columns:1fr 1fr;gap:20px;">
    <div style="background:#252830;padding:18px;border-radius:8px;">
        <h3 style="color:#4CAF50;margin-top:0;">Driver Info</h3>
        <div style="display:flex;justify-content:space-between;padding:6px 0;"><strong>Driver</strong><span><?php echo htmlspecialchars($job['username'] ?? 'Unknown'); ?></span></div>
        <div style="display:flex;justify-content:space-between;padding:6px 0;"><strong>Steam ID</strong><span><?php echo htmlspecialchars($job['driver_steam_id']); ?></span></div>
        <?php if (!empty($job['user_id'])): ?>
            <div style="margin-top:8px;"><a href="/user/<?php echo (int)$job['user_id']; ?>" style="color:#87e0a4;">View driver profile</a></div>
        <?php endif; ?>
    </div>

    <div style="background:#252830;padding:18px;border-radius:8px;">
        <h3 style="color:#4CAF50;margin-top:0;">Job Info</h3>
        <div style="display:flex;justify-content:space-between;padding:6px 0;"><strong>Game</strong><span><?php echo htmlspecialchars(translateGame($job['game'] ?? '')); ?></span></div>
        <div style="display:flex;justify-content:space-between;padding:6px 0;"><strong>Status</strong><span><?php echo htmlspecialchars(ucfirst($job['status'] ?? '')); ?></span></div>
        <div style="display:flex;justify-content:space-between;padding:6px 0;"><strong>Market</strong><span><?php echo htmlspecialchars($job['market'] ?? ''); ?></span></div>
        <div style="display:flex;justify-content:space-between;padding:6px 0;"><strong>Income</strong><span>€ <?php echo is_null($job['income']) ? '—' : number_format($job['income']); ?></span></div>
        <div style="display:flex;justify-content:space-between;padding:6px 0;"><strong>XP</strong><span><?php echo is_null($job['xp']) ? '0' : htmlspecialchars($job['xp']); ?></span></div>
    </div>

    <div style="background:#252830;padding:18px;border-radius:8px;">
        <h3 style="color:#4CAF50;margin-top:0;">Cargo & Route</h3>
        <div style="display:flex;justify-content:space-between;padding:6px 0;"><strong>Cargo</strong><span><?php echo htmlspecialchars($job['cargo'] ?? '—'); ?></span></div>
        <div style="display:flex;justify-content:space-between;padding:6px 0;"><strong>Mass</strong><span><?php echo isset($job['cargoMass']) ? (($job['cargoMass'] / 1000) . ' t') : '—'; ?></span></div>
        <div style="display:flex;justify-content:space-between;padding:6px 0;"><strong>From</strong><span><?php echo htmlspecialchars(($job['source_city'] ?? '—') . ($job['source_company'] ? ' (' . $job['source_company'] . ')' : '')); ?></span></div>
        <div style="display:flex;justify-content:space-between;padding:6px 0;"><strong>To</strong><span><?php echo htmlspecialchars(($job['destination_city'] ?? '—') . ($job['destination_company'] ? ' (' . $job['destination_company'] . ')' : '')); ?></span></div>
        <div style="display:flex;justify-content:space-between;padding:6px 0;"><strong>Distance Driven</strong><span><?php echo is_null($job['driven_distance_km']) ? '—' : round($job['driven_distance_km'],2) . ' km'; ?></span></div>
        <div style="display:flex;justify-content:space-between;padding:6px 0;"><strong>Duration</strong><span><?php echo isset($job['duration_seconds']) ? formatDuration((int)$job['duration_seconds']) : '—'; ?></span></div>
    </div>

    <div style="background:#252830;padding:18px;border-radius:8px;">
        <h3 style="color:#4CAF50;margin-top:0;">Vehicle</h3>
        <div style="display:flex;justify-content:space-between;padding:6px 0;"><strong>Truck</strong><span><?php echo htmlspecialchars($job['truck'] ?? '—'); ?></span></div>
        <div style="display:flex;justify-content:space-between;padding:6px 0;"><strong>Truck Plate</strong><span><?php echo htmlspecialchars($job['truckLicensePlate'] ?? '—'); ?></span></div>
        <div style="display:flex;justify-content:space-between;padding:6px 0;"><strong>Trailer Plate</strong><span><?php echo htmlspecialchars($job['trailerLicensePlate'] ?? '—'); ?></span></div>
        <div style="display:flex;justify-content:space-between;padding:6px 0;"><strong>Max Speed</strong><span><?php echo is_null($job['maxSpeed']) ? '—' : round($job['maxSpeed']) . ' km/h'; ?></span></div>
    </div>

    <?php if (!empty($transports)): ?>
    <div style="grid-column:1 / -1;background:#252830;padding:18px;border-radius:8px;">
        <h3 style="color:#4CAF50;margin-top:0;">Transports Used (Ferry / Train)</h3>
        <?php foreach ($transports as $t): ?>
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #333;">
                <strong><?php echo htmlspecialchars(ucfirst($t['transport_type'])); ?></strong>
                <span><?php echo htmlspecialchars($t['source_name']) . ' → ' . htmlspecialchars($t['destination_name']); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>