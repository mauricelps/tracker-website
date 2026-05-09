<?php
require_once 'db.php';

$driver_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$driver_id) {
    header('Location: /drivers');
    exit;
}

try {
    // Levels laden
    $stmt = $pdo->query("SELECT id, level, points FROM tracker_levels ORDER BY points ASC");
    $levels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fahrer Details
    $stmt = $pdo->prepare("SELECT id, name FROM core_users WHERE id = :id");
    $stmt->execute(['id' => $driver_id]);
    $driver = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$driver) {
        header('Location: /drivers');
        exit;
    }
    
    // Fahrer Gesamtstatistiken
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(id) as total_jobs,
            COALESCE(SUM(xp), 0) as total_xp,
            COALESCE(SUM(driven_distance_km), 0) as total_km,
            COALESCE(SUM(income), 0) as total_income,
            COALESCE(SUM(fuel_consumption), 0) as total_fuel
        FROM tracker_jobs
        WHERE driver_steam_id = :id
    ");
    $stmt->execute(['id' => $driver_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Letzte 5 Jobs
    $stmt = $pdo->prepare("
        SELECT 
            id,
            game,
            truck,
            cargo,
            source_city,
            destination_city,
            driven_distance_km,
            xp,
            income,
            start_time
        FROM tracker_jobs
        WHERE driver_steam_id = :id
        ORDER BY id DESC
        LIMIT 5
    ");
    $stmt->execute(['id' => $driver_id]);
    $recent_jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistiken der letzten 30 Tage (täglich aggregiert)
    $stmt = $pdo->prepare("
        SELECT 
            DATE(start_time) as day,
            COUNT(id) as jobs_count,
            COALESCE(SUM(driven_distance_km), 0) as km_day,
            COALESCE(SUM(xp), 0) as xp_day,
            COALESCE(SUM(income), 0) as income_day,
            COALESCE(SUM(fuel_consumption), 0) as fuel_day
        FROM tracker_jobs
        WHERE driver_steam_id = :id AND start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(start_time)
        ORDER BY day ASC
    ");
    $stmt->execute(['id' => $driver_id]);
    $daily_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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

function translateGame($game) {
    $games = [
        'eut2' => 'Euro Truck Simulator 2',
        'ats' => 'American Truck Simulator'
    ];
    return $games[strtolower($game)] ?? $game;
}

// Daten für Chart.js vorbereiten
$chart_dates = [];
$chart_jobs = [];
$chart_km = [];
$chart_xp = [];
$chart_income = [];
$chart_fuel = [];

foreach ($daily_stats as $day) {
    $chart_dates[] = $day['day'];
    $chart_jobs[] = (int)$day['jobs_count'];
    $chart_km[] = (int)$day['km_day'];
    $chart_xp[] = (int)$day['xp_day'];
    $chart_income[] = (int)$day['income_day'];
    $chart_fuel[] = (float)$day['fuel_day'];
}

$chart_dates_json = json_encode($chart_dates);
$chart_jobs_json = json_encode($chart_jobs);
$chart_km_json = json_encode($chart_km);
$chart_xp_json = json_encode($chart_xp);
$chart_income_json = json_encode($chart_income);
$chart_fuel_json = json_encode($chart_fuel);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fahrer: <?php echo htmlspecialchars($driver['name']); ?> - Trucker Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .driver-header {
            background-color: var(--bg-secondary);
            padding: 30px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .driver-header-info h1 {
            margin: 0 0 10px 0;
            font-size: 32px;
        }
        
        .level-badge {
            display: inline-block;
            background-color: var(--accent);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 16px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: var(--bg-secondary);
            padding: 20px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            text-align: center;
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: var(--text-secondary);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-card .value {
            font-size: 28px;
            font-weight: bold;
            color: var(--accent);
        }
        
        .chart-container {
            background-color: var(--bg-secondary);
            padding: 20px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            margin-bottom: 30px;
            position: relative;
            height: 300px;
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .section {
            background-color: var(--bg-secondary);
            padding: 25px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            margin-bottom: 30px;
        }
        
        .section h2 {
            margin: 0 0 20px 0;
            color: var(--accent);
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
            font-size: 20px;
        }
        
        .jobs-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .jobs-table thead {
            background-color: var(--bg-tertiary);
        }
        
        .jobs-table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 11px;
            text-transform: uppercase;
            border-bottom: 2px solid var(--border-color);
        }
        
        .jobs-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .jobs-table tbody tr:hover {
            background-color: var(--bg-tertiary);
        }
        
        .jobs-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--accent);
            text-decoration: none;
            font-size: 16px;
        }
        
        .back-link:hover {
            text-decoration: underline;
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
        <a href="/drivers" class="back-link">← Zurück zu Fahrern</a>
        
        <div class="driver-header">
            <div class="driver-header-info">
                <h1><?php echo htmlspecialchars($driver['name']); ?></h1>
                <span class="level-badge">Level <?php echo getLevelByKm($stats['total_km'], $levels); ?></span>
            </div>
        </div>
        
        <!-- Gesamtstatistiken -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Gesamt Jobs</h3>
                <div class="value"><?php echo number_format($stats['total_jobs'], 0, ',', '.'); ?></div>
            </div>
            <div class="stat-card">
                <h3>Gesamt XP</h3>
                <div class="value"><?php echo number_format($stats['total_xp'], 0, ',', '.'); ?></div>
            </div>
            <div class="stat-card">
                <h3>Gesamt KM</h3>
                <div class="value"><?php echo number_format($stats['total_km'], 0, ',', '.'); ?></div>
            </div>
            <div class="stat-card">
                <h3>Gesamt Einkommen</h3>
                <div class="value">€<?php echo number_format($stats['total_income'], 0, ',', '.'); ?></div>
            </div>
            <div class="stat-card">
                <h3>Gesamt Diesel</h3>
                <div class="value"><?php echo number_format($stats['total_fuel'], 2, ',', '.'); ?>L</div>
            </div>
        </div>
        
        <!-- Charts für letzte 30 Tage -->
        <div class="charts-grid">
            <div class="chart-container">
                <canvas id="jobsChart"></canvas>
            </div>
            <div class="chart-container">
                <canvas id="kmChart"></canvas>
            </div>
            <div class="chart-container">
                <canvas id="xpChart"></canvas>
            </div>
            <div class="chart-container">
                <canvas id="incomeChart"></canvas>
            </div>
            <div class="chart-container">
                <canvas id="fuelChart"></canvas>
            </div>
        </div>
        
        <!-- Letzte 5 Jobs -->
        <div class="section">
            <h2>📋 Letzte 5 Jobs</h2>
            <table class="jobs-table">
                <thead>
                    <tr>
                        <th>Job ID</th>
                        <th>Spiel</th>
                        <th>Truck</th>
                        <th>Fracht</th>
                        <th>Route</th>
                        <th>KM</th>
                        <th>XP</th>
                        <th>Einkommen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_jobs as $job): ?>
                    <tr>
                        <td><a href="/job/<?php echo htmlspecialchars($job['id']); ?>" style="color: var(--accent);">#<?php echo htmlspecialchars($job['id']); ?></a></td>
                        <td><?php echo htmlspecialchars(translateGame($job['game'])); ?></td>
                        <td><?php echo htmlspecialchars($job['truck']); ?></td>
                        <td><?php echo htmlspecialchars($job['cargo']); ?></td>
                        <td><?php echo htmlspecialchars($job['source_city']); ?> → <?php echo htmlspecialchars($job['destination_city']); ?></td>
                        <td><?php echo number_format($job['driven_distance_km'], 0, ',', '.'); ?> km</td>
                        <td><?php echo number_format($job['xp'], 0, ',', '.'); ?></td>
                        <td>€<?php echo number_format($job['income'], 0, ',', '.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function toggleTheme() {
            const body = document.body;
            const currentTheme = body.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            body.setAttribute('data-theme', newTheme);
        }
        
        const chartDates = <?php echo $chart_dates_json; ?>;
        const chartColor = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim();
        
        // Jobs Chart
        new Chart(document.getElementById('jobsChart'), {
            type: 'line',
            data: {
                labels: chartDates,
                datasets: [{
                    label: 'Jobs pro Tag',
                    data: <?php echo $chart_jobs_json; ?>,
                    borderColor: chartColor,
                    backgroundColor: chartColor + '20',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary').trim() } }
                },
                scales: {
                    y: { ticks: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() }, grid: { color: getComputedStyle(document.documentElement).getPropertyValue('--border-color').trim() } },
                    x: { ticks: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() }, grid: { color: getComputedStyle(document.documentElement).getPropertyValue('--border-color').trim() } }
                }
            }
        });
        
        // KM Chart
        new Chart(document.getElementById('kmChart'), {
            type: 'line',
            data: {
                labels: chartDates,
                datasets: [{
                    label: 'KM pro Tag',
                    data: <?php echo $chart_km_json; ?>,
                    borderColor: chartColor,
                    backgroundColor: chartColor + '20',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary').trim() } }
                },
                scales: {
                    y: { ticks: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() }, grid: { color: getComputedStyle(document.documentElement).getPropertyValue('--border-color').trim() } },
                    x: { ticks: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() }, grid: { color: getComputedStyle(document.documentElement).getPropertyValue('--border-color').trim() } }
                }
            }
        });
        
        // XP Chart
        new Chart(document.getElementById('xpChart'), {
            type: 'line',
            data: {
                labels: chartDates,
                datasets: [{
                    label: 'XP pro Tag',
                    data: <?php echo $chart_xp_json; ?>,
                    borderColor: chartColor,
                    backgroundColor: chartColor + '20',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary').trim() } }
                },
                scales: {
                    y: { ticks: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() }, grid: { color: getComputedStyle(document.documentElement).getPropertyValue('--border-color').trim() } },
                    x: { ticks: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() }, grid: { color: getComputedStyle(document.documentElement).getPropertyValue('--border-color').trim() } }
                }
            }
        });
        
        // Income Chart
        new Chart(document.getElementById('incomeChart'), {
            type: 'line',
            data: {
                labels: chartDates,
                datasets: [{
                    label: 'Einkommen pro Tag (€)',
                    data: <?php echo $chart_income_json; ?>,
                    borderColor: chartColor,
                    backgroundColor: chartColor + '20',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary').trim() } }
                },
                scales: {
                    y: { ticks: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() }, grid: { color: getComputedStyle(document.documentElement).getPropertyValue('--border-color').trim() } },
                    x: { ticks: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() }, grid: { color: getComputedStyle(document.documentElement).getPropertyValue('--border-color').trim() } }
                }
            }
        });
        
        // Fuel Chart
        new Chart(document.getElementById('fuelChart'), {
            type: 'line',
            data: {
                labels: chartDates,
                datasets: [{
                    label: 'Diesel pro Tag (Liter)',
                    data: <?php echo $chart_fuel_json; ?>,
                    borderColor: chartColor,
                    backgroundColor: chartColor + '20',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary').trim() } }
                },
                scales: {
                    y: { ticks: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() }, grid: { color: getComputedStyle(document.documentElement).getPropertyValue('--border-color').trim() } },
                    x: { ticks: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() }, grid: { color: getComputedStyle(document.documentElement).getPropertyValue('--border-color').trim() } }
                }
            }
        });
    </script>
</body>
</html>
