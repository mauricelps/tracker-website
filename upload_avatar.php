<?php
// upload_avatar.php - secure avatar upload (updated to check session ownership)
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/db.php';

if (!is_logged_in()) {
    http_response_code(403);
    echo "Not authenticated.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method not allowed.";
    exit;
}

// Validate CSRF token
CSRF::validateRequest();

$user = current_user();
if (!$user) {
    http_response_code(403);
    echo "User not found.";
    exit;
}

// Ensure posted user_id matches logged-in user
if (!isset($_POST['user_id']) || (int)$_POST['user_id'] !== (int)$user['id']) {
    http_response_code(403);
    echo "You can only upload avatar for your own account.";
    exit;
}

// --- file checks ---
$uploadDir = __DIR__ . '/uploads/avatars';
$maxSize = 1.5 * 1024 * 1024; // 1.5MB
$allowedTypes = ['image/png', 'image/jpeg', 'image/webp'];

if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo "No file uploaded or upload error.";
    exit;
}

$file = $_FILES['avatar'];
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo "File too large.";
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
if (!in_array($mime, $allowedTypes, true)) {
    http_response_code(400);
    echo "Invalid file type.";
    exit;
}

// Resize to 256x256 and save as PNG
$targetSize = 256;
$targetPath = $uploadDir . '/' . $user['id'] . '.png';

switch ($mime) {
    case 'image/png':
        $srcImg = imagecreatefrompng($file['tmp_name']);
        break;
    case 'image/jpeg':
        $srcImg = imagecreatefromjpeg($file['tmp_name']);
        break;
    case 'image/webp':
        if (function_exists('imagecreatefromwebp')) {
            $srcImg = imagecreatefromwebp($file['tmp_name']);
        } else {
            http_response_code(500);
            echo "WebP not supported on server.";
            exit;
        }
        break;
    default:
        http_response_code(400);
        echo "Unsupported image type.";
        exit;
}

if (!$srcImg) {
    http_response_code(500);
    echo "Failed to read image.";
    exit;
}

// Crop to square & resize
$w = imagesx($srcImg);
$h = imagesy($srcImg);
$size = min($w, $h);
$srcX = (int)(($w - $size) / 2);
$srcY = (int)(($h - $size) / 2);

$dst = imagecreatetruecolor($targetSize, $targetSize);
imagesavealpha($dst, true);
$transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
imagefill($dst, 0, 0, $transparent);
imagecopyresampled($dst, $srcImg, 0, 0, $srcX, $srcY, $targetSize, $targetSize, $size, $size);

if (!imagepng($dst, $targetPath, 6)) {
    http_response_code(500);
    echo "Failed to save avatar.";
    exit;
}
imagedestroy($srcImg);
imagedestroy($dst);
@chmod($targetPath, 0644);

$publicPath = '/uploads/avatars/' . $user['id'] . '.png';
try {
    $stmt = $pdo->prepare("UPDATE users SET avatar_url = ? WHERE id = ?");
    $stmt->execute([$publicPath, $user['id']]);
} catch (PDOException $e) {
    http_response_code(500);
    echo "DB error: " . htmlspecialchars($e->getMessage());
    exit;
}

// Clear current_user cache
unset($_SESSION['current_user_cached']);

header('Location: /user/' . $user['id']);
exit;
?>