<?php
/**
 * ============================================
 * NACOS DASHBOARD - EDIT RESOURCE
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
requireAdminRole();

$db = getDB();
$resource_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$resource = $db->fetchOne("SELECT * FROM RESOURCES WHERE resource_id = :id", [':id' => $resource_id]);

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
                UPDATE RESOURCES 
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
</head>
<body>
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <?php include 'includes/navbar.php'; ?>
            <div class="container-fluid px-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">Edit Resource</h1>
                    <a href="resources.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
                </div>
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                <div class="card">
                    <div class="card-body">
                        <form action="edit_resource.php?id=<?php echo $resource_id; ?>" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <div class="mb-3">
                                <label class="form-label">Title *</label>
                                <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($_POST['title']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
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
                                    <input type="number" class="form-control" name="event_id" value="<?php echo htmlspecialchars($_POST['event_id'] ?? ''); ?>" placeholder="Event ID (optional)">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tags</label>
                                    <input type="text" class="form-control" name="tags" value="<?php echo htmlspecialchars($_POST['tags'] ?? ''); ?>" placeholder="comma,separated,tags">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Current File</label>
                                <div class="alert alert-info"><?php echo htmlspecialchars($resource['file_name'] ?? 'No file'); ?></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Replace File (Optional)</label>
                                <input type="file" class="form-control" name="resource_file">
                                <small class="text-muted">Leave empty to keep current file</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Resource URL/Link *</label>
                                <input type="text" class="form-control" name="link_url" value="<?php echo htmlspecialchars($_POST['link_url'] ?? ''); ?>" required>
                                <small class="text-muted">External URL or file path. Will be updated if you upload a new file.</small>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
                            <a href="resources.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
