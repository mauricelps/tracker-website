<?php
// register.php - User registration via Steam OpenID
// First user to register becomes admin and registration is automatically closed
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/steam_openid.php';
require_once __DIR__ . '/includes/steam_api.php';
require_once __DIR__ . '/db.php';

// If already logged in, redirect to home
if (is_logged_in()) {
    header('Location: /');
    exit;
}

// Check if registration is open
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'registration_open' LIMIT 1");
    $stmt->execute();
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);
    $registrationOpen = ($setting && $setting['setting_value'] === '1');
    
    // Check if users table is empty (first registration)
    $userCountStmt = $pdo->prepare("SELECT COUNT(*) as count FROM users");
    $userCountStmt->execute();
    $userCount = $userCountStmt->fetch(PDO::FETCH_ASSOC);
    $isFirstUser = ($userCount['count'] == 0);
    
    // Allow registration if it's open OR if this is the first user
    if (!$registrationOpen && !$isFirstUser) {
        $page_title = 'Registration Closed';
        include __DIR__ . '/includes/header.php';
        ?>
        <div class="card" style="max-width:500px;margin:60px auto;">
            <h1 style="text-align:center;">Registration Closed</h1>
            <p style="text-align:center;color:var(--text-secondary);">
                New user registration is currently closed. Please contact an administrator.
            </p>
            <div style="text-align:center;margin-top:24px;">
                <a href="/" class="btn btn-secondary">← Back to Home</a>
            </div>
        </div>
        <?php
        include __DIR__ . '/includes/footer.php';
        exit;
    }
    
} catch (PDOException $e) {
    error_log('Registration check error: ' . $e->getMessage());
    die('Database error occurred.');
}

// Build Steam login URL for registration
$returnTo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
            '://' . $_SERVER['HTTP_HOST'] . '/register_callback.php';
$realm = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
         '://' . $_SERVER['HTTP_HOST'];

$steamLoginUrl = SteamOpenID::getLoginUrl($returnTo, $realm);

$page_title = 'Register';
include __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width:500px;margin:60px auto;">
    <h1 style="text-align:center;">Register with Steam</h1>
    
    <?php if ($isFirstUser): ?>
        <div class="alert alert-info" style="margin-bottom:20px;">
            <strong>First Registration!</strong><br>
            You will be the first user and will automatically become an administrator.
        </div>
    <?php endif; ?>
    
    <p style="text-align:center;color:var(--text-secondary);">
        Click the button below to create an account using your Steam account.
    </p>
    
    <div style="text-align:center;margin-top:24px;">
        <a href="<?php echo htmlspecialchars($steamLoginUrl); ?>" class="btn" style="padding:12px 24px;font-size:1.1rem;">
            <span style="margin-right:8px;">🎮</span> Register with Steam
        </a>
    </div>
    
    <div style="margin-top:24px;padding-top:16px;border-top:1px solid var(--border-color);">
        <p style="font-size:0.85rem;color:var(--text-secondary);text-align:center;">
            Already have an account? <a href="/login.php">Login here</a>
        </p>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
