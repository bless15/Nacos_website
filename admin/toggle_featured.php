<?php
require_once __DIR__ . '/../includes/security.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
requireAdminRole();

$db = getDB();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

if (!$id || !in_array($action, ['on','off'])) {
    redirectWithMessage('partners.php', 'Invalid request.', 'error');
}

$is_featured = $action === 'on' ? 1 : 0;
try {
    $db->query("UPDATE PARTNERS SET is_featured = ? WHERE partner_id = ?", [$is_featured, $id]);
    redirectWithMessage('partners.php', 'Partner feature flag updated.', 'success');
} catch (Exception $e) {
    redirectWithMessage('partners.php', 'Failed to update feature flag.', 'error');
}
