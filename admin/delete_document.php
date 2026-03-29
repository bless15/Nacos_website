<?php
/**
 * ============================================
 * NACOS DASHBOARD - DELETE DOCUMENT
 * ============================================
 */

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdminRole();

$db = getDB();
$doc_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($doc_id <= 0) {
    redirectWithMessage('documents.php', 'Invalid document ID.', 'error');
}

$document = $db->fetchOne("SELECT * FROM documents WHERE doc_id = :id", [':id' => $doc_id]);
if (!$document) {
    redirectWithMessage('documents.php', 'Document not found.', 'error');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithMessage('documents.php', 'Use the delete confirmation popup to remove a document.', 'info');
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    redirectWithMessage('documents.php', 'Invalid request.', 'error');
}

try {
    $db->query("DELETE FROM documents WHERE doc_id = :id", [':id' => $doc_id]);

    if (!empty($document['file_path']) && file_exists($document['file_path'])) {
        unlink($document['file_path']);
    }

    redirectWithMessage('documents.php', 'Document deleted successfully!', 'success');
} catch (Exception $e) {
    logSecurityEvent("Document deletion failed: " . $e->getMessage(), 'error');
    redirectWithMessage('documents.php', 'An error occurred while deleting the document.', 'error');
}
