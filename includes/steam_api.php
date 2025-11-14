<?php
// includes/steam_api.php
// Steam WebAPI wrapper for fetching user profile data

class SteamAPI {
    private const API_BASE = 'https://api.steampowered.com';
    
    /**
     * Fetch player profile summaries from Steam WebAPI
     * @param string|array $steamIds Steam ID(s) to fetch
     * @param string $apiKey Steam WebAPI key
     * @return array|null Player data on success, null on failure
     */
    public static function getPlayerSummaries($steamIds, string $apiKey): ?array {
        if (empty($apiKey)) {
            error_log('Steam API: No API key provided');
            return null;
        }
        
        // Convert single ID to array for consistent handling
        if (!is_array($steamIds)) {
            $steamIds = [$steamIds];
        }
        
        $url = self::API_BASE . '/ISteamUser/GetPlayerSummaries/v2/?' . http_build_query([
            'key' => $apiKey,
            'steamids' => implode(',', $steamIds),
        ]);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'MyTruckTracker/1.0',
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);
        
        if ($curlErrno !== 0) {
            error_log('Steam API: cURL error (' . $curlErrno . '): ' . $curlError);
            return null;
        }
        
        if ($httpCode !== 200) {
            error_log('Steam API: HTTP error code: ' . $httpCode);
            return null;
        }
        
        if (!$response) {
            error_log('Steam API: Empty response');
            return null;
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Steam API: JSON decode error: ' . json_last_error_msg());
            return null;
        }
        
        if (empty($data['response']['players'])) {
            error_log('Steam API: No players in response');
            return null;
        }
        
        return $data['response']['players'];
    }
    
    /**
     * Get profile data for a single Steam user
     * @param string $steamId Steam ID (64-bit)
     * @param string $apiKey Steam WebAPI key
     * @return array|null Profile data with keys: steamid, personaname, avatar, avatarmedium, avatarfull
     */
    public static function getUserProfile(string $steamId, string $apiKey): ?array {
        $players = self::getPlayerSummaries($steamId, $apiKey);
        
        if (empty($players[0])) {
            return null;
        }
        
        return $players[0];
    }
    
    /**
     * Extract clean profile data for database storage
     * @param string $steamId Steam ID
     * @param string $apiKey Steam WebAPI key (optional)
     * @return array Profile data with keys: display_name, avatar_url
     */
    public static function getProfileForStorage(string $steamId, string $apiKey = ''): array {
        // Default fallback values
        $profile = [
            'display_name' => 'User_' . substr($steamId, -6),
            'avatar_url' => '/assets/default-avatar.png',
        ];
        
        // If no API key, return defaults
        if (empty($apiKey)) {
            error_log('Steam API: No API key provided, using defaults');
            return $profile;
        }
        
        // Fetch from Steam API
        $steamProfile = self::getUserProfile($steamId, $apiKey);
        
        if ($steamProfile) {
            $profile['display_name'] = $steamProfile['personaname'] ?? $profile['display_name'];
            $profile['avatar_url'] = $steamProfile['avatarfull'] ?? 
                                      $steamProfile['avatarmedium'] ?? 
                                      $steamProfile['avatar'] ?? 
                                      $profile['avatar_url'];
        }
        
        return $profile;
    }
}
