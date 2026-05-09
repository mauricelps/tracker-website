<?php
require_once 'db.php';

try {
    $stmt = $pdo->query("
        SELECT 
            cu.id,
            cu.name,
            COALESCE(SUM(tj.driven_distance_km), 0) as km,
            COALESCE(SUM(tj.xp), 0) as xp,
            COUNT(tj.id) as jobs
        FROM core_users cu
        LEFT JOIN tracker_jobs tj ON cu.id = tj.driver_steam_id
        GROUP BY cu.id, cu.name
        ORDER BY xp DESC
    ");
    $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Levels aus tracker_levels laden
    $stmt = $pdo->query("SELECT id, level, points FROM tracker_levels ORDER BY points ASC");
    $levels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Funktion um Level anhand KM zu berechnen
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
    
} catch (PDOException $e) {
    die("Datenbankfehler: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fahrer - Trucker Dashboard</title>
    <link rel="stylesheet" href="style.css">
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
        <h1>Alle Fahrer</h1>
        
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Level</th>
                    <th>XP</th>
                    <th>KM</th>
                    <th>Jobs</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($drivers as $driver): ?>
                <tr>
                    <td><?php echo htmlspecialchars($driver['name']); ?></td>
                    <td><?php echo getLevelByKm($driver['km'], $levels); ?></td>
                    <td><?php echo number_format($driver['xp'], 0, ',', '.'); ?></td>
                    <td><?php echo number_format($driver['km'], 0, ',', '.'); ?></td>
                    <td><?php echo number_format($driver['jobs'], 0, ',', '.'); ?></td>
                    <td>
                        <a href="/driver/<?php echo htmlspecialchars($driver['id']); ?>" style="color: var(--accent);">Anzeigen</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
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
