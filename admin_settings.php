<?php
// admin_settings.php - Admin settings and site management
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/db.php';

require_login();
require_admin();

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
                $stmt = $pdo->prepare("SELECT `value` FROM site_settings WHERE `key` = 'registration_open' LIMIT 1");
                $stmt->execute();
                $current = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $newValue = ($current && $current['value'] == '1') ? '0' : '1';
                
                // Update or insert setting
                $updateStmt = $pdo->prepare("
                    INSERT INTO site_settings (`key`, `value`) 
                    VALUES ('registration_open', :value)
                    ON DUPLICATE KEY UPDATE `value` = :value
                ");
                $updateStmt->execute([':value' => $newValue]);
                
                $success = 'Registration status updated successfully.';
                break;
                
            case 'reset_site':
                $confirmText = $_POST['confirm_text'] ?? '';
                
                if ($confirmText !== 'RESET') {
                    $error = 'Please type RESET to confirm site reset.';
                } else {
                    // Delete all data except admin users
                    $pdo->beginTransaction();
                    
                    try {
                        // Delete jobs
                        $pdo->exec("DELETE FROM jobs");
                        
                        // Delete VTC memberships
                        $pdo->exec("DELETE FROM vtc_members");
                        
                        // Delete VTCs
                        $pdo->exec("DELETE FROM vtcs");
                        
                        // Delete non-admin users
                        $pdo->exec("DELETE FROM users WHERE is_admin = 0");
                        
                        // Reset auto-increment
                        $pdo->exec("ALTER TABLE jobs AUTO_INCREMENT = 1");
                        $pdo->exec("ALTER TABLE vtcs AUTO_INCREMENT = 1");
                        $pdo->exec("ALTER TABLE vtc_members AUTO_INCREMENT = 1");
                        
                        $pdo->commit();
                        $success = 'Site reset successfully. All non-admin data has been deleted.';
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        throw $e;
                    }
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

// Get current settings
try {
    $stmt = $pdo->prepare("SELECT `value` FROM site_settings WHERE `key` = 'registration_open' LIMIT 1");
    $stmt->execute();
    $regSetting = $stmt->fetch(PDO::FETCH_ASSOC);
    $registrationOpen = ($regSetting && $regSetting['value'] == '1');
    
    // Get statistics
    $statsStmt = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM users) as total_users,
            (SELECT COUNT(*) FROM users WHERE is_admin = 1) as admin_users,
            (SELECT COUNT(*) FROM jobs) as total_jobs,
            (SELECT COUNT(*) FROM vtcs) as total_vtcs
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Admin settings fetch error: ' . $e->getMessage());
    $registrationOpen = false;
    $stats = ['total_users' => 0, 'admin_users' => 0, 'total_jobs' => 0, 'total_vtcs' => 0];
}

$page_title = 'Admin Settings';
include __DIR__ . '/includes/header.php';
?>

<h1>⚙️ Admin Settings</h1>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Site Statistics -->
<div class="card">
    <h2>Site Statistics</h2>
    <div class="kpi-grid">
        <div class="kpi">
            <div class="num"><?php echo $stats['total_users']; ?></div>
            <div class="label">Total Users</div>
        </div>
        <div class="kpi">
            <div class="num"><?php echo $stats['admin_users']; ?></div>
            <div class="label">Admin Users</div>
        </div>
        <div class="kpi">
            <div class="num"><?php echo $stats['total_jobs']; ?></div>
            <div class="label">Total Jobs</div>
        </div>
        <div class="kpi">
            <div class="num"><?php echo $stats['total_vtcs']; ?></div>
            <div class="label">VTCs</div>
        </div>
    </div>
</div>

<!-- Registration Settings -->
<div class="card">
    <div class="settings-section">
        <h3>Registration Settings</h3>
        <p class="meta">
            Control whether new users can register for local accounts.
            Steam login is always available.
        </p>
        
        <div style="margin-top:16px;">
            <p>
                <strong>Current Status:</strong> 
                <span style="color:<?php echo $registrationOpen ? 'var(--accent-primary)' : 'var(--error-text)'; ?>">
                    <?php echo $registrationOpen ? '✓ Open' : '✗ Closed'; ?>
                </span>
            </p>
        </div>
        
        <form method="post" style="margin-top:16px;">
            <?php echo CSRF::getTokenInput(); ?>
            <input type="hidden" name="action" value="toggle_registration">
            <button type="submit" class="btn">
                <?php echo $registrationOpen ? 'Close Registration' : 'Open Registration'; ?>
            </button>
        </form>
    </div>
</div>

<!-- Danger Zone -->
<div class="card danger-zone">
    <h3>⚠️ Danger Zone</h3>
    
    <div class="settings-section">
        <h4>Reset Site Data</h4>
        <p class="meta">
            This will permanently delete ALL non-admin users, jobs, and VTCs. 
            Admin accounts will be preserved. This action cannot be undone!
        </p>
        
        <form method="post" style="margin-top:16px;">
            <?php echo CSRF::getTokenInput(); ?>
            <input type="hidden" name="action" value="reset_site">
            
            <div class="form-group">
                <label>
                    Type <strong>RESET</strong> to confirm:
                    <input type="text" name="confirm_text" 
                           placeholder="RESET" 
                           autocomplete="off"
                           style="max-width:200px;">
                </label>
            </div>
            
            <button type="submit" class="btn btn-danger">
                Reset All Site Data
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
