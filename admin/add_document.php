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

// Include required files
require_once '../config/database.php';
require_once '../includes/auth.php';

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
                                INSERT INTO DOCUMENTS 
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
                                ':uploaded_by' => $_SESSION['member_id']
                            ];

                            $db->query($query, $params);
                            redirectWithMessage('documents.php', 'Document uploaded successfully!', 'success');
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
    <link rel="stylesheet" href="../assets/css/admin.css">
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
                        <h1 class="h3 mb-0">Upload New Document</h1>
                        <p class="text-muted">Add an official document to the system</p>
                    </div>
                    <a href="documents.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Documents
                    </a>
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
                                
                                <form action="add_document.php" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Document Title *</label>
                                        <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($input_data['title'] ?? ''); ?>" required>
                                        <small class="text-muted">e.g., "AGM Minutes - November 2025"</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($input_data['description'] ?? ''); ?></textarea>
                                        <small class="text-muted">Brief description of the document content</small>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="doc_type" class="form-label">Document Type *</label>
                                            <select class="form-select" id="doc_type" name="doc_type" required>
                                                <option value="">-- Select Type --</option>
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
                                            <input type="date" class="form-control" id="document_date" name="document_date" value="<?php echo htmlspecialchars($input_data['document_date'] ?? ''); ?>">
                                            <small class="text-muted">Date the document relates to</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="academic_session" class="form-label">Academic Session</label>
                                            <input type="text" class="form-control" id="academic_session" name="academic_session" value="<?php echo htmlspecialchars($input_data['academic_session'] ?? ''); ?>" placeholder="e.g., 2024/2025">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="tags" class="form-label">Tags</label>
                                        <input type="text" class="form-control" id="tags" name="tags" value="<?php echo htmlspecialchars($input_data['tags'] ?? ''); ?>" placeholder="e.g., AGM, 2024, Financial">
                                        <small class="text-muted">Comma-separated tags for easier search</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="document_file" class="form-label">Upload File *</label>
                                        <input type="file" class="form-control" id="document_file" name="document_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt" required>
                                        <small class="text-muted">Allowed: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT (Max: 10MB)</small>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-upload"></i> Upload Document
                                        </button>
                                        <a href="documents.php" class="btn btn-secondary">Cancel</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Upload Guidelines</h5>
                            </div>
                            <div class="card-body">
                                <h6><i class="fas fa-info-circle text-primary"></i> File Requirements</h6>
                                <ul class="small">
                                    <li>Maximum file size: <strong>10MB</strong></li>
                                    <li>Supported formats: PDF, Word, Excel, PowerPoint, Text</li>
                                    <li>Use clear, descriptive titles</li>
                                </ul>
                                
                                <h6 class="mt-3"><i class="fas fa-shield-alt text-success"></i> Visibility Settings</h6>
                                <ul class="small">
                                    <li><strong>Admin Only:</strong> Sensitive documents</li>
                                    <li><strong>Members:</strong> Internal documents</li>
                                    <li><strong>Public:</strong> Public-facing documents</li>
                                </ul>
                                
                                <h6 class="mt-3"><i class="fas fa-tags text-warning"></i> Tagging Tips</h6>
                                <ul class="small mb-0">
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
