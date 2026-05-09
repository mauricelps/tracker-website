<?php
require_once 'db.php';

// Job ID aus URL holen
$job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$job_id) {
    header('Location: /jobs');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM tracker_jobs WHERE id = :id");
    $stmt->execute(['id' => $job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$job) {
        header('Location: /jobs');
        exit;
    }
} catch (PDOException $e) {
    die("Datenbankfehler: " . $e->getMessage());
}

// Game-Übersetzung
function translateGame($game) {
    $games = [
        'eut2' => 'Euro Truck Simulator 2',
        'ats' => 'American Truck Simulator'
    ];
    return $games[strtolower($game)] ?? $game;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job #<?php echo $job['id']; ?> - Trucker Dashboard</title>
    <link rel="stylesheet" href="/style.css">
    <style>
        /* Job Detail Specific Styles */
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
        
        .job-detail {
            background-color: var(--bg-secondary);
            padding: 30px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }
        
        .job-detail > h2 {
            margin: 0 0 25px 0;
            color: var(--accent);
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
            font-size: 28px;
        }
        
        .detail-section {
            margin-bottom: 35px;
            padding: 20px;
            background-color: var(--bg-primary);
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        
        .detail-section:last-child {
            margin-bottom: 0;
        }
        
        .detail-section > h3 {
            font-size: 18px;
            color: var(--text-primary);
            margin: 0 0 20px 0;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
        }
        
        .detail-item {
            padding: 15px 18px;
            background-color: var(--bg-tertiary);
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }
        
        .detail-item label {
            display: block;
            color: var(--text-secondary);
            font-size: 11px;
            text-transform: uppercase;
            margin-bottom: 8px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .detail-item .value {
            font-size: 16px;
            font-weight: 500;
            color: var(--text-primary);
            word-break: break-word;
            line-height: 1.4;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-badge.delivered {
            background-color: #28a745;
            color: white;
        }
        
        .status-badge.pending {
            background-color: #ffc107;
            color: #000;
        }
        
        .status-badge.cancelled {
            background-color: #dc3545;
            color: white;
        }
        
        .status-badge.active {
            background-color: #17a2b8;
            color: white;
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
        <a href="/jobs" class="back-link">← Zurück zu Jobs</a>
        
        <div class="job-detail">
            <h2>Job #<?php echo htmlspecialchars($job['id']); ?></h2>
            
            <!-- Grundinformationen -->
            <div class="detail-section">
                <h3>📋 Grundinformationen</h3>
                <div class="detail-grid">
                    <?php if (isset($job['game'])): ?>
                    <div class="detail-item">
                        <label>Spiel</label>
                        <div class="value"><?php echo htmlspecialchars(translateGame($job['game'])); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($job['driver_steam_id'])): ?>
                    <div class="detail-item">
                        <label>Fahrer Steam ID</label>
                        <div class="value"><?php echo htmlspecialchars($job['driver_steam_id']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($job['status'])): ?>
                    <div class="detail-item">
                        <label>Status</label>
                        <div class="value">
                            <span class="status-badge <?php echo strtolower($job['status']); ?>">
                                <?php echo htmlspecialchars($job['status']); ?>
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Fahrzeug -->
            <?php if (isset($job['truck']) || isset($job['truck_license_plate']) || isset($job['truck_plate_country'])): ?>
            <div class="detail-section">
                <h3>🚛 Fahrzeug</h3>
                <div class="detail-grid">
                    <?php if (isset($job['truck'])): ?>
                    <div class="detail-item">
                        <label>Truck</label>
                        <div class="value"><?php echo htmlspecialchars($job['truck']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($job['truck_license_plate'])): ?>
                    <div class="detail-item">
                        <label>Kennzeichen</label>
                        <div class="value"><?php echo htmlspecialchars($job['truck_license_plate']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($job['truck_plate_country'])): ?>
                    <div class="detail-item">
                        <label>Land</label>
                        <div class="value"><?php echo htmlspecialchars($job['truck_plate_country']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Fracht & Route -->
            <div class="detail-section">
                <h3>📦 Fracht & Route</h3>
                <div class="detail-grid">
                    <?php if (isset($job['cargo'])): ?>
                    <div class="detail-item">
                        <label>Fracht</label>
                        <div class="value"><?php echo htmlspecialchars($job['cargo']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($job['source_city'])): ?>
                    <div class="detail-item">
                        <label>Startstadt</label>
                        <div class="value"><?php echo htmlspecialchars($job['source_city']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($job['source_company'])): ?>
                    <div class="detail-item">
                        <label>Start-Firma</label>
                        <div class="value"><?php echo htmlspecialchars($job['source_company']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($job['destination_city'])): ?>
                    <div class="detail-item">
                        <label>Zielstadt</label>
                        <div class="value"><?php echo htmlspecialchars($job['destination_city']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($job['destination_company'])): ?>
                    <div class="detail-item">
                        <label>Ziel-Firma</label>
                        <div class="value"><?php echo htmlspecialchars($job['destination_company']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Distanz & Zeiten -->
            <div class="detail-section">
                <h3>📏 Distanz & Zeiten</h3>
                <div class="detail-grid">
                    <?php if (isset($job['planned_distance_km'])): ?>
                    <div class="detail-item">
                        <label>Geplante Distanz</label>
                        <div class="value"><?php echo number_format($job['planned_distance_km'], 0, ',', '.'); ?> km</div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($job['driven_distance_km'])): ?>
                    <div class="detail-item">
                        <label>Gefahrene Distanz</label>
                        <div class="value"><?php echo number_format($job['driven_distance_km'], 0, ',', '.'); ?> km</div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($job['start_time'])): ?>
                    <div class="detail-item">
                        <label>Startzeit</label>
                        <div class="value"><?php echo htmlspecialchars($job['start_time']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($job['end_time'])): ?>
                    <div class="detail-item">
                        <label>Endzeit</label>
                        <div class="value"><?php echo htmlspecialchars($job['end_time']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($job['income'])): ?>
                    <div class="detail-item">
                        <label>Einkommen</label>
                        <div class="value">€<?php echo number_format($job['income'], 0, ',', '.'); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Anhänger -->
            <?php if (isset($job['trailer_license_plate']) || isset($job['trailer_plate_country'])): ?>
            <div class="detail-section">
                <h3>🚚 Anhänger</h3>
                <div class="detail-grid">
                    <?php if (isset($job['trailer_license_plate'])): ?>
                    <div class="detail-item">
                        <label>Kennzeichen</label>
                        <div class="value"><?php echo htmlspecialchars($job['trailer_license_plate']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($job['trailer_plate_country'])): ?>
                    <div class="detail-item">
                        <label>Land</label>
                        <div class="value"><?php echo htmlspecialchars($job['trailer_plate_country']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Weitere Details -->
            <?php
            $displayed_fields = ['id', 'game', 'driver_steam_id', 'status', 'truck', 'truck_license_plate', 
                                'truck_plate_country', 'cargo', 'source_city', 'source_company', 
                                'destination_city', 'destination_company', 'planned_distance_km', 
                                'driven_distance_km', 'start_time', 'end_time', 'income',
                                'trailer_license_plate', 'trailer_plate_country'];
            $other_fields = [];
            foreach ($job as $key => $value) {
                if (!in_array($key, $displayed_fields)) {
                    $other_fields[$key] = $value;
                }
            }
            ?>
            
            <?php if (!empty($other_fields)): ?>
            <div class="detail-section">
                <h3>ℹ️ Weitere Details</h3>
                <div class="detail-grid">
                    <?php foreach ($other_fields as $key => $value): ?>
                    <div class="detail-item">
                        <label><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $key))); ?></label>
                        <div class="value"><?php echo htmlspecialchars($value ?? 'N/A'); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
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
    </script>
</body>
</html>