<?php
/**
 * ============================================
 * NACOS DASHBOARD - DELETE RESOURCE
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Include required files
require_once '../config/database.php';
require_once '../includes/auth.php';
requireAdminRole();

$db = getDB();
$resource_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$resource = $db->fetchOne("SELECT * FROM RESOURCES WHERE resource_id = :id", [':id' => $resource_id]);

if (!$resource) {
    redirectWithMessage('resources.php', 'Resource not found.', 'error');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        redirectWithMessage('resources.php', 'Invalid request.', 'error');
    }

    try {
        $db->query("DELETE FROM RESOURCES WHERE resource_id = :id", [':id' => $resource_id]);
        
        if (!empty($resource['file_path']) && file_exists($resource['file_path'])) {
            unlink($resource['file_path']);
        }
        
        redirectWithMessage('resources.php', 'Resource deleted successfully!', 'success');
    } catch (Exception $e) {
        redirectWithMessage('resources.php', 'An error occurred.', 'error');
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Resource - NACOS Admin</title>
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
                    <h1 class="h3 mb-0">Delete Resource</h1>
                    <a href="resources.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
                </div>
                <div class="row justify-content-center">
                    <div class="col-lg-6">
                        <div class="card border-danger">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-exclamation-triangle fa-4x text-danger mb-3"></i>
                                <h3 class="mb-3">Confirm Deletion</h3>
                                <p class="text-muted mb-4">Are you sure you want to delete this resource?</p>
                                <div class="alert alert-info text-start">
                                    <strong>Title:</strong> <?php echo htmlspecialchars($resource['title']); ?><br>
                                    <strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $resource['resource_type'])); ?><br>
                                    <strong>Downloads:</strong> <?php echo $resource['download_count']; ?>
                                </div>
                                <form action="delete_resource.php?id=<?php echo $resource_id; ?>" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <button type="submit" class="btn btn-danger me-2">
                                        <i class="fas fa-trash"></i> Yes, Delete
                                    </button>
                                    <a href="resources.php" class="btn btn-secondary">Cancel</a>
                                </form>
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
