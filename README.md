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

- PHP 7.4 or higher
- MySQL/MariaDB database
- Web server (Apache/Nginx)
- cURL extension enabled

### Installation

1. Clone the repository
2. Create a `db.php` file in the root directory with your database configuration:

```php
<?php
$pdo = new PDO('mysql:host=localhost;dbname=your_database', 'username', 'password');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
?>
```

3. Import the database schema (if not already done)
4. Configure your web server to point to the project directory
5. Ensure the `uploads/avatars/` directory exists and is writable:
   ```bash
   mkdir -p uploads/avatars
   chmod 755 uploads/avatars
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
- Login via Steam OpenID
- Automatic user creation on first login
- Session management with security best practices

### CSRF Protection
All forms include CSRF tokens:
- Login/Logout
- Settings updates
- Avatar uploads
- VTC actions (when implemented)

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
