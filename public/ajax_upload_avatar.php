<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

if (!isMemberLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['avatar'];
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$maxBytes = 2 * 1024 * 1024;
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!isset($allowed[$mime])) {
    echo json_encode(['success' => false, 'message' => 'Only JPG, PNG or WEBP images are allowed.']);
    exit;
}
if ($file['size'] > $maxBytes) {
    echo json_encode(['success' => false, 'message' => 'Image must be 2MB or smaller.']);
    exit;
}

$db = getDB();
$member = getCurrentMember();
$member_id = $member['member_id'];

$ext = $allowed[$mime];
$filename = 'member_' . $member_id . '_' . time() . '.' . $ext;
$targetDir = __DIR__ . '/../uploads/members/';
if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
$targetPath = $targetDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file.']);
    exit;
}

$relativePath = 'uploads/members/' . $filename;
try {
    $db->query("UPDATE members SET avatar = :avatar WHERE member_id = :id", [':avatar' => $relativePath, ':id' => $member_id]);
    echo json_encode(['success' => true, 'message' => 'Avatar updated', 'path' => $relativePath]);
    exit;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to update database']);
    exit;
}
