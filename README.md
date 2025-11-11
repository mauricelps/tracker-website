# MyTruckTracker

A web application for tracking Euro Truck Simulator 2 and American Truck Simulator jobs and statistics.

## Features

- **Steam Authentication**: Secure login using Steam OpenID
- **CSRF Protection**: All forms protected against CSRF attacks
- **Theme Toggle**: Dark/Light mode with localStorage persistence
- **Job Tracking**: Track deliveries, statistics, and history
- **User Profiles**: View driver profiles and statistics
- **Responsive Design**: Works on desktop and mobile devices

## Setup

### Requirements

- PHP 7.4 or higher with cURL extension enabled
- MySQL/MariaDB database (5.7+ or 10.2+)
- Web server (Apache/Nginx)
- cURL extension enabled (`php -m | grep curl`)
- Sessions support enabled

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/mauricelps/tracker-website.git
   cd tracker-website
   ```

2. **Create database and import schema**
   ```bash
   mysql -u root -p
   ```
   ```sql
   CREATE DATABASE truck_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   USE truck_tracker;
   SOURCE sql/schema.sql;
   EXIT;
   ```

3. **Configure database connection**
   
   Create a `db.php` file in the root directory:
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

4. **Set up file permissions**
   ```bash
   mkdir -p uploads/avatars
   chmod 755 uploads/avatars
   ```

5. **Configure Steam API (Optional but recommended)**
   
   Get a Steam Web API key from https://steamcommunity.com/dev/apikey
   
   Set as environment variable:
   ```bash
   export STEAM_API_KEY="your_steam_api_key_here"
   ```
   
   Or add to your web server configuration (Apache):
   ```apache
   SetEnv STEAM_API_KEY "your_steam_api_key_here"
   ```
   
   Or Nginx with PHP-FPM in your pool config:
   ```ini
   env[STEAM_API_KEY] = "your_steam_api_key_here"
   ```

6. **Configure web server**
   
   For Apache, ensure `.htaccess` is enabled or configure virtual host.
   
   For Nginx, configure location blocks for clean URLs.

### Testing Locally

#### Option 1: PHP Built-in Server (Development Only)

```bash
php -S localhost:8000
```

**Note:** Steam OpenID requires a publicly accessible URL. For local testing, use ngrok:

```bash
# Install ngrok from https://ngrok.com/
ngrok http 8000
```

Then use the ngrok URL (e.g., `https://abc123.ngrok.io`) when testing Steam login.

#### Option 2: Docker (Recommended for Local Development)

```bash
# Example docker-compose.yml
docker-compose up
```

### Testing Steps

1. **Database Connection Test**
   - Visit homepage (`/`) - should load without database errors
   - Check PHP error logs for any PDO connection errors

2. **Steam Login Test**
   - Click "Login with Steam" button
   - You'll be redirected to Steam
   - Authorize the application
   - Should redirect back and create user account
   - Check `users` table: `SELECT * FROM users;`

3. **CSRF Protection Test**
   - Open browser developer tools → Network tab
   - Inspect any form (Settings, Logout, etc.)
   - Verify hidden input: `<input type="hidden" name="csrf_token" value="...">`
   - Verify meta tag: `<meta name="csrf-token" content="...">`
   - Try submitting form without token → Should get 403 error
   - Try submitting with invalid token → Should get 403 error

4. **Theme Toggle Test**
   - Click theme toggle button (top right)
   - Should switch between dark and light themes
   - Refresh page → theme should persist
   - Check localStorage: `localStorage.getItem('theme')`

5. **Settings Page Test**
   - Login and navigate to Settings (`/settings.php`)
   - Update profile fields (display name, bio, etc.)
   - Submit form → Should show success message
   - Verify database update: `SELECT * FROM users WHERE id=X;`

6. **Session Security Test**
   - Verify session cookie has HttpOnly flag (check browser dev tools)
   - Verify session regenerates after login
   - Test logout → session should be destroyed

7. **API Endpoints Test**
   ```bash
   # Test start job (requires valid auth_token)
   curl -X POST http://localhost:8000/api/start_job.php \
     -H "Content-Type: application/json" \
     -d '{"driver_steam_id":"76561198012345678","source_city":"Berlin","destination_city":"Prague"}'
   ```

### Production Deployment

Before deploying to production:

1. **Enable HTTPS**
   - Obtain SSL certificate (Let's Encrypt recommended)
   - Update `includes/auth.php` - uncomment `cookie_secure` option
   
2. **Set Steam API Key**
   - Configure `STEAM_API_KEY` environment variable
   - This enables fetching real usernames and avatars from Steam

3. **Configure Error Reporting**
   ```php
   // In production, disable display_errors
   ini_set('display_errors', 0);
   error_reporting(E_ALL);
   ini_set('log_errors', 1);
   ini_set('error_log', '/path/to/php-errors.log');
   ```

4. **Database Optimization**
   ```sql
   -- Add indexes for frequently queried fields
   ALTER TABLE jobs ADD INDEX idx_created (created_at);
   ALTER TABLE users ADD INDEX idx_created (created_at);
   ```

5. **File Upload Security**
   ```bash
   # Restrict upload directory
   chmod 755 uploads/
   # Add .htaccess to uploads/ to prevent PHP execution
   echo "php_flag engine off" > uploads/.htaccess
   ```

### Database Tables

The application expects the following tables (at minimum):

- `users`: User accounts (id, username, steamId, avatar_url, display_name, bio, wot_text, truckersmp_text, auth_token, account_status)
- `jobs`: Job records
- `job_transports`: Transport records (ferry/train)

Optional tables for future features:
- `vtcs`: Virtual Trucking Companies
- `vtc_members`: VTC membership

## Features Implemented

### Steam-Only Authentication

**Features:**
- Login via Steam OpenID (no passwords stored)
- Automatic user creation on first login
- Session management with security best practices
- Robust cURL-based validation with comprehensive error logging

**Implementation Details:**
- `includes/steam_openid.php` - Robust Steam OpenID validation class
- `auth_callback.php` - Handles Steam OAuth callback
- `login.php` - Initiates Steam login flow
- Error logging for debugging authentication issues
- Optional Steam Web API integration for profile data

**Error Logging:**
All Steam authentication errors are logged to PHP error log:
- OpenID validation failures
- cURL errors with details
- Steam ID extraction failures
- Profile fetch errors

Check logs: `tail -f /var/log/php_errors.log`

### CSRF Protection

All forms that modify state include CSRF tokens and are validated server-side:

**Protected Forms:**
- Login/Logout (POST to `/logout.php`)
- Settings updates (`/settings.php` - profile, pause, reset, delete)
- Avatar uploads (`/upload_avatar.php`)
- VTC creation/join/leave (when implemented)
- All API endpoints that mutate data

**CSRF Token Behavior:**
- Generated per session using cryptographically secure random bytes
- 1-hour expiration (automatically renewed)
- Validated using timing-safe comparison (`hash_equals`)
- Rejected requests return 403 Forbidden
- Meta tag available for AJAX: `<meta name="csrf-token">`

**Testing CSRF Protection:**
```bash
# Valid request (with token)
curl -X POST http://localhost:8000/logout.php \
  -H "Cookie: PHPSESSID=xxx" \
  -d "csrf_token=valid_token_here"

# Invalid request (no token) → 403 Forbidden
curl -X POST http://localhost:8000/logout.php \
  -H "Cookie: PHPSESSID=xxx"
```

### Unified Styling
- Single stylesheet (`assets/style.css`) used site-wide
- SCSS source file (`assets/style.scss`) for maintainability
- CSS variables for easy theming
- Dark theme by default with light theme option

### Theme Toggle
- JavaScript-based theme switcher
- Persists preference in localStorage
- Smooth transitions between themes
- Accessible button with proper labels

### User Settings
Users can update:
- Display name
- Bio
- World of Trucks profile
- TruckersMP profile
- API authentication token
- Account actions (pause, reset stats, delete account)

## Testing Locally

1. Set up your database connection in `db.php`
2. Start a local PHP server:
   ```bash
   php -S localhost:8000
   ```
3. Visit `http://localhost:8000` in your browser
4. Click "Login with Steam" to test the Steam authentication flow
5. Test CSRF protection by inspecting forms (all should include hidden CSRF tokens)
6. Test theme toggle using the button in the navigation bar

## API Endpoints

The application includes API endpoints for job tracking:
- `/api/start_job.php`: Start a new job
- `/api/finish_job.php`: Complete a job
- `/api/record_transport.php`: Record ferry/train transport

All API endpoints use prepared statements for security.

## Security Features

- CSRF tokens on all state-changing requests
- Prepared statements for all database queries
- Session security (httponly cookies, strict mode)
- Steam OpenID authentication (no password storage)
- Secure session regeneration on login
- Input validation and sanitization

**Note:** The following files are development/debug utilities and should be deleted in production:
- `generate_password_hash.php` - Legacy utility (Steam-only auth doesn't need password hashes)
- `verify_password.php` - Legacy verification tool
- `login_debug.php` - Debug logger for troubleshooting

These files have their own access tokens and are not part of the main application.

## File Structure

```
├── api/                    # API endpoints
├── assets/                 # Static assets
│   ├── style.css          # Compiled CSS
│   ├── style.scss         # SCSS source
│   └── theme.js           # Theme toggle script
├── includes/              # PHP includes
│   ├── auth.php          # Authentication helpers
│   ├── csrf.php          # CSRF protection
│   ├── header.php        # Unified header
│   └── footer.php        # Unified footer
├── uploads/              # User uploads (avatars)
├── auth_callback.php     # Steam auth callback
├── index.php             # Home page
├── jobs.php              # Jobs listing
├── job.php               # Single job view
├── login.php             # Login page
├── logout.php            # Logout handler
├── profile.php           # User profile redirect
├── settings.php          # User settings
├── stats.php             # Statistics page
├── user.php              # User profile page
├── upload_avatar.php     # Avatar upload handler
├── vtcs.php              # VTC listing (placeholder)
└── vtc.php               # Single VTC view (placeholder)
```

## Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

## License

[Add your license here]
