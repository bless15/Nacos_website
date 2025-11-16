<?php
/**
 * ============================================
 * NACOS DASHBOARD - DELETE DOCUMENT
 * ============================================
 * Purpose: Delete document with confirmation
 * Access: Admin only
 * Created: November 3, 2025
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Include required files
require_once '../config/database.php';
require_once '../includes/auth.php';

// Require admin login
requireAdminRole();

// Initialize database
$db = getDB();

// Get document ID
$doc_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch document details
$document = $db->fetchOne("SELECT * FROM DOCUMENTS WHERE doc_id = :id", [':id' => $doc_id]);

if (!$document) {
    redirectWithMessage('documents.php', 'Document not found.', 'error');
}

$error_message = '';

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = "Invalid request. Please try again.";
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'delete') {
            try {
                // Delete from database
                $db->query("DELETE FROM DOCUMENTS WHERE doc_id = :id", [':id' => $doc_id]);
                
                // Delete physical file
                if (file_exists($document['file_path'])) {
                    unlink($document['file_path']);
                }
                
                redirectWithMessage('documents.php', 'Document deleted successfully!', 'success');
            } catch (Exception $e) {
                $error_message = "An error occurred while deleting the document. Please try again.";
                logSecurityEvent("Document deletion failed: " . $e->getMessage(), 'error');
            }
        } elseif ($action === 'archive') {
            try {
                // Archive instead of delete
                $db->query("UPDATE DOCUMENTS SET is_archived = 1 WHERE doc_id = :id", [':id' => $doc_id]);
                
                redirectWithMessage('documents.php', 'Document archived successfully!', 'success');
            } catch (Exception $e) {
                $error_message = "An error occurred while archiving the document. Please try again.";
                logSecurityEvent("Document archive failed: " . $e->getMessage(), 'error');
            }
        } else {
            redirectWithMessage('documents.php', 'Operation cancelled.', 'info');
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Get file extension
$file_ext = strtolower(pathinfo($document['file_name'], PATHINFO_EXTENSION));

// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Document - NACOS Admin</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    
    <style>
        .warning-icon {
            font-size: 5rem;
            color: #dc3545;
            opacity: 0.2;
        }
        .danger-zone {
            border: 2px solid #dc3545;
            border-radius: 8px;
            padding: 2rem;
            background: #fff5f5;
        }
        .document-preview {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navigation -->
            <?php include 'includes/navbar.php'; ?>
            
            <!-- Page Content -->
            <div class="container-fluid px-4 py-4">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-0">Delete Document</h1>
                        <p class="text-muted">Permanently remove document from the system</p>
                    </div>
                    <a href="documents.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Documents
                    </a>
                </div>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <!-- Document Preview -->
                        <div class="document-preview">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <?php if ($file_ext === 'pdf'): ?>
                                        <i class="fas fa-file-pdf fa-3x text-danger"></i>
                                    <?php elseif (in_array($file_ext, ['doc', 'docx'])): ?>
                                        <i class="fas fa-file-word fa-3x text-primary"></i>
                                    <?php elseif (in_array($file_ext, ['xls', 'xlsx'])): ?>
                                        <i class="fas fa-file-excel fa-3x text-success"></i>
                                    <?php elseif (in_array($file_ext, ['ppt', 'pptx'])): ?>
                                        <i class="fas fa-file-powerpoint fa-3x text-warning"></i>
                                    <?php else: ?>
                                        <i class="fas fa-file fa-3x text-secondary"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($document['title']); ?></h5>
                                    <p class="text-muted mb-1">
                                        <small>
                                            <i class="fas fa-file"></i> <?php echo htmlspecialchars($document['file_name']); ?> 
                                            <span class="ms-2"><i class="fas fa-hdd"></i> <?php echo formatFileSize($document['file_size']); ?></span>
                                        </small>
                                    </p>
                                    <p class="text-muted mb-0">
                                        <small>
                                            <i class="fas fa-calendar"></i> Uploaded: <?php echo date('M d, Y', strtotime($document['upload_date'])); ?>
                                            <span class="ms-2"><i class="fas fa-download"></i> <?php echo $document['download_count']; ?> downloads</span>
                                        </small>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Warning Card -->
                        <div class="card border-danger mb-3">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-exclamation-triangle warning-icon"></i>
                                <h3 class="mb-3 text-danger">Warning: This Action Cannot Be Undone!</h3>
                                <p class="text-muted mb-0">
                                    You are about to permanently delete this document from the system. 
                                    The file will be removed from the server and cannot be recovered.
                                </p>
                            </div>
                        </div>
                        
                        <!-- Action Options -->
                        <div class="danger-zone">
                            <h5 class="mb-3"><i class="fas fa-shield-alt"></i> Choose an Action</h5>
                            <p class="text-muted mb-4">
                                You can either <strong>archive</strong> this document (recommended) or <strong>permanently delete</strong> it.
                            </p>
                            
                            <form action="delete_document.php?id=<?php echo $doc_id; ?>" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                
                                <div class="row g-3">
                                    <!-- Archive Option -->
                                    <div class="col-md-6">
                                        <div class="card h-100">
                                            <div class="card-body text-center">
                                                <i class="fas fa-archive fa-3x text-warning mb-3"></i>
                                                <h5>Archive Document</h5>
                                                <p class="text-muted small">
                                                    Hide document from main list but keep it in the system. 
                                                    Can be restored later.
                                                </p>
                                                <button type="submit" name="action" value="archive" class="btn btn-warning w-100">
                                                    <i class="fas fa-archive"></i> Archive Document
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Delete Option -->
                                    <div class="col-md-6">
                                        <div class="card h-100 border-danger">
                                            <div class="card-body text-center">
                                                <i class="fas fa-trash-alt fa-3x text-danger mb-3"></i>
                                                <h5 class="text-danger">Delete Permanently</h5>
                                                <p class="text-muted small">
                                                    Remove document and file from the system permanently. 
                                                    This cannot be undone!
                                                </p>
                                                <button type="button" name="action" value="delete" class="btn btn-danger w-100 confirm-action-btn" data-action="delete" data-message="Are you absolutely sure? This will permanently delete the document and file.">
                                                    <i class="fas fa-trash-alt"></i> Delete Forever
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-center mt-4">
                                    <a href="documents.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Cancel & Go Back
                                    </a>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Info Alert -->
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle"></i> <strong>Recommendation:</strong> 
                            We recommend archiving documents instead of deleting them to maintain historical records. 
                            Archived documents can be viewed by filtering in the documents list.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php // Include admin footer which loads Bootstrap and confirmation modal ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
