<?php
/**
 * ============================================
 * NACOS DASHBOARD - DELETE PROJECT
 * ============================================
 */

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdminRole();

$db = getDB();
$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($project_id <= 0) {
    redirectWithMessage('projects.php', 'Invalid project ID.', 'error');
}

$project = $db->fetchOne("SELECT project_id FROM projects WHERE project_id = :id", [':id' => $project_id]);
if (!$project) {
    redirectWithMessage('projects.php', 'Project not found.', 'error');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithMessage('projects.php', 'Use the delete confirmation popup to remove a project.', 'info');
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    redirectWithMessage('projects.php', 'Invalid request.', 'error');
}

try {
    $db->beginTransaction();
    $db->query("DELETE FROM member_projects WHERE project_id = :id", [':id' => $project_id]);
    $db->query("DELETE FROM projects WHERE project_id = :id", [':id' => $project_id]);
    $db->commit();

    redirectWithMessage('projects.php', 'Project deleted successfully.', 'success');
} catch (Exception $e) {
    if (method_exists($db, 'rollBack')) {
        $db->rollBack();
    }
    logSecurityEvent("Project deletion failed: " . $e->getMessage(), 'error');
    redirectWithMessage('projects.php', 'An error occurred while deleting the project.', 'error');
}
