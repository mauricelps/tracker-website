<?php
// register.php - Admin-first registration page
// Allows one admin registration if users table is empty
// Otherwise requires registration_open site setting
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/db.php';

// If already logged in, redirect
if (is_logged_in()) {
    header('Location: /');
    exit;
}

$error = '';
$success = '';

// Check if this is the first registration (admin)
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $isFirstRegistration = ($result['count'] == 0);
    
    // If not first registration, check if registration is open
    if (!$isFirstRegistration) {
        $stmt = $pdo->prepare("SELECT `value` FROM site_settings WHERE `key` = 'registration_open' LIMIT 1");
        $stmt->execute();
        $setting = $stmt->fetch(PDO::FETCH_ASSOC);
        $registrationOpen = ($setting && $setting['value'] == '1');
        
        if (!$registrationOpen) {
            $error = 'Registration is currently closed. Please contact an administrator.';
        }
    }
} catch (PDOException $e) {
    error_log('Registration check error: ' . $e->getMessage());
    $error = 'Database error. Please try again later.';
}

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    CSRF::validateRequest();
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $username = trim($_POST['username'] ?? '');
    
    // Validate inputs
    if (empty($email) || empty($password) || empty($username)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $password_confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            if ($stmt->fetch()) {
                $error = 'Email already registered.';
            } else {
                // Create user account
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                
                // Generate a unique steamId placeholder for local accounts
                $localSteamId = 'local_' . bin2hex(random_bytes(8));
                
                $insertStmt = $pdo->prepare("
                    INSERT INTO users (
                        username, steamId, email, password_hash, is_admin, 
                        display_name, created_at
                    ) VALUES (
                        :username, :steamId, :email, :password_hash, :is_admin,
                        :display_name, NOW()
                    )
                ");
                
                $insertStmt->execute([
                    ':username' => $username,
                    ':steamId' => $localSteamId,
                    ':email' => $email,
                    ':password_hash' => $passwordHash,
                    ':is_admin' => $isFirstRegistration ? 1 : 0,
                    ':display_name' => $username
                ]);
                
                $userId = $pdo->lastInsertId();
                
                // Log in the user
                $_SESSION['user_id'] = (int)$userId;
                unset($_SESSION['current_user_cached']);
                
                if (function_exists('session_regenerate_id')) {
                    session_regenerate_id(true);
                }
                
                // Redirect to home
                header('Location: /');
                exit;
            }
        } catch (PDOException $e) {
            error_log('Registration error: ' . $e->getMessage());
            $error = 'Registration failed. Please try again.';
        }
    }
}

$page_title = 'Register';
include __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width:500px;margin:60px auto;">
    <?php if ($isFirstRegistration): ?>
        <h1 style="text-align:center;">Admin Registration</h1>
        <p style="text-align:center;color:var(--text-secondary);">
            Create the first admin account for this site.
        </p>
    <?php else: ?>
        <h1 style="text-align:center;">Register</h1>
        <p style="text-align:center;color:var(--text-secondary);">
            Create a new account.
        </p>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if (!$error || ($_SERVER['REQUEST_METHOD'] !== 'POST')): ?>
        <form method="post" style="margin-top:24px;">
            <?php echo CSRF::getTokenInput(); ?>
            
            <div class="form-group">
                <label>
                    Username
                    <input type="text" name="username" required 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </label>
            </div>
            
            <div class="form-group">
                <label>
                    Email
                    <input type="email" name="email" required 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </label>
            </div>
            
            <div class="form-group">
                <label>
                    Password
                    <input type="password" name="password" required minlength="8">
                </label>
                <small class="meta">Minimum 8 characters</small>
            </div>
            
            <div class="form-group">
                <label>
                    Confirm Password
                    <input type="password" name="password_confirm" required minlength="8">
                </label>
            </div>
            
            <button type="submit" class="btn" style="width:100%;margin-top:12px;">
                <?php echo $isFirstRegistration ? 'Create Admin Account' : 'Register'; ?>
            </button>
        </form>
        
        <div style="margin-top:24px;text-align:center;">
            <p class="meta">
                Already have an account? <a href="/admin_login.php">Login here</a>
            </p>
            <p class="meta">
                Or <a href="/login.php">Login with Steam</a>
            </p>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
