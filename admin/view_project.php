<?php
/**
 * ============================================
 * NACOS DASHBOARD - VIEW PROJECT DETAILS
 * ============================================
 * Purpose: Display comprehensive project information
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

// Fetch project team members
$team_members = $db->fetchAll(
    "SELECT m.* FROM MEMBERS m
     JOIN MEMBER_PROJECTS mp ON m.member_id = mp.member_id
     WHERE mp.project_id = :id
     ORDER BY m.full_name ASC",
    [':id' => $project_id]
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Project - NACOS Admin</title>
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
                        <h1 class="h3 mb-0"><?php echo htmlspecialchars($project['title']); ?></h1>
                        <p class="text-muted">Complete project details and team information</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="edit_project.php?id=<?php echo $project_id; ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Edit Project
                        </a>
                        <a href="projects.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Projects
                        </a>
                    </div>
                </div>
                
                <!-- Project Details -->
                <div class="row">
                    <div class="col-lg-8">
                        <!-- Main Details Card -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Project Information</h5>
                                <span class="badge bg-<?php 
                                    echo match($project['project_status']) {
                                        'completed' => 'success',
                                        'in-progress' => 'primary',
                                        'planned' => 'warning',
                                        'on-hold' => 'secondary',
                                        default => 'info'
                                    };
                                ?> fs-6">
                                    <?php echo ucfirst(str_replace('-', ' ', $project['project_status'])); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <h6 class="text-muted">Description</h6>
                                <p><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
                                
                                <hr>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <h6 class="text-muted">Start Date</h6>
                                        <p><i class="fas fa-calendar-alt text-primary"></i> <?php echo date('F d, Y', strtotime($project['start_date'])); ?></p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <h6 class="text-muted">Completion Date</h6>
                                        <p><i class="fas fa-calendar-check text-success"></i> 
                                            <?php echo $project['completion_date'] ? date('F d, Y', strtotime($project['completion_date'])) : 'Not set'; ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <?php if ($project['repository_link']): ?>
                                    <h6 class="text-muted">Repository Link</h6>
                                    <p>
                                        <a href="<?php echo htmlspecialchars($project['repository_link']); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                            <i class="fab fa-github"></i> View Repository
                                        </a>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Team Members Card -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Team Members (<?php echo count($team_members); ?>)</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($team_members)): ?>
                                    <p class="text-muted text-center py-4">No team members assigned to this project yet.</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($team_members as $member): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($member['full_name']); ?></h6>
                                                        <small class="text-muted">
                                                            <i class="fas fa-id-card"></i> <?php echo htmlspecialchars($member['matric_no']); ?> | 
                                                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($member['email']); ?>
                                                        </small>
                                                    </div>
                                                    <a href="view_member.php?id=<?php echo $member['member_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        View Profile
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sidebar -->
                    <div class="col-lg-4">
                        <!-- Quick Stats -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Quick Stats</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-muted">Project ID</span>
                                    <strong>#<?php echo $project_id; ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-muted">Team Size</span>
                                    <strong><?php echo count($team_members); ?> members</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-muted">Duration</span>
                                    <strong>
                                        <?php 
                                        if ($project['completion_date']) {
                                            $start = new DateTime($project['start_date']);
                                            $end = new DateTime($project['completion_date']);
                                            $interval = $start->diff($end);
                                            echo $interval->days . ' days';
                                        } else {
                                            echo 'Ongoing';
                                        }
                                        ?>
                                    </strong>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Actions</h5>
                            </div>
                            <div class="card-body">
                                <a href="edit_project.php?id=<?php echo $project_id; ?>" class="btn btn-primary w-100 mb-2">
                                    <i class="fas fa-edit"></i> Edit Project
                                </a>
                                <a href="delete_project.php?id=<?php echo $project_id; ?>" class="btn btn-danger w-100">
                                    <i class="fas fa-trash"></i> Delete Project
                                </a>
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
