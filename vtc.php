<?php
// vtc.php - Single VTC view with join/leave actions
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/db.php';

$vtc_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success = '';
$error = '';

// Handle join/leave actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_logged_in()) {
    CSRF::validateRequest();
    
    $action = $_POST['action'] ?? '';
    $user = current_user();
    
    try {
        if ($action === 'join') {
            // Check if already a member
            $checkStmt = $pdo->prepare("
                SELECT id FROM vtc_members 
                WHERE vtc_id = :vtc_id AND user_id = :user_id AND status = 'active'
                LIMIT 1
            ");
            $checkStmt->execute([
                ':vtc_id' => $vtc_id,
                ':user_id' => $user['id']
            ]);
            
            if ($checkStmt->fetch()) {
                $error = 'You are already a member of this VTC.';
            } else {
                // Join VTC
                $insertStmt = $pdo->prepare("
                    INSERT INTO vtc_members (vtc_id, user_id, role, joined_at, status)
                    VALUES (:vtc_id, :user_id, 'member', NOW(), 'active')
                ");
                $insertStmt->execute([
                    ':vtc_id' => $vtc_id,
                    ':user_id' => $user['id']
                ]);
                $success = 'Successfully joined the VTC!';
            }
        } elseif ($action === 'leave') {
            // Check if user is owner
            $vtcStmt = $pdo->prepare("SELECT owner_user_id FROM vtcs WHERE id = :vtc_id LIMIT 1");
            $vtcStmt->execute([':vtc_id' => $vtc_id]);
            $vtc = $vtcStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($vtc && $vtc['owner_user_id'] == $user['id']) {
                $error = 'VTC owners cannot leave their own VTC. Please transfer ownership first or delete the VTC.';
            } else {
                // Leave VTC (set status to inactive)
                $updateStmt = $pdo->prepare("
                    UPDATE vtc_members 
                    SET status = 'inactive', left_at = NOW()
                    WHERE vtc_id = :vtc_id AND user_id = :user_id AND status = 'active'
                ");
                $updateStmt->execute([
                    ':vtc_id' => $vtc_id,
                    ':user_id' => $user['id']
                ]);
                $success = 'Successfully left the VTC.';
            }
        }
    } catch (PDOException $e) {
        error_log('VTC action error: ' . $e->getMessage());
        $error = 'An error occurred. Please try again.';
    }
}

// Fetch VTC details
try {
    $stmt = $pdo->prepare("
        SELECT v.*, u.username as owner_name
        FROM vtcs v
        LEFT JOIN users u ON v.owner_user_id = u.id
        WHERE v.id = :vtc_id
        LIMIT 1
    ");
    $stmt->execute([':vtc_id' => $vtc_id]);
    $vtc = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vtc) {
        header('Location: /vtcs.php');
        exit;
    }
    
    // Fetch members
    $membersStmt = $pdo->prepare("
        SELECT u.id, u.username, u.display_name, u.avatar_url, vm.role, vm.joined_at
        FROM vtc_members vm
        JOIN users u ON vm.user_id = u.id
        WHERE vm.vtc_id = :vtc_id AND vm.status = 'active'
        ORDER BY 
            CASE vm.role 
                WHEN 'owner' THEN 1 
                WHEN 'admin' THEN 2 
                ELSE 3 
            END,
            vm.joined_at ASC
    ");
    $membersStmt->execute([':vtc_id' => $vtc_id]);
    $members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if current user is a member
    $isMember = false;
    if (is_logged_in()) {
        $user = current_user();
        foreach ($members as $member) {
            if ($member['id'] == $user['id']) {
                $isMember = true;
                break;
            }
        }
    }
} catch (PDOException $e) {
    error_log('VTC fetch error: ' . $e->getMessage());
    header('Location: /vtcs.php');
    exit;
}

$page_title = htmlspecialchars($vtc['name']);
include __DIR__ . '/includes/header.php';
?>

<h1>[<?php echo htmlspecialchars($vtc['tag']); ?>] <?php echo htmlspecialchars($vtc['name']); ?></h1>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- VTC Information -->
<div class="card">
    <h3>About</h3>
    <p><strong>Tag:</strong> <?php echo htmlspecialchars($vtc['tag']); ?></p>
    <p><strong>Owner:</strong> <?php echo htmlspecialchars($vtc['owner_name'] ?? 'Unknown'); ?></p>
    <p><strong>Created:</strong> <?php echo date('F j, Y', strtotime($vtc['created_at'])); ?></p>
    
    <?php if (!empty($vtc['description'])): ?>
        <div style="margin-top:16px;">
            <strong>Description:</strong>
            <p><?php echo nl2br(htmlspecialchars($vtc['description'])); ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Join/Leave Actions -->
    <?php if (is_logged_in()): ?>
        <div style="margin-top:24px;">
            <?php if ($isMember): ?>
                <form method="post" style="display:inline;">
                    <?php echo CSRF::getTokenInput(); ?>
                    <input type="hidden" name="action" value="leave">
                    <button type="submit" class="btn btn-secondary" 
                            onclick="return confirm('Are you sure you want to leave this VTC?');">
                        Leave VTC
                    </button>
                </form>
            <?php else: ?>
                <form method="post" style="display:inline;">
                    <?php echo CSRF::getTokenInput(); ?>
                    <input type="hidden" name="action" value="join">
                    <button type="submit" class="btn">
                        Join VTC
                    </button>
                </form>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p class="meta" style="margin-top:24px;">
            Please <a href="/login.php">login</a> to join this VTC.
        </p>
    <?php endif; ?>
</div>

<!-- Members List -->
<div class="card">
    <h3>Members (<?php echo count($members); ?>)</h3>
    
    <?php if (empty($members)): ?>
        <p class="meta">No members yet.</p>
    <?php else: ?>
        <table class="jobs-table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Role</th>
                    <th>Joined</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $member): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <?php if (!empty($member['avatar_url'])): ?>
                                <img src="<?php echo htmlspecialchars($member['avatar_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($member['username']); ?>"
                                     style="width:32px;height:32px;border-radius:4px;">
                            <?php endif; ?>
                            <span><?php echo htmlspecialchars($member['display_name'] ?? $member['username']); ?></span>
                        </div>
                    </td>
                    <td>
                        <span style="text-transform:uppercase;font-weight:600;
                                     color:<?php echo $member['role'] === 'owner' ? 'var(--accent-primary)' : 'var(--text-secondary)'; ?>">
                            <?php echo htmlspecialchars($member['role']); ?>
                        </span>
                    </td>
                    <td><?php echo date('M j, Y', strtotime($member['joined_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div style="margin-top:16px;">
    <a href="/vtcs.php" class="btn btn-secondary">← Back to VTC List</a>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
