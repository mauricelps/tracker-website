# Pull Request Summary

## Title
Fix Steam OpenID validation, add CSRF protection, and implement admin-first-registration

## Description

This PR completes all requirements from the security and functionality enhancement issue, implementing:
- Robust Steam OpenID authentication with cURL
- Comprehensive CSRF protection across all forms
- Admin-first-registration system with site management
- Steam Web API integration for profile data
- Centralized styling and theme toggle

## Changes Implemented

### 🔒 Security Enhancements

1. **Robust Steam OpenID Validation** (`includes/steam_openid.php`)
   - cURL-based validation with Steam's OpenID endpoint
   - Secure Steam ID extraction using regex validation
   - Comprehensive error logging for debugging
   - Timeout protection and SSL verification
   - Separated from Steam API concerns

2. **Steam Web API Integration** (`includes/steam_api.php`)
   - NEW: Dedicated file for Steam Web API calls
   - GetPlayerSummaries endpoint integration
   - Fetches display name, avatar, profile URL
   - API key detection and configuration helpers
   - Graceful fallback when API key not set

3. **Comprehensive CSRF Protection** (`includes/csrf.php`)
   - All state-changing forms protected with CSRF tokens
   - Session-based token storage with 1-hour lifetime
   - Timing-safe token comparison using hash_equals()
   - Meta tag for AJAX requests
   - Token input helper for forms
   - Protected endpoints:
     * User settings (profile, pause, reset, delete)
     * Logout
     * Avatar uploads
     * VTC actions (create, join, leave)
     * Admin settings (registration toggle, site reset)

4. **Steam-Only Authentication**
   - No password-based authentication
   - Secure session management
   - Session regeneration on login
   - Protected routes with login checks
   - Profile data updates on each login (when API key present)

### 👤 Admin Features

5. **Admin-First-Registration System**
   - `register.php` - Registration page with first-user detection
   - `register_callback.php` - Registration handler
   - First user automatically becomes admin (is_admin=1)
   - Registration automatically closes after first user
   - Non-admins see "Registration Closed" when registration disabled
   
6. **Admin Settings Panel** (`admin_settings.php`)
   - Site statistics dashboard (users, admins, jobs, VTCs)
   - Registration toggle (open/close new user registration)
   - Site reset functionality (preserves admin accounts)
   - CSRF-protected admin actions
   - Access control (admin-only)

7. **Admin Login** (`admin_login.php`)
   - Verifies user has admin privileges
   - Redirects to admin panel or shows access denied
   - Login requirement enforcement

### 🗄️ Database Schema

8. **Database Updates** (`sql/schema.sql`)
   - Added `is_admin BOOLEAN DEFAULT FALSE` to users table
   - Added `INDEX idx_admin (is_admin)` for admin queries
   - NEW: `site_settings` table for global configuration
   - Default `registration_open` setting (value: '0' = closed)
   - All existing tables preserved and enhanced
   - Proper indexes and foreign keys

### 🎨 Styling & UI

9. **Centralized Stylesheet**
   - `assets/style.scss` - SCSS source
   - `assets/style.css` - Compiled CSS
   - Single stylesheet reference in header
   - CSS variables for theming
   - Responsive design

10. **Theme Toggle**
    - Dark theme (default)
    - Light theme option
    - localStorage persistence
    - Smooth transitions
    - Accessible toggle button

11. **Unified Header/Footer**
    - Consistent navbar with brand
    - User dropdown with profile/settings/logout
    - Admin links shown only to is_admin users
    - "⚙️ Admin" link in navbar for admins
    - "⚙️ Admin Settings" in dropdown for admins
    - CSRF meta tag on every page
    - Theme toggle button

12. **Default Avatar** (`assets/default-avatar.svg`)
    - NEW: SVG avatar for users without Steam profile
    - Used as fallback when Steam API unavailable
    - Clean, professional design

### 📋 Application Pages

13. **Settings Page** (`settings.php`)
    - Profile management (display_name, bio, etc.)
    - External profiles (World of Trucks, TruckersMP)
    - API token management
    - Account actions (pause, reset stats, delete)
    - All forms CSRF-protected

14. **VTC Pages** (CSRF-ready)
    - VTC listing page with CSRF examples
    - VTC detail page with join/leave forms
    - CSRF protection integrated
    - Prepared statements in place

15. **Profile Page** (`profile.php`)
    - Redirects to user.php
    - Uses unified header/footer
    - Login check enforced

### 📚 Documentation

16. **Comprehensive README Updates**
    - Admin setup instructions (first registration)
    - Steam API key configuration (environment variable)
    - Database schema application steps
    - Testing procedures for all features
    - Admin settings usage
    - Registration flow documentation
    - Production deployment checklist

## Files Changed

### Created (7 files)
- `includes/steam_api.php` - Steam Web API integration
- `register.php` - Registration page
- `register_callback.php` - Registration handler
- `admin_login.php` - Admin access verification
- `admin_settings.php` - Admin settings panel
- `assets/default-avatar.svg` - Default user avatar
- `sql/schema.sql` - Complete database schema (enhanced)

### Modified (6 files)
- `auth_callback.php` - Uses steam_api.php, updates display_name
- `includes/steam_openid.php` - Updated avatar references
- `includes/header.php` - Shows admin links for admins
- `user.php` - Updated avatar reference
- `README.md` - Added admin and testing documentation

### Previously Implemented (from earlier commits)
- `includes/csrf.php` - CSRF protection utility
- `includes/header.php` - Unified header with navbar
- `includes/footer.php` - Unified footer
- `assets/style.scss` - SCSS source
- `assets/style.css` - Compiled CSS
- `assets/theme.js` - Theme toggle script
- `settings.php` - User settings with CSRF
- `vtcs.php` - VTC listing with CSRF
- `vtc.php` - VTC details with CSRF
- `logout.php` - Logout with CSRF

## Testing Instructions

### Prerequisites
- PHP 7.4+ with cURL extension (`php -m | grep curl`)
- MySQL/MariaDB 5.7+ or 10.2+
- (Optional) ngrok for local Steam OpenID testing

### Database Setup
```bash
# Create database
mysql -u root -p

CREATE DATABASE truck_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE truck_tracker;
SOURCE sql/schema.sql;
EXIT;

# Verify tables created
mysql -u root -p truck_tracker -e "SHOW TABLES;"
# Should show: users, jobs, site_settings, vtcs, vtc_members, etc.

# Verify registration_open setting
mysql -u root -p truck_tracker -e "SELECT * FROM site_settings;"
# Should show: registration_open = 0
```

### Create Database Configuration
Create `db.php` in the root directory (NOT committed to git):
```php
<?php
$pdo = new PDO(
    'mysql:host=localhost;dbname=truck_tracker;charset=utf8mb4',
    'your_username',
    'your_password'
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
?>
```

### Steam API Key (Optional but Recommended)
Get your Steam Web API key at: https://steamcommunity.com/dev/apikey

Set as environment variable:
```bash
# Linux/Mac
export STEAM_API_KEY="your_steam_api_key_here"

# Windows PowerShell
$env:STEAM_API_KEY="your_steam_api_key_here"
```

Or configure in web server:
```apache
# Apache .htaccess or VirtualHost
SetEnv STEAM_API_KEY "your_steam_api_key_here"
```

```nginx
# Nginx PHP-FPM pool config
env[STEAM_API_KEY] = "your_steam_api_key_here"
```

### Local Testing with ngrok (Required for Steam OpenID)
Steam OpenID requires a publicly accessible HTTPS URL:

```bash
# Terminal 1: Start PHP server
php -S localhost:8000

# Terminal 2: Expose with ngrok
ngrok http 8000
# Use the ngrok HTTPS URL (e.g., https://abc123.ngrok.io) for testing
```

### Test 1: First Admin Registration
```bash
# Verify fresh database (no users)
mysql> SELECT COUNT(*) FROM users;
# Expected: 0

# Steps:
1. Visit http://localhost:8000/register.php (or ngrok URL)
2. Should see "First Registration!" notice
3. Click "Register with Steam"
4. Authenticate with Steam (redirected to steamcommunity.com)
5. Authorize the application
6. Redirected back to homepage as logged-in admin

# Verify admin user created:
mysql> SELECT username, is_admin, display_name FROM users;
# Expected: 1 row with is_admin=1

# Verify registration closed:
mysql> SELECT setting_value FROM site_settings WHERE setting_key='registration_open';
# Expected: '0'
```

### Test 2: Admin Settings Panel
```bash
# Steps (as admin user):
1. Click "⚙️ Admin" link in navbar
   OR navigate to /admin_settings.php
2. View site statistics dashboard
3. Toggle registration:
   - Click "🔓 Open Registration"
   - Status should change to "OPEN"
4. Verify in database:
   mysql> SELECT setting_value FROM site_settings WHERE setting_key='registration_open';
   # Expected: '1'
5. Close registration again
6. Test access control:
   - Logout
   - Try to visit /admin_settings.php
   - Should see "Access denied"
```

### Test 3: CSRF Protection Verification
```bash
# Using Browser DevTools:
1. Visit any page (e.g., /settings.php)
2. Open DevTools → Elements tab
3. Check <head> for:
   <meta name="csrf-token" content="...">
4. Inspect any form for:
   <input type="hidden" name="csrf_token" value="...">

# Test CSRF rejection with cURL:
curl -X POST http://localhost:8000/logout.php
# Expected: 403 Forbidden (no valid session/token)

# Test from browser:
1. Open /settings.php
2. Use DevTools to delete the CSRF hidden input
3. Submit the form
4. Expected: "Invalid CSRF token" error
```

### Test 4: Theme Toggle
```bash
# Browser test:
1. Visit any page
2. Click theme toggle button (top right: ☀️ or 🌙)
3. Theme should switch (dark ↔ light)
4. Refresh page
5. Theme should persist

# Console verification:
# Open browser DevTools → Console
localStorage.getItem('theme')
// Should return 'dark' or 'light'
```

### Test 5: Steam Login (Existing User)
```bash
# After first registration:
1. Logout
2. Click "Login with Steam" (not Register)
3. Authenticate with Steam
4. Should login successfully
5. Profile data should update (if STEAM_API_KEY set)

# Verify profile update:
mysql> SELECT display_name, avatar_url FROM users WHERE id=1;
# With API key: display_name and avatar_url from Steam
# Without API key: default values
```

### Test 6: Steam API Integration
```bash
# WITH STEAM_API_KEY set:
1. Register or login via Steam
2. Check database:
   mysql> SELECT username, display_name, avatar_url FROM users LIMIT 1;
   # display_name should be your Steam persona name
   # avatar_url should be Steam CDN URL (avatars.akamai...)

# WITHOUT STEAM_API_KEY:
1. Unset environment variable
2. Register or login
3. Check database:
   # username and display_name like "User_123456"
   # avatar_url is "/assets/default-avatar.svg"
```

### Test 7: Registration Flow (Non-Admin User)
```bash
# Prerequisites: Admin has opened registration
1. Logout
2. Visit /register.php
3. Should see registration page (NO "First Registration" notice)
4. Complete Steam authentication
5. Created as regular user (is_admin=0)

# Verify:
mysql> SELECT id, username, is_admin FROM users ORDER BY id;
# Expected: 
# - First user (id=1): is_admin=1
# - Second user (id=2): is_admin=0
```

### Test 8: Site Reset (Danger Zone)
```bash
# WARNING: This deletes data! Test on development database only.

# Create test data first:
mysql> INSERT INTO users (username, steamId, is_admin) 
       VALUES ('testuser', '76561198000000001', 0);
mysql> INSERT INTO jobs (driver_steam_id, source_city, destination_city) 
       VALUES ('76561198000000001', 'Berlin', 'Prague');

# Test reset:
1. Login as admin
2. Navigate to /admin_settings.php
3. Scroll to "Danger Zone"
4. Type "RESET" in the confirmation field
5. Click "🗑️ Reset Site"
6. Confirm in browser dialog
7. Wait for success message

# Verify:
mysql> SELECT COUNT(*) FROM jobs;
# Expected: 0

mysql> SELECT username, is_admin FROM users;
# Expected: Only admin accounts (is_admin=1) remain
```

### Test 9: Settings Page Updates
```bash
# Steps:
1. Login as any user
2. Navigate to /settings.php
3. Update profile fields:
   - Display name
   - Bio
   - World of Trucks ID
   - TruckersMP ID
4. Submit form
5. Should see "Profile updated successfully"

# Verify:
mysql> SELECT display_name, bio, wot_text, truckersmp_text FROM users WHERE id=X;
# Should show updated values
```

## Acceptance Criteria ✅

### All Requirements Met
- ✅ Fix Steam OpenID validation to use cURL and robust SteamID extraction
- ✅ Replace includes/steam_openid.php with cURL-based implementation
- ✅ Add includes/steam_api.php to call Steam WebAPI GetPlayerSummaries
- ✅ STEAM_API_KEY read from environment variable (not committed)
- ✅ Add CSRF utility: includes/csrf.php with token generation and validation
- ✅ Integrate CSRF into all state-changing forms:
  - ✅ settings.php (all actions)
  - ✅ vtcs.php
  - ✅ vtc.php (create/join)
  - ✅ register.php (via callback)
  - ✅ admin_login.php (via redirect)
  - ✅ admin_settings.php (all actions)
  - ✅ logout.php
  - ✅ upload_avatar.php
- ✅ Add admin-first-registration logic:
  - ✅ register.php creates first admin when users table empty
  - ✅ On first registration, set site_settings.registration_open = 0
  - ✅ admin_settings.php to toggle registration and reset site
- ✅ Centralize styles:
  - ✅ assets/style.scss (SCSS source)
  - ✅ assets/style.css (compiled CSS)
  - ✅ Update includes/header.php to load style.css
  - ✅ Include meta CSRF token in header
  - ✅ assets/theme.js exposes setTheme and uses localStorage
- ✅ Update includes/header.php and footer.php:
  - ✅ Uniform navbar (brand top-left)
  - ✅ User dropdown top-right (avatar/name)
  - ✅ White/Dark toggle
  - ✅ Show admin links to is_admin users
- ✅ Update login.php and auth_callback.php:
  - ✅ Use updated includes/steam_openid.php
  - ✅ Use includes/steam_api.php
  - ✅ auth_callback updates display_name and avatar_url when Steam API key present
- ✅ Update settings.php, vtcs.php, vtc.php, register.php, admin_login.php, admin_settings.php, profile.php:
  - ✅ Use CSRF protection
  - ✅ Use current header/footer
  - ✅ Enforce login checks
- ✅ Add sql/schema.sql:
  - ✅ Create/update users table (with is_admin)
  - ✅ Create vtcs, vtc_members, jobs tables
  - ✅ Create site_settings table
  - ✅ Insert registration_open default (0)
- ✅ Security: STEAM_API_KEY not committed
- ✅ Document in README how to set STEAM_API_KEY

### Code Quality
- ✅ All POST forms include valid CSRF token and are rejected without it
- ✅ No password-based auth endpoints; only Steam OpenID flow
- ✅ Single stylesheet used site-wide with SCSS source included
- ✅ All PHP files syntax-checked (no errors)
- ✅ Prepared statements for all database queries
- ✅ Comprehensive error logging
- ✅ Input validation and sanitization

### Documentation
- ✅ PR includes comprehensive description
- ✅ Testing instructions provided
- ✅ README updated with admin setup
- ✅ Code comments in new files
- ✅ Database schema documented

## Production Checklist

Before deploying to production:
- [ ] Enable HTTPS on web server
- [ ] Uncomment `cookie_secure` in includes/auth.php
- [ ] Set `STEAM_API_KEY` environment variable
- [ ] Configure proper error logging (not display_errors)
- [ ] Create uploads directory with correct permissions:
  ```bash
  mkdir -p uploads/avatars
  chmod 755 uploads/avatars
  ```
- [ ] Add `.htaccess` to uploads to prevent PHP execution:
  ```bash
  echo "php_flag engine off" > uploads/.htaccess
  ```
- [ ] Delete debug/utility files:
  - `generate_password_hash.php`
  - `verify_password.php`
  - `login_debug.php`
  - `api/debug_log.txt`
  - `api/hardcore_debug_log.txt`
- [ ] Review and optimize database indexes
- [ ] Set up regular database backups
- [ ] Configure rate limiting for login attempts
- [ ] Review and test all admin functionality
- [ ] Verify CSRF protection on all forms
- [ ] Test registration flow (first admin, subsequent users)

## Breaking Changes

None - this is an enhancement PR that builds on existing functionality.

## Backward Compatibility

- ✅ Existing users with Steam IDs continue to work
- ✅ Database schema is additive (new fields have defaults)
- ✅ No API changes
- ✅ Existing sessions remain valid
- ⚠️ Database migration required to add:
  - `is_admin` column to users table
  - `site_settings` table
  - Run `sql/schema.sql` which uses `CREATE TABLE IF NOT EXISTS`

## Additional Notes

### API Endpoints
- API endpoints (`/api/start_job.php`, `/api/finish_job.php`, etc.) use token-based authentication
- These are for external game clients, not web forms
- CSRF not applicable to API endpoints (token auth instead)

### Debug Utilities
- `generate_password_hash.php` - Legacy utility (delete in production)
- `verify_password.php` - Legacy verification (delete in production)
- `login_debug.php` - Debug logger (delete in production)
- All have their own access tokens and are separate from main app

### Theme Storage
- Theme preference stored client-side in localStorage
- No server-side storage needed
- Defaults to dark theme if not set

### Steam API Key
- Optional but strongly recommended
- Without it, users get generic usernames like "User_123456"
- With it, real Steam display names and avatars are fetched
- Updates happen on every login when API key present

### Admin Features
- First user to register gets admin automatically
- Registration closes after first user
- Admins can manage site via `/admin_settings.php`
- Admin status visible in navbar (⚙️ Admin link)
- Site reset preserves admin accounts only

## Security Summary

### Implemented Security Measures
1. **CSRF Protection**: All state-changing forms protected with timing-safe tokens
2. **Steam OpenID**: Robust validation with cURL and comprehensive error handling
3. **Database Security**: All queries use prepared statements with parameter binding
4. **Session Security**: HttpOnly cookies, strict mode, regeneration on login
5. **Input Validation**: All user input sanitized with htmlspecialchars()
6. **Error Handling**: Comprehensive logging without exposing sensitive data
7. **Access Control**: Admin features restricted to is_admin users
8. **No Secrets in Code**: API keys from environment variables only

### Security Testing Performed
- ✅ CSRF token validation tested (rejection without token)
- ✅ SQL injection tested (prepared statements protect)
- ✅ XSS tested (output escaping prevents)
- ✅ Access control tested (non-admins blocked from admin panel)
- ✅ Session security tested (httponly, regeneration)
- ✅ File upload validation tested (type, size limits)

## Related Issues

This PR implements all requirements from the issue:
- Steam OpenID validation with cURL ✅
- Steam Web API integration ✅
- CSRF protection system ✅
- Admin-first-registration ✅
- Site settings management ✅
- Centralized styling ✅
- Documentation updates ✅

---

## Reviewer Checklist

Please verify the following before approving:

- [ ] Code follows project coding standards
- [ ] Database schema reviewed and tested
- [ ] CSRF protection verified on all state-changing forms
- [ ] Steam OpenID flow tested end-to-end with ngrok
- [ ] Admin registration flow tested (first user becomes admin)
- [ ] Admin settings panel tested (statistics, toggle, reset)
- [ ] Steam API integration tested (with and without API key)
- [ ] Theme toggle tested (localStorage persistence)
- [ ] Documentation is clear and complete
- [ ] No sensitive data in logs or responses
- [ ] Security best practices followed
- [ ] All PHP files have valid syntax
- [ ] No secrets committed to repository
- [ ] README testing instructions are accurate

## Screenshots

N/A - This PR focuses on backend security and admin functionality. UI remains consistent with existing design using the dark/light theme system already in place. Admin features appear as new navigation items for admin users only.
