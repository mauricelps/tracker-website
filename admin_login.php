<?php
// admin_login.php - Admin login page (requires existing user account with is_admin=1)
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/db.php';

// If not logged in, redirect to regular login
if (!is_logged_in()) {
    header('Location: /login.php?return=' . urlencode('/admin_login.php'));
    exit;
}

$user = current_user();

// Check if user is admin
try {
    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $user['id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData || !$userData['is_admin']) {
        // Not an admin, show access denied
        $page_title = 'Access Denied';
        include __DIR__ . '/includes/header.php';
        ?>
        <div class="card" style="max-width:500px;margin:60px auto;">
            <h1 style="text-align:center;">Access Denied</h1>
            <p style="text-align:center;color:var(--text-secondary);">
                You do not have administrator privileges.
            </p>
            <div style="text-align:center;margin-top:24px;">
                <a href="/" class="btn btn-secondary">← Back to Home</a>
            </div>
        </div>
        <?php
        include __DIR__ . '/includes/footer.php';
        exit;
    }
    
    // User is admin, redirect to admin settings
    header('Location: /admin_settings.php');
    exit;
    
} catch (PDOException $e) {
    error_log('Admin check error: ' . $e->getMessage());
    die('Database error occurred.');
}
