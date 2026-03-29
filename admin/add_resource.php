<?php
/**
 * ============================================
 * NACOS DASHBOARD - ADD RESOURCE
 * ============================================
 * Purpose: Add new learning resource
 * Access: Admin only
 * Created: November 3, 2025
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Bootstrap and includes
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Require login
requireAdminRole();

// Initialize database
$db = getDB();

$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = "Invalid request. Please try again.";
    } else {
        $title = sanitizeInput($_POST['title']);
        $description = sanitizeInput($_POST['description']);
        $resource_type = sanitizeInput($_POST['resource_type']);
        $level = sanitizeInput($_POST['level']);
        $course_code = !empty($_POST['course_code']) ? sanitizeInput($_POST['course_code']) : null;
        $tags = sanitizeInput($_POST['tags']);
        $external_link = !empty($_POST['external_link']) ? sanitizeInput($_POST['external_link']) : null;
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $uploaded_by = getCurrentMember()['member_id'];

        if (empty($title) || empty($resource_type) || empty($level)) {
            $error_message = "Title, resource type, and level are required.";
        } else {
            try {
                $file_path = null;
                $file_name = null;
                $file_size = null;

                // Handle file upload
                if (!empty($_FILES['resource_file']['name'])) {
                    $file = $_FILES['resource_file'];
                    if ($file['error'] === UPLOAD_ERR_OK) {
                        $file_name = $file['name'];
                        $file_tmp = $file['tmp_name'];
                        $file_size = $file['size'];
                        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                        // Allowed extensions
                        $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'rar', 'txt', 'py', 'java', 'c', 'cpp', 'html', 'css', 'js', 'php'];

                        if (!in_array($file_ext, $allowed)) {
                            $error_message = "Invalid file type.";
                        } elseif ($file_size > 20971520) { // 20MB
                            $error_message = "File size exceeds 20MB limit.";
                        } else {
                            $upload_dir = '../uploads/resources/';
                            if (!is_dir($upload_dir)) {
                                mkdir($upload_dir, 0755, true);
                            }

                            $unique_filename = time() . '_' . uniqid() . '.' . $file_ext;
                            $file_path = $upload_dir . $unique_filename;

                            if (!move_uploaded_file($file_tmp, $file_path)) {
                                $error_message = "Failed to upload file.";
                            }
                        }
                    }
                }

                if (empty($error_message)) {
                    $query = "
                        INSERT INTO resources 
                        (title, description, resource_type, file_path, file_name, file_size, 
                         external_link, course_code, level, tags, uploaded_by, is_featured) 
                        VALUES 
                        (:title, :description, :resource_type, :file_path, :file_name, :file_size,
                         :external_link, :course_code, :level, :tags, :uploaded_by, :is_featured)
                    ";
                    $params = [
                        ':title' => $title,
                        ':description' => $description,
                        ':resource_type' => $resource_type,
                        ':file_path' => $file_path,
                        ':file_name' => $file_name,
                        ':file_size' => $file_size,
                        ':external_link' => $external_link,
                        ':course_code' => $course_code,
                        ':level' => $level,
                        ':tags' => $tags,
                        ':uploaded_by' => $uploaded_by,
                        ':is_featured' => $is_featured
                    ];

                    $db->query($query, $params);
                    redirectWithMessage('resources.php', 'Resource added successfully!', 'success');
                }
            } catch (Exception $e) {
                $error_message = "An error occurred. Please try again.";
                logSecurityEvent("Resource add failed: " . $e->getMessage(), 'error');
            }
        }
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Resource - NACOS Admin</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
:root {
    --primary-color: #0F6B3E;
    --secondary-color: #1B8A56;
    --sidebar-bg: #2c3e50;
    --sidebar-hover: #34495e;
    --success-gradient: linear-gradient(135deg, #0F6B3E, #1B8A56);
    --purple-gradient: linear-gradient(135deg, #0F6B3E, #1B8A56);
    --blue-gradient: linear-gradient(135deg, #0F6B3E, #1B8A56);
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(180deg, #f7fbf8 0%, #edf6ef 100%);
    min-height: 100vh;
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
    box-shadow: 4px 0 20px rgba(0,0,0,0.1);
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
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--purple-gradient);
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

/* Form Sections */
.form-section {
    background: white;
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 25px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    transition: all 0.3s;
}

.form-section:hover {
    box-shadow: 0 6px 25px rgba(15, 107, 62, 0.15);
}

.section-header {
    display: flex;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.section-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    background: var(--purple-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
    margin-right: 15px;
    box-shadow: 0 4px 12px rgba(15, 107, 62, 0.25);
}

.section-title {
    font-size: 20px;
    font-weight: 700;
    color: #2c3e50;
    margin: 0;
}

.section-subtitle {
    font-size: 13px;
    color: #6c757d;
    margin: 0;
}

/* Form Controls */
.form-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
    font-size: 14px;
}

.form-control, .form-select {
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 14px;
    transition: all 0.3s;
    background: #f8f9fa;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(15, 107, 62, 0.15);
    background: white;
}

textarea.form-control {
    resize: vertical;
    min-height: 100px;
}

/* File Upload Area */
.file-upload-area {
    border: 3px dashed #e9ecef;
    border-radius: 15px;
    padding: 40px 30px;
    text-align: center;
    background: #f8f9fa;
    transition: all 0.3s;
    cursor: pointer;
    position: relative;
}

.file-upload-area:hover {
    border-color: var(--primary-color);
    background: #eef7f1;
    transform: translateY(-2px);
}

.file-upload-area.dragover {
    border-color: var(--primary-color);
    background: #eef7f1;
}

.file-upload-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    background: var(--blue-gradient);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 35px;
}

.file-upload-area h5 {
    color: #2c3e50;
    font-weight: 700;
    margin-bottom: 10px;
}

.file-upload-area p {
    color: #6c757d;
    margin-bottom: 15px;
}

.file-upload-area input[type="file"] {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}

.file-types {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: center;
    margin-top: 15px;
}

.file-type-badge {
    padding: 4px 12px;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 20px;
    font-size: 11px;
    color: #6c757d;
    font-weight: 600;
}

/* Checkbox */
.form-check {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
    border: 2px solid #e9ecef;
    transition: all 0.3s;
}

.form-check:hover {
    background: #eef7f1;
    border-color: var(--primary-color);
}

.form-check-input:checked {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

/* Sticky Guidelines Card */
.guidelines-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    position: sticky;
    top: 20px;
    overflow: hidden;
}

.guidelines-header {
    background: var(--purple-gradient);
    color: white;
    padding: 20px;
    text-align: center;
}

.guidelines-header h5 {
    margin: 0;
    font-weight: 700;
    font-size: 18px;
    color: #ffffff;
}

.guidelines-body {
    padding: 25px;
}

.guideline-section {
    margin-bottom: 25px;
}

.guideline-section:last-child {
    margin-bottom: 0;
}

.guideline-section h6 {
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    font-size: 15px;
}

.guideline-section h6 i {
    margin-right: 8px;
    font-size: 16px;
}

.guideline-section ul {
    margin: 0;
    padding-left: 20px;
}

.guideline-section li {
    margin-bottom: 8px;
    color: #6c757d;
    font-size: 13px;
    line-height: 1.6;
}

.resource-type-item {
    padding: 10px 12px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 10px;
    border-left: 3px solid var(--primary-color);
}

.resource-type-item strong {
    color: #2c3e50;
    display: block;
    margin-bottom: 2px;
}

.resource-type-item span {
    color: #6c757d;
    font-size: 12px;
}

/* Alert */
.alert {
    border-radius: 10px;
    border: none;
    padding: 15px 20px;
    margin-bottom: 25px;
}

.alert-danger {
    background: linear-gradient(135deg, #ff6b6b, #ee5a6f);
    color: white;
}

/* Buttons */
.btn {
    padding: 12px 30px;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s;
    border: none;
}

.btn-primary {
    background: var(--purple-gradient);
    color: white;
    box-shadow: 0 4px 12px rgba(15, 107, 62, 0.25);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(15, 107, 62, 0.35);
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

/* Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.form-section {
    animation: fadeInUp 0.6s ease-out;
}

.form-section:nth-child(1) { animation-delay: 0.1s; }
.form-section:nth-child(2) { animation-delay: 0.2s; }
.form-section:nth-child(3) { animation-delay: 0.3s; }
.form-section:nth-child(4) { animation-delay: 0.4s; }

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

    .form-actions {
        flex-direction: column;
    }

    .form-actions .btn {
        width: 100%;
    }
    
    .guidelines-card {
        position: relative;
        top: 0;
        margin-top: 25px;
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

    .section-header {
        margin-bottom: 18px;
        padding-bottom: 12px;
    }

    .section-icon {
        width: 40px;
        height: 40px;
        font-size: 16px;
    }

    .section-title {
        font-size: 18px;
    }

    .guidelines-body {
        padding: 18px;
    }
}
    </style>
</head>
<body>
    <div class="wrapper">
   <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
        
        
        <div class="main-content">
            <!--?php include 'includes/navbar.php'; ? -->
            
            <div class="container-fluid">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-2 mb-md-0">
                                <button type="button" class="menu-toggle" id="menuToggle" aria-label="Toggle navigation menu">
                                    <i class="fas fa-bars"></i>
                                </button>
                                <h1><i class="fas fa-file-upload me-2"></i>Add New Resource</h1>
                            </div>
                            <p class="mb-0 text-muted">Upload learning materials, tutorials, or study guides</p>
                        </div>
                        <div class="page-header-actions">
                            <a href="resources.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Form Content -->
                <div class="row">
                    <div class="col-lg-8">
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form action="add_resource.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <!-- Basic Information Section -->
                            <div class="form-section">
                                <div class="section-header">
                                    <div class="section-icon">
                                        <i class="fas fa-info-circle"></i>
                                    </div>
                                    <div>
                                        <h3 class="section-title">Basic Information</h3>
                                        <p class="section-subtitle">Resource details and description</p>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="title" placeholder="Enter resource title" required>
                                </div>
                                
                                <div class="mb-0">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="description" rows="3" placeholder="Brief description of the resource..."></textarea>
                                </div>
                            </div>
                                    
                            <!-- Classification Section -->
                            <div class="form-section">
                                <div class="section-header">
                                    <div class="section-icon">
                                        <i class="fas fa-tag"></i>
                                    </div>
                                    <div>
                                        <h3 class="section-title">Classification</h3>
                                        <p class="section-subtitle">Categorize your resource</p>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Resource Type <span class="text-danger">*</span></label>
                                        <select class="form-select" name="resource_type" required>
                                            <option value="tutorial">Tutorial</option>
                                            <option value="code_sample">Code Sample</option>
                                            <option value="past_question">Past Question</option>
                                            <option value="study_guide">Study Guide</option>
                                            <option value="video">Video</option>
                                            <option value="pdf">PDF Document</option>
                                            <option value="link">External Link</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Level <span class="text-danger">*</span></label>
                                        <select class="form-select" name="level" required>
                                            <option value="general">General (All Levels)</option>
                                            <option value="100">100 Level</option>
                                            <option value="200">200 Level</option>
                                            <option value="300">300 Level</option>
                                            <option value="400">400 Level</option>
                                            <option value="500">500 Level</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-0">
                                        <label class="form-label">Course Code</label>
                                        <input type="text" class="form-control" name="course_code" placeholder="e.g., CSC201">
                                    </div>
                                    <div class="col-md-6 mb-0">
                                        <label class="form-label">Tags</label>
                                        <input type="text" class="form-control" name="tags" placeholder="programming, python, algorithms">
                                    </div>
                                </div>
                            </div>
                                    
                            <!-- File Upload Section -->
                            <div class="form-section">
                                <div class="section-header">
                                    <div class="section-icon">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                    </div>
                                    <div>
                                        <h3 class="section-title">Upload Resource</h3>
                                        <p class="section-subtitle">Upload file or provide external link</p>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Upload File</label>
                                    <div class="file-upload-area">
                                        <div class="file-upload-icon">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                        </div>
                                        <h5>Drag & Drop your file here</h5>
                                        <p class="text-muted">or click to browse from your computer</p>
                                        <div class="file-types">
                                            <span class="file-type-badge">PDF</span>
                                            <span class="file-type-badge">DOC</span>
                                            <span class="file-type-badge">PPT</span>
                                            <span class="file-type-badge">ZIP</span>
                                            <span class="file-type-badge">Code Files</span>
                                        </div>
                                        <input type="file" name="resource_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.zip,.rar,.txt,.py,.java,.c,.cpp,.html,.css,.js,.php">
                                    </div>
                                    <small class="text-muted d-block mt-2"><i class="fas fa-info-circle me-1"></i>Max 20MB. Allowed: PDF, DOC, PPT, ZIP, code files</small>
                                </div>
                                
                                <div class="mb-0">
                                    <label class="form-label">External Link (Optional)</label>
                                    <input type="url" class="form-control" name="external_link" placeholder="https://example.com">
                                    <small class="text-muted d-block mt-2"><i class="fas fa-info-circle me-1"></i>For YouTube videos, GitHub repos, or external resources</small>
                                </div>
                            </div>
                            
                            <!-- Settings Section -->
                            <div class="form-section">
                                <div class="section-header">
                                    <div class="section-icon">
                                        <i class="fas fa-cog"></i>
                                    </div>
                                    <div>
                                        <h3 class="section-title">Display Settings</h3>
                                        <p class="section-subtitle">Configure resource visibility</p>
                                    </div>
                                </div>
                                
                                <div class="form-check mb-0">
                                    <input type="checkbox" class="form-check-input" id="is_featured" name="is_featured" value="1">
                                    <label class="form-check-label" for="is_featured">
                                        <strong><i class="fas fa-star me-2"></i>Mark as Featured Resource</strong>
                                        <small class="d-block text-muted mt-1">Featured resources are highlighted on the main page</small>
                                    </label>
                                </div>
                            </div>
                                    
                            <!-- Form Actions -->
                            <div class="d-flex gap-3 form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Add Resource
                                </button>
                                <a href="resources.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Guidelines Sidebar -->
                    <div class="col-lg-4">
                        <div class="guidelines-card">
                            <div class="guidelines-header">
                                <i class="fas fa-lightbulb fa-2x mb-2"></i>
                                <h5>Resource Guidelines</h5>
                            </div>
                            <div class="guidelines-body">
                                <div class="guideline-section">
                                    <h6><i class="fas fa-th-list text-primary"></i>Resource Types</h6>
                                    <div class="resource-type-item">
                                        <strong>Tutorial</strong>
                                        <span>Step-by-step guides</span>
                                    </div>
                                    <div class="resource-type-item">
                                        <strong>Code Sample</strong>
                                        <span>Example code snippets</span>
                                    </div>
                                    <div class="resource-type-item">
                                        <strong>Past Question</strong>
                                        <span>Exam questions & answers</span>
                                    </div>
                                    <div class="resource-type-item">
                                        <strong>Study Guide</strong>
                                        <span>Study materials</span>
                                    </div>
                                    <div class="resource-type-item">
                                        <strong>Video</strong>
                                        <span>Video tutorials</span>
                                    </div>
                                    <div class="resource-type-item">
                                        <strong>External Link</strong>
                                        <span>Web resources</span>
                                    </div>
                                </div>
                                
                                <div class="guideline-section">
                                    <h6><i class="fas fa-check-circle text-success"></i>Best Practices</h6>
                                    <ul>
                                        <li>Use clear, descriptive titles</li>
                                        <li>Add relevant tags for easy search</li>
                                        <li>Include course codes when applicable</li>
                                        <li>Feature high-quality resources</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php // Include admin footer which loads Bootstrap and confirmation modal ?>
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
