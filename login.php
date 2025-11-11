<?php
// login.php - Steam OpenID authentication only
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/steam_openid.php';

// If already logged in, redirect to home
if (is_logged_in()) {
    header('Location: /');
    exit;
}

// Check for login error from callback
$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

// Build Steam login URL using SteamOpenID helper
$returnTo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
            '://' . $_SERVER['HTTP_HOST'] . '/auth_callback.php';
$realm = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
         '://' . $_SERVER['HTTP_HOST'];

$steamLoginUrl = SteamOpenID::getLoginUrl($returnTo, $realm);

$page_title = 'Login';
include __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width:500px;margin:60px auto;">
    <h1 style="text-align:center;">Login with Steam</h1>
    
    <?php if ($error): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <p style="text-align:center;color:var(--text-secondary);">
        Click the button below to sign in using your Steam account.
    </p>
    
    <div style="text-align:center;margin-top:24px;">
        <a href="<?php echo htmlspecialchars($steamLoginUrl); ?>" class="btn" style="padding:12px 24px;font-size:1.1rem;">
            <span style="margin-right:8px;">🎮</span> Sign in through Steam
        </a>
    </div>
    
    <div style="margin-top:24px;padding-top:16px;border-top:1px solid var(--border-color);">
        <p style="font-size:0.85rem;color:var(--text-secondary);text-align:center;">
            By logging in, you agree to our terms of service and privacy policy.
        </p>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>