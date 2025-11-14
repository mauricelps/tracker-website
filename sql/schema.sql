-- MyTruckTracker Database Schema
-- Complete schema for users, jobs, VTCs, and related tables

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    steamId VARCHAR(17) NOT NULL UNIQUE,
    avatar_url VARCHAR(500),
    display_name VARCHAR(255),
    bio TEXT,
    wot_text VARCHAR(255) COMMENT 'World of Trucks ID or profile link',
    truckersmp_text VARCHAR(255) COMMENT 'TruckersMP ID or profile link',
    auth_token VARCHAR(255) COMMENT 'API authentication token',
    account_status ENUM('active', 'paused') DEFAULT 'active',
    is_admin BOOLEAN DEFAULT FALSE COMMENT 'Administrator flag',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_steamid (steamId),
    INDEX idx_status (account_status),
    INDEX idx_admin (is_admin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jobs table - tracks individual delivery jobs
CREATE TABLE IF NOT EXISTS jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    driver_steam_id VARCHAR(17) NOT NULL,
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at TIMESTAMP NULL,
    source_city VARCHAR(255) NOT NULL,
    source_company VARCHAR(255),
    destination_city VARCHAR(255) NOT NULL,
    destination_company VARCHAR(255),
    cargo_name VARCHAR(255),
    cargo_mass INT COMMENT 'Cargo mass in kg',
    distance INT COMMENT 'Distance in km or miles',
    income DECIMAL(10,2) COMMENT 'Job income',
    fuel_used DECIMAL(10,2) COMMENT 'Fuel consumed',
    game VARCHAR(50) COMMENT 'ETS2 or ATS',
    truck_model VARCHAR(255),
    status ENUM('in_progress', 'completed', 'cancelled') DEFAULT 'in_progress',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_driver (driver_steam_id),
    INDEX idx_status (status),
    INDEX idx_finished (finished_at),
    FOREIGN KEY (driver_steam_id) REFERENCES users(steamId) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Job transports table - tracks ferry/train transports during jobs
CREATE TABLE IF NOT EXISTS job_transports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    transport_type ENUM('ferry', 'train') NOT NULL,
    source_location VARCHAR(255),
    destination_location VARCHAR(255),
    duration INT COMMENT 'Duration in seconds',
    cost DECIMAL(10,2) COMMENT 'Transport cost',
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_job (job_id),
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Virtual Trucking Companies table
CREATE TABLE IF NOT EXISTS vtcs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    tag VARCHAR(10) NOT NULL COMMENT 'Company tag/abbreviation',
    description TEXT,
    owner_user_id INT NOT NULL,
    logo_url VARCHAR(500),
    website VARCHAR(500),
    discord_invite VARCHAR(500),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_owner (owner_user_id),
    INDEX idx_status (status),
    UNIQUE KEY unique_tag (tag),
    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- VTC Members table - tracks membership in VTCs
CREATE TABLE IF NOT EXISTS vtc_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vtc_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('owner', 'admin', 'member') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    left_at TIMESTAMP NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    INDEX idx_vtc (vtc_id),
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    UNIQUE KEY unique_active_membership (vtc_id, user_id, status),
    FOREIGN KEY (vtc_id) REFERENCES vtcs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sessions table (optional - for database-backed sessions)
CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) NOT NULL PRIMARY KEY,
    user_id INT,
    data TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Statistics view - aggregated user statistics
CREATE OR REPLACE VIEW user_stats AS
SELECT 
    u.id as user_id,
    u.steamId,
    u.username,
    COUNT(DISTINCT j.id) as total_jobs,
    COUNT(DISTINCT CASE WHEN j.status = 'completed' THEN j.id END) as completed_jobs,
    SUM(CASE WHEN j.status = 'completed' THEN j.distance ELSE 0 END) as total_distance,
    SUM(CASE WHEN j.status = 'completed' THEN j.income ELSE 0 END) as total_income,
    SUM(CASE WHEN j.status = 'completed' THEN j.fuel_used ELSE 0 END) as total_fuel,
    MIN(j.started_at) as first_job_date,
    MAX(j.finished_at) as last_job_date
FROM users u
LEFT JOIN jobs j ON u.steamId = j.driver_steam_id
GROUP BY u.id, u.steamId, u.username;

-- VTC statistics view
CREATE OR REPLACE VIEW vtc_stats AS
SELECT 
    v.id as vtc_id,
    v.name as vtc_name,
    v.tag as vtc_tag,
    COUNT(DISTINCT vm.user_id) as member_count,
    COUNT(DISTINCT j.id) as total_jobs,
    SUM(CASE WHEN j.status = 'completed' THEN j.distance ELSE 0 END) as total_distance,
    SUM(CASE WHEN j.status = 'completed' THEN j.income ELSE 0 END) as total_income
FROM vtcs v
LEFT JOIN vtc_members vm ON v.id = vm.vtc_id AND vm.status = 'active'
LEFT JOIN users u ON vm.user_id = u.id
LEFT JOIN jobs j ON u.steamId = j.driver_steam_id
GROUP BY v.id, v.name, v.tag;

-- Site settings table - global application settings
CREATE TABLE IF NOT EXISTS site_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(255) NOT NULL UNIQUE,
    setting_value TEXT,
    description VARCHAR(500),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default site settings
INSERT INTO site_settings (setting_key, setting_value, description)
VALUES ('registration_open', '0', 'Whether new user registration is open (1=open, 0=closed)')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Initial data / Sample configuration
-- You can add default data here if needed

-- Example: Create a default "Independent Drivers" VTC for users without a company
-- INSERT INTO vtcs (name, tag, description, owner_user_id, status) 
-- VALUES ('Independent Drivers', 'IND', 'Default group for independent drivers', 1, 'active')
-- ON DUPLICATE KEY UPDATE name = name;

-- Database schema version tracking (optional but recommended)
CREATE TABLE IF NOT EXISTS schema_versions (
    version INT PRIMARY KEY,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    description VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Record this schema version
INSERT INTO schema_versions (version, description) 
VALUES (1, 'Initial schema with users, jobs, VTCs, and statistics views')
ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP;
