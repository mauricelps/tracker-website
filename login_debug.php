<?php
// Temporary debug login helper - remove after use!
// Writes debug info to login_debug_log.txt in the same dir (or /tmp if writable)
$logFile = __DIR__ . '/login_debug_log.txt';
function dbg($m) {
    global $logFile;
    $t = date('Y-m-d H:i:s');
    @file_put_contents($logFile, "[$t] " . $m . PHP_EOL, FILE_APPEND);
}

// Basic protections for debug script: optional token via GET to avoid public use
$token = 'replace_with_short_secret'; // set a secret before uploading!
if (!isset($_GET['token']) || $_GET['token'] !== $token) {
    http_response_code(403);
    echo "Forbidden - missing token";
    exit;
}

dbg("=== Request start ===");
dbg("REMOTE_ADDR: " . ($_SERVER['REMOTE_ADDR'] ?? ''));
dbg("Method: " . $_SERVER['REQUEST_METHOD']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    dbg("POST keys: " . implode(',', array_keys($_POST)));
    // Start session and log status
    try {
        session_start();
        dbg("Session started, id=" . session_id() . ", status=" . session_status());
    } catch (Throwable $e) {
        dbg("Session start failed: " . $e->getMessage());
    }

    // Try to include auth helper (if exists) and log errors
    try {
        if (file_exists(__DIR__ . '/includes/auth.php')) {
            dbg("includes/auth.php exists, attempting require_once");
            require_once __DIR__ . '/includes/auth.php';
            dbg("includes/auth.php loaded");
        } else {
            dbg("includes/auth.php not found");
        }
    } catch (Throwable $e) {
        dbg("Error including auth.php: " . $e->getMessage());
    }

    // Log POST values (don't log passwords in production; ok for temporary debug)
    foreach ($_POST as $k => $v) {
        $val = $k === 'password' ? '[REDACTED]' : $v;
        dbg("POST $k = " . $val);
    }

    // Attempt a DB connection test
    try {
        if (file_exists(__DIR__ . '/db.php')) {
            require_once __DIR__ . '/db.php';
            if (isset($pdo) && $pdo instanceof PDO) {
                dbg("PDO present, testing simple query...");
                $r = $pdo->query("SELECT 1")->fetchColumn();
                dbg("DB test query result: " . var_export($r, true));
            } else {
                dbg("PDO not available after db.php include.");
            }
        } else {
            dbg("db.php not found");
        }
    } catch (Throwable $e) {
        dbg("DB error: " . $e->getMessage());
    }

    // Try to call attempt_login() if function exists
    if (function_exists('attempt_login')) {
        try {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            dbg("Calling attempt_login with username=" . $username);
            $res = attempt_login($username, $password);
            dbg("attempt_login returned: " . ($res ? 'true' : 'false'));
            if ($res && !empty($_SESSION['user_id'])) {
                dbg("Login succeeded. session user_id=" . $_SESSION['user_id']);
            }
        } catch (Throwable $e) {
            dbg("attempt_login threw: " . $e->getMessage());
        }
    } else {
        dbg("attempt_login() not defined in includes/auth.php");
    }

    dbg("=== Request end ===");
    header('Content-Type: text/plain');
    echo "Logged. Check login_debug_log.txt in webroot.";
    exit;
}

// GET: show a small form to POST to this script
?>
<!doctype html><html><head><meta charset="utf-8"><title>Login Debug</title></head><body style="background:#111;color:#ddd;padding:20px;font-family:sans-serif;">
<h1>Login Debug</h1>
<p>Use this form to reproduce the login POST. Remove file after use.</p>
<form method="post">
<label>Username<input name="username"></label><br><label>Password<input type="password" name="password"></label><br><button type="submit">Submit</button>
</form>
</body></html>