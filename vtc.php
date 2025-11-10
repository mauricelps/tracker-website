<?php
// vtc.php - Single VTC view with join action (placeholder)
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/db.php';

$page_title = 'VTC Details';
include __DIR__ . '/includes/header.php';

// Note: This is a placeholder. VTC functionality requires database tables.
$vtc_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
?>

<h1>VTC Details</h1>

<div class="card">
    <p class="meta">
        VTC functionality is not yet implemented. This page will display detailed information 
        about a specific Virtual Trucking Company, including:
    </p>
    
    <ul>
        <li>Company information (name, tag, description)</li>
        <li>Member list with roles</li>
        <li>Company statistics (total deliveries, kilometers, etc.)</li>
        <li>Join/Leave actions with CSRF protection</li>
    </ul>
    
    <?php if (is_logged_in()): ?>
    <h3 style="margin-top:24px;">Example Join Form (with CSRF protection):</h3>
    <form method="post" action="/vtc_join.php" style="max-width:400px;">
        <?php echo CSRF::getTokenInput(); ?>
        <input type="hidden" name="vtc_id" value="<?php echo $vtc_id; ?>">
        <button type="submit" class="btn" disabled>
            Join VTC (Not Yet Available)
        </button>
    </form>
    
    <h3 style="margin-top:24px;">Example Leave Form (with CSRF protection):</h3>
    <form method="post" action="/vtc_leave.php" style="max-width:400px;">
        <?php echo CSRF::getTokenInput(); ?>
        <input type="hidden" name="vtc_id" value="<?php echo $vtc_id; ?>">
        <button type="submit" class="btn btn-secondary" disabled>
            Leave VTC (Not Yet Available)
        </button>
    </form>
    <?php else: ?>
    <p class="meta" style="margin-top:24px;">
        Please <a href="/login.php">login</a> to join a VTC.
    </p>
    <?php endif; ?>
</div>

<div style="margin-top:16px;">
    <a href="/vtcs.php" class="btn btn-secondary">← Back to VTC List</a>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
