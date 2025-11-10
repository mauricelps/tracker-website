<?php
// profile.php - Current user's profile (redirects to user.php)
require_once __DIR__ . '/includes/auth.php';

require_login();
$user = current_user();

if (!$user) {
    header('Location: /login.php');
    exit;
}

// Redirect to user.php with current user's ID
header('Location: /user/' . $user['id']);
exit;
?>
