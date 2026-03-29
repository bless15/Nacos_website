<?php
/**
 * ============================================
 * NACOS DASHBOARD - DELETE RESOURCE
 * ============================================
 */

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdminRole();

$db = getDB();
$resource_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($resource_id <= 0) {
    redirectWithMessage('resources.php', 'Invalid resource ID.', 'error');
}

$resource = $db->fetchOne("SELECT * FROM resources WHERE resource_id = :id", [':id' => $resource_id]);
if (!$resource) {
    redirectWithMessage('resources.php', 'Resource not found.', 'error');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithMessage('resources.php', 'Use the delete confirmation popup to remove a resource.', 'info');
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    redirectWithMessage('resources.php', 'Invalid request.', 'error');
}

try {
    $db->query("DELETE FROM resources WHERE resource_id = :id", [':id' => $resource_id]);

    if (!empty($resource['file_path']) && file_exists($resource['file_path'])) {
        unlink($resource['file_path']);
    }

    redirectWithMessage('resources.php', 'Resource deleted successfully!', 'success');
} catch (Exception $e) {
    redirectWithMessage('resources.php', 'An error occurred while deleting the resource.', 'error');
}
