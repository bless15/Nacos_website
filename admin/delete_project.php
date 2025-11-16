<?php
/**
 * ============================================
 * NACOS DASHBOARD - DELETE PROJECT
 * ============================================
 * Purpose: Delete project with confirmation
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

// Get project ID
$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch project details
$project = $db->fetchOne("SELECT * FROM PROJECTS WHERE project_id = :id", [':id' => $project_id]);

if (!$project) {
    redirectWithMessage('projects.php', 'Project not found.', 'error');
}

// Fetch team member count
$member_count = $db->fetchOne(
    "SELECT COUNT(*) as count FROM MEMBER_PROJECTS WHERE project_id = :id",
    [':id' => $project_id]
)['count'];

$error_message = '';

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = "Invalid request. Please try again.";
    } else {
        $action = $_POST['action'];
        
        if ($action === 'delete') {
            try {
                // Delete all member associations first
                $db->query("DELETE FROM MEMBER_PROJECTS WHERE project_id = :id", [':id' => $project_id]);
                
                // Delete the project
                $db->query("DELETE FROM PROJECTS WHERE project_id = :id", [':id' => $project_id]);
                
                redirectWithMessage('projects.php', 'Project deleted successfully.', 'success');
            } catch (Exception $e) {
                $error_message = "An error occurred while deleting the project. Please try again.";
                logSecurityEvent("Project deletion failed: " . $e->getMessage(), 'error');
            }
        } elseif ($action === 'cancel') {
            redirectWithMessage('projects.php', 'Project deletion cancelled.', 'info');
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
    <title>Delete Project - NACOS Admin</title>
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
                        <h1 class="h3 mb-0">Delete Project</h1>
                        <p class="text-muted">Confirm project deletion</p>
                    </div>
                    <a href="projects.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Projects
                    </a>
                </div>
                
                <!-- Confirmation Card -->
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="card border-danger">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Warning: Project Deletion</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($error_message)): ?>
                                    <div class="alert alert-danger">
                                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="alert alert-warning">
                                    <h5 class="alert-heading">Are you sure you want to delete this project?</h5>
                                    <p class="mb-0">This action cannot be undone. All data associated with this project will be permanently deleted.</p>
                                </div>
                                
                                <!-- Project Details -->
                                <div class="bg-light p-4 rounded mb-4">
                                    <h5 class="mb-3">Project Details</h5>
                                    <table class="table table-sm mb-0">
                                        <tr>
                                            <th width="30%">Title:</th>
                                            <td><?php echo htmlspecialchars($project['title']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Description:</th>
                                            <td><?php echo substr(htmlspecialchars($project['description']), 0, 150) . '...'; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Status:</th>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo match($project['project_status']) {
                                                        'completed' => 'success',
                                                        'in-progress' => 'primary',
                                                        'planned' => 'warning',
                                                        'on-hold' => 'secondary',
                                                        default => 'info'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst(str_replace('-', ' ', $project['project_status'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Team Members:</th>
                                            <td><?php echo $member_count; ?> member(s)</td>
                                        </tr>
                                        <tr>
                                            <th>Start Date:</th>
                                            <td><?php echo date('F d, Y', strtotime($project['start_date'])); ?></td>
                                        </tr>
                                    </table>
                                </div>
                                
                                <!-- Impact Notice -->
                                <div class="alert alert-info">
                                    <h6 class="alert-heading"><i class="fas fa-info-circle"></i> What will be deleted:</h6>
                                    <ul class="mb-0">
                                        <li>The project record and all its details</li>
                                        <li>All team member assignments for this project</li>
                                        <?php if ($member_count > 0): ?>
                                            <li class="text-danger"><strong>Note:</strong> This project has <?php echo $member_count; ?> team member(s) assigned</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                                
                                <!-- Action Buttons -->
                                <form action="delete_project.php?id=<?php echo $project_id; ?>" method="POST" class="confirm-action-form" data-message="Are you absolutely sure you want to delete this project? This action CANNOT be undone!">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    
                                    <div class="d-flex gap-3 justify-content-end">
                                        <button type="submit" name="action" value="cancel" class="btn btn-secondary">
                                            <i class="fas fa-times"></i> Cancel - Keep Project
                                        </button>
                                        <button type="submit" name="action" value="delete" class="btn btn-danger">
                                            <i class="fas fa-trash"></i> Yes, Delete Project Permanently
                                        </button>
                                    </div>
                                </form>
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
