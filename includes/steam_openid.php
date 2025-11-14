<?php
// includes/steam_openid.php
// Robust Steam OpenID validation using cURL with comprehensive error logging

class SteamOpenID {
    private const STEAM_LOGIN_URL = 'https://steamcommunity.com/openid/login';
    private const OPENID_NS = 'http://specs.openid.net/auth/2.0';
    
    /**
     * Validate Steam OpenID response from callback
     * @param array $params GET parameters from callback
     * @return string|null Steam ID on success, null on failure
     */
    public static function validateLogin(array $params): ?string {
        // Check if we received an OpenID response
        if (empty($params['openid_mode'])) {
            error_log('Steam OpenID: No openid_mode in response');
            return null;
        }
        
        // Check if user cancelled
        if ($params['openid_mode'] === 'cancel') {
            error_log('Steam OpenID: User cancelled authentication');
            return null;
        }
        
        // Validate required OpenID parameters
        if (empty($params['openid_claimed_id'])) {
            error_log('Steam OpenID: Missing openid_claimed_id');
            return null;
        }
        
        if (empty($params['openid_sig'])) {
            error_log('Steam OpenID: Missing openid_sig');
            return null;
        }
        
        // Perform validation with Steam
        if (!self::verifyWithSteam($params)) {
            error_log('Steam OpenID: Verification with Steam failed');
            return null;
        }
        
        // Extract Steam ID from claimed_id
        $steamId = self::extractSteamId($params['openid_claimed_id']);
        
        if (!$steamId) {
            error_log('Steam OpenID: Failed to extract Steam ID from: ' . $params['openid_claimed_id']);
            return null;
        }
        
        error_log('Steam OpenID: Successfully validated Steam ID: ' . $steamId);
        return $steamId;
    }
    
    /**
     * Verify OpenID response with Steam using cURL
     * @param array $params GET parameters from callback
     * @return bool True if verification succeeds
     */
    private static function verifyWithSteam(array $params): bool {
        // Prepare validation parameters
        $validationParams = $params;
        $validationParams['openid.mode'] = 'check_authentication';
        
        // Initialize cURL
        $ch = curl_init();
        
        if (!$ch) {
            error_log('Steam OpenID: Failed to initialize cURL');
            return false;
        }
        
        // Set cURL options
        curl_setopt_array($ch, [
            CURLOPT_URL => self::STEAM_LOGIN_URL,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($validationParams),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'MyTruckTracker/1.0',
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
        ]);
        
        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        
        curl_close($ch);
        
        // Check for cURL errors
        if ($curlErrno !== 0) {
            error_log('Steam OpenID: cURL error (' . $curlErrno . '): ' . $curlError);
            return false;
        }
        
        // Check HTTP response code
        if ($httpCode !== 200) {
            error_log('Steam OpenID: HTTP error code: ' . $httpCode);
            return false;
        }
        
        // Check response content
        if ($response === false || empty($response)) {
            error_log('Steam OpenID: Empty response from Steam');
            return false;
        }
        
        // Parse response to check if validation succeeded
        if (preg_match('/is_valid\s*:\s*true/i', $response)) {
            return true;
        }
        
        error_log('Steam OpenID: Validation response did not contain is_valid:true');
        error_log('Steam OpenID: Response snippet: ' . substr($response, 0, 200));
        
        return false;
    }
    
    /**
     * Extract Steam ID from OpenID claimed_id URL
     * @param string $claimedId OpenID claimed_id (e.g., https://steamcommunity.com/openid/id/76561198012345678)
     * @return string|null Steam ID (64-bit) on success, null on failure
     */
    private static function extractSteamId(string $claimedId): ?string {
        // Steam OpenID claimed_id format: https://steamcommunity.com/openid/id/{steamid64}
        if (preg_match('#^https?://steamcommunity\.com/openid/id/(\d{17})$#', $claimedId, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Build Steam login URL for initiating OpenID authentication
     * @param string $returnTo URL to return to after authentication
     * @param string $realm OpenID realm (usually your domain)
     * @return string Steam login URL
     */
    public static function getLoginUrl(string $returnTo, string $realm): string {
        $params = [
            'openid.ns' => self::OPENID_NS,
            'openid.mode' => 'checkid_setup',
            'openid.return_to' => $returnTo,
            'openid.realm' => $realm,
            'openid.identity' => self::OPENID_NS . '/identifier_select',
            'openid.claimed_id' => self::OPENID_NS . '/identifier_select',
        ];
        
        return self::STEAM_LOGIN_URL . '?' . http_build_query($params);
    }
    
    /**
     * Fetch Steam user profile data from Steam Web API
     * @param string $steamId Steam ID (64-bit)
     * @param string $apiKey Steam Web API key
     * @return array|null User profile data on success, null on failure
     */
    public static function getSteamProfile(string $steamId, string $apiKey = ''): ?array {
        // If no API key is provided, return basic info
        if (empty($apiKey)) {
            error_log('Steam OpenID: No API key provided, returning basic profile');
            return [
                'steamId' => $steamId,
                'username' => 'User_' . substr($steamId, -6),
                'avatar_url' => '/assets/default-avatar.svg',
            ];
        }
        
        // Fetch profile from Steam Web API
        $url = 'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v2/?' . http_build_query([
            'key' => $apiKey,
            'steamids' => $steamId,
        ]);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'MyTruckTracker/1.0',
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$response) {
            error_log('Steam API: Failed to fetch profile (HTTP ' . $httpCode . ')');
            return null;
        }
        
        $data = json_decode($response, true);
        
        if (empty($data['response']['players'][0])) {
            error_log('Steam API: No player data in response');
            return null;
        }
        
        $player = $data['response']['players'][0];
        
        return [
            'steamId' => $steamId,
            'username' => $player['personaname'] ?? 'User_' . substr($steamId, -6),
            'avatar_url' => $player['avatarfull'] ?? $player['avatarmedium'] ?? $player['avatar'] ?? '/assets/default-avatar.svg',
            'profileurl' => $player['profileurl'] ?? '',
        ];
    }
}
