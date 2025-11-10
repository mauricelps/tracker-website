<?php
// One-time password hash generator for environments without CLI (e.g. Plesk).
// USAGE:
// 1) Set $secretToken to a strong random value.
// 2) Upload this file to your server (HTTPS).
// 3) Visit: https://your-domain.tld/generate_password_hash.php?token=YOUR_SECRET
// 4) Enter password, get hash, copy to DB, DELETE THIS FILE afterwards.

// --- CONFIG ---
$secretToken = 'MauriceAlexanderHöfner020420003449'; // <-- SET THIS before uploading
// Optional: limit access by allowed IPs (leave empty to allow all)
$allowedIps = []; // e.g. ['1.2.3.4', '5.6.7.8']

// --- End CONFIG ---
header('Content-Type: text/html; charset=utf-8');

$provided = $_GET['token'] ?? $_POST['token'] ?? '';

if (empty($provided) || $provided !== $secretToken) {
    http_response_code(403);
    echo "<h1>403 Forbidden</h1><p>Invalid token.</p>";
    exit;
}

if (!empty($allowedIps) && !in_array($_SERVER['REMOTE_ADDR'], $allowedIps, true)) {
    http_response_code(403);
    echo "<h1>403 Forbidden</h1><p>Your IP is not allowed to use this tool.</p>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $pw = $_POST['password'];
    if ($pw === '') {
        echo "<p style='color:#c33;'>Empty password provided.</p>";
    } else {
        // Generate password hash using PHP's password_hash (bcrypt or default)
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        // Output safely
        echo "<h2>Password hash</h2>";
        echo "<textarea rows='4' cols='80' readonly>" . htmlspecialchars($hash) . "</textarea>";
        echo "<p style='color:#9aa3a8;'>Copy this hash into your DB (UPDATE users SET password_hash = '...').</p>";
        echo "<hr>";
        echo "<p style='color:#c33;font-weight:700;'>IMPORTANT: Delete this file from the server immediately after use.</p>";
        exit;
    }
}

// Show form
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Generate password hash</title>
<style>
body { background:#1a1d24;color:#e0e0e0;font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial; padding:20px; }
.container { max-width:720px;margin:auto;background:#252830;padding:18px;border-radius:8px;border:1px solid #333; }
h1 { color:#4CAF50;margin-top:0; }
input[type="password"] { width:100%; padding:10px;border-radius:6px;border:1px solid #333;background:#121317;color:#e0e0e0; }
button { margin-top:10px;padding:10px 14px;border-radius:6px;border:none;background:#4CAF50;color:#07110b;font-weight:700; }
small { color:#9aa3a8; display:block;margin-top:8px; }
</style>
</head>
<body>
<div class="container">
  <h1>Generate password hash</h1>
  <p>Enter password to create a PHP <code>password_hash()</code>. This page requires the secret token in the URL and should be deleted after use.</p>
  <form method="post">
    <input type="hidden" name="token" value="<?php echo htmlspecialchars($provided); ?>">
    <label>
      Password:
      <input type="password" name="password" required autocomplete="new-password">
    </label>
    <button type="submit">Generate Hash</button>
  </form>
  <small>Do NOT leave this file on the server. Delete it immediately after copying the hash into your database.</small>
</div>
</body>
</html>