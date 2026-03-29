<?php
/**
 * ============================================
 * NACOS DASHBOARD - EDIT DOCUMENT
 * ============================================
 * Purpose: Edit document metadata and optionally replace file
 * Access: Admin only
 * Created: November 3, 2025
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Bootstrap and includes
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Require admin login
requireAdminRole();

// Initialize database
$db = getDB();

// Get document ID
$doc_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch document details
$document = $db->fetchOne("SELECT * FROM documents WHERE doc_id = :id", [':id' => $doc_id]);

if (!$document) {
    redirectWithMessage('documents.php', 'Document not found.', 'error');
}

$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = "Invalid request. Please try again.";
    } else {
        // Sanitize and retrieve input data
        $title = sanitizeInput($_POST['title']);
        $description = sanitizeInput($_POST['description']);
        $doc_type = sanitizeInput($_POST['doc_type']);
        $document_date = !empty($_POST['document_date']) ? sanitizeInput($_POST['document_date']) : null;
        $visibility = sanitizeInput($_POST['visibility']);
        $tags = sanitizeInput($_POST['tags']);
        $academic_session = !empty($_POST['academic_session']) ? sanitizeInput($_POST['academic_session']) : null;
        $is_archived = isset($_POST['is_archived']) ? 1 : 0;

        // Validation
        if (empty($title) || empty($doc_type) || empty($visibility)) {
            $error_message = "Title, document type, and visibility are required.";
        } else {
            try {
                $file_path = $document['file_path'];
                $file_name = $document['file_name'];
                $file_size = $document['file_size'];

                // Check if new file is uploaded
                if (!empty($_FILES['document_file']['name'])) {
                    $file = $_FILES['document_file'];
                    $file_error = $file['error'];

                    if ($file_error === UPLOAD_ERR_OK) {
                        $new_file_name = $file['name'];
                        $file_tmp = $file['tmp_name'];
                        $new_file_size = $file['size'];
                        $file_ext = strtolower(pathinfo($new_file_name, PATHINFO_EXTENSION));

                        // Allowed file types
                        $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'];

                        // Validate file extension
                        if (!in_array($file_ext, $allowed_extensions)) {
                            $error_message = "Invalid file type. Allowed: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT";
                        } elseif ($new_file_size > 10485760) { // 10MB limit
                            $error_message = "File size exceeds 10MB limit.";
                        } else {
                            // Create uploads directory if it doesn't exist
                            $upload_dir = '../uploads/documents/';
                            if (!is_dir($upload_dir)) {
                                mkdir($upload_dir, 0755, true);
                            }

                            // Generate unique filename
                            $unique_filename = time() . '_' . uniqid() . '.' . $file_ext;
                            $new_file_path = $upload_dir . $unique_filename;

                            // Move uploaded file
                            if (move_uploaded_file($file_tmp, $new_file_path)) {
                                // Delete old file
                                if (file_exists($file_path)) {
                                    unlink($file_path);
                                }

                                // Update file info
                                $file_path = $new_file_path;
                                $file_name = $new_file_name;
                                $file_size = $new_file_size;
                            } else {
                                $error_message = "Failed to upload new file.";
                            }
                        }
                    }
                }

                if (empty($error_message)) {
                    // Update document
                    $query = "
                        UPDATE documents 
                        SET title = :title,
                            file_name = :file_name,
                            file_path = :file_path,
                            doc_type = :doc_type,
                            description = :description,
                            document_date = :document_date,
                            visibility = :visibility,
                            tags = :tags,
                            file_size = :file_size,
                            academic_session = :academic_session,
                            is_archived = :is_archived
                        WHERE doc_id = :doc_id
                    ";
                    $params = [
                        ':title' => $title,
                        ':file_name' => $file_name,
                        ':file_path' => $file_path,
                        ':doc_type' => $doc_type,
                        ':description' => $description,
                        ':document_date' => $document_date,
                        ':visibility' => $visibility,
                        ':tags' => $tags,
                        ':file_size' => $file_size,
                        ':academic_session' => $academic_session,
                        ':is_archived' => $is_archived,
                        ':doc_id' => $doc_id
                    ];

                    $db->query($query, $params);
                    redirectWithMessage('documents.php', 'Document updated successfully!', 'success');
                }
            } catch (Exception $e) {
                $error_message = "An error occurred while updating the document. Please try again.";
                logSecurityEvent("Document update failed: " . $e->getMessage(), 'error');
            }
        }
    }
} else {
    // Pre-populate form with existing data
    $_POST = [
        'title' => $document['title'],
        'description' => $document['description'],
        'doc_type' => $document['doc_type'],
        'document_date' => $document['document_date'],
        'visibility' => $document['visibility'],
        'tags' => $document['tags'],
        'academic_session' => $document['academic_session'],
        'is_archived' => $document['is_archived']
    ];
}

// Generate CSRF token
$csrf_token = generateCSRFToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Document - NACOS Admin</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">

    <style>
    
:root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --sidebar-bg: #2c3e50;
            --sidebar-hover: #34495e;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
        }
        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 260px;
            background: var(--sidebar-bg);
            color: white;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 1000;
        }
        .sidebar-header {
            padding: 20px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            text-align: center;
        }
        .sidebar-header h4 {
            margin: 10px 0 5px;
            font-size: 20px;
            font-weight: 600;
        }
        .sidebar-header small {
            opacity: 0.9;
        }
        .sidebar-menu {
            padding: 20px 0;
        }
        .sidebar-menu a {
            display: block;
            padding: 12px 20px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s;
        }
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: var(--sidebar-hover);
            color: white;
            padding-left: 30px;
        }
        .sidebar-menu a i {
            width: 25px;
            margin-right: 10px;
        }
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 20px;
            min-height: 100vh;
        }

        .menu-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 10px;
            border: 2px solid #dee2e6;
            background: #fff;
            color: #2c3e50;
            font-size: 18px;
        }

        .menu-toggle:hover {
            background: #f8f9fa;
        }

        .sidebar-overlay {
            display: none;
        }

        .header-actions {
            display: flex;
            gap: 8px;
        }

        .form-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .form-actions .btn-danger {
            margin-left: auto;
        }

        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
                box-shadow: 6px 0 20px rgba(0, 0, 0, 0.2);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 15px;
            }

            .menu-toggle {
                display: inline-flex;
            }

            .sidebar-overlay {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.45);
                z-index: 999;
            }

            .sidebar-overlay.show {
                display: block;
            }

            .header-actions {
                width: 100%;
            }

            .header-actions .btn {
                width: 100%;
            }

            .form-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .form-actions .btn-danger {
                margin-left: 0;
            }

            .form-actions .btn {
                width: 100%;
            }
        }

        @media (max-width: 767.98px) {
            .sidebar-header {
                padding: 16px;
            }

            .sidebar-header h4 {
                font-size: 18px;
            }

            .sidebar-menu a {
                padding: 10px 16px;
            }

            .sidebar-menu a:hover,
            .sidebar-menu a.active {
                padding-left: 20px;
            }

            .main-content {
                padding: 12px;
            }
        }


</style>
</head>

<body>
    	     <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <div class="wrapper">
        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Content -->
            <div class="container-fluid px-4 py-4">
                <!-- Page Header -->
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-2 mb-md-0">
                            <button type="button" class="menu-toggle" id="menuToggle" aria-label="Toggle navigation menu">
                                <i class="fas fa-bars"></i>
                            </button>
                            <h1 class="h3 mb-0">Edit Document</h1>
                        </div>
                        <p class="text-muted">Update document metadata and optionally replace file</p>
                    </div>
                    <div class="header-actions">
                        <a href="documents.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Documents
                        </a>
                    </div>
                </div>
                
                <!-- Form -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                                <?php if (!empty($error_message)): ?>
                                    <div class="alert alert-danger">
                                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <form action="edit_document.php?id=<?php echo $doc_id; ?>" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Document Title *</label>
                                        <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($_POST['title']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($_POST['description']); ?></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="doc_type" class="form-label">Document Type *</label>
                                            <select class="form-select" id="doc_type" name="doc_type" required>
                                                <option value="meeting_minutes" <?php echo ($_POST['doc_type'] === 'meeting_minutes') ? 'selected' : ''; ?>>Meeting Minutes</option>
                                                <option value="financial_report" <?php echo ($_POST['doc_type'] === 'financial_report') ? 'selected' : ''; ?>>Financial Report</option>
                                                <option value="constitution" <?php echo ($_POST['doc_type'] === 'constitution') ? 'selected' : ''; ?>>Constitution</option>
                                                <option value="policy" <?php echo ($_POST['doc_type'] === 'policy') ? 'selected' : ''; ?>>Policy Document</option>
                                                <option value="annual_report" <?php echo ($_POST['doc_type'] === 'annual_report') ? 'selected' : ''; ?>>Annual Report</option>
                                                <option value="event_report" <?php echo ($_POST['doc_type'] === 'event_report') ? 'selected' : ''; ?>>Event Report</option>
                                                <option value="proposal" <?php echo ($_POST['doc_type'] === 'proposal') ? 'selected' : ''; ?>>Proposal</option>
                                                <option value="correspondence" <?php echo ($_POST['doc_type'] === 'correspondence') ? 'selected' : ''; ?>>Correspondence</option>
                                                <option value="handover" <?php echo ($_POST['doc_type'] === 'handover') ? 'selected' : ''; ?>>Handover Document</option>
                                                <option value="other" <?php echo ($_POST['doc_type'] === 'other') ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="visibility" class="form-label">Visibility *</label>
                                            <select class="form-select" id="visibility" name="visibility" required>
                                                <option value="admin" <?php echo ($_POST['visibility'] === 'admin') ? 'selected' : ''; ?>>Admin Only</option>
                                                <option value="members" <?php echo ($_POST['visibility'] === 'members') ? 'selected' : ''; ?>>Members</option>
                                                <option value="public" <?php echo ($_POST['visibility'] === 'public') ? 'selected' : ''; ?>>Public</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="document_date" class="form-label">Document Date</label>
                                            <input type="date" class="form-control" id="document_date" name="document_date" value="<?php echo htmlspecialchars($_POST['document_date'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="academic_session" class="form-label">Academic Session</label>
                                            <input type="text" class="form-control" id="academic_session" name="academic_session" value="<?php echo htmlspecialchars($_POST['academic_session'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="tags" class="form-label">Tags</label>
                                        <input type="text" class="form-control" id="tags" name="tags" value="<?php echo htmlspecialchars($_POST['tags'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Current File</label>
                                        <div class="alert alert-info">
                                            <i class="fas fa-file"></i> <?php echo htmlspecialchars($document['file_name']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="document_file" class="form-label">Replace File (Optional)</label>
                                        <input type="file" class="form-control" id="document_file" name="document_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt">
                                        <small class="text-muted">Leave empty to keep current file</small>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="is_archived" name="is_archived" value="1" <?php echo $_POST['is_archived'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_archived">
                                            Archive this document
                                        </label>
                                    </div>
                                    
                                    <div class="form-actions">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Update Document
                                        </button>
                                        <a href="documents.php" class="btn btn-secondary">Cancel</a>
                                        <button type="button" class="btn btn-danger" onclick="deleteDocument(<?php echo (int)$doc_id; ?>)">
                                            <i class="fas fa-trash"></i> Delete Document
                                        </button>
                                    </div>
                                </form>

                                <form id="deleteDocumentForm" method="POST" action="" style="display:none;">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Document Info</h5>
                            </div>
                            <div class="card-body">
                                <p class="small"><strong>Document ID:</strong> <?php echo $doc_id; ?></p>
                                <p class="small"><strong>Uploaded:</strong> <?php echo date('M d, Y', strtotime($document['upload_date'])); ?></p>
                                <p class="small"><strong>Downloads:</strong> <?php echo $document['download_count']; ?> times</p>
                                <p class="small mb-0"><strong>File Size:</strong> <?php echo number_format($document['file_size'] / 1048576, 2); ?> MB</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include __DIR__ . '/includes/footer.php'; ?>
    <script>
        function deleteDocument(documentId) {
            const form = document.getElementById('deleteDocumentForm');
            if (!form || !documentId) return;

            const submitDelete = function() {
                form.action = 'delete_document.php?id=' + encodeURIComponent(documentId);
                form.submit();
            };

            if (typeof window.confirmModal !== 'function') {
                if (confirm('Are you sure you want to delete this document? This action cannot be undone.')) {
                    submitDelete();
                }
                return;
            }

            window.confirmModal('Are you sure you want to delete this document? This action cannot be undone.', {
                title: 'Delete Document',
                okLabel: 'Delete'
            }).then(function(confirmed) {
                if (confirmed) submitDelete();
            });
        }

        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.querySelector('.sidebar');
        const sidebarOverlay = document.getElementById('sidebarBackdrop') || document.getElementById('sidebarOverlay');

        function closeSidebar() {
            if (!sidebar || !sidebarOverlay) return;
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        }

        if (menuToggle && sidebar && sidebarOverlay) {
            menuToggle.addEventListener('click', function () {
                sidebar.classList.toggle('show');
                sidebarOverlay.classList.toggle('show');
            });

            sidebarOverlay.addEventListener('click', closeSidebar);

            window.addEventListener('resize', function () {
                if (window.innerWidth > 991.98) {
                    closeSidebar();
                }
            });
        }
    </script>
</body>
</html>
