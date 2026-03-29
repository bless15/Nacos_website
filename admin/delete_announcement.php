<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/announcements_helper.php';

requireAdminRole();

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    redirectWithMessage('announcements.php', 'Invalid announcement ID.', 'error');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithMessage('announcements.php', 'Invalid request method.', 'error');
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    redirectWithMessage('announcements.php', 'Invalid request token.', 'error');
}

$announcement = $db->fetchOne("SELECT announcement_id, image_path FROM announcements WHERE announcement_id = ?", [$id]);
if (!$announcement) {
    redirectWithMessage('announcements.php', 'Announcement not found.', 'error');
}

try {
    $db->beginTransaction();
    $db->query("DELETE FROM announcements WHERE announcement_id = ?", [$id]);
    logAnnouncementAudit($db, $id, 'deleted', 'Announcement deleted');
    $db->commit();

    if (!empty($announcement['image_path'])) {
        $full = __DIR__ . '/../' . $announcement['image_path'];
        if (is_file($full)) {
            @unlink($full);
        }
    }

    redirectWithMessage('announcements.php', 'Announcement deleted successfully.', 'success');
} catch (Exception $e) {
    $db->rollback();
    redirectWithMessage('announcements.php', 'Failed to delete announcement.', 'error');
}
