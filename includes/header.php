<?php
// includes/header.php
// Simple dark-mode navbar. Include this at top of pages (after require_once 'includes/auth.php')
require_once __DIR__ . '/auth.php';
$user = current_user();
$avatarUrl = $user['avatar_url'] ?? '/assets/default-avatar.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo htmlspecialchars($page_title ?? 'Dashboard'); ?></title>
<style>
/* Minimal navbar + darkmode styling - keep consistent with other pages */
body { background:#1a1d24; color:#e0e0e0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial; margin:0; padding:0; }
.navbar { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:12px 20px; background:#121317; border-bottom:1px solid #24272b; position:sticky; top:0; z-index:1000;}
.nav-left { display:flex; gap:12px; align-items:center; }
.brand { color:#4CAF50; font-weight:600; font-size:1.1rem; text-decoration:none; }
.nav-links a { color:#cfead6; text-decoration:none; padding:8px 10px; border-radius:6px; }
.nav-links a:hover { background:#252830; }
.nav-right { display:flex; gap:12px; align-items:center; }
.user-box { display:flex; gap:8px; align-items:center; }
.user-avatar { width:36px; height:36px; border-radius:6px; overflow:hidden; border:1px solid #333; }
.user-avatar img { width:100%; height:100%; object-fit:cover; display:block; }
.login-btn, .logout-btn { background:#4CAF50; color:#07110b; border:none; padding:6px 10px; border-radius:6px; text-decoration:none; font-weight:600; }
.small { font-size:0.9rem; color:#9aa3a8; }
.container { max-width:1100px; margin:20px auto; padding:0 16px; }
</style>
</head>
<body>
<div class="navbar">
    <div class="nav-left">
        <a class="brand" href="/">MyTruckTracker</a>
        <nav class="nav-links" aria-label="Main navigation">
            <a href="/">Home</a>
            <a href="/stats">Stats</a>
            <a href="/jobs">Jobs</a>
        </nav>
    </div>

    <div class="nav-right">
        <?php if ($user): ?>
            <div class="user-box">
                <a href="/user/<?php echo (int)$user['id']; ?>" title="<?php echo htmlspecialchars($user['username']); ?>">
                    <div class="user-avatar"><img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt=""></div>
                </a>
                <div class="small">
                    <div><?php echo htmlspecialchars($user['username']); ?></div>
                    <div style="font-size:0.75rem; color:#9aa3a8;"><?php echo htmlspecialchars($user['steamId']); ?></div>
                </div>
                <form action="/logout.php" method="post" style="margin:0;">
                    <button class="logout-btn" type="submit">Logout</button>
                </form>
            </div>
        <?php else: ?>
            <a class="login-btn" href="/login.php">Login</a>
        <?php endif; ?>
    </div>
</div>

<div class="container">
<!-- page content starts here -->