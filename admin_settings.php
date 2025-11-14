<?php
// admin_settings.php - Admin settings page with registration toggle and site reset
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/db.php';

// Require login
require_login();
$user = current_user();

if (!$user) {
    header('Location: /login.php');
    exit;
}

// Check if user is admin
try {
    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $user['id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData || !$userData['is_admin']) {
        http_response_code(403);
        die('Access denied. You must be an administrator.');
    }
} catch (PDOException $e) {
    error_log('Admin check error: ' . $e->getMessage());
    die('Database error occurred.');
}

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::validateRequest();
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'toggle_registration':
                // Get current registration status
                $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'registration_open' LIMIT 1");
                $stmt->execute();
                $setting = $stmt->fetch(PDO::FETCH_ASSOC);
                $currentValue = ($setting && $setting['setting_value'] === '1') ? '1' : '0';
                
                // Toggle the value
                $newValue = ($currentValue === '1') ? '0' : '1';
                
                // Update the setting
                $updateStmt = $pdo->prepare("
                    UPDATE site_settings 
                    SET setting_value = :value 
                    WHERE setting_key = 'registration_open'
                ");
                $updateStmt->execute([':value' => $newValue]);
                
                $success = 'Registration has been ' . ($newValue === '1' ? 'opened' : 'closed') . '.';
                break;
                
            case 'reset_site':
                // Confirm this is a destructive action
                $confirm = $_POST['confirm'] ?? '';
                
                if ($confirm !== 'RESET') {
                    $error = 'You must type "RESET" to confirm this action.';
                    break;
                }
                
                // Begin transaction
                $pdo->beginTransaction();
                
                try {
                    // Delete all jobs and related data
                    $pdo->exec("DELETE FROM job_transports");
                    $pdo->exec("DELETE FROM jobs");
                    
                    // Delete VTC memberships and VTCs
                    $pdo->exec("DELETE FROM vtc_members");
                    $pdo->exec("DELETE FROM vtcs");
                    
                    // Delete all non-admin users
                    $pdo->exec("DELETE FROM users WHERE is_admin = 0");
                    
                    // Reset registration setting to closed
                    $pdo->prepare("
                        UPDATE site_settings 
                        SET setting_value = '0' 
                        WHERE setting_key = 'registration_open'
                    ")->execute();
                    
                    // Commit transaction
                    $pdo->commit();
                    
                    $success = 'Site has been reset successfully. All data except admin accounts has been deleted.';
                    error_log('Admin ' . $user['id'] . ' reset the site');
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
                break;
                
            default:
                $error = 'Invalid action.';
        }
    } catch (PDOException $e) {
        error_log('Admin settings error: ' . $e->getMessage());
        $error = 'An error occurred. Please try again.';
    }
}

// Get current registration status
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'registration_open' LIMIT 1");
    $stmt->execute();
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);
    $registrationOpen = ($setting && $setting['setting_value'] === '1');
} catch (PDOException $e) {
    error_log('Error fetching settings: ' . $e->getMessage());
    $registrationOpen = false;
}

// Get site statistics
try {
    $statsStmt = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM users) as total_users,
            (SELECT COUNT(*) FROM users WHERE is_admin = 1) as admin_users,
            (SELECT COUNT(*) FROM jobs) as total_jobs,
            (SELECT COUNT(*) FROM vtcs) as total_vtcs
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error fetching stats: ' . $e->getMessage());
    $stats = ['total_users' => 0, 'admin_users' => 0, 'total_jobs' => 0, 'total_vtcs' => 0];
}

$page_title = 'Admin Settings';
include __DIR__ . '/includes/header.php';
?>

<h1>Admin Settings</h1>

<?php if ($success): ?>
    <div class="alert alert-success">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="card" style="margin-bottom:20px;">
    <h2>Site Statistics</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-top:16px;">
        <div style="padding:16px;background:var(--bg-tertiary);border-radius:8px;">
            <div style="font-size:2rem;font-weight:bold;color:var(--accent-primary);">
                <?php echo (int)$stats['total_users']; ?>
            </div>
            <div style="color:var(--text-secondary);">Total Users</div>
        </div>
        <div style="padding:16px;background:var(--bg-tertiary);border-radius:8px;">
            <div style="font-size:2rem;font-weight:bold;color:var(--accent-primary);">
                <?php echo (int)$stats['admin_users']; ?>
            </div>
            <div style="color:var(--text-secondary);">Administrators</div>
        </div>
        <div style="padding:16px;background:var(--bg-tertiary);border-radius:8px;">
            <div style="font-size:2rem;font-weight:bold;color:var(--accent-primary);">
                <?php echo (int)$stats['total_jobs']; ?>
            </div>
            <div style="color:var(--text-secondary);">Total Jobs</div>
        </div>
        <div style="padding:16px;background:var(--bg-tertiary);border-radius:8px;">
            <div style="font-size:2rem;font-weight:bold;color:var(--accent-primary);">
                <?php echo (int)$stats['total_vtcs']; ?>
            </div>
            <div style="color:var(--text-secondary);">VTCs</div>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom:20px;">
    <h2>Registration Settings</h2>
    <p style="color:var(--text-secondary);">
        Control whether new users can register for the site.
    </p>
    
    <div style="margin-top:16px;padding:16px;background:var(--bg-tertiary);border-radius:8px;">
        <div style="font-weight:bold;margin-bottom:8px;">
            Current Status:
            <span style="color:<?php echo $registrationOpen ? 'var(--accent-primary)' : 'var(--error-text)'; ?>">
                <?php echo $registrationOpen ? 'OPEN' : 'CLOSED'; ?>
            </span>
        </div>
        
        <form method="post" style="margin-top:12px;">
            <?php echo CSRF::getTokenInput(); ?>
            <input type="hidden" name="action" value="toggle_registration">
            <button type="submit" class="btn">
                <?php echo $registrationOpen ? '🔒 Close Registration' : '🔓 Open Registration'; ?>
            </button>
        </form>
    </div>
</div>

<div class="card" style="margin-bottom:20px;border:2px solid var(--error-border);">
    <h2 style="color:var(--error-text);">⚠️ Danger Zone</h2>
    <p style="color:var(--text-secondary);">
        These actions are permanent and cannot be undone.
    </p>
    
    <div style="margin-top:16px;padding:16px;background:var(--error-bg);border-radius:8px;border:1px solid var(--error-border);">
        <h3 style="margin-top:0;">Reset Site</h3>
        <p style="color:var(--text-secondary);font-size:0.9rem;">
            This will delete:
        </p>
        <ul style="color:var(--text-secondary);font-size:0.9rem;">
            <li>All jobs and job data</li>
            <li>All VTCs and VTC memberships</li>
            <li>All non-admin user accounts</li>
        </ul>
        <p style="color:var(--error-text);font-weight:bold;">
            ⚠️ Admin accounts will be preserved but all other data will be permanently deleted!
        </p>
        
        <form method="post" onsubmit="return confirm('Are you ABSOLUTELY SURE you want to reset the site? This action CANNOT be undone!');">
            <?php echo CSRF::getTokenInput(); ?>
            <input type="hidden" name="action" value="reset_site">
            
            <div style="margin-bottom:12px;">
                <label for="confirm" style="display:block;margin-bottom:4px;color:var(--text-primary);">
                    Type <strong>RESET</strong> to confirm:
                </label>
                <input 
                    type="text" 
                    id="confirm" 
                    name="confirm" 
                    required
                    placeholder="Type RESET here"
                    style="width:200px;padding:8px;background:var(--bg-primary);color:var(--text-primary);border:1px solid var(--border-color);border-radius:4px;"
                >
            </div>
            
            <button type="submit" class="btn" style="background:var(--error-bg);color:var(--error-text);border-color:var(--error-border);">
                🗑️ Reset Site
            </button>
        </form>
    </div>
</div>

<div style="margin-top:20px;">
    <a href="/" class="btn btn-secondary">← Back to Home</a>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
