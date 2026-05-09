<?php
require_once 'db.php';

try {
    $stmt = $pdo->query("
        SELECT 
            cu.id,
            cu.name,
            cu.level,
            COALESCE(SUM(tj.driven_distance_km), 0) as km,
            COALESCE(SUM(tj.xp), 0) as xp,
            COUNT(tj.id) as jobs
        FROM core_users cu
        LEFT JOIN tracker_jobs tj ON cu.id = tj.driver_steam_id
        GROUP BY cu.id, cu.name, cu.level
        ORDER BY xp DESC
    ");
    $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                </tr>
            </thead>
            <tbody>
                <?php foreach ($drivers as $driver): ?>
                <tr>
                    <td><?php echo htmlspecialchars($driver['name']); ?></td>
                    <td><?php echo htmlspecialchars($driver['level']); ?></td>
                    <td><?php echo number_format($driver['xp'], 0, ',', '.'); ?></td>
                    <td><?php echo number_format($driver['km'], 0, ',', '.'); ?></td>
                    <td><?php echo number_format($driver['jobs'], 0, ',', '.'); ?></td>
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
