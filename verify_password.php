<?php
// One-time verification script to check a plaintext password against DB hash.
// IMPORTANT: Set $secretToken before uploading. Delete file after use.

$secretToken = 'MauriceH'; // <-- SET BEFORE UPLOAD

if (!isset($_GET['token']) || $_GET['token'] !== $secretToken) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        echo "Provide username and password.";
        exit;
    }

    require_once __DIR__ . '/db.php'; // expects $pdo

    try {
        $stmt = $pdo->prepare("SELECT id, username, steamId, password_hash FROM users WHERE username = :u OR steamId = :u LIMIT 1");
        $stmt->bindValue(':u', $username, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        echo "DB error: " . htmlspecialchars($e->getMessage());
        exit;
    }

    if (!$row) {
        echo "No user found for '".htmlspecialchars($username)."'.";
        exit;
    }

    if (empty($row['password_hash'])) {
        echo "User found (id={$row['id']}, username=".htmlspecialchars($row['username'])."), but password_hash is empty.";
        exit;
    }

    $ok = password_verify($password, $row['password_hash']);
    echo "User id: " . (int)$row['id'] . " - username: " . htmlspecialchars($row['username']) . "<br>";
    echo "password_verify: " . ($ok ? "<strong style='color:green'>MATCH</strong>" : "<strong style='color:red'>NO MATCH</strong>") . "<br>";
    echo "Hash preview: " . htmlspecialchars(substr($row['password_hash'], 0, 60)) . "...";
    exit;
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>Verify password</title></head><body style="background:#111;color:#ddd;font-family:sans-serif;padding:20px;">
<h1>Verify Password</h1>
<form method="post">
<label>Username or SteamID<br><input name="username" style="width:320px"></label><br><br>
<label>Password<br><input name="password" type="password" style="width:320px"></label><br><br>
<button type="submit">Verify</button>
</form>
<p style="color:#f99">Delete this file immediately after use.</p>
</body></html>