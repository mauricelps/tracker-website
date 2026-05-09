<?php
require_once 'db.php';

try {
    $stmt = $pdo->query("
        SELECT 
            cu.id as driver_id,
            cu.name as driver_name,
            tl.cargoName,
            tl.sourceCity,
            tl.sourceCompany,
            tl.targetCity,
            tl.targetCompany,
            tl.plannedDistance,
            tl.drivenDistance,
            tl.drivenDistanceSession,
            tl.remainingDistance,
            tl.dieselValue,
            tl.adblueValue,
            tl.truck,
            tl.speed,
            tl.speedLimit,
            tl.game,
            tl.gameUnits,
            tl.isActive
        FROM tracker_livestatus tl
        LEFT JOIN core_users cu ON tl.userid = cu.id
        WHERE tl.isActive = 1
        ORDER BY cu.name ASC
    ");
    $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Datenbankfehler: " . $e->getMessage());
}

function translateGame($game) {
    $games = [
        'eut2' => 'Euro Truck Simulator 2',
        'ats' => 'American Truck Simulator'
    ];
    return $games[strtolower($game)] ?? $game;
}

function hasActiveJob($driver) {
    return !empty($driver['cargoName']) || !empty($driver['sourceCity']) || !empty($driver['targetCity']);
}

function formatValue($value) {
    if ($value === null || $value === '') {
        return '0';
    }
    return $value;
}

function parseGameUnits($gameUnits) {
    if (empty($gameUnits)) {
        return ['kmh', 'km', 'liters', 'celsius', 'kg']; // Default metric
    }
    return explode(';', $gameUnits);
}

function getUnits($gameUnits) {
    $units = parseGameUnits($gameUnits);
    return [
        'speed' => $units[0],      // kmh oder mph
        'distance' => $units[1],   // km oder mi
        'fuel' => $units[2],       // liters oder gal
        'temp' => $units[3],       // celsius oder fahrenheit
        'weight' => $units[4]      // kg oder lb
    ];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Status - Trucker Dashboard</title>
    <link rel="stylesheet" href="/style.css">
    <style>
        .live-wrapper {
            max-width: 1400px;
            margin: 0 auto;
        }

        .live-info {
            margin-bottom: 20px;
            padding: 15px;
            background-color: var(--bg-secondary);
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .live-info h1 {
            margin: 0 0 5px 0;
            font-size: 24px;
        }

        .live-info p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 14px;
        }

        .drivers-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .driver-card {
            background-color: var(--bg-secondary);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.3s;
        }

        .driver-card:hover {
            border-color: var(--accent);
            box-shadow: 0 4px 12px rgba(74, 158, 255, 0.2);
        }

        .driver-bar {
            padding: 18px 25px;
            cursor: pointer;
            user-select: none;
            display: flex;
            align-items: center;
            gap: 20px;
            background-color: var(--bg-secondary);
            transition: background-color 0.2s;
        }

        .driver-bar:hover {
            background-color: var(--bg-tertiary);
        }

        .driver-card.expanded .driver-bar {
            background-color: var(--bg-tertiary);
        }

        .driver-name {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            min-width: 200px;
        }

        .driver-status-line {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 16px;
        }

        .driver-status-line.freeroaming {
            color: #ffc107;
            font-style: italic;
            font-weight: 600;
        }

        .driver-status-line .city {
            color: var(--accent);
            font-weight: 700;
        }

        .driver-status-line .arrow {
            color: var(--text-secondary);
            font-size: 20px;
        }

        .driver-status-line .remaining {
            margin-left: auto;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 15px;
            background-color: var(--bg-primary);
            padding: 5px 12px;
            border-radius: 15px;
        }

        .expand-icon {
            color: var(--text-secondary);
            transition: transform 0.3s;
            font-size: 18px;
        }

        .driver-card.expanded .expand-icon {
            transform: rotate(180deg);
        }

        .driver-details {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease-out;
            background-color: var(--bg-primary);
        }

        .driver-card.expanded .driver-details {
            max-height: 2000px;
            transition: max-height 0.6s ease-in;
        }

        .driver-details-content {
            padding: 25px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
        }

        .detail-section {
            background-color: var(--bg-secondary);
            padding: 20px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .detail-section h3 {
            margin: 0 0 15px 0;
            font-size: 14px;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .detail-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .detail-row:first-child {
            padding-top: 0;
        }

        .detail-label {
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 600;
        }

        .detail-value {
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 600;
            text-align: right;
        }

        .game-badge {
            display: inline-block;
            padding: 6px 12px;
            background: linear-gradient(135deg, var(--accent), #6bb0ff);
            color: white;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 15px;
        }

        /* Geschwindigkeitsbalken */
        .speed-section {
            background-color: var(--bg-secondary);
            padding: 20px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .speed-display {
            display: flex;
            align-items: baseline;
            gap: 8px;
            margin-bottom: 12px;
        }

        .speed-current {
            font-size: 42px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1;
        }

        .speed-unit {
            font-size: 16px;
            color: var(--text-secondary);
        }

        .speed-limit-text {
            margin-left: auto;
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 600;
        }

        .speed-bar {
            height: 30px;
            background-color: var(--bg-primary);
            border-radius: 15px;
            overflow: hidden;
            position: relative;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .speed-fill {
            height: 100%;
            transition: width 0.3s, background 0.3s;
            border-radius: 15px;
        }

        .speed-fill.green {
            background: linear-gradient(90deg, #28a745, #20c997);
        }

        .speed-fill.yellow {
            background: linear-gradient(90deg, #ffc107, #ffdb4d);
        }

        .speed-fill.red {
            background: linear-gradient(90deg, #dc3545, #ff6b6b);
        }

        /* Fuel Bars */
        .fuel-item {
            margin-bottom: 15px;
        }

        .fuel-item:last-child {
            margin-bottom: 0;
        }

        .fuel-label {
            font-size: 12px;
            color: var(--text-secondary);
            font-weight: 700;
            margin-bottom: 8px;
            display: block;
            text-transform: uppercase;
        }

        .fuel-bar {
            height: 24px;
            background-color: var(--bg-primary);
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .fuel-fill {
            height: 100%;
            transition: width 0.3s;
        }

        .fuel-fill.diesel {
            background: linear-gradient(90deg, #FFB84D, #FFA500);
        }

        .fuel-fill.adblue {
            background: linear-gradient(90deg, #4D9FFF, #1E90FF);
        }

        .fuel-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 12px;
            font-weight: 700;
            color: var(--text-primary);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
        }

        /* Progress Bar */
        .progress-section {
            margin-top: 20px;
        }

        .progress-label {
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 8px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .progress-bar {
            height: 35px;
            background-color: var(--bg-primary);
            border-radius: 17px;
            overflow: hidden;
            position: relative;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent), #6bb0ff);
            transition: width 0.5s;
        }

        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 14px;
            font-weight: 700;
            color: var(--text-primary);
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.5);
        }

        .no-drivers {
            padding: 60px 20px;
            text-align: center;
            background-color: var(--bg-secondary);
            border-radius: 10px;
            border: 2px dashed var(--border-color);
        }

        .no-drivers p {
            font-size: 18px;
            color: var(--text-secondary);
            margin: 0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .driver-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .driver-name {
                min-width: auto;
                width: 100%;
            }

            .driver-status-line {
                width: 100%;
                flex-wrap: wrap;
            }

            .expand-icon {
                position: absolute;
                top: 18px;
                right: 25px;
            }

            .driver-details-content {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body data-theme="dark">
    <header>
        <div class="container">
            <nav>
                <a href="/">Home</a>
                <a href="/drivers">Fahrer</a>
                <a href="/jobs">Jobs</a>
                <a href="/live">Live Status</a>
            </nav>
            <button class="theme-toggle" onclick="toggleTheme()">🌓 Theme wechseln</button>
        </div>
    </header>

    <div class="container">
        <div class="live-wrapper">
            <div class="live-info">
                <h1>🔴 Live Status</h1>
                <p>Automatic Refresh every 2.5 seconds | Active Drivers: <strong><?php echo count($drivers); ?></strong></p>
            </div>

            <?php if (empty($drivers)): ?>
                <div class="no-drivers">
                    <p>No driver online.</p>
                </div>
            <?php else: ?>
                <div class="drivers-list">
                    <?php foreach ($drivers as $driver): ?>
                        <?php 
                        $hasJob = hasActiveJob($driver);
                        $driverId = $driver['driver_id'];
                        $units = getUnits($driver['gameUnits']);
                        ?>
                        <div class="driver-card" id="driver-<?php echo $driverId; ?>" data-driver-id="<?php echo $driverId; ?>">
                            <div class="driver-bar" onclick="toggleDriver(<?php echo $driverId; ?>)">
                                <div class="driver-name"><?php echo htmlspecialchars($driver['driver_name'] ?? 'Unknown'); ?></div>
                                
                                <div class="driver-status-line <?php echo !$hasJob ? 'freeroaming' : ''; ?>">
                                    <?php if ($hasJob): ?>
                                        <span class="city"><?php echo htmlspecialchars($driver['sourceCity']); ?></span>
                                        <span class="arrow">→</span>
                                        <span class="city"><?php echo htmlspecialchars($driver['targetCity']); ?></span>
                                        <span class="remaining">
                                            <?php echo number_format(formatValue($driver['remainingDistance']), 0, ',', '.'); ?> <?php echo $units['distance']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span>🚗 Freeroaming</span>
                                    <?php endif; ?>
                                </div>

                                <span class="expand-icon">▼</span>
                            </div>

                            <div class="driver-details">
                                <div class="driver-details-content">
                                    <!-- Geschwindigkeit Section -->
                                    <div class="speed-section">
                                        <span class="game-badge"><?php echo htmlspecialchars(translateGame($driver['game'])); ?></span>
                                        
                                        <h3>⚡ Speed</h3>
                                        <div class="speed-display">
                                            <span class="speed-current"><?php echo formatValue($driver['speed']); ?></span>
                                            <span class="speed-unit"><?php echo $units['speed']; ?></span>
                                            <?php if ($driver['speedLimit']): ?>
                                                <span class="speed-limit-text">
                                                    Limit: <?php echo $driver['speedLimit']; ?> <?php echo $units['speed']; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php
                                        $speed = (int)formatValue($driver['speed']);
                                        $limit = (int)formatValue($driver['speedLimit']);
                                        $tolerance = $limit + 10;
                                        
                                        if ($limit > 0) {
                                            $maxSpeed = max($tolerance, $speed);
                                            $percentage = ($speed / $maxSpeed) * 100;
                                            
                                            if ($speed <= $limit) {
                                                $color = 'green';
                                            } elseif ($speed <= $tolerance) {
                                                $color = 'yellow';
                                            } else {
                                                $color = 'red';
                                            }
                                        } else {
                                            $percentage = 0;
                                            $color = 'green';
                                        }
                                        ?>
                                        <div class="speed-bar">
                                            <div class="speed-fill <?php echo $color; ?>" style="width: <?php echo min(100, $percentage); ?>%"></div>
                                        </div>
                                    </div>

                                    <!-- Job Details (nur wenn Job aktiv) -->
                                    <?php if ($hasJob): ?>
                                    <div class="detail-section">
                                        <h3>📦 Job Details</h3>
                                        <div class="detail-row">
                                            <span class="detail-label">Cargo</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($driver['cargoName'] ?: '-'); ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Truck</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($driver['truck'] ?: '-'); ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Source</span>
                                            <span class="detail-value">
                                                <?php echo htmlspecialchars($driver['sourceCity'] ?: '-'); ?> - 
                                                <?php echo htmlspecialchars($driver['sourceCompany'] ?: '-'); ?>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Destination</span>
                                            <span class="detail-value">
                                                <?php echo htmlspecialchars($driver['targetCity'] ?: '-'); ?> - 
                                                <?php echo htmlspecialchars($driver['targetCompany'] ?: '-'); ?>
                                            </span>
                                        </div>

                                        <!-- Progress -->
                                        <?php if ($driver['plannedDistance'] && $driver['drivenDistance']): ?>
                                        <div class="progress-section">
                                            <div class="progress-label">Job Progress</div>
                                            <?php 
                                            $progress = ($driver['drivenDistance'] / $driver['plannedDistance']) * 100;
                                            $progress = min(100, max(0, $progress));
                                            ?>
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                                                <span class="progress-text"><?php echo round($progress); ?>%</span>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="detail-section">
                                        <h3>📏 Distances</h3>
                                        <div class="detail-row">
                                            <span class="detail-label">Planned</span>
                                            <span class="detail-value">
                                                <?php echo number_format(formatValue($driver['plannedDistance']), 0, ',', '.'); ?> <?php echo $units['distance']; ?>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Driven</span>
                                            <span class="detail-value">
                                                <?php echo number_format(formatValue($driver['drivenDistance']), 0, ',', '.'); ?> <?php echo $units['distance']; ?>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Remaining</span>
                                            <span class="detail-value">
                                                <?php echo number_format(formatValue($driver['remainingDistance']), 0, ',', '.'); ?> <?php echo $units['distance']; ?>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Session</span>
                                            <span class="detail-value">
                                                <?php echo number_format(formatValue($driver['drivenDistanceSession']), 0, ',', '.'); ?> <?php echo $units['distance']; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <!-- Nur Session KM bei Freeroaming -->
                                    <div class="detail-section">
                                        <h3>📏 Session</h3>
                                        <div class="detail-row">
                                            <span class="detail-label">Driven</span>
                                            <span class="detail-value">
                                                <?php echo number_format(formatValue($driver['drivenDistanceSession']), 0, ',', '.'); ?> <?php echo $units['distance']; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Fuel -->
                                    <div class="detail-section">
                                        <h3>⛽ Fuel</h3>
                                        <div class="fuel-item">
                                            <span class="fuel-label">Diesel</span>
                                            <div class="fuel-bar">
                                                <div class="fuel-fill diesel" style="width: <?php echo formatValue($driver['dieselValue']); ?>%"></div>
                                                <span class="fuel-text"><?php echo formatValue($driver['dieselValue']); ?>%</span>
                                            </div>
                                        </div>
                                        <div class="fuel-item">
                                            <span class="fuel-label">AdBlue</span>
                                            <div class="fuel-bar">
                                                <div class="fuel-fill adblue" style="width: <?php echo formatValue($driver['adblueValue']); ?>%"></div>
                                                <span class="fuel-text"><?php echo formatValue($driver['adblueValue']); ?>%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleTheme() {
            const body = document.body;
            const currentTheme = body.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            body.setAttribute('data-theme', newTheme);
        }

        function toggleDriver(driverId) {
            const driverCard = document.getElementById('driver-' + driverId);
            
            if (driverCard.classList.contains('expanded')) {
                driverCard.classList.remove('expanded');
                removeFromExpanded(driverId);
            } else {
                driverCard.classList.add('expanded');
                addToExpanded(driverId);
            }
        }

        function getExpandedDrivers() {
            const expanded = localStorage.getItem('expandedDrivers');
            return expanded ? JSON.parse(expanded) : [];
        }

        function addToExpanded(driverId) {
            const expanded = getExpandedDrivers();
            if (!expanded.includes(driverId)) {
                expanded.push(driverId);
                localStorage.setItem('expandedDrivers', JSON.stringify(expanded));
            }
        }

        function removeFromExpanded(driverId) {
            let expanded = getExpandedDrivers();
            expanded = expanded.filter(id => id !== driverId);
            localStorage.setItem('expandedDrivers', JSON.stringify(expanded));
        }

        function restoreExpandedState() {
            const expanded = getExpandedDrivers();
            expanded.forEach(driverId => {
                const driverCard = document.getElementById('driver-' + driverId);
                if (driverCard) {
                    driverCard.classList.add('expanded');
                }
            });
        }

        setTimeout(function() {
            location.reload();
        }, 2500);

        document.addEventListener('DOMContentLoaded', restoreExpandedState);
    </script>
</body>
</html>