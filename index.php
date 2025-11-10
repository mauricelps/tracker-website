<?php
// index.php - Landing Page / Dashboard (uses includes/header.php + includes/footer.php)
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
    echo "<div class='alert alert-error'>Database error: " . htmlspecialchars($e->getMessage()) . "</div>";
    $totalUsers = $totalJobs = $deliveredJobs = $cancelledJobs = 0;
    $recentJobs = [];
}

$user = current_user();
?>

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
    <h2 style="color:var(--accent-primary);margin-top:0;">Recent Jobs</h2>
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
                        <td><a href="/job/<?php echo (int)$r['id']; ?>"><?php echo (int)$r['id']; ?></a></td>
                        <td>
                            <?php if (!empty($r['user_id'])): ?>
                                <a href="/user/<?php echo (int)$r['user_id']; ?>"><?php echo htmlspecialchars($r['username'] ?? 'Unknown'); ?></a>
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