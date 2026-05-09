<?php
require_once 'db.php';

$per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $per_page;

// Mapping für Game-Übersetzung
$game_names = [
    'ats' => 'American Truck Simulator',
    'eut2' => 'Euro Truck Simulator 2'
];

try {
    // Gesamtanzahl Jobs
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tracker_jobs");
    $total_jobs = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_jobs / $per_page);
    
    // Jobs für aktuelle Seite mit Fahrernamen
    $stmt = $pdo->prepare("
        SELECT 
            tj.id,
            tj.game,
            tj.driver_steam_id,
            COALESCE(cu.name, 'Unbekannt') as driver_name,
            tj.truck,
            tj.cargo,
            tj.source_city,
            tj.source_company,
            tj.destination_city,
            tj.destination_company
        FROM tracker_jobs tj
        LEFT JOIN core_users cu ON tj.driver_steam_id = cu.id
        ORDER BY tj.id DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Datenbankfehler: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jobs - Trucker Dashboard</title>
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
        <h1>Alle Jobs</h1>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Game</th>
                    <th>Fahrer</th>
                    <th>Truck</th>
                    <th>Fracht</th>
                    <th>Von</th>
                    <th>Nach</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jobs as $job): ?>
                <tr>
                    <td><?php echo htmlspecialchars($job['id']); ?></td>
                    <td><?php echo htmlspecialchars($game_names[$job['game']] ?? $job['game']); ?></td>
                    <td><?php echo htmlspecialchars($job['driver_name']); ?></td>
                    <td><?php echo htmlspecialchars($job['truck']); ?></td>
                    <td><?php echo htmlspecialchars($job['cargo']); ?></td>
                    <td>
                        <?php echo htmlspecialchars($job['source_city']); ?><br>
                        <small style="color: var(--text-secondary);"><?php echo htmlspecialchars($job['source_company']); ?></small>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($job['destination_city']); ?><br>
                        <small style="color: var(--text-secondary);"><?php echo htmlspecialchars($job['destination_company']); ?></small>
                    </td>
                    <td>
                        <a href="/job/<?php echo $job['id']; ?>" style="color: var(--accent);">Anzeigen</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=1">« Erste</a>
                <a href="?page=<?php echo $page - 1; ?>">‹ Zurück</a>
            <?php endif; ?>
            
            <?php
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            
            for ($i = $start; $i <= $end; $i++):
            ?>
                <?php if ($i == $page): ?>
                    <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>">Weiter ›</a>
                <a href="?page=<?php echo $total_pages; ?>">Letzte »</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
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
