<?php
// includes/steam_api.php
// Steam Web API integration for fetching player summaries

class SteamAPI {
    private const API_BASE_URL = 'https://api.steampowered.com';
    
    /**
     * Fetch Steam user profile data from Steam Web API GetPlayerSummaries
     * @param string $steamId Steam ID (64-bit)
     * @param string $apiKey Steam Web API key
     * @return array|null User profile data on success, null on failure
     */
    public static function getPlayerSummaries(string $steamId, string $apiKey): ?array {
        if (empty($apiKey)) {
            error_log('Steam API: No API key provided');
            return null;
        }
        
        if (empty($steamId)) {
            error_log('Steam API: No Steam ID provided');
            return null;
        }
        
        // Build API URL
        $url = self::API_BASE_URL . '/ISteamUser/GetPlayerSummaries/v2/?' . http_build_query([
            'key' => $apiKey,
            'steamids' => $steamId,
        ]);
        
        // Initialize cURL
        $ch = curl_init();
        
        if (!$ch) {
            error_log('Steam API: Failed to initialize cURL');
            return null;
        }
        
        // Set cURL options
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'MyTruckTracker/1.0',
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        
        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        
        curl_close($ch);
        
        // Check for cURL errors
        if ($curlErrno !== 0) {
            error_log('Steam API: cURL error (' . $curlErrno . '): ' . $curlError);
            return null;
        }
        
        // Check HTTP response code
        if ($httpCode !== 200) {
            error_log('Steam API: HTTP error code: ' . $httpCode);
            return null;
        }
        
        // Check response content
        if ($response === false || empty($response)) {
            error_log('Steam API: Empty response from Steam API');
            return null;
        }
        
        // Parse JSON response
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Steam API: JSON decode error: ' . json_last_error_msg());
            return null;
        }
        
        // Validate response structure
        if (empty($data['response']['players'][0])) {
            error_log('Steam API: No player data in response for Steam ID: ' . $steamId);
            return null;
        }
        
        $player = $data['response']['players'][0];
        
        // Return normalized player data
        return [
            'steamId' => $steamId,
            'personaname' => $player['personaname'] ?? '',
            'profileurl' => $player['profileurl'] ?? '',
            'avatar' => $player['avatar'] ?? '',
            'avatarmedium' => $player['avatarmedium'] ?? '',
            'avatarfull' => $player['avatarfull'] ?? '',
            'personastate' => $player['personastate'] ?? 0,
            'communityvisibilitystate' => $player['communityvisibilitystate'] ?? 1,
            'profilestate' => $player['profilestate'] ?? 0,
            'lastlogoff' => $player['lastlogoff'] ?? 0,
            'timecreated' => $player['timecreated'] ?? 0,
        ];
    }
    
    /**
     * Get best available avatar URL from player data
     * @param array $playerData Player data from getPlayerSummaries
     * @param string $size Size preference: 'full', 'medium', or 'small'
     * @return string Avatar URL
     */
    public static function getAvatarUrl(array $playerData, string $size = 'full'): string {
        $default = '/assets/default-avatar.svg';
        
        if (empty($playerData)) {
            return $default;
        }
        
        // Try to get requested size first, then fall back
        switch ($size) {
            case 'full':
                return $playerData['avatarfull'] ?? $playerData['avatarmedium'] ?? $playerData['avatar'] ?? $default;
            case 'medium':
                return $playerData['avatarmedium'] ?? $playerData['avatar'] ?? $playerData['avatarfull'] ?? $default;
            case 'small':
            default:
                return $playerData['avatar'] ?? $playerData['avatarmedium'] ?? $playerData['avatarfull'] ?? $default;
        }
    }
    
    /**
     * Check if Steam API key is configured
     * @return bool True if API key is available
     */
    public static function isConfigured(): bool {
        $apiKey = getenv('STEAM_API_KEY');
        return !empty($apiKey);
    }
    
    /**
     * Get Steam API key from environment
     * @return string|null API key or null if not set
     */
    public static function getApiKey(): ?string {
        $apiKey = getenv('STEAM_API_KEY');
        return !empty($apiKey) ? $apiKey : null;
    }
}
