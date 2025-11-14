# Pull Request Summary

## Title
Complete Steam OpenID, Admin System, and VTC Features with Enhanced Security

## Description

This PR implements all requirements from the enhancement issue, including robust Steam OpenID authentication with WebAPI integration, comprehensive CSRF protection, admin-first registration system, site settings management, and fully functional VTC (Virtual Trucking Company) features.

## Changes Implemented

### 🔒 Security Enhancements

1. **Robust Steam OpenID Validation** (`includes/steam_openid.php`)
   - cURL-based validation with Steam's OpenID endpoint
   - Secure Steam ID extraction using regex validation
   - Comprehensive error logging for debugging
   - Timeout protection and SSL verification

2. **Steam WebAPI Integration** (`includes/steam_api.php`)
   - Automatic profile fetching using Steam Web API
   - Populates display_name and avatar_url on first login
   - Safe fallback when API key not present
   - Last profile update tracking

3. **Comprehensive CSRF Protection** (`includes/csrf.php`)
   - All state-changing forms protected with CSRF tokens
   - Session-based token storage with 1-hour lifetime
   - Timing-safe token comparison
   - Meta tag for AJAX requests
   - Protected endpoints:
     * User settings (profile, pause, reset, delete)
     * Logout
     * VTC creation, join, leave
     * Admin settings
     * Registration and login

4. **Admin-First Registration System**
   - First registration creates admin account
   - Subsequent registrations require `registration_open` setting
   - Email/password authentication for local admin accounts
   - Steam authentication always available
   - Admin settings page for site management

### 🎨 Styling & UI

5. **Centralized Stylesheet**
   - `assets/style.scss` - SCSS source (576 lines)
   - `assets/style.css` - Compiled CSS (644 lines)
   - Single stylesheet reference in header
   - CSS variables for theming
   - Responsive design

6. **Theme Toggle**
   - Dark theme (default)
   - Light theme option
   - localStorage persistence
   - Smooth transitions
   - Accessible toggle button
   - Exposed setTheme function for external use

7. **Unified Header/Footer**
   - Consistent navbar with brand
   - User dropdown with profile/settings/logout
   - Admin links visible for is_admin users
   - CSRF meta tag
   - Theme toggle button

### 📋 Application Pages

8. **Settings Page** (`settings.php`)
   - Profile management (display_name, bio, etc.)
   - External profiles (World of Trucks, TruckersMP)
   - API token management
   - Account actions (pause, reset stats, delete)
   - All forms CSRF-protected

9. **Admin Pages**
   - **Register** (`register.php`) - Admin-first registration
   - **Admin Login** (`admin_login.php`) - Local email/password authentication
   - **Admin Settings** (`admin_settings.php`) - Site management dashboard
     * Toggle registration open/closed
     * Site statistics (users, jobs, VTCs)
     * Site reset functionality (preserves admin accounts)

10. **VTC Features** (`vtcs.php`, `vtc.php`)
    - Create new Virtual Trucking Companies
    - Browse active VTCs with member counts
    - Join/leave VTCs with CSRF protection
    - View VTC details and member lists
    - Owner/admin role management
    - Prevent VTC owners from leaving their company

### 🗄️ Database

11. **Enhanced SQL Schema** (`sql/schema.sql`)
    - **Users table updates:**
      * `is_admin` - Admin flag for local accounts
      * `email` - Email for local admin accounts
      * `password_hash` - Password hash for local accounts
      * `last_profile_update` - Steam API update tracking
    - **Site Settings table:**
      * Key-value store for application settings
      * `registration_open` default setting
    - **VTCs and VTC Members tables** (fully implemented)
    - Statistics views
    - Schema version tracking (v2)
    - Migration notes for existing databases

### 📚 Configuration & Setup

12. **Database Configuration** (`db.php.example`)
    - Environment variable support for credentials
    - `STEAM_API_KEY` support (optional)
    - PDO connection with security settings
    - Example configuration provided

## Files Changed

### Created (7 files)
- `includes/steam_api.php` - Steam WebAPI wrapper
- `includes/steam_openid.php` - Steam OpenID validation
- `includes/csrf.php` - CSRF protection utility
- `register.php` - Admin-first registration page
- `admin_login.php` - Admin authentication
- `admin_settings.php` - Site management dashboard
- `db.php.example` - Database configuration template

### Modified (8 files)
- `sql/schema.sql` - Added admin fields, site_settings table
- `auth_callback.php` - Integrated Steam WebAPI for profile data
- `includes/auth.php` - Added is_admin() and require_admin() functions
- `includes/header.php` - Added admin links in dropdown
- `vtcs.php` - Implemented VTC creation with CSRF
- `vtc.php` - Implemented join/leave functionality
- `assets/theme.js` - Exposed setTheme globally
- `README.md` - Updated documentation

## Testing Instructions

### Database Setup

Run the following SQL to create/update your database:

```sql
-- Create database
CREATE DATABASE IF NOT EXISTS truck_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE truck_tracker;

-- Import schema
SOURCE sql/schema.sql;

-- Verify tables created
SHOW TABLES;
```

### Database Configuration

Copy and configure `db.php.example`:

```bash
cp db.php.example db.php
# Edit db.php with your database credentials
```

Example `db.php`:

```php
<?php
$pdo = new PDO(
    'mysql:host=localhost;dbname=truck_tracker;charset=utf8mb4',
    'your_username',
    'your_password',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

// Optional: Set Steam API Key
// Get from: https://steamcommunity.com/dev/apikey
define('STEAM_API_KEY', getenv('STEAM_API_KEY') ?: '');
?>
```

### Local Testing with ngrok

Steam OpenID requires a publicly accessible URL:

```bash
# Terminal 1: Start PHP server
php -S localhost:8000

# Terminal 2: Expose with ngrok
ngrok http 8000
# Use the ngrok HTTPS URL for testing
```

### Testing Checklist

#### 1. Admin Registration (First User)
1. Navigate to `/register.php`
2. Should show "Admin Registration" title
3. Create first account (becomes admin)
4. Check database: `SELECT is_admin FROM users WHERE id=1;` → should be 1

#### 2. Registration Control
1. Log in as admin
2. Go to `/admin_settings.php`
3. Verify "Registration Closed" status
4. Click "Open Registration"
5. Log out and verify `/register.php` allows registration
6. Click "Close Registration" as admin
7. Verify registration blocked for non-admins

#### 3. Steam Login with API
1. Set `STEAM_API_KEY` in `db.php` or environment
2. Visit `/login.php`
3. Click "Login with Steam"
4. Authorize on Steam
5. Check database: `SELECT display_name, avatar_url, last_profile_update FROM users WHERE steamId = 'your_steam_id';`
6. Verify display_name and avatar_url populated from Steam

#### 4. Steam Login without API
1. Remove or unset `STEAM_API_KEY`
2. Log in with Steam
3. Should create user with fallback username (User_XXXXXX)
4. Should use default avatar

#### 5. CSRF Protection
1. Inspect any form → should contain `<input type="hidden" name="csrf_token">`
2. Check header → `<meta name="csrf-token">` should be present
3. Test invalid token:
   ```bash
   curl -X POST http://localhost:8000/logout.php
   # Should return: 403 Forbidden
   ```

#### 6. VTC Creation
1. Log in (Steam or admin)
2. Go to `/vtcs.php`
3. Create a new VTC with name and tag
4. Verify VTC appears in listing
5. Check database: `SELECT * FROM vtcs;`
6. Check membership: `SELECT * FROM vtc_members;` → role should be 'owner'

#### 7. VTC Join/Leave
1. Log in with a different user
2. Go to `/vtcs.php` → click "View" on a VTC
3. Click "Join VTC"
4. Verify membership in database
5. Click "Leave VTC"
6. Verify status changed to 'inactive'

#### 8. Admin Links
1. Log in as admin user (is_admin = 1)
2. Click user dropdown in header
3. Should see "⚙️ Admin Settings" link
4. Non-admin users should not see this link

#### 9. Site Reset
1. Go to `/admin_settings.php` as admin
2. Create some test data (users, jobs, VTCs)
3. Type "RESET" in confirmation field
4. Click "Reset All Site Data"
5. Verify non-admin data deleted
6. Verify admin account preserved

#### 10. Theme Toggle
1. Click theme button (☀️/🌙)
2. Verify theme switches
3. Refresh page → theme persists
4. Console: `localStorage.getItem('theme')` → 'dark' or 'light'

## Acceptance Criteria ✅

- ✅ Steam OpenID validation using cURL with robust SteamID extraction
- ✅ Steam WebAPI integration auto-fills display_name and avatar_url when STEAM_API_KEY present
- ✅ Robust CSRF utility used across all state-changing forms
- ✅ Single SCSS source and compiled CSS with dark theme and JS toggle
- ✅ Admin-first registration: first user becomes admin, registration controlled by site setting
- ✅ CSRF integrated into settings.php, vtcs.php, vtc.php, register.php, admin_login.php, admin_settings.php
- ✅ Header/footer with user dropdown and admin links for is_admin users
- ✅ Steam API wrapper safely calls GetPlayerSummaries using STEAM_API_KEY
- ✅ auth_callback.php stores display_name, avatar_url, and last_profile_update
- ✅ SQL schema includes site_settings, is_admin, last_profile_update, email, password_hash fields

## SQL Schema Updates

For existing databases, run these migrations:

```sql
-- Add new columns to users table
ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0 COMMENT 'Admin flag';
ALTER TABLE users ADD COLUMN email VARCHAR(255) COMMENT 'Email for local accounts';
ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) COMMENT 'Password for local accounts';
ALTER TABLE users ADD COLUMN last_profile_update DATETIME COMMENT 'Last Steam API update';
ALTER TABLE users ADD INDEX idx_admin (is_admin);

-- Create site_settings table
CREATE TABLE IF NOT EXISTS site_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(128) NOT NULL UNIQUE,
    `value` TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Set default registration setting
INSERT INTO site_settings (`key`, `value`) VALUES ('registration_open', '0') 
ON DUPLICATE KEY UPDATE `value` = `value`;
```

## Production Checklist

Before deploying to production:

- [ ] Enable HTTPS
- [ ] Set `STEAM_API_KEY` environment variable (get from https://steamcommunity.com/dev/apikey)
- [ ] Configure database connection in `db.php`
- [ ] Delete debug files (`generate_password_hash.php`, `verify_password.php`, `login_debug.php`)
- [ ] Disable PHP error display: `ini_set('display_errors', 0);`
- [ ] Set up error logging
- [ ] Create uploads directory: `mkdir -p uploads/avatars && chmod 755 uploads/avatars`
- [ ] Add `.htaccess` to uploads: `echo "php_flag engine off" > uploads/.htaccess`
- [ ] Enable secure cookies in `includes/auth.php`: uncomment `'cookie_secure' => true`
- [ ] Set up cron job for session cleanup (optional)

## Breaking Changes

None - this is an enhancement that maintains backward compatibility with existing Steam authentication.

## Backward Compatibility

- Existing Steam users continue to work seamlessly
- Profile data auto-updates on next login if STEAM_API_KEY is set
- New fields are nullable or have defaults
- No API changes

## Additional Notes

- **Steam API Key**: Optional but highly recommended. Without it, usernames default to "User_XXXXXX" format
- **Admin System**: Dual authentication - Steam (always available) and local email/password (for admins)
- **VTC Ownership**: VTC owners cannot leave their own VTC (must transfer ownership or delete VTC)
- **Theme Storage**: Client-side (localStorage), no database impact
- **CSRF Tokens**: 1-hour lifetime, automatically renewed
- **Site Reset**: Preserves admin accounts, deletes all other data

## Security Features

- Prepared statements for all database queries
- CSRF protection on all state-changing operations
- Password hashing with bcrypt (PASSWORD_DEFAULT)
- Session security (httponly, strict mode)
- Timing-safe token comparison
- SQL injection protection
- Input validation and sanitization
- Error logging (sensitive data not exposed to users)

## Related Issues

Implements all requirements from the enhancement issue.

---

## Reviewer Checklist

- [ ] Code follows project coding standards
- [ ] Database schema reviewed and tested
- [ ] CSRF protection verified on all forms
- [ ] Steam OpenID flow tested end-to-end
- [ ] Steam WebAPI integration tested with and without API key
- [ ] Admin registration flow tested
- [ ] VTC creation/join/leave tested
- [ ] Site settings management tested
- [ ] Documentation is clear and complete
- [ ] No sensitive data in logs or responses
- [ ] Security best practices followed
- [ ] db.php excluded from version control (.gitignore)
