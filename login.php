<?php
// login.php - simple login form & processing (no registration)
require_once __DIR__ . '/includes/auth.php';

$err = '';
$return = isset($_GET['return']) ? $_GET['return'] : '/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $return = $_POST['return'] ?? '/';
    if (attempt_login(trim($username), $password)) {
        header('Location: ' . ($return ?: '/'));
        exit;
    } else {
        $err = 'Invalid credentials.';
    }
}

$page_title = 'Login';
include __DIR__ . '/includes/header.php';
?>

<h1>Login</h1>
<?php if ($err): ?>
    <div style="background:#3a1b1b;color:#ffdede;padding:10px;border-radius:6px;margin-bottom:12px;"><?php echo htmlspecialchars($err); ?></div>
<?php endif; ?>

<form method="post" style="max-width:420px;">
    <input type="hidden" name="return" value="<?php echo htmlspecialchars($return); ?>">
    <label style="display:block;margin-bottom:8px;color:#9aa3a8;">Username or SteamID
        <input name="username" required style="width:100%;padding:10px;margin-top:6px;border-radius:6px;border:1px solid #333;background:#121317;color:#e0e0e0;">
    </label>
    <label style="display:block;margin-bottom:8px;color:#9aa3a8;">Password
        <input name="password" type="password" required style="width:100%;padding:10px;margin-top:6px;border-radius:6px;border:1px solid #333;background:#121317;color:#e0e0e0;">
    </label>
    <div style="margin-top:12px;">
        <button type="submit" style="background:#4CAF50;color:#07110b;border:none;padding:10px 14px;border-radius:6px;font-weight:600;">Login</button>
        <a href="/" style="margin-left:12px;color:#9aa3a8;">Cancel</a>
    </div>
</form>

<?php include __DIR__ . '/includes/footer.php'; ?>