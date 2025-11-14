#!/bin/bash
# test_implementation.sh - Verify implementation completeness

echo "=========================================="
echo "Implementation Verification Tests"
echo "=========================================="
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test counter
PASSED=0
FAILED=0

# Function to check file exists
check_file() {
    if [ -f "$1" ]; then
        echo -e "${GREEN}✓${NC} $1 exists"
        ((PASSED++))
    else
        echo -e "${RED}✗${NC} $1 missing"
        ((FAILED++))
    fi
}

# Function to check PHP syntax
check_php_syntax() {
    if php -l "$1" > /dev/null 2>&1; then
        echo -e "${GREEN}✓${NC} $1 syntax valid"
        ((PASSED++))
    else
        echo -e "${RED}✗${NC} $1 has syntax errors"
        ((FAILED++))
    fi
}

# Function to check file contains text
check_contains() {
    if grep -q "$2" "$1" 2>/dev/null; then
        echo -e "${GREEN}✓${NC} $1 contains '$2'"
        ((PASSED++))
    else
        echo -e "${RED}✗${NC} $1 missing '$2'"
        ((FAILED++))
    fi
}

echo "1. Checking required files exist..."
echo "-----------------------------------"
check_file "includes/csrf.php"
check_file "includes/steam_openid.php"
check_file "includes/steam_api.php"
check_file "includes/auth.php"
check_file "includes/header.php"
check_file "includes/footer.php"
check_file "register.php"
check_file "admin_login.php"
check_file "admin_settings.php"
check_file "vtcs.php"
check_file "vtc.php"
check_file "settings.php"
check_file "logout.php"
check_file "auth_callback.php"
check_file "login.php"
check_file "assets/style.scss"
check_file "assets/style.css"
check_file "assets/theme.js"
check_file "sql/schema.sql"
check_file "db.php.example"
echo ""

echo "2. Checking PHP syntax..."
echo "-------------------------"
check_php_syntax "includes/csrf.php"
check_php_syntax "includes/steam_openid.php"
check_php_syntax "includes/steam_api.php"
check_php_syntax "includes/auth.php"
check_php_syntax "register.php"
check_php_syntax "admin_login.php"
check_php_syntax "admin_settings.php"
check_php_syntax "vtcs.php"
check_php_syntax "vtc.php"
echo ""

echo "3. Checking CSRF implementation..."
echo "-----------------------------------"
check_contains "includes/csrf.php" "class CSRF"
check_contains "includes/csrf.php" "validateRequest"
check_contains "settings.php" "CSRF::validateRequest"
check_contains "vtcs.php" "CSRF::validateRequest"
check_contains "vtc.php" "CSRF::validateRequest"
check_contains "register.php" "CSRF::validateRequest"
check_contains "admin_login.php" "CSRF::validateRequest"
check_contains "admin_settings.php" "CSRF::validateRequest"
check_contains "logout.php" "CSRF::validateRequest"
echo ""

echo "4. Checking Steam OpenID implementation..."
echo "-------------------------------------------"
check_contains "includes/steam_openid.php" "class SteamOpenID"
check_contains "includes/steam_openid.php" "validateLogin"
check_contains "includes/steam_openid.php" "curl_init"
check_contains "auth_callback.php" "SteamOpenID::validateLogin"
check_contains "login.php" "SteamOpenID::getLoginUrl"
echo ""

echo "5. Checking Steam API integration..."
echo "-------------------------------------"
check_contains "includes/steam_api.php" "class SteamAPI"
check_contains "includes/steam_api.php" "getPlayerSummaries"
check_contains "includes/steam_api.php" "getProfileForStorage"
check_contains "auth_callback.php" "SteamAPI::getProfileForStorage"
check_contains "auth_callback.php" "last_profile_update"
check_contains "db.php.example" "STEAM_API_KEY"
echo ""

echo "6. Checking admin system..."
echo "----------------------------"
check_contains "includes/auth.php" "function is_admin"
check_contains "includes/auth.php" "function require_admin"
check_contains "register.php" "isFirstRegistration"
check_contains "register.php" "is_admin"
check_contains "admin_login.php" "password_verify"
check_contains "admin_settings.php" "require_admin"
check_contains "admin_settings.php" "registration_open"
check_contains "includes/header.php" "is_admin"
check_contains "includes/header.php" "Admin Settings"
echo ""

echo "7. Checking database schema..."
echo "-------------------------------"
check_contains "sql/schema.sql" "CREATE TABLE IF NOT EXISTS users"
check_contains "sql/schema.sql" "is_admin TINYINT"
check_contains "sql/schema.sql" "email VARCHAR"
check_contains "sql/schema.sql" "password_hash VARCHAR"
check_contains "sql/schema.sql" "last_profile_update DATETIME"
check_contains "sql/schema.sql" "CREATE TABLE IF NOT EXISTS site_settings"
check_contains "sql/schema.sql" "registration_open"
check_contains "sql/schema.sql" "CREATE TABLE IF NOT EXISTS vtcs"
check_contains "sql/schema.sql" "CREATE TABLE IF NOT EXISTS vtc_members"
echo ""

echo "8. Checking VTC functionality..."
echo "---------------------------------"
check_contains "vtcs.php" "action.*create_vtc"
check_contains "vtcs.php" "INSERT INTO vtcs"
check_contains "vtc.php" "action.*join"
check_contains "vtc.php" "action.*leave"
check_contains "vtc.php" "INSERT INTO vtc_members"
echo ""

echo "9. Checking theme system..."
echo "----------------------------"
check_contains "assets/style.scss" "data-theme.*light"
check_contains "assets/theme.js" "setTheme"
check_contains "assets/theme.js" "localStorage"
check_contains "assets/theme.js" "window.setTheme"
check_contains "includes/header.php" "theme-toggle"
echo ""

echo "10. Checking security measures..."
echo "----------------------------------"
check_contains "db.php.example" "PDO::ATTR_ERRMODE"
check_contains "db.php.example" "PDO::ERRMODE_EXCEPTION"
check_contains "register.php" "password_hash"
check_contains "admin_login.php" "password_verify"
echo ""

echo "=========================================="
echo "Test Summary"
echo "=========================================="
echo -e "${GREEN}Passed: $PASSED${NC}"
echo -e "${RED}Failed: $FAILED${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}All tests passed! ✓${NC}"
    echo ""
    echo "Next steps:"
    echo "1. Copy db.php.example to db.php and configure your database"
    echo "2. Import sql/schema.sql into your database"
    echo "3. Set STEAM_API_KEY environment variable (optional but recommended)"
    echo "4. Test locally with: php -S localhost:8000"
    echo "5. For Steam login testing, use ngrok to expose your local server"
    exit 0
else
    echo -e "${RED}Some tests failed! ✗${NC}"
    echo "Please review the errors above."
    exit 1
fi
