<?php
// admin_login.php - Local admin login with email/password
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/db.php';

// If already logged in, redirect
if (is_logged_in()) {
    header('Location: /');
    exit;
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::validateRequest();
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Email and password are required.';
    } else {
        try {
            // Find user by email
            $stmt = $pdo->prepare("
                SELECT id, username, email, password_hash, is_admin 
                FROM users 
                WHERE email = :email 
                LIMIT 1
            ");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && !empty($user['password_hash']) && password_verify($password, $user['password_hash'])) {
                // Valid login
                $_SESSION['user_id'] = (int)$user['id'];
                unset($_SESSION['current_user_cached']);
                
                if (function_exists('session_regenerate_id')) {
                    session_regenerate_id(true);
                }
                
                // Redirect to admin settings if admin, otherwise home
                if (!empty($user['is_admin'])) {
                    header('Location: /admin_settings.php');
                } else {
                    header('Location: /');
                }
                exit;
            } else {
                $error = 'Invalid email or password.';
                // Add a small delay to prevent timing attacks
                usleep(500000); // 0.5 seconds
            }
        } catch (PDOException $e) {
            error_log('Admin login error: ' . $e->getMessage());
            $error = 'Login failed. Please try again.';
        }
    }
}

$page_title = 'Admin Login';
include __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width:500px;margin:60px auto;">
    <h1 style="text-align:center;">Admin Login</h1>
    <p style="text-align:center;color:var(--text-secondary);">
        Login with your email and password.
    </p>
    
    <?php if ($error): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <form method="post" style="margin-top:24px;">
        <?php echo CSRF::getTokenInput(); ?>
        
        <div class="form-group">
            <label>
                Email
                <input type="email" name="email" required 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                       autocomplete="email">
            </label>
        </div>
        
        <div class="form-group">
            <label>
                Password
                <input type="password" name="password" required 
                       autocomplete="current-password">
            </label>
        </div>
        
        <button type="submit" class="btn" style="width:100%;margin-top:12px;">
            Login
        </button>
    </form>
    
    <div style="margin-top:24px;padding-top:16px;border-top:1px solid var(--border-color);">
        <p style="text-align:center;color:var(--text-secondary);">
            <a href="/login.php">Login with Steam instead</a>
        </p>
        <p style="text-align:center;color:var(--text-secondary);margin-top:8px;">
            <a href="/register.php">Need an account? Register here</a>
        </p>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
