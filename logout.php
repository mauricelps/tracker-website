<?php
// logout.php - POST only with CSRF protection
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::validateRequest();
    logout();
}
header('Location: /');
exit;
?>