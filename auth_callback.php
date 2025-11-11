<?php
// auth_callback.php - Handle Steam OpenID authentication callback
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/steam_openid.php';
require_once __DIR__ . '/db.php';

// Process the callback using robust Steam OpenID validation
$steamId = SteamOpenID::validateLogin($_GET);

if (!$steamId) {
    // Validation failed
    $_SESSION['login_error'] = 'Steam authentication failed. Please try again.';
    header('Location: /login.php');
    exit;
}

// Get Steam profile data (use environment variable for API key if available)
$steamApiKey = getenv('STEAM_API_KEY') ?: '';
$profile = SteamOpenID::getSteamProfile($steamId, $steamApiKey);

if (!$profile) {
    $_SESSION['login_error'] = 'Failed to retrieve Steam profile.';
    header('Location: /login.php');
    exit;
}

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE steamId = :steamId LIMIT 1");
    $stmt->execute([':steamId' => $steamId]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingUser) {
        // User exists, log them in
        $userId = $existingUser['id'];
        
        // Update last login time (optional)
        $updateStmt = $pdo->prepare("UPDATE users SET avatar_url = :avatar WHERE id = :id");
        $updateStmt->execute([
            ':avatar' => $profile['avatar_url'],
            ':id' => $userId
        ]);
    } else {
        // Create new user
        $insertStmt = $pdo->prepare("
            INSERT INTO users (username, steamId, avatar_url, created_at) 
            VALUES (:username, :steamId, :avatar, NOW())
        ");
        $insertStmt->execute([
            ':username' => $profile['username'],
            ':steamId' => $steamId,
            ':avatar' => $profile['avatar_url']
        ]);
        $userId = $pdo->lastInsertId();
    }
    
    // Set session
    $_SESSION['user_id'] = (int)$userId;
    unset($_SESSION['current_user_cached']);
    
    // Regenerate session ID for security
    if (function_exists('session_regenerate_id')) {
        session_regenerate_id(true);
    }
    
    // Redirect to home
    header('Location: /');
    exit;
    
} catch (PDOException $e) {
    error_log('Steam auth DB error: ' . $e->getMessage());
    $_SESSION['login_error'] = 'Database error occurred. Please try again.';
    header('Location: /login.php');
    exit;
}
