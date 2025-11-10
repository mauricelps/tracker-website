<?php
// settings.php - User settings page with CSRF protection
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/db.php';

require_login();
$user = current_user();

if (!$user) {
    header('Location: /login.php');
    exit;
}

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::validateRequest();
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_profile':
                $displayName = trim($_POST['display_name'] ?? '');
                $bio = trim($_POST['bio'] ?? '');
                $wotText = trim($_POST['wot_text'] ?? '');
                $truckersmpText = trim($_POST['truckersmp_text'] ?? '');
                $authToken = trim($_POST['auth_token'] ?? '');
                
                // Validate display name
                if (empty($displayName)) {
                    $displayName = $user['username'];
                }
                
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET display_name = :display_name,
                        bio = :bio,
                        wot_text = :wot_text,
                        truckersmp_text = :truckersmp_text,
                        auth_token = :auth_token
                    WHERE id = :user_id
                ");
                $stmt->execute([
                    ':display_name' => $displayName,
                    ':bio' => $bio,
                    ':wot_text' => $wotText,
                    ':truckersmp_text' => $truckersmpText,
                    ':auth_token' => $authToken,
                    ':user_id' => $user['id']
                ]);
                
                $success = 'Profile updated successfully.';
                unset($_SESSION['current_user_cached']);
                $user = current_user();
                break;
                
            case 'pause_account':
                $stmt = $pdo->prepare("UPDATE users SET account_status = 'paused' WHERE id = :user_id");
                $stmt->execute([':user_id' => $user['id']]);
                $success = 'Account paused successfully.';
                break;
                
            case 'reset_stats':
                // Delete user's jobs
                $stmt = $pdo->prepare("DELETE FROM jobs WHERE driver_steam_id = :steam_id");
                $stmt->execute([':steam_id' => $user['steamId']]);
                $success = 'Stats reset successfully.';
                break;
                
            case 'delete_account':
                // Delete user and all associated data
                $deleteJobs = $pdo->prepare("DELETE FROM jobs WHERE driver_steam_id = :steam_id");
                $deleteJobs->execute([':steam_id' => $user['steamId']]);
                
                $deleteUser = $pdo->prepare("DELETE FROM users WHERE id = :user_id");
                $deleteUser->execute([':user_id' => $user['id']]);
                
                logout();
                header('Location: /?deleted=1');
                exit;
        }
    } catch (PDOException $e) {
        error_log('Settings update error: ' . $e->getMessage());
        $error = 'An error occurred. Please try again.';
    }
}

// Fetch current user data with additional fields
try {
    $stmt = $pdo->prepare("
        SELECT id, username, steamId, avatar_url, display_name, bio, 
               wot_text, truckersmp_text, auth_token, account_status
        FROM users 
        WHERE id = :user_id
    ");
    $stmt->execute([':user_id' => $user['id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        $userData = $user;
    }
} catch (PDOException $e) {
    error_log('Settings fetch error: ' . $e->getMessage());
    $userData = $user;
}

$page_title = 'Settings';
include __DIR__ . '/includes/header.php';
?>

<h1>Account Settings</h1>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Profile Settings -->
<div class="card">
    <div class="settings-section">
        <h3>Profile Information</h3>
        <form method="post">
            <?php echo CSRF::getTokenInput(); ?>
            <input type="hidden" name="action" value="update_profile">
            
            <div class="form-group">
                <label>
                    Display Name
                    <input type="text" name="display_name" 
                           value="<?php echo htmlspecialchars($userData['display_name'] ?? $userData['username']); ?>" 
                           placeholder="Your display name">
                </label>
            </div>
            
            <div class="form-group">
                <label>
                    Steam ID (read-only)
                    <input type="text" value="<?php echo htmlspecialchars($userData['steamId']); ?>" disabled>
                </label>
            </div>
            
            <div class="form-group">
                <label>
                    Bio
                    <textarea name="bio" rows="4" placeholder="Tell us about yourself..."><?php echo htmlspecialchars($userData['bio'] ?? ''); ?></textarea>
                </label>
            </div>
            
            <div class="form-group">
                <label>
                    World of Trucks ID/Link
                    <input type="text" name="wot_text" 
                           value="<?php echo htmlspecialchars($userData['wot_text'] ?? ''); ?>" 
                           placeholder="Your World of Trucks profile">
                </label>
            </div>
            
            <div class="form-group">
                <label>
                    TruckersMP ID/Link
                    <input type="text" name="truckersmp_text" 
                           value="<?php echo htmlspecialchars($userData['truckersmp_text'] ?? ''); ?>" 
                           placeholder="Your TruckersMP profile">
                </label>
            </div>
            
            <div class="form-group">
                <label>
                    API Auth Token
                    <input type="password" name="auth_token" 
                           value="<?php echo htmlspecialchars($userData['auth_token'] ?? ''); ?>" 
                           placeholder="Leave empty to keep current">
                </label>
                <small class="meta">This token is used for API authentication. Keep it secret!</small>
            </div>
            
            <button type="submit" class="btn">Save Changes</button>
        </form>
    </div>
</div>

<!-- Danger Zone -->
<div class="card danger-zone">
    <h3>Danger Zone</h3>
    
    <div class="settings-section">
        <h4>Pause Account</h4>
        <p class="meta">Temporarily pause your account. You can reactivate it anytime by logging in.</p>
        <form method="post" style="margin-top:12px;">
            <?php echo CSRF::getTokenInput(); ?>
            <input type="hidden" name="action" value="pause_account">
            <button type="submit" class="btn btn-secondary" 
                    onclick="return confirm('Are you sure you want to pause your account?');">
                Pause Account
            </button>
        </form>
    </div>
    
    <div class="settings-section" style="margin-top:24px;">
        <h4>Reset Statistics</h4>
        <p class="meta">Delete all your job history and statistics. This cannot be undone!</p>
        <form method="post" style="margin-top:12px;">
            <?php echo CSRF::getTokenInput(); ?>
            <input type="hidden" name="action" value="reset_stats">
            <button type="submit" class="btn btn-danger" 
                    onclick="return confirm('Are you sure? This will delete ALL your job history!');">
                Reset All Stats
            </button>
        </form>
    </div>
    
    <div class="settings-section" style="margin-top:24px;">
        <h4>Delete Account</h4>
        <p class="meta">Permanently delete your account and all associated data. This cannot be undone!</p>
        <form method="post" style="margin-top:12px;">
            <?php echo CSRF::getTokenInput(); ?>
            <input type="hidden" name="action" value="delete_account">
            <button type="submit" class="btn btn-danger" 
                    onclick="return confirm('Are you ABSOLUTELY sure? This will permanently delete your account and all data!');">
                Delete Account Permanently
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
