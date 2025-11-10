<?php
// auth_callback.php - Handle Steam OpenID authentication callback
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/db.php';

function validateSteamLogin(): ?string {
    // Validate OpenID response
    $params = $_GET;
    
    // Check if we received an OpenID response
    if (!isset($params['openid_mode'])) {
        return null;
    }
    
    if ($params['openid_mode'] === 'cancel') {
        return null;
    }
    
    // Validate the response with Steam
    $params['openid.mode'] = 'check_authentication';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://steamcommunity.com/openid/login');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if (!$response) {
        return null;
    }
    
    // Check if validation succeeded
    if (preg_match('/is_valid\s*:\s*true/i', $response)) {
        // Extract Steam ID from claimed_id
        if (preg_match('/^https?:\/\/steamcommunity\.com\/openid\/id\/(\d+)$/', $params['openid_claimed_id'], $matches)) {
            return $matches[1];
        }
    }
    
    return null;
}

function getSteamProfile(string $steamId): ?array {
    // You would need a Steam API key for this
    // For now, we'll just return basic info from Steam ID
    // TODO: Implement Steam API call to get profile data
    return [
        'steamId' => $steamId,
        'username' => 'User_' . substr($steamId, -6), // Temporary username
        'avatar_url' => '/assets/default-avatar.png'
    ];
}

// Process the callback
$steamId = validateSteamLogin();

if (!$steamId) {
    // Validation failed
    $_SESSION['login_error'] = 'Steam authentication failed. Please try again.';
    header('Location: /login.php');
    exit;
}

// Get Steam profile data
$profile = getSteamProfile($steamId);

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
