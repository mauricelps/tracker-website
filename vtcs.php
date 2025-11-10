<?php
// vtcs.php - VTC (Virtual Trucking Company) listing page
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/db.php';

$page_title = 'Virtual Trucking Companies';
include __DIR__ . '/includes/header.php';

// Note: VTC functionality requires additional database tables
// This is a placeholder page that can be extended when VTC tables are created
?>

<h1>Virtual Trucking Companies</h1>

<div class="card">
    <p class="meta">
        Virtual Trucking Company (VTC) functionality is coming soon!
        This feature will allow you to create and join trucking companies with other drivers.
    </p>
    
    <h3 style="margin-top:24px;">Planned Features:</h3>
    <ul>
        <li>Create and manage your own VTC</li>
        <li>Join existing VTCs</li>
        <li>View company statistics and leaderboards</li>
        <li>Organize company events and convoys</li>
        <li>Track company-wide delivery statistics</li>
    </ul>
    
    <?php if (is_logged_in()): ?>
    <div style="margin-top:24px;">
        <p class="meta">
            Database tables for VTC functionality need to be created. 
            Suggested schema:
        </p>
        <pre style="background:var(--bg-tertiary);padding:12px;border-radius:6px;overflow-x:auto;font-size:0.85rem;">
CREATE TABLE vtcs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    tag VARCHAR(10) NOT NULL,
    description TEXT,
    owner_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_user_id) REFERENCES users(id)
);

CREATE TABLE vtc_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vtc_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('owner', 'admin', 'member') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vtc_id) REFERENCES vtcs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_membership (vtc_id, user_id)
);
        </pre>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
