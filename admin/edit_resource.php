<?php
/**
 * ============================================
 * NACOS DASHBOARD - EDIT RESOURCE
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/config.php';
require_once '../includes/auth.php';
requireAdminRole();

$db = getDB();
$resource_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$resource = $db->fetchOne("SELECT * FROM resources WHERE resource_id = :id", [':id' => $resource_id]);

if (!$resource) {
    redirectWithMessage('resources.php', 'Resource not found.', 'error');
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = "Invalid request.";
    } else {
        $title = sanitizeInput($_POST['title']);
        $description = sanitizeInput($_POST['description']);
        $resource_type = sanitizeInput($_POST['resource_type']);
        $event_id = !empty($_POST['event_id']) ? (int)$_POST['event_id'] : null;
        $tags = sanitizeInput($_POST['tags']);
        $link_url = sanitizeInput($_POST['link_url']);
        $visibility = sanitizeInput($_POST['visibility']);

        try {
            $file_size = $resource['file_size'];

            // Handle new file upload
            if (!empty($_FILES['resource_file']['name'])) {
                $file = $_FILES['resource_file'];
                if ($file['error'] === UPLOAD_ERR_OK) {
                    $file_tmp = $file['tmp_name'];
                    $new_file_size = $file['size'];
                    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                    $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'rar', 'txt', 'py', 'java', 'c', 'cpp', 'html', 'css', 'js', 'php'];

                    if (in_array($file_ext, $allowed) && $new_file_size <= 20971520) {
                        $upload_dir = '../uploads/resources/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        $unique_filename = time() . '_' . uniqid() . '.' . $file_ext;
                        $new_file_path = $upload_dir . $unique_filename;

                        if (move_uploaded_file($file_tmp, $new_file_path)) {
                            // Delete old file if it exists and is a local file
                            if (!empty($resource['link_url']) && file_exists($resource['link_url'])) {
                                unlink($resource['link_url']);
                            }
                            $link_url = $new_file_path;
                            $file_size = $new_file_size;
                        }
                    }
                }
            }

            $query = "
                UPDATE resources 
                SET title = :title, description = :description, resource_type = :resource_type,
                    link_url = :link_url, file_size = :file_size, event_id = :event_id,
                    tags = :tags, visibility = :visibility
                WHERE resource_id = :resource_id
            ";
            $params = [
                ':title' => $title, ':description' => $description, ':resource_type' => $resource_type,
                ':link_url' => $link_url, ':file_size' => $file_size, ':event_id' => $event_id,
                ':tags' => $tags, ':visibility' => $visibility, ':resource_id' => $resource_id
            ];

            $db->query($query, $params);
            redirectWithMessage('resources.php', 'Resource updated successfully!', 'success');
        } catch (Exception $e) {
            $error_message = "An error occurred.";
        }
    }
} else {
    $_POST = $resource;
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Resource - NACOS Admin</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
:root {
    --primary-color: #0F6B3E;
    --secondary-color: #1B8A56;
    --sidebar-bg: #0b5d35;
    --sidebar-hover: #0d6f40;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(180deg, #f7fbf8 0%, #edf6ef 100%);
}

/* Main Content */
.main-content {
    margin-left: 260px;
    padding: 30px;
    min-height: 100vh;
    flex: 1;
    width: calc(100% - 260px);
}

.wrapper {
    display: flex;
    width: 100%;
}

.container-fluid {
    width: 100% !important;
    max-width: 100% !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
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

.page-header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
}

.page-header-actions {
    display: flex;
    align-items: center;
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
    box-shadow: 0 0 0 0.2rem rgba(15, 107, 62, 0.15);
}

textarea.form-control {
    min-height: 120px;
}

/* Current File Display */
.current-file-display {
    background: linear-gradient(135deg, #edf6ef, #f7fbf8);
    border-left: 4px solid var(--primary-color);
    padding: 15px 20px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.current-file-display i {
    font-size: 32px;
    color: var(--primary-color);
}

.current-file-info p {
    margin: 0;
    color: #2c3e50;
}

.current-file-info strong {
    font-size: 16px;
}

.current-file-info small {
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
    box-shadow: 0 4px 12px rgba(15, 107, 62, 0.25);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

/* File Upload */
.file-upload-wrapper {
    position: relative;
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    padding: 30px;
    text-align: center;
    transition: all 0.3s;
}

.file-upload-wrapper:hover {
    border-color: var(--primary-color);
    background: #eef7f1;
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
        width: 100%;
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

    .page-header {
        padding: 18px;
    }

    .page-header-content {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
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
}

@media (max-width: 767.98px) {
    .main-content {
        padding: 12px;
    }

    .form-section {
        padding: 18px;
    }
}
    </style>
</head>
<body>
    <div class="wrapper">
        <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-content">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2 mb-md-0">
                        <button type="button" class="menu-toggle" id="menuToggle" aria-label="Toggle navigation menu">
                            <i class="fas fa-bars"></i>
                        </button>
                        <h1><i class="fas fa-edit me-2"></i>Edit Resource</h1>
                    </div>
                    <p class="mb-0 text-muted">Update resource information and files</p>
                </div>
                <div class="page-header-actions">
                    <a href="resources.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back
                    </a>
                </div>
            </div>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Form Card -->
        <form action="edit_resource.php?id=<?php echo $resource_id; ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="form-card">
                <!-- Basic Information Section -->
                <div class="form-section">
                    <div class="form-section-header">
                        <i class="fas fa-info-circle"></i>
                        <h5>Basic Information</h5>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" class="form-control" name="title" 
                               value="<?php echo htmlspecialchars($_POST['title']); ?>" 
                               placeholder="Enter resource title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" 
                                  placeholder="Provide a detailed description of this resource"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- Classification Section -->
                <div class="form-section">
                    <div class="form-section-header">
                        <i class="fas fa-tags"></i>
                        <h5>Classification & Visibility</h5>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Resource Type *</label>
                            <select class="form-select" name="resource_type" required>
                                <option value="slides" <?php echo ($_POST['resource_type'] ?? '') === 'slides' ? 'selected' : ''; ?>>Slides</option>
                                <option value="video" <?php echo ($_POST['resource_type'] ?? '') === 'video' ? 'selected' : ''; ?>>Video</option>
                                <option value="document" <?php echo ($_POST['resource_type'] ?? '') === 'document' ? 'selected' : ''; ?>>Document</option>
                                <option value="code" <?php echo ($_POST['resource_type'] ?? '') === 'code' ? 'selected' : ''; ?>>Code</option>
                                <option value="link" <?php echo ($_POST['resource_type'] ?? '') === 'link' ? 'selected' : ''; ?>>External Link</option>
                                <option value="book" <?php echo ($_POST['resource_type'] ?? '') === 'book' ? 'selected' : ''; ?>>Book</option>
                                <option value="other" <?php echo ($_POST['resource_type'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Visibility *</label>
                            <select class="form-select" name="visibility" required>
                                <option value="public" <?php echo ($_POST['visibility'] ?? 'public') === 'public' ? 'selected' : ''; ?>>Public</option>
                                <option value="members_only" <?php echo ($_POST['visibility'] ?? '') === 'members_only' ? 'selected' : ''; ?>>Members Only</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Related Event</label>
                            <input type="number" class="form-control" name="event_id" 
                                   value="<?php echo htmlspecialchars($_POST['event_id'] ?? ''); ?>" 
                                   placeholder="Enter event ID (optional)">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tags</label>
                            <input type="text" class="form-control" name="tags" 
                                   value="<?php echo htmlspecialchars($_POST['tags'] ?? ''); ?>" 
                                   placeholder="e.g., Git, Version Control, Tutorial">
                        </div>
                    </div>
                </div>

                <!-- File Management Section -->
                <div class="form-section">
                    <div class="form-section-header">
                        <i class="fas fa-file-upload"></i>
                        <h5>File Management</h5>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Current File</label>
                        <div class="current-file-display">
                            <i class="fas fa-file-pdf"></i>
                            <div class="current-file-info">
                                <p><strong><?php echo htmlspecialchars($resource['file_name'] ?? 'No file'); ?></strong></p>
                                <small class="text-muted">Current file attached to this resource</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Replace File (Optional)</label>
                        <div class="file-upload-wrapper">
                            <input type="file" name="resource_file" id="resourceFile">
                            <label class="file-upload-label" for="resourceFile">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span class="upload-text">Choose a file or drag it here</span>
                                <span class="upload-hint">Leave empty to keep current file</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Resource URL/Link *</label>
                        <input type="text" class="form-control" name="link_url" 
                               value="<?php echo htmlspecialchars($_POST['link_url'] ?? ''); ?>" 
                               placeholder="https://example.com/resource.pdf" required>
                        <small class="text-muted">External URL or file path. Will be updated if you upload a new file.</small>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Resource
                    </button>
                    <a href="resources.php" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Cancel
                    </a>
                </div>
            </div>
        </form>
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
                if (window.innerWidth > 991.98) {
                    closeSidebar();
                }
            });
        }
    </script>
</body>
</html>
