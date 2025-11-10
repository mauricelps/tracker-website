<?php
// logout.php - POST only
require_once __DIR__ . '/includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    logout();
}
header('Location: /');
exit;
?>