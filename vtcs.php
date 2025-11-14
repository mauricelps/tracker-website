<?php
// vtcs.php - VTC (Virtual Trucking Company) listing and creation
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/db.php';

$success = '';
$error = '';

// Handle VTC creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_logged_in()) {
    CSRF::validateRequest();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_vtc') {
        $name = trim($_POST['name'] ?? '');
        $tag = trim($_POST['tag'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        // Validate inputs
        if (empty($name) || empty($tag)) {
            $error = 'VTC name and tag are required.';
        } elseif (strlen($tag) > 10) {
            $error = 'Tag must be 10 characters or less.';
        } else {
            try {
                $user = current_user();
                
                // Check if tag already exists
                $checkStmt = $pdo->prepare("SELECT id FROM vtcs WHERE tag = :tag LIMIT 1");
                $checkStmt->execute([':tag' => $tag]);
                
                if ($checkStmt->fetch()) {
                    $error = 'A VTC with that tag already exists.';
                } else {
                    // Create VTC
                    $insertStmt = $pdo->prepare("
                        INSERT INTO vtcs (name, tag, description, owner_user_id, created_at)
                        VALUES (:name, :tag, :description, :owner_id, NOW())
                    ");
                    $insertStmt->execute([
                        ':name' => $name,
                        ':tag' => $tag,
                        ':description' => $description,
                        ':owner_id' => $user['id']
                    ]);
                    
                    $vtcId = $pdo->lastInsertId();
                    
                    // Add creator as owner member
                    $memberStmt = $pdo->prepare("
                        INSERT INTO vtc_members (vtc_id, user_id, role, joined_at, status)
                        VALUES (:vtc_id, :user_id, 'owner', NOW(), 'active')
                    ");
                    $memberStmt->execute([
                        ':vtc_id' => $vtcId,
                        ':user_id' => $user['id']
                    ]);
                    
                    $success = 'VTC created successfully!';
                }
            } catch (PDOException $e) {
                error_log('VTC creation error: ' . $e->getMessage());
                $error = 'Failed to create VTC. Please try again.';
            }
        }
    }
}

// Fetch all active VTCs
try {
    $stmt = $pdo->query("
        SELECT v.*, u.username as owner_name,
               COUNT(DISTINCT vm.user_id) as member_count
        FROM vtcs v
        LEFT JOIN users u ON v.owner_user_id = u.id
        LEFT JOIN vtc_members vm ON v.id = vm.vtc_id AND vm.status = 'active'
        WHERE v.status = 'active'
        GROUP BY v.id
        ORDER BY v.created_at DESC
    ");
    $vtcs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('VTC fetch error: ' . $e->getMessage());
    $vtcs = [];
}

$page_title = 'Virtual Trucking Companies';
include __DIR__ . '/includes/header.php';
?>

<h1>Virtual Trucking Companies</h1>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if (is_logged_in()): ?>
<!-- Create VTC Form -->
<div class="card">
    <h3>Create a New VTC</h3>
    <form method="post" style="max-width:600px;">
        <?php echo CSRF::getTokenInput(); ?>
        <input type="hidden" name="action" value="create_vtc">
        
        <div class="form-group">
            <label>
                VTC Name
                <input type="text" name="name" required maxlength="255" 
                       placeholder="e.g., European Transport Alliance">
            </label>
        </div>
        
        <div class="form-group">
            <label>
                Tag (max 10 chars)
                <input type="text" name="tag" required maxlength="10" 
                       placeholder="e.g., ETA"
                       style="max-width:150px;text-transform:uppercase;">
            </label>
        </div>
        
        <div class="form-group">
            <label>
                Description (optional)
                <textarea name="description" rows="3" 
                          placeholder="Brief description of your VTC..."></textarea>
            </label>
        </div>
        
        <button type="submit" class="btn">Create VTC</button>
    </form>
</div>
<?php endif; ?>

<!-- VTC Listing -->
<div class="card">
    <h3>Active VTCs</h3>
    
    <?php if (empty($vtcs)): ?>
        <p class="meta">No VTCs found. Be the first to create one!</p>
    <?php else: ?>
        <table class="jobs-table">
            <thead>
                <tr>
                    <th>Tag</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Members</th>
                    <th>Owner</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vtcs as $vtc): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($vtc['tag']); ?></strong></td>
                    <td><?php echo htmlspecialchars($vtc['name']); ?></td>
                    <td><?php echo htmlspecialchars(substr($vtc['description'] ?? '', 0, 100)); ?></td>
                    <td><?php echo (int)$vtc['member_count']; ?></td>
                    <td><?php echo htmlspecialchars($vtc['owner_name'] ?? 'Unknown'); ?></td>
                    <td>
                        <a href="/vtc.php?id=<?php echo (int)$vtc['id']; ?>" class="btn btn-secondary">
                            View
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
