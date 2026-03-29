<?php
/**
 * ============================================
 * NACOS DASHBOARD - ADD NEW DOCUMENT
 * ============================================
 * Purpose: Upload new official document
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

$error_message = '';
$input_data = [];

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

        $input_data = $_POST;

        // Validation
        if (empty($title) || empty($doc_type) || empty($visibility)) {
            $error_message = "Title, document type, and visibility are required.";
        } elseif (empty($_FILES['document_file']['name'])) {
            $error_message = "Please select a file to upload.";
        } else {
            // Handle file upload
            $file = $_FILES['document_file'];
            $file_error = $file['error'];

            if ($file_error === UPLOAD_ERR_OK) {
                $file_name = $file['name'];
                $file_tmp = $file['tmp_name'];
                $file_size = $file['size'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                // Allowed file types
                $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'];

                // Validate file extension
                if (!in_array($file_ext, $allowed_extensions)) {
                    $error_message = "Invalid file type. Allowed: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT";
                } elseif ($file_size > 10485760) { // 10MB limit
                    $error_message = "File size exceeds 10MB limit.";
                } else {
                    try {
                        // Create uploads directory if it doesn't exist
                        $upload_dir = '../uploads/documents/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }

                        // Generate unique filename
                        $new_filename = time() . '_' . uniqid() . '.' . $file_ext;
                        $file_path = $upload_dir . $new_filename;

                        // Move uploaded file
                        if (move_uploaded_file($file_tmp, $file_path)) {
                            // Insert document into database
                            $query = "
                                INSERT INTO documents 
                                (title, file_name, file_path, doc_type, description, document_date, visibility, tags, file_size, academic_session, uploaded_by) 
                                VALUES 
                                (:title, :file_name, :file_path, :doc_type, :description, :document_date, :visibility, :tags, :file_size, :academic_session, :uploaded_by)
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
                                ':uploaded_by' => isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : null
                            ];

                            // If admin_id is not set, show error and block upload
                            if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
                                $error_message = 'Admin session missing. Please log in again.';
                            } else {
                                $db->query($query, $params);
                                redirectWithMessage('documents.php', 'Document uploaded successfully!', 'success');
                            }
                        } else {
                            $error_message = "Failed to upload file. Please try again.";
                        }
                    } catch (Exception $e) {
                        $error_message = "An error occurred while uploading the document. Please try again.";
                        logSecurityEvent("Document upload failed: " . $e->getMessage(), 'error');
                    }
                }
            } else {
                $error_message = "File upload error: " . $file_error;
            }
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload New Document - NACOS Admin</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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
    padding: 30px;
    min-height: 100vh;
}

/* Page Header */
.page-header {
    background: white;
    padding: 25px 30px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 30px;
}

.page-header h1 {
    font-size: 28px;
    font-weight: 700;
    color: #2c3e50;
    margin: 0;
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

/* Form Card */
.form-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    overflow: hidden;
}

.form-section {
    padding: 30px;
    border-bottom: 1px solid #e9ecef;
}

.form-section:last-child {
    border-bottom: none;
}

.form-section-header {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f1f3f5;
}

.form-section-header i {
    font-size: 24px;
    color: var(--primary-color);
    margin-right: 12px;
}

.form-section-header h5 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #2c3e50;
}

/* Form Controls */
.form-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
    font-size: 14px;
}

.form-control, .form-select {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 12px 15px;
    font-size: 14px;
    transition: all 0.3s;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
}

textarea.form-control {
    min-height: 120px;
}

/* File Upload */
.file-upload-wrapper {
    position: relative;
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    padding: 40px;
    text-align: center;
    transition: all 0.3s;
}

.file-upload-wrapper:hover {
    border-color: var(--primary-color);
    background: #f0f4ff;
}

.file-upload-wrapper input[type="file"] {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}

.file-upload-label {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    pointer-events: none;
}

.file-upload-label i {
    font-size: 48px;
    color: var(--primary-color);
}

.file-upload-label .upload-text {
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
}

.file-upload-label .upload-hint {
    font-size: 13px;
    color: #6c757d;
}

/* Action Buttons */
.form-actions {
    padding: 25px 30px;
    background: #f8f9fa;
    display: flex;
    gap: 15px;
    justify-content: flex-start;
}

.btn {
    padding: 12px 30px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s;
    border: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
}

.btn-primary:hover {
    background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

/* Info Card */
.info-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    overflow: hidden;
    position: sticky;
    top: 30px;
}

.info-card-header {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    padding: 20px;
}

.info-card-header h5 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
}

.info-card-body {
    padding: 25px;
}

.info-card-section {
    margin-bottom: 25px;
}

.info-card-section:last-child {
    margin-bottom: 0;
}

.info-card-section h6 {
    font-size: 14px;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-card-section ul {
    list-style: none;
    padding: 0;
    margin: 0;
    font-size: 13px;
    color: #6c757d;
}

.info-card-section ul li {
    padding: 8px 0;
    border-bottom: 1px solid #f1f3f5;
}

.info-card-section ul li:last-child {
    border-bottom: none;
}

/* Alert Messages */
.alert {
    border-radius: 8px;
    padding: 15px 20px;
    margin-bottom: 25px;
    border: none;
}

.alert-danger {
    background: #fee;
    color: #c33;
    border-left: 4px solid #c33;
}

/* Responsive */
@media (max-width: 992px) {
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

    .page-header-actions {
        width: 100%;
    }

    .page-header-actions .btn {
        width: 100%;
    }
    
    .form-section {
        padding: 20px;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
    }
    
    .info-card {
        position: static;
        margin-top: 20px;
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
    <div class="wrapper">
        <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-2 mb-md-0">
                            <button type="button" class="menu-toggle" id="menuToggle" aria-label="Toggle navigation menu">
                                <i class="fas fa-bars"></i>
                            </button>
                            <h1><i class="fas fa-file-upload me-2"></i>Upload New Document</h1>
                        </div>
                        <p class="mb-0 text-muted">Add an official document to the system</p>
                    </div>
                    <div class="page-header-actions">
                        <a href="documents.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Documents
                        </a>
                    </div>
                </div>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Form -->
            <div class="row">
                <div class="col-lg-8">
                    <form action="add_document.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="form-card">
                            <!-- Basic Information Section -->
                            <div class="form-section">
                                <div class="form-section-header">
                                    <i class="fas fa-info-circle"></i>
                                    <h5>Basic Information</h5>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="title" class="form-label">Document Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           placeholder='e.g., "AGM Minutes - November 2025"'
                                           value="<?php echo htmlspecialchars($input_data['title'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" 
                                              placeholder="Brief description of the document content"><?php echo htmlspecialchars($input_data['description'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <!-- Classification Section -->
                            <div class="form-section">
                                <div class="form-section-header">
                                    <i class="fas fa-tags"></i>
                                    <h5>Classification & Access</h5>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="doc_type" class="form-label">Document Type *</label>
                                        <select class="form-select" id="doc_type" name="doc_type" required>
                                            <option value="">Select Type</option>
                                            <option value="meeting_minutes" <?php echo (($input_data['doc_type'] ?? '') === 'meeting_minutes') ? 'selected' : ''; ?>>Meeting Minutes</option>
                                            <option value="financial_report" <?php echo (($input_data['doc_type'] ?? '') === 'financial_report') ? 'selected' : ''; ?>>Financial Report</option>
                                            <option value="constitution" <?php echo (($input_data['doc_type'] ?? '') === 'constitution') ? 'selected' : ''; ?>>Constitution</option>
                                            <option value="policy" <?php echo (($input_data['doc_type'] ?? '') === 'policy') ? 'selected' : ''; ?>>Policy Document</option>
                                            <option value="annual_report" <?php echo (($input_data['doc_type'] ?? '') === 'annual_report') ? 'selected' : ''; ?>>Annual Report</option>
                                            <option value="event_report" <?php echo (($input_data['doc_type'] ?? '') === 'event_report') ? 'selected' : ''; ?>>Event Report</option>
                                            <option value="proposal" <?php echo (($input_data['doc_type'] ?? '') === 'proposal') ? 'selected' : ''; ?>>Proposal</option>
                                            <option value="correspondence" <?php echo (($input_data['doc_type'] ?? '') === 'correspondence') ? 'selected' : ''; ?>>Correspondence</option>
                                            <option value="handover" <?php echo (($input_data['doc_type'] ?? '') === 'handover') ? 'selected' : ''; ?>>Handover Document</option>
                                            <option value="other" <?php echo (($input_data['doc_type'] ?? '') === 'other') ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="visibility" class="form-label">Visibility *</label>
                                        <select class="form-select" id="visibility" name="visibility" required>
                                            <option value="admin" <?php echo (($input_data['visibility'] ?? 'admin') === 'admin') ? 'selected' : ''; ?>>Admin Only</option>
                                            <option value="members" <?php echo (($input_data['visibility'] ?? '') === 'members') ? 'selected' : ''; ?>>Members</option>
                                            <option value="public" <?php echo (($input_data['visibility'] ?? '') === 'public') ? 'selected' : ''; ?>>Public</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="document_date" class="form-label">Document Date</label>
                                        <input type="date" class="form-control" id="document_date" name="document_date" 
                                               value="<?php echo htmlspecialchars($input_data['document_date'] ?? ''); ?>">
                                        <small class="text-muted">Date the document relates to</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="academic_session" class="form-label">Academic Session</label>
                                        <input type="text" class="form-control" id="academic_session" name="academic_session" 
                                               value="<?php echo htmlspecialchars($input_data['academic_session'] ?? ''); ?>" 
                                               placeholder="e.g., 2024/2025">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="tags" class="form-label">Tags</label>
                                    <input type="text" class="form-control" id="tags" name="tags" 
                                           value="<?php echo htmlspecialchars($input_data['tags'] ?? ''); ?>" 
                                           placeholder="e.g., AGM, 2024, Financial">
                                    <small class="text-muted">Comma-separated tags for easier search</small>
                                </div>
                            </div>

                            <!-- File Upload Section -->
                            <div class="form-section">
                                <div class="form-section-header">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <h5>Upload Document</h5>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="document_file" class="form-label">Upload File *</label>
                                    <div class="file-upload-wrapper">
                                        <input type="file" id="document_file" name="document_file" 
                                               accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt" required>
                                        <label class="file-upload-label" for="document_file">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <span class="upload-text">Choose a file or drag it here</span>
                                            <span class="upload-hint">PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT (Max: 10MB)</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload me-2"></i>Upload Document
                                </button>
                                <a href="documents.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div class="col-lg-4">
                    <!-- Info Card -->
                    <div class="info-card">
                        <div class="info-card-header">
                            <h5><i class="fas fa-info-circle me-2"></i>Upload Guidelines</h5>
                        </div>
                        <div class="info-card-body">
                            <div class="info-card-section">
                                <h6><i class="fas fa-file text-primary"></i> File Requirements</h6>
                                <ul>
                                    <li>Maximum file size: <strong>10MB</strong></li>
                                    <li>Supported formats: PDF, Word, Excel, PowerPoint, Text</li>
                                    <li>Use clear, descriptive titles</li>
                                </ul>
                            </div>
                            
                            <div class="info-card-section">
                                <h6><i class="fas fa-shield-alt text-success"></i> Visibility Settings</h6>
                                <ul>
                                    <li><strong>Admin Only:</strong> Sensitive documents</li>
                                    <li><strong>Members:</strong> Internal documents</li>
                                    <li><strong>Public:</strong> Public-facing documents</li>
                                </ul>
                            </div>
                            
                            <div class="info-card-section">
                                <h6><i class="fas fa-tags text-warning"></i> Tagging Tips</h6>
                                <ul>
                                    <li>Use relevant keywords</li>
                                    <li>Include year/session</li>
                                    <li>Add event names if applicable</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include __DIR__ . '/includes/footer.php'; ?>
    <script>
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
                if (window.innerWidth > 992) {
                    closeSidebar();
                }
            });
        }
    </script>
</body>
</html>
