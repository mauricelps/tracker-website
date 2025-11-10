<?php
// login.php - Steam OpenID authentication only
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect to home
if (is_logged_in()) {
    header('Location: /');
    exit;
}

// Check for login error from callback
$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

// Steam OpenID URL
$steamOpenIdUrl = 'https://steamcommunity.com/openid/login';
$returnTo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
            '://' . $_SERVER['HTTP_HOST'] . '/auth_callback.php';
$realm = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
         '://' . $_SERVER['HTTP_HOST'];

// Build Steam login URL
$params = [
    'openid.ns' => 'http://specs.openid.net/auth/2.0',
    'openid.mode' => 'checkid_setup',
    'openid.return_to' => $returnTo,
    'openid.realm' => $realm,
    'openid.identity' => 'http://specs.openid.net/auth/2.0/identifier_select',
    'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
];
$steamLoginUrl = $steamOpenIdUrl . '?' . http_build_query($params);

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