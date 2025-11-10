<?php
// includes/csrf.php
// CSRF Protection Utility

class CSRF {
    private const TOKEN_NAME = 'csrf_token';
    private const TOKEN_TIME_NAME = 'csrf_token_time';
    private const TOKEN_LIFETIME = 3600; // 1 hour

    /**
     * Generate a new CSRF token and store it in the session
     * @return string The generated token
     */
    public static function generateToken(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION[self::TOKEN_NAME] = $token;
        $_SESSION[self::TOKEN_TIME_NAME] = time();
        
        return $token;
    }

    /**
     * Get the current CSRF token, generating one if it doesn't exist or is expired
     * @return string The current token
     */
    public static function getToken(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if token exists and is not expired
        if (
            !empty($_SESSION[self::TOKEN_NAME]) && 
            !empty($_SESSION[self::TOKEN_TIME_NAME]) &&
            (time() - $_SESSION[self::TOKEN_TIME_NAME]) < self::TOKEN_LIFETIME
        ) {
            return $_SESSION[self::TOKEN_NAME];
        }
        
        // Generate new token if expired or doesn't exist
        return self::generateToken();
    }

    /**
     * Get HTML input field for CSRF token
     * @return string HTML hidden input field
     */
    public static function getTokenInput(): string {
        $token = self::getToken();
        return '<input type="hidden" name="' . self::TOKEN_NAME . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Get HTML meta tag for CSRF token (for AJAX requests)
     * @return string HTML meta tag
     */
    public static function getTokenMeta(): string {
        $token = self::getToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Validate CSRF token from request
     * @param array|null $data Optional data array (defaults to $_POST)
     * @return bool True if valid, false otherwise
     */
    public static function validateToken(?array $data = null): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $data = $data ?? $_POST;
        
        // Check if token exists in request
        if (empty($data[self::TOKEN_NAME])) {
            return false;
        }
        
        // Check if token exists in session
        if (empty($_SESSION[self::TOKEN_NAME])) {
            return false;
        }
        
        // Check if token is expired
        if (
            empty($_SESSION[self::TOKEN_TIME_NAME]) ||
            (time() - $_SESSION[self::TOKEN_TIME_NAME]) >= self::TOKEN_LIFETIME
        ) {
            return false;
        }
        
        // Compare tokens using timing-safe comparison
        return hash_equals($_SESSION[self::TOKEN_NAME], $data[self::TOKEN_NAME]);
    }

    /**
     * Validate CSRF token and exit with error if invalid
     * @param array|null $data Optional data array (defaults to $_POST)
     * @param string $errorMessage Optional custom error message
     */
    public static function validateRequest(?array $data = null, string $errorMessage = 'Invalid CSRF token'): void {
        if (!self::validateToken($data)) {
            http_response_code(403);
            die($errorMessage);
        }
    }
}
