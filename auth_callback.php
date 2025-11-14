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

// Get Steam profile data using Steam Web API if key is present
$profile = null;
$steamApiKey = SteamAPI::getApiKey();

if ($steamApiKey) {
    $apiProfile = SteamAPI::getPlayerSummaries($steamId, $steamApiKey);
    if ($apiProfile) {
        $profile = [
            'steamId' => $steamId,
            'username' => $apiProfile['personaname'],
            'display_name' => $apiProfile['personaname'],
            'avatar_url' => SteamAPI::getAvatarUrl($apiProfile, 'full'),
        ];
    }
}

// Fallback if API call failed or no API key
if (!$profile) {
    $profile = [
        'steamId' => $steamId,
        'username' => 'User_' . substr($steamId, -6),
        'display_name' => 'User_' . substr($steamId, -6),
        'avatar_url' => '/assets/default-avatar.svg',
    ];
}

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE steamId = :steamId LIMIT 1");
    $stmt->execute([':steamId' => $steamId]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingUser) {
        // User exists, update their profile data when Steam API key is present
        $userId = $existingUser['id'];
        
        if ($steamApiKey) {
            // Update display_name and avatar_url from Steam API
            $updateStmt = $pdo->prepare("
                UPDATE users 
                SET display_name = :display_name, 
                    avatar_url = :avatar 
                WHERE id = :id
            ");
            $updateStmt->execute([
                ':display_name' => $profile['display_name'],
                ':avatar' => $profile['avatar_url'],
                ':id' => $userId
            ]);
        } else {
            // Only update avatar if no API key
            $updateStmt = $pdo->prepare("UPDATE users SET avatar_url = :avatar WHERE id = :id");
            $updateStmt->execute([
                ':avatar' => $profile['avatar_url'],
                ':id' => $userId
            ]);
        }
    } else {
        // Create new user
        $insertStmt = $pdo->prepare("
            INSERT INTO users (username, steamId, display_name, avatar_url, created_at) 
            VALUES (:username, :steamId, :display_name, :avatar, NOW())
        ");
        $insertStmt->execute([
            ':username' => $profile['username'],
            ':steamId' => $steamId,
            ':display_name' => $profile['display_name'],
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

