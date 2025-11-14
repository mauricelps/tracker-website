<?php
// register_callback.php - Handle Steam OpenID registration callback
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/steam_openid.php';
require_once __DIR__ . '/includes/steam_api.php';
require_once __DIR__ . '/db.php';

// Process the callback using robust Steam OpenID validation
$steamId = SteamOpenID::validateLogin($_GET);

if (!$steamId) {
    // Validation failed
    $_SESSION['login_error'] = 'Steam authentication failed. Please try again.';
    header('Location: /register.php');
    exit;
}

try {
    // Check if registration is open
    $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'registration_open' LIMIT 1");
    $stmt->execute();
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);
    $registrationOpen = ($setting && $setting['setting_value'] === '1');
    
    // Check if users table is empty (first registration)
    $userCountStmt = $pdo->prepare("SELECT COUNT(*) as count FROM users");
    $userCountStmt->execute();
    $userCount = $userCountStmt->fetch(PDO::FETCH_ASSOC);
    $isFirstUser = ($userCount['count'] == 0);
    
    // Allow registration if it's open OR if this is the first user
    if (!$registrationOpen && !$isFirstUser) {
        $_SESSION['login_error'] = 'Registration is currently closed.';
        header('Location: /register.php');
        exit;
    }
    
    // Check if user already exists
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE steamId = :steamId LIMIT 1");
    $checkStmt->execute([':steamId' => $steamId]);
    $existingUser = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingUser) {
        // User already exists, just log them in
        $_SESSION['user_id'] = (int)$existingUser['id'];
        unset($_SESSION['current_user_cached']);
        session_regenerate_id(true);
        header('Location: /');
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
    
    // Create new user
    // If this is the first user, make them admin
    $insertStmt = $pdo->prepare("
        INSERT INTO users (username, steamId, display_name, avatar_url, is_admin, created_at) 
        VALUES (:username, :steamId, :display_name, :avatar, :is_admin, NOW())
    ");
    $insertStmt->execute([
        ':username' => $profile['username'],
        ':steamId' => $steamId,
        ':display_name' => $profile['display_name'],
        ':avatar' => $profile['avatar_url'],
        ':is_admin' => $isFirstUser ? 1 : 0
    ]);
    $userId = $pdo->lastInsertId();
    
    // If this is the first user, close registration
    if ($isFirstUser) {
        $updateSettingStmt = $pdo->prepare("
            UPDATE site_settings 
            SET setting_value = '0' 
            WHERE setting_key = 'registration_open'
        ");
        $updateSettingStmt->execute();
        error_log('First user registered (ID: ' . $userId . '), registration closed automatically');
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
    error_log('Registration DB error: ' . $e->getMessage());
    $_SESSION['login_error'] = 'Database error occurred. Please try again.';
    header('Location: /register.php');
    exit;
}
