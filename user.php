<?php
// user.php - User profile + stats + jobs list
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/db.php';

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    http_response_code(404);
    echo "404 - User not found";
    exit;
}
$user_id = (int)$_GET['id'];

try {
    // load user
    $userStmt = $pdo->prepare("SELECT id, username, steamId, avatar_url FROM users WHERE id = :id LIMIT 1");
    $userStmt->execute([':id' => $user_id]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        http_response_code(404);
        echo "404 - User not found";
        exit;
    }

    // stats
    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE driver_steam_id = :steam");
    $totalStmt->execute([':steam' => $user['steamId']]);
    $totalJobs = (int)$totalStmt->fetchColumn();

    $delivStmt = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE driver_steam_id = :steam AND status = 'delivered'");
    $delivStmt->execute([':steam' => $user['steamId']]);
    $delivered = (int)$delivStmt->fetchColumn();

    $cancelStmt = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE driver_steam_id = :steam AND status = 'cancelled'");
    $cancelStmt->execute([':steam' => $user['steamId']]);
    $cancelled = (int)$cancelStmt->fetchColumn();

    $realStmt = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE driver_steam_id = :steam AND driven_distance_km IS NOT NULL AND maxSpeed <= 100");
    $realStmt->execute([':steam' => $user['steamId']]);
    $realCount = (int)$realStmt->fetchColumn();

    $raceStmt = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE driver_steam_id = :steam AND driven_distance_km IS NOT NULL AND maxSpeed >= 101");
    $raceStmt->execute([':steam' => $user['steamId']]);
    $raceCount = (int)$raceStmt->fetchColumn();

    // jobs list pagination (named params for limit/offset)
    $perPage = 50;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($page - 1) * $perPage;

    $jobsStmt = $pdo->prepare("
        SELECT j.id, j.status, j.driven_distance_km, j.source_city, j.destination_city, j.income, j.end_time
        FROM jobs j
        WHERE j.driver_steam_id = :steam
        ORDER BY j.end_time DESC, j.id DESC
        LIMIT :limit OFFSET :offset
    ");
    $jobsStmt->bindValue(':steam', $user['steamId'], PDO::PARAM_STR);
    $jobsStmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
    $jobsStmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $jobsStmt->execute();
    $jobs = $jobsStmt->fetchAll(PDO::FETCH_ASSOC);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE driver_steam_id = :steam");
    $countStmt->execute([':steam' => $user['steamId']]);
    $totalForPages = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalForPages / $perPage));

} catch (PDOException $e) {
    echo "<div style='color:#ffb3b3;padding:12px;border-radius:6px;background:#3a1b1b;'>Database error: " . htmlspecialchars($e->getMessage()) . "</div>";
    exit;
}

$page_title = 'User: ' . ($user['username'] ?? 'User');
include __DIR__ . '/includes/header.php';

// default avatar
$defaultAvatar = '/assets/default-avatar.png';
$avatar = $user['avatar_url'] ? $user['avatar_url'] : $defaultAvatar;
?>

<h1><?php echo htmlspecialchars($user['username']); ?></h1>

<div style="display:flex;gap:20px;align-items:flex-start;max-width:1000px;">
    <div style="width:140px;">
        <div style="width:140px;height:140px;border-radius:8px;overflow:hidden;border:1px solid #333;background:#23242a;">
            <img src="<?php echo htmlspecialchars($avatar); ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover;display:block;">
        </div>
        <?php if (is_logged_in() && current_user() && current_user()['id'] == $user['id']): ?>
            <form action="/upload_avatar.php" method="post" enctype="multipart/form-data" style="margin-top:12px;">
                <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                <label style="color:#9aa3a8;font-size:0.9rem;">Change avatar
                    <input type="file" name="avatar" accept="image/*" style="display:block;margin-top:8px;">
                </label>
                <button type="submit" style="margin-top:8px;background:#4CAF50;color:#07110b;border:none;padding:8px 12px;border-radius:6px;">Upload</button>
            </form>
        <?php endif; ?>
    </div>

    <div style="flex:1;">
        <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <div style="background:#252830;padding:12px;border-radius:8px;border:1px solid #333;">Total Jobs<br><strong style="font-size:1.2rem;"><?php echo $totalJobs; ?></strong></div>
            <div style="background:#252830;padding:12px;border-radius:8px;border:1px solid #333;">Delivered<br><strong style="font-size:1.2rem;"><?php echo $delivered; ?></strong></div>
            <div style="background:#252830;padding:12px;border-radius:8px;border:1px solid #333;">Cancelled<br><strong style="font-size:1.2rem;"><?php echo $cancelled; ?></strong></div>
            <div style="background:#252830;padding:12px;border-radius:8px;border:1px solid #333;">Real (≤100 km/h)<br><strong style="font-size:1.2rem;"><?php echo $realCount; ?></strong></div>
            <div style="background:#252830;padding:12px;border-radius:8px;border:1px solid #333;">Race (≥101 km/h)<br><strong style="font-size:1.2rem;"><?php echo $raceCount; ?></strong></div>
        </div>

        <div style="margin-top:20px;background:#252830;padding:14px;border-radius:8px;">
            <h3 style="margin-top:0;color:#4CAF50;">Jobs (page <?php echo $page; ?> / <?php echo $totalPages; ?>)</h3>
            <table style="width:100%;border-collapse:collapse;">
                <thead style="background:#2c3e50;color:#bde5c8;">
                    <tr>
                        <th style="padding:8px;border:1px solid #2a2f36;">JobID</th>
                        <th style="padding:8px;border:1px solid #2a2f36;">Status</th>
                        <th style="padding:8px;border:1px solid #2a2f36;">KM</th>
                        <th style="padding:8px;border:1px solid #2a2f36;">Start</th>
                        <th style="padding:8px;border:1px solid #2a2f36;">Destination</th>
                        <th style="padding:8px;border:1px solid #2a2f36;">Income</th>
                        <th style="padding:8px;border:1px solid #2a2f36;">Ended</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($jobs)): ?>
                        <tr><td colspan="7" style="padding:12px;">No jobs found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($jobs as $j): ?>
                            <tr>
                                <td style="padding:8px;border:1px solid #2a2f36;"><a href="/job/<?php echo (int)$j['id']; ?>" style="color:#87e0a4;"><?php echo (int)$j['id']; ?></a></td>
                                <td style="padding:8px;border:1px solid #2a2f36;"><?php echo htmlspecialchars(ucfirst($j['status'])); ?></td>
                                <td style="padding:8px;border:1px solid #2a2f36;"><?php echo is_null($j['driven_distance_km']) ? '—' : round($j['driven_distance_km'],2); ?></td>
                                <td style="padding:8px;border:1px solid #2a2f36;"><?php echo htmlspecialchars($j['source_city'] ?? '—'); ?></td>
                                <td style="padding:8px;border:1px solid #2a2f36;"><?php echo htmlspecialchars($j['destination_city'] ?? '—'); ?></td>
                                <td style="padding:8px;border:1px solid #2a2f36;"><?php echo is_null($j['income']) ? '—' : number_format($j['income']); ?></td>
                                <td style="padding:8px;border:1px solid #2a2f36;"><?php echo htmlspecialchars($j['end_time'] ?? '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div style="margin-top:12px;">
                <?php if ($page > 1): ?>
                    <a class="page-link" href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?' . http_build_query(['id'=>$user_id,'page'=>$page-1])); ?>" style="margin-right:8px;background:#252830;padding:6px 10px;border-radius:6px;color:#e0e0e0;text-decoration:none;border:1px solid #333;">&laquo; Prev</a>
                <?php endif; ?>
                <span style="color:#9aa3a8;">Page <?php echo $page; ?> / <?php echo $totalPages; ?></span>
                <?php if ($page < $totalPages): ?>
                    <a class="page-link" href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?' . http_build_query(['id'=>$user_id,'page'=>$page+1])); ?>" style="margin-left:8px;background:#252830;padding:6px 10px;border-radius:6px;color:#e0e0e0;text-decoration:none;border:1px solid #333;">Next &raquo;</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>