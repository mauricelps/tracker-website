<?php
require_once 'db.php';

$per_page = 25;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $per_page;

try {
    // Levels laden
    $stmt = $pdo->query("SELECT id, level, points FROM tracker_levels ORDER BY points ASC");
    $levels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Gesamtanzahl Fahrer
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM core_users");
    $total_drivers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_drivers / $per_page);
    
    // ========== JOBS RANGLISTE ==========
    $stmt = $pdo->prepare("
        SELECT 
            cu.id,
            cu.name,
            COUNT(tj.id) as total_jobs,
            COALESCE(SUM(tj.driven_distance_km), 0) as total_km,
            COALESCE(SUM(tj.xp), 0) as total_xp
        FROM core_users cu
        LEFT JOIN tracker_jobs tj ON cu.id = tj.driver_steam_id
        GROUP BY cu.id, cu.name
        ORDER BY total_jobs DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $drivers_by_jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ========== XP RANGLISTE ==========
    $stmt = $pdo->prepare("
        SELECT 
            cu.id,
            cu.name,
            COUNT(tj.id) as total_jobs,
            COALESCE(SUM(tj.driven_distance_km), 0) as total_km,
            COALESCE(SUM(tj.xp), 0) as total_xp
        FROM core_users cu
        LEFT JOIN tracker_jobs tj ON cu.id = tj.driver_steam_id
        GROUP BY cu.id, cu.name
        ORDER BY total_xp DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $drivers_by_xp = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ========== KM RANGLISTE ==========
    $stmt = $pdo->prepare("
        SELECT 
            cu.id,
            cu.name,
            COUNT(tj.id) as total_jobs,
            COALESCE(SUM(tj.driven_distance_km), 0) as total_km,
            COALESCE(SUM(tj.xp), 0) as total_xp
        FROM core_users cu
        LEFT JOIN tracker_jobs tj ON cu.id = tj.driver_steam_id
        GROUP BY cu.id, cu.name
        ORDER BY total_km DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $drivers_by_km = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    <title>Fahrer Rangliste - Trucker Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .leaderboard-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid var(--border-color);
            flex-wrap: wrap;
        }
        
        .leaderboard-tabs button {
            padding: 12px 20px;
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .leaderboard-tabs button.active {
            color: var(--accent);
            border-bottom-color: var(--accent);
        }
        
        .leaderboard-tabs button:hover {
            color: var(--accent);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .leaderboard-table {
            width: 100%;
            border-collapse: collapse;
            background-color: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .leaderboard-table thead {
            background-color: var(--bg-tertiary);
        }
        
        .leaderboard-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 11px;
            text-transform: uppercase;
            border-bottom: 2px solid var(--border-color);
            letter-spacing: 0.5px;
        }
        
        .leaderboard-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .leaderboard-table tbody tr:hover {
            background-color: var(--bg-tertiary);
        }
        
        .leaderboard-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .rank-cell {
            font-weight: bold;
            min-width: 60px;
        }
        
        .rank-badge {
            display: inline-block;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: var(--bg-primary);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
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
        
        .driver-name {
            font-weight: 600;
        }
        
        .driver-link {
            color: var(--accent);
            text-decoration: none;
        }
        
        .driver-link:hover {
            text-decoration: underline;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            color: var(--accent);
            text-decoration: none;
            background-color: var(--bg-secondary);
        }
        
        .pagination a:hover {
            background-color: var(--bg-tertiary);
        }
        
        .pagination .current {
            background-color: var(--accent);
            color: white;
            border-color: var(--accent);
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
        <h1>🏆 Fahrer Rangliste</h1>
        
        <div class="leaderboard-tabs">
            <button class="tab-button active" onclick="switchTab('jobs', this)">📋 Nach Jobs</button>
            <button class="tab-button" onclick="switchTab('xp', this)">⭐ Nach XP</button>
            <button class="tab-button" onclick="switchTab('km', this)">🛣️ Nach KM</button>
        </div>
        
        <!-- Jobs Rangliste -->
        <div id="jobs" class="tab-content active">
            <table class="leaderboard-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">Rang</th>
                        <th>Fahrer</th>
                        <th style="text-align: right;">Jobs</th>
                        <th style="text-align: right;">XP</th>
                        <th style="text-align: right;">KM</th>
                        <th style="text-align: center;">Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rank = $offset + 1; foreach ($drivers_by_jobs as $driver): ?>
                    <tr>
                        <td class="rank-cell">
                            <span class="rank-badge <?php if ($rank <= 3) echo 'rank' . $rank; ?>"><?php echo $rank; ?></span>
                        </td>
                        <td>
                            <span class="driver-name"><?php echo htmlspecialchars($driver['name']); ?></span>
                        </td>
                        <td style="text-align: right;"><?php echo number_format($driver['total_jobs'], 0, ',', '.'); ?></td>
                        <td style="text-align: right;"><?php echo number_format($driver['total_xp'], 0, ',', '.'); ?></td>
                        <td style="text-align: right;"><?php echo number_format($driver['total_km'], 0, ',', '.'); ?></td>
                        <td style="text-align: center;">
                            <a href="/driver/<?php echo htmlspecialchars($driver['id']); ?>" class="driver-link">Details</a>
                        </td>
                    </tr>
                    <?php $rank++; endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- XP Rangliste -->
        <div id="xp" class="tab-content">
            <table class="leaderboard-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">Rang</th>
                        <th>Fahrer</th>
                        <th style="text-align: right;">XP</th>
                        <th style="text-align: right;">Jobs</th>
                        <th style="text-align: right;">KM</th>
                        <th style="text-align: center;">Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rank = $offset + 1; foreach ($drivers_by_xp as $driver): ?>
                    <tr>
                        <td class="rank-cell">
                            <span class="rank-badge <?php if ($rank <= 3) echo 'rank' . $rank; ?>"><?php echo $rank; ?></span>
                        </td>
                        <td>
                            <span class="driver-name"><?php echo htmlspecialchars($driver['name']); ?></span>
                        </td>
                        <td style="text-align: right;"><?php echo number_format($driver['total_xp'], 0, ',', '.'); ?></td>
                        <td style="text-align: right;"><?php echo number_format($driver['total_jobs'], 0, ',', '.'); ?></td>
                        <td style="text-align: right;"><?php echo number_format($driver['total_km'], 0, ',', '.'); ?></td>
                        <td style="text-align: center;">
                            <a href="/driver/<?php echo htmlspecialchars($driver['id']); ?>" class="driver-link">Details</a>
                        </td>
                    </tr>
                    <?php $rank++; endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- KM Rangliste -->
        <div id="km" class="tab-content">
            <table class="leaderboard-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">Rang</th>
                        <th>Fahrer</th>
                        <th style="text-align: right;">KM</th>
                        <th style="text-align: right;">Jobs</th>
                        <th style="text-align: right;">XP</th>
                        <th style="text-align: center;">Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rank = $offset + 1; foreach ($drivers_by_km as $driver): ?>
                    <tr>
                        <td class="rank-cell">
                            <span class="rank-badge <?php if ($rank <= 3) echo 'rank' . $rank; ?>"><?php echo $rank; ?></span>
                        </td>
                        <td>
                            <span class="driver-name"><?php echo htmlspecialchars($driver['name']); ?></span>
                        </td>
                        <td style="text-align: right;"><?php echo number_format($driver['total_km'], 0, ',', '.'); ?></td>
                        <td style="text-align: right;"><?php echo number_format($driver['total_jobs'], 0, ',', '.'); ?></td>
                        <td style="text-align: right;"><?php echo number_format($driver['total_xp'], 0, ',', '.'); ?></td>
                        <td style="text-align: center;">
                            <a href="/driver/<?php echo htmlspecialchars($driver['id']); ?>" class="driver-link">Details</a>
                        </td>
                    </tr>
                    <?php $rank++; endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
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
        
        function switchTab(tabName, button) {
            // Hide all tabs
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Remove active class from all buttons
            const buttons = document.querySelectorAll('.tab-button');
            buttons.forEach(btn => btn.classList.remove('active'));
            
            // Show selected tab and activate button
            document.getElementById(tabName).classList.add('active');
            button.classList.add('active');
        }
    </script>
</body>
</html>
