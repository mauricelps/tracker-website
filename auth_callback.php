<?php
// auth_callback.php - Handle Steam OpenID authentication callback
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/steam_openid.php';
require_once __DIR__ . '/includes/steam_api.php';
require_once __DIR__ . '/db.php';

// Process the callback using robust Steam OpenID validation
$steamId = SteamOpenID::validateLogin($_GET);

if (!$steamId) {
    // Validation failed
    $_SESSION['login_error'] = 'Steam authentication failed. Please try again.';
    header('Location: /login.php');
    exit;
}

// Get Steam profile data using Steam API if key is available
$profile = SteamAPI::getProfileForStorage($steamId, STEAM_API_KEY);

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE steamId = :steamId LIMIT 1");
    $stmt->execute([':steamId' => $steamId]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingUser) {
        // User exists, update profile from Steam API
        $userId = $existingUser['id'];
        
        // Update profile data and last_profile_update timestamp
        $updateStmt = $pdo->prepare("
            UPDATE users 
            SET display_name = :display_name,
                avatar_url = :avatar_url,
                last_profile_update = NOW()
            WHERE id = :id
        ");
        $updateStmt->execute([
            ':display_name' => $profile['display_name'],
            ':avatar_url' => $profile['avatar_url'],
            ':id' => $userId
        ]);
    } else {
        // Create new user with Steam API profile data
        $insertStmt = $pdo->prepare("
            INSERT INTO users (username, steamId, display_name, avatar_url, last_profile_update, created_at) 
            VALUES (:username, :steamId, :display_name, :avatar_url, NOW(), NOW())
        ");
        $insertStmt->execute([
            ':username' => $profile['display_name'],
            ':steamId' => $steamId,
            ':display_name' => $profile['display_name'],
            ':avatar_url' => $profile['avatar_url']
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
