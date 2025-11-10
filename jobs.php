<?php
// jobs.php - Recent Jobs (paginated) using includes/header.php + includes/footer.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/db.php';

$perPage = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;

$page_title = 'Jobs';
include __DIR__ . '/includes/header.php';

// Inline table CSS (since header does not include table styles)
?>
<style>
.jobs-table { width: 100%; border-collapse: collapse; margin-top: 18px; }
.jobs-table th, .jobs-table td { padding: 12px; border: 1px solid #2a2f36; text-align: left; color: #e0e0e0; }
.jobs-table thead { background: #2c3e50; }
.jobs-table tbody tr:nth-child(even) { background: #242831; }
.jobs-table tbody tr:hover { background: #2f3339; }
a { color: #87e0a4; text-decoration: none; }
.meta { color:#9aa3a8; margin-top:8px; }
.pagination { display:flex; gap:8px; flex-wrap:wrap; margin: 20px 0; align-items: center; }
.page-link { background:#252830; color:#e0e0e0; padding:8px 12px; border-radius:6px; text-decoration:none; border:1px solid #333; }
.page-link.active { background:#4CAF50; color:#0b0b0b; font-weight:600; }
.page-link.disabled { opacity:0.5; pointer-events:none; }
.container { padding-bottom: 30px; }
</style>

<?php
// Count total
try {
    $countStmt = $pdo->query("SELECT COUNT(*) AS cnt FROM jobs");
    $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
} catch (PDOException $e) {
    echo "<div style='color:#ffb3b3;padding:12px;border-radius:6px;background:#3a1b1b;'>Database error: " . htmlspecialchars($e->getMessage()) . "</div>";
    include __DIR__ . '/includes/footer.php';
    exit;
}
$totalPages = (int) max(1, ceil($total / $perPage));

// Fetch jobs with driver info (user.id as user_id)
try {
    $sql = "SELECT j.id, j.driven_distance_km, j.source_city, j.destination_city, u.username, u.id AS user_id
            FROM jobs j
            LEFT JOIN users u ON j.driver_steam_id = u.steamId
            ORDER BY j.end_time DESC, j.id DESC
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<div style='color:#ffb3b3;padding:12px;border-radius:6px;background:#3a1b1b;'>Database error: " . htmlspecialchars($e->getMessage()) . "</div>";
    include __DIR__ . '/includes/footer.php';
    exit;
}

// Helpers for pretty URLs (use /jobs?page=n for pagination)
function jobUrl(int $id): string { return '/job/' . $id; }
function userUrl(int $id): string { return '/user/' . $id; }
function pageUrl(int $p): string { return '/jobs' . ($p > 1 ? ('?page=' . $p) : ''); }
?>

<h1>Recent Jobs</h1>
<p class="meta">Showing page <?php echo $page; ?> of <?php echo $totalPages; ?> — total jobs: <?php echo $total; ?></p>

<table class="jobs-table" role="table" aria-describedby="recent-jobs">
    <thead>
        <tr>
            <th># JobID</th>
            <th>Driver</th>
            <th>KM</th>
            <th>Start City</th>
            <th>Destination City</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($jobs)): ?>
        <tr><td colspan="5" style="padding:12px;">No jobs found.</td></tr>
    <?php else: ?>
        <?php foreach ($jobs as $job): ?>
            <tr>
                <td><a href="<?php echo htmlspecialchars(jobUrl((int)$job['id'])); ?>"><?php echo (int)$job['id']; ?></a></td>
                <td>
                    <?php if (!empty($job['user_id'])): ?>
                        <a href="<?php echo htmlspecialchars(userUrl((int)$job['user_id'])); ?>"><?php echo htmlspecialchars($job['username'] ?? 'Unknown'); ?></a>
                    <?php else: ?>
                        <?php echo htmlspecialchars($job['username'] ?? 'Unknown'); ?>
                    <?php endif; ?>
                </td>
                <td><?php echo is_null($job['driven_distance_km']) ? '—' : round($job['driven_distance_km'], 2); ?></td>
                <td><?php echo htmlspecialchars($job['source_city'] ?? '—'); ?></td>
                <td><?php echo htmlspecialchars($job['destination_city'] ?? '—'); ?></td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<!-- Pagination -->
<div class="pagination" role="navigation" aria-label="Pagination">
    <?php
    // Prev
    if ($page > 1) {
        echo '<a class="page-link" href="' . htmlspecialchars(pageUrl($page - 1)) . '">&laquo; Prev</a>';
    } else {
        echo '<span class="page-link disabled">&laquo; Prev</span>';
    }

    // Page numbers (up to 7 visible)
    $start = max(1, $page - 3);
    $end = min($totalPages, $page + 3);
    if ($start > 1) {
        echo '<a class="page-link" href="' . htmlspecialchars(pageUrl(1)) . '">1</a>';
        if ($start > 2) echo '<span class="page-link disabled">...</span>';
    }
    for ($p = $start; $p <= $end; $p++) {
        $cls = $p === $page ? 'page-link active' : 'page-link';
        echo '<a class="' . $cls . '" href="' . htmlspecialchars(pageUrl($p)) . '">' . $p . '</a>';
    }
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) echo '<span class="page-link disabled">...</span>';
        echo '<a class="page-link" href="' . htmlspecialchars(pageUrl($totalPages)) . '">' . $totalPages . '</a>';
    }

    // Next
    if ($page < $totalPages) {
        echo '<a class="page-link" href="' . htmlspecialchars(pageUrl($page + 1)) . '">Next &raquo;</a>';
    } else {
        echo '<span class="page-link disabled">Next &raquo;</span>';
    }
    ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>