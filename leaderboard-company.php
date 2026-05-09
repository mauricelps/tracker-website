<?php
require_once 'db.php';

try {
    // Levels laden
    $stmt = $pdo->query("SELECT id, level, points FROM tracker_levels ORDER BY points ASC");
    $levels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ========== ETS2 DATEN ==========
    // Top 5 Routen für ETS2
    $stmt = $pdo->query("
        SELECT 
            CONCAT(source_city, ' (', source_company, ') → ', destination_city, ' (', destination_company, ')') as route,
            COUNT(*) as count,
            COALESCE(SUM(driven_distance_km), 0) as total_km
        FROM tracker_jobs
        WHERE game = 'eut2'
        GROUP BY source_city, source_company, destination_city, destination_company
        ORDER BY count DESC
        LIMIT 5
    ");
    $ets2_routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top 5 Frachten für ETS2
    $stmt = $pdo->query("
        SELECT 
            cargo,
            COUNT(*) as count
        FROM tracker_jobs
        WHERE game = 'eut2' AND cargo IS NOT NULL
        GROUP BY cargo
        ORDER BY count DESC
        LIMIT 5
    ");
    $ets2_cargo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top 5 LKW für ETS2
    $stmt = $pdo->query("
        SELECT 
            truck,
            COUNT(*) as count
        FROM tracker_jobs
        WHERE game = 'eut2' AND truck IS NOT NULL
        GROUP BY truck
        ORDER BY count DESC
        LIMIT 5
    ");
    $ets2_trucks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top 5 Fahrer für ETS2
    $stmt = $pdo->query("
        SELECT 
            cu.name,
            COUNT(tj.id) as count,
            COALESCE(SUM(tj.driven_distance_km), 0) as total_km
        FROM tracker_jobs tj
        LEFT JOIN core_users cu ON tj.driver_steam_id = cu.id
        WHERE tj.game = 'eut2'
        GROUP BY tj.driver_steam_id, cu.name
        ORDER BY count DESC
        LIMIT 5
    ");
    $ets2_drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ========== ATS DATEN ==========
    // Top 5 Routen für ATS
    $stmt = $pdo->query("
        SELECT 
            CONCAT(source_city, ' (', source_company, ') → ', destination_city, ' (', destination_company, ')') as route,
            COUNT(*) as count,
            COALESCE(SUM(driven_distance_km), 0) as total_km
        FROM tracker_jobs
        WHERE game = 'ats'
        GROUP BY source_city, source_company, destination_city, destination_company
        ORDER BY count DESC
        LIMIT 5
    ");
    $ats_routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top 5 Frachten für ATS
    $stmt = $pdo->query("
        SELECT 
            cargo,
            COUNT(*) as count
        FROM tracker_jobs
        WHERE game = 'ats' AND cargo IS NOT NULL
        GROUP BY cargo
        ORDER BY count DESC
        LIMIT 5
    ");
    $ats_cargo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top 5 LKW für ATS
    $stmt = $pdo->query("
        SELECT 
            truck,
            COUNT(*) as count
        FROM tracker_jobs
        WHERE game = 'ats' AND truck IS NOT NULL
        GROUP BY truck
        ORDER BY count DESC
        LIMIT 5
    ");
    $ats_trucks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top 5 Fahrer für ATS
    $stmt = $pdo->query("
        SELECT 
            cu.name,
            COUNT(tj.id) as count,
            COALESCE(SUM(tj.driven_distance_km), 0) as total_km
        FROM tracker_jobs tj
        LEFT JOIN core_users cu ON tj.driver_steam_id = cu.id
        WHERE tj.game = 'ats'
        GROUP BY tj.driver_steam_id, cu.name
        ORDER BY count DESC
        LIMIT 5
    ");
    $ats_drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Datenbankfehler: " . $e->getMessage());
}

function getLevelByKm($km, $levels) {
    $current_level = 1;
    foreach ($levels as $level) {
        if ($km >= $level['points']) {
            $current_level = $level['level'];
        } else {
            break;
        }
    }
    return $current_level;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firmen Rangliste - Trucker Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .game-section {
            background-color: var(--bg-secondary);
            padding: 30px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            margin-bottom: 40px;
        }
        
        .game-section h2 {
            color: var(--accent);
            margin: 0 0 30px 0;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
            font-size: 24px;
        }
        
        .ranking-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        
        .ranking-card {
            background-color: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .ranking-card-header {
            background-color: var(--bg-tertiary);
            padding: 15px;
            border-bottom: 2px solid var(--border-color);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 14px;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
        }
        
        .ranking-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .ranking-list li {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .ranking-list li:last-child {
            border-bottom: none;
        }
        
        .ranking-list li:hover {
            background-color: var(--bg-tertiary);
        }
        
        .rank-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--bg-secondary);
            font-weight: bold;
            font-size: 16px;
            min-width: 40px;
        }
        
        .rank-badge.rank1 {
            background-color: #ffd700;
            color: #000;
        }
        
        .rank-badge.rank2 {
            background-color: #c0c0c0;
            color: #000;
        }
        
        .rank-badge.rank3 {
            background-color: #cd7f32;
            color: white;
        }
        
        .rank-info {
            flex: 1;
        }
        
        .rank-info .name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 3px;
        }
        
        .rank-info .count {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .rank-value {
            text-align: right;
            font-weight: 600;
            color: var(--accent);
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
    			<a href="/leaderboard/drivers">Fahrer Rangliste</a>
    			<a href="/leaderboard/company">Firmen Rangliste</a>
			</nav>
            <button class="theme-toggle" onclick="toggleTheme()">🌓 Theme wechseln</button>
        </div>
    </header>

    <div class="container">
        <h1>📊 Firmen Rangliste</h1>
        
        <!-- ETS2 -->
        <div class="game-section">
            <h2>🚛 Euro Truck Simulator 2</h2>
            
            <div class="ranking-grid">
                <!-- Routen -->
                <div class="ranking-card">
                    <div class="ranking-card-header">📍 Top Routen</div>
                    <ul class="ranking-list">
                        <?php $rank = 1; foreach ($ets2_routes as $route): ?>
                        <li>
                            <div class="rank-badge <?php echo 'rank' . $rank; ?>"><?php echo $rank; ?></div>
                            <div class="rank-info">
                                <div class="name"><?php echo htmlspecialchars(substr($route['route'], 0, 45)); ?></div>
                                <div class="count"><?php echo $route['count']; ?> Jobs | <?php echo number_format($route['total_km'], 0, ',', '.'); ?> km</div>
                            </div>
                        </li>
                        <?php $rank++; endforeach; ?>
                    </ul>
                </div>
                
                <!-- Frachten -->
                <div class="ranking-card">
                    <div class="ranking-card-header">📦 Top Frachten</div>
                    <ul class="ranking-list">
                        <?php $rank = 1; foreach ($ets2_cargo as $cargo): ?>
                        <li>
                            <div class="rank-badge <?php echo 'rank' . $rank; ?>"><?php echo $rank; ?></div>
                            <div class="rank-info">
                                <div class="name"><?php echo htmlspecialchars($cargo['cargo']); ?></div>
                                <div class="count"><?php echo $cargo['count']; ?> Jobs</div>
                            </div>
                        </li>
                        <?php $rank++; endforeach; ?>
                    </ul>
                </div>
                
                <!-- LKW -->
                <div class="ranking-card">
                    <div class="ranking-card-header">🚛 Top LKW</div>
                    <ul class="ranking-list">
                        <?php $rank = 1; foreach ($ets2_trucks as $truck): ?>
                        <li>
                            <div class="rank-badge <?php echo 'rank' . $rank; ?>"><?php echo $rank; ?></div>
                            <div class="rank-info">
                                <div class="name"><?php echo htmlspecialchars($truck['truck']); ?></div>
                                <div class="count"><?php echo $truck['count']; ?> Jobs</div>
                            </div>
                        </li>
                        <?php $rank++; endforeach; ?>
                    </ul>
                </div>
                
                <!-- Fahrer -->
                <div class="ranking-card">
                    <div class="ranking-card-header">👨‍💼 Top Fahrer</div>
                    <ul class="ranking-list">
                        <?php $rank = 1; foreach ($ets2_drivers as $driver): ?>
                        <li>
                            <div class="rank-badge <?php echo 'rank' . $rank; ?>"><?php echo $rank; ?></div>
                            <div class="rank-info">
                                <div class="name"><?php echo htmlspecialchars($driver['name']); ?></div>
                                <div class="count"><?php echo $driver['count']; ?> Jobs | <?php echo number_format($driver['total_km'], 0, ',', '.'); ?> km</div>
                            </div>
                        </li>
                        <?php $rank++; endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- ATS -->
        <div class="game-section">
            <h2>🚙 American Truck Simulator</h2>
            
            <div class="ranking-grid">
                <!-- Routen -->
                <div class="ranking-card">
                    <div class="ranking-card-header">📍 Top Routen</div>
                    <ul class="ranking-list">
                        <?php $rank = 1; foreach ($ats_routes as $route): ?>
                        <li>
                            <div class="rank-badge <?php echo 'rank' . $rank; ?>"><?php echo $rank; ?></div>
                            <div class="rank-info">
                                <div class="name"><?php echo htmlspecialchars(substr($route['route'], 0, 45)); ?></div>
                                <div class="count"><?php echo $route['count']; ?> Jobs | <?php echo number_format($route['total_km'], 0, ',', '.'); ?> km</div>
                            </div>
                        </li>
                        <?php $rank++; endforeach; ?>
                    </ul>
                </div>
                
                <!-- Frachten -->
                <div class="ranking-card">
                    <div class="ranking-card-header">📦 Top Frachten</div>
                    <ul class="ranking-list">
                        <?php $rank = 1; foreach ($ats_cargo as $cargo): ?>
                        <li>
                            <div class="rank-badge <?php echo 'rank' . $rank; ?>"><?php echo $rank; ?></div>
                            <div class="rank-info">
                                <div class="name"><?php echo htmlspecialchars($cargo['cargo']); ?></div>
                                <div class="count"><?php echo $cargo['count']; ?> Jobs</div>
                            </div>
                        </li>
                        <?php $rank++; endforeach; ?>
                    </ul>
                </div>
                
                <!-- LKW -->
                <div class="ranking-card">
                    <div class="ranking-card-header">🚙 Top LKW</div>
                    <ul class="ranking-list">
                        <?php $rank = 1; foreach ($ats_trucks as $truck): ?>
                        <li>
                            <div class="rank-badge <?php echo 'rank' . $rank; ?>"><?php echo $rank; ?></div>
                            <div class="rank-info">
                                <div class="name"><?php echo htmlspecialchars($truck['truck']); ?></div>
                                <div class="count"><?php echo $truck['count']; ?> Jobs</div>
                            </div>
                        </li>
                        <?php $rank++; endforeach; ?>
                    </ul>
                </div>
                
                <!-- Fahrer -->
                <div class="ranking-card">
                    <div class="ranking-card-header">👨‍💼 Top Fahrer</div>
                    <ul class="ranking-list">
                        <?php $rank = 1; foreach ($ats_drivers as $driver): ?>
                        <li>
                            <div class="rank-badge <?php echo 'rank' . $rank; ?>"><?php echo $rank; ?></div>
                            <div class="rank-info">
                                <div class="name"><?php echo htmlspecialchars($driver['name']); ?></div>
                                <div class="count"><?php echo $driver['count']; ?> Jobs | <?php echo number_format($driver['total_km'], 0, ',', '.'); ?> km</div>
                            </div>
                        </li>
                        <?php $rank++; endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleTheme() {
            const body = document.body;
            const currentTheme = body.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            body.setAttribute('data-theme', newTheme);
        }
    </script>
</body>
</html>
