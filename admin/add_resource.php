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

// Include required files
require_once '../config/database.php';
require_once '../includes/auth.php';

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
                        INSERT INTO RESOURCES 
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
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/navbar.php'; ?>
            
            <div class="container-fluid px-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-0">Add New Resource</h1>
                        <p class="text-muted">Upload learning materials, tutorials, or study guides</p>
                    </div>
                    <a href="resources.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                                <form action="add_resource.php" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Title *</label>
                                        <input type="text" class="form-control" name="title" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" name="description" rows="3"></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Resource Type *</label>
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
                                            <label class="form-label">Level *</label>
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
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Course Code</label>
                                            <input type="text" class="form-control" name="course_code" placeholder="e.g., CSC201">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Tags</label>
                                            <input type="text" class="form-control" name="tags" placeholder="programming, python, algorithms">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Upload File</label>
                                        <input type="file" class="form-control" name="resource_file">
                                        <small class="text-muted">Max 20MB. Allowed: PDF, DOC, PPT, ZIP, code files</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">External Link (Optional)</label>
                                        <input type="url" class="form-control" name="external_link" placeholder="https://example.com">
                                        <small class="text-muted">For YouTube videos, GitHub repos, or external resources</small>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" name="is_featured" value="1">
                                        <label class="form-check-label">
                                            Mark as Featured Resource
                                        </label>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Add Resource
                                        </button>
                                        <a href="resources.php" class="btn btn-secondary">Cancel</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Guidelines</h5>
                            </div>
                            <div class="card-body">
                                <p class="small"><strong>Resource Types:</strong></p>
                                <ul class="small">
                                    <li><strong>Tutorial:</strong> Step-by-step guides</li>
                                    <li><strong>Code Sample:</strong> Example code</li>
                                    <li><strong>Past Question:</strong> Exam questions</li>
                                    <li><strong>Study Guide:</strong> Study materials</li>
                                    <li><strong>Video:</strong> Video tutorials</li>
                                    <li><strong>External Link:</strong> Web resources</li>
                                </ul>
                                
                                <p class="small mt-3"><strong>Tips:</strong></p>
                                <ul class="small mb-0">
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
    
    <?php // Include admin footer which loads Bootstrap and confirmation modal ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
