<?php
// index.php - Landing Page / Dashboard (Darkmode, uses includes/header.php + includes/footer.php)
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/db.php';

$page_title = 'Home';
include __DIR__ . '/includes/header.php';

// Gather some quick stats
try {
    $totUsersStmt = $pdo->query("SELECT COUNT(*) AS cnt FROM users");
    $totalUsers = (int) ($totUsersStmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

    $totJobsStmt = $pdo->query("SELECT COUNT(*) AS cnt FROM jobs");
    $totalJobs = (int) ($totJobsStmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

    $deliveredStmt = $pdo->query("SELECT COUNT(*) AS cnt FROM jobs WHERE status = 'delivered'");
    $deliveredJobs = (int) ($deliveredStmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

    $cancelledStmt = $pdo->query("SELECT COUNT(*) AS cnt FROM jobs WHERE status = 'cancelled'");
    $cancelledJobs = (int) ($cancelledStmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

    // recent 5 jobs
    $recentStmt = $pdo->prepare("SELECT j.id, j.driven_distance_km, j.source_city, j.destination_city, j.status, u.username, u.id AS user_id
                                 FROM jobs j
                                 LEFT JOIN users u ON j.driver_steam_id = u.steamId
                                 ORDER BY j.end_time DESC, j.id DESC
                                 LIMIT 5");
    $recentStmt->execute();
    $recentJobs = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<div style='color:#ffb3b3;padding:12px;border-radius:6px;background:#3a1b1b;margin:16px 0;'>Database error: " . htmlspecialchars($e->getMessage()) . "</div>";
    $totalUsers = $totalJobs = $deliveredJobs = $cancelledJobs = 0;
    $recentJobs = [];
}

$user = current_user();
?>

<style>
/* Simple landing page styles (keeps darkmode, matches navbar) */
.hero { background: linear-gradient(90deg, rgba(36,41,48,0.9), rgba(28,31,36,0.9)); padding:28px; border-radius:10px; margin-top:12px; }
.hero h1 { color:#4CAF50; margin:0 0 6px 0; }
.hero p { color:#bfcfc3; margin:0 0 12px 0; }

.kpi-grid { display:grid; grid-template-columns: repeat(auto-fit,minmax(180px,1fr)); gap:14px; margin-top:18px; }
.kpi { background:#252830; padding:16px; border-radius:8px; border:1px solid #2a2f36; text-align:center; }
.kpi .num { font-size:1.6rem; color:#eaf7ea; font-weight:700; }
.kpi .label { color:#9aa3a8; margin-top:6px; }

.card { background:#252830; padding:14px; border-radius:8px; border:1px solid #2a2f36; margin-top:18px; }
.recent-table { width:100%; border-collapse:collapse; margin-top:12px; }
.recent-table th, .recent-table td { padding:10px;border:1px solid #2a2f36; color:#e0e0e0; text-align:left; }
.recent-table thead { background:#2c3e50; color:#bde5c8; }
.recent-table tbody tr:nth-child(even) { background:#242831; }
.recent-table tbody tr:hover { background:#2f3339; }
.quick-links { display:flex; gap:10px; flex-wrap:wrap; margin-top:12px; }
.link-btn { background:#4CAF50; color:#07110b; padding:8px 12px; border-radius:8px; text-decoration:none; font-weight:700; }
.small { color:#9aa3a8; font-size:0.9rem; }
.container { padding-bottom:30px; }
</style>

<div class="hero">
    <h1>Welcome to MyTruckTracker</h1>
    <p>Quick overview of your fleet and drivers. Use the links below to jump into detailed stats or job listings.</p>

    <div class="quick-links">
        <a class="link-btn" href="/stats">View Stats</a>
        <a class="link-btn" href="/jobs">Recent Jobs</a>
        <?php if ($user): ?>
            <a class="link-btn" href="/user/<?php echo (int)$user['id']; ?>">My Profile</a>
        <?php endif; ?>
    </div>

    <div class="kpi-grid" aria-hidden="false">
        <div class="kpi">
            <div class="num"><?php echo number_format($totalUsers); ?></div>
            <div class="label">Total Users</div>
        </div>
        <div class="kpi">
            <div class="num"><?php echo number_format($totalJobs); ?></div>
            <div class="label">Total Jobs</div>
        </div>
        <div class="kpi">
            <div class="num"><?php echo number_format($deliveredJobs); ?></div>
            <div class="label">Delivered Jobs</div>
        </div>
        <div class="kpi">
            <div class="num"><?php echo number_format($cancelledJobs); ?></div>
            <div class="label">Cancelled Jobs</div>
        </div>
    </div>
</div>

<div class="card">
    <h2 style="color:#4CAF50;margin-top:0;">Recent Jobs</h2>
    <?php if (empty($recentJobs)): ?>
        <p class="small">No recent jobs found.</p>
    <?php else: ?>
        <table class="recent-table" role="table" aria-label="Recent jobs">
            <thead>
                <tr><th>JobID</th><th>Driver</th><th>KM</th><th>From</th><th>To</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($recentJobs as $r): ?>
                    <tr>
                        <td><a href="/job/<?php echo (int)$r['id']; ?>" style="color:#87e0a4;"><?php echo (int)$r['id']; ?></a></td>
                        <td>
                            <?php if (!empty($r['user_id'])): ?>
                                <a href="/user/<?php echo (int)$r['user_id']; ?>" style="color:#87e0a4;"><?php echo htmlspecialchars($r['username'] ?? 'Unknown'); ?></a>
                            <?php else: ?>
                                <?php echo htmlspecialchars($r['username'] ?? 'Unknown'); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo is_null($r['driven_distance_km']) ? '—' : round($r['driven_distance_km'],2); ?></td>
                        <td><?php echo htmlspecialchars($r['source_city'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($r['destination_city'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($r['status'] ?? '—')); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>