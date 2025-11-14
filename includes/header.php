<?php
// includes/header.php
// Unified header with CSRF, theme toggle, and user dropdown
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
$user = current_user();
$avatarUrl = $user['avatar_url'] ?? '/assets/default-avatar.png';

// Check if user is admin
$isAdmin = false;
if ($user) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $user['id']]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        $isAdmin = ($userData && $userData['is_admin']);
    } catch (PDOException $e) {
        error_log('Error checking admin status: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo htmlspecialchars($page_title ?? 'Dashboard'); ?></title>
<?php echo CSRF::getTokenMeta(); ?>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="navbar">
    <div class="nav-left">
        <a class="brand" href="/">MyTruckTracker</a>
        <nav class="nav-links" aria-label="Main navigation">
            <a href="/">Home</a>
            <a href="/stats">Stats</a>
            <a href="/jobs">Jobs</a>
            <?php if ($isAdmin): ?>
                <a href="/admin_settings.php" style="color:var(--accent-primary);">⚙️ Admin</a>
            <?php endif; ?>
        </nav>
    </div>

    <div class="nav-right">
        <button id="theme-toggle" class="theme-toggle" aria-label="Toggle theme">🌙 Dark</button>
        
        <?php if ($user): ?>
            <div class="user-dropdown">
                <div class="user-box">
                    <div class="user-avatar">
                        <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="<?php echo htmlspecialchars($user['username']); ?>">
                    </div>
                    <div class="user-info">
                        <div class="username"><?php echo htmlspecialchars($user['username']); ?></div>
                        <div class="steamid"><?php echo htmlspecialchars($user['steamId']); ?></div>
                    </div>
                </div>
                <div class="dropdown-menu">
                    <a href="/user/<?php echo (int)$user['id']; ?>">Profile</a>
                    <a href="/settings.php">Settings</a>
                    <?php if ($isAdmin): ?>
                        <div class="dropdown-divider"></div>
                        <a href="/admin_settings.php" style="color:var(--accent-primary);">⚙️ Admin Settings</a>
                    <?php endif; ?>
                    <div class="dropdown-divider"></div>
                    <form action="/logout.php" method="post" style="margin:0;">
                        <?php echo CSRF::getTokenInput(); ?>
                        <button type="submit">Logout</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <a class="login-btn" href="/login.php">Login with Steam</a>
        <?php endif; ?>
    </div>
</div>

<div class="container">
<!-- page content starts here -->