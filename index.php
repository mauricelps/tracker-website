<?php
require_once 'db.php';

// Stats abfragen
try {
    // Gesamt KM
    $stmt = $pdo->query("SELECT SUM(driven_distance_km) as total_km FROM tracker_jobs");
    $total_km = $stmt->fetch(PDO::FETCH_ASSOC)['total_km'] ?? 0;
    
    // Anzahl Fahrer
    $stmt = $pdo->query("SELECT COUNT(*) as total_drivers FROM core_users");
    $total_drivers = $stmt->fetch(PDO::FETCH_ASSOC)['total_drivers'] ?? 0;
    
    // Gesamt XP
    $stmt = $pdo->query("SELECT SUM(xp) as total_xp FROM tracker_jobs");
    $total_xp = $stmt->fetch(PDO::FETCH_ASSOC)['total_xp'] ?? 0;
    
    // Gesamt Einkommen (angenommen es gibt eine income/revenue Spalte in tracker_jobs)
    // Falls nicht vorhanden, kannst du diese Zeile anpassen
    $stmt = $pdo->query("SELECT SUM(income) as total_revenue FROM tracker_jobs");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_revenue = $result['total_revenue'] ?? 0;
    
} catch (PDOException $e) {
    die("Datenbankfehler: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trucker Dashboard</title>
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
        <h1>Dashboard</h1>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Geloggte KM</h3>
                <div class="value"><?php echo number_format($total_km, 0, ',', '.'); ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Alle Fahrer</h3>
                <div class="value"><?php echo number_format($total_drivers, 0, ',', '.'); ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Gesamt Einkommen</h3>
                <div class="value">€<?php echo number_format($total_revenue, 0, ',', '.'); ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Gesamt XP</h3>
                <div class="value"><?php echo number_format($total_xp, 0, ',', '.'); ?></div>
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