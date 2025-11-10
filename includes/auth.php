<?php
// includes/auth.php
// Simple auth helper: session management, login/logout helpers, current user retrieval
// NOTE: place this file in includes/ and ensure db.php is one level above (../db.php)

// Start session securely if not started yet
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', 1);
    session_start([
        'cookie_lifetime' => 60*60*24*7, // 7 days
        'cookie_httponly' => true,
        // 'cookie_secure' => true, // uncomment if using HTTPS
        'use_only_cookies' => 1,
    ]);
}

// load DB (expects $pdo)
require_once __DIR__ . '/../db.php';

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array {
    global $pdo;
    if (!is_logged_in()) return null;
    if (!empty($_SESSION['current_user_cached'])) {
        return $_SESSION['current_user_cached'];
    }

    try {
        $stmt = $pdo->prepare("SELECT id, username, steamId, avatar_url FROM users WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', (int)$_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $_SESSION['current_user_cached'] = $user;
        }
        return $user ?: null;
    } catch (Throwable $e) {
        // Log the error to PHP error log for debugging (do NOT echo to users)
        error_log('current_user() DB error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Attempt login with username (or steam id) + password.
 * Returns true on success (session user_id set), false otherwise.
 */
function attempt_login(string $usernameOrSteam, string $password): bool {
    global $pdo;

    try {
        // Prepare statement with named placeholder (single placeholder used twice by binding once)
        $sql = "SELECT id, password_hash FROM users WHERE username = :u OR steamId = :u LIMIT 1";
        $stmt = $pdo->prepare($sql);

        // Bind once; ensures driver doesn't get mixed named/positional params
        $stmt->bindValue(':u', $usernameOrSteam, PDO::PARAM_STR);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            // no user found
            return false;
        }

        if (empty($row['password_hash'])) {
            // user has no password set
            return false;
        }

        if (password_verify($password, $row['password_hash'])) {
            // success
            $_SESSION['user_id'] = (int)$row['id'];
            unset($_SESSION['current_user_cached']);
            if (function_exists('session_regenerate_id')) {
                session_regenerate_id(true);
            }
            return true;
        }

        // wrong password
        return false;
    } catch (Throwable $e) {
        // Log details to server error log for debugging
        error_log('attempt_login() DB error: ' . $e->getMessage());
        // Optionally log stack trace:
        error_log($e->getTraceAsString());
        return false;
    }
}

function require_login(): void {
    if (!is_logged_in()) {
        $return = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: /login.php?return=' . urlencode($return));
        exit;
    }
}

function logout(): void {
    // Clear session safely
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    unset($_SESSION['current_user_cached']);
}
?>