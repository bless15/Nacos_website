<?php
/**
 * ============================================
 * NACOS DASHBOARD - ADD NEW PROJECT
 * ============================================
 * Purpose: Create a new project
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
        $project_status = sanitizeInput($_POST['project_status']);
        $start_date = sanitizeInput($_POST['start_date']);
        $completion_date = !empty($_POST['completion_date']) ? sanitizeInput($_POST['completion_date']) : null;
        $repository_link = !empty($_POST['repository_link']) ? sanitizeInput($_POST['repository_link']) : null;
        $selected_members = isset($_POST['members']) ? $_POST['members'] : [];

        $input_data = $_POST;

        // Validation
            // Normalize incoming project_status values to DB enum when possible
            $status_map = [
                'planned' => 'ideation',
                'in-progress' => 'in_progress',
                'in_progress' => 'in_progress',
                'completed' => 'completed',
                'on-hold' => 'archived',
                'archived' => 'archived',
                'ideation' => 'ideation'
            ];
            $project_status = $status_map[$project_status] ?? $project_status;

            // Prepare tech stack (technologies + key features) as JSON to store in tech_stack column
            $technologies_input = isset($_POST['technologies']) ? trim($_POST['technologies']) : '';
            $features_input = isset($_POST['key_features']) ? trim($_POST['key_features']) : '';

            $technologies = [];
            if ($technologies_input !== '') {
                // Accept comma-separated or newline-separated values
                $technologies = array_values(array_filter(array_map('trim', preg_split('/[,\n]+/', $technologies_input))));
            }

            $features = [];
            if ($features_input !== '') {
                $features = array_values(array_filter(array_map('trim', preg_split('/[\n]+/', $features_input))));
            }

            $tech_stack_payload = json_encode([
                'technologies' => $technologies,
                'features' => $features
            ]);

            if (empty($title) || empty($description) || empty($project_status) || empty($start_date)) {
            $error_message = "Title, description, status, and start date are required.";
        } elseif ($completion_date && strtotime($completion_date) < strtotime($start_date)) {
            $error_message = "Completion date cannot be before start date.";
            } 
        else {
            try {
                // Insert new project (include tech_stack JSON)
                $query = "
                    INSERT INTO PROJECTS 
                    (title, description, project_status, start_date, completion_date, repository_link, tech_stack) 
                    VALUES 
                    (:title, :description, :project_status, :start_date, :completion_date, :repository_link, :tech_stack)
                ";
                $params = [
                    ':title' => $title,
                    ':description' => $description,
                    ':project_status' => $project_status,
                    ':start_date' => $start_date,
                    ':completion_date' => $completion_date,
                    ':repository_link' => $repository_link,
                    ':tech_stack' => $tech_stack_payload
                ];

                $db->query($query, $params);
                // Use the Database wrapper to get the last inserted id
                $project_id = $db->lastInsertId();

                // Add selected members to the project
                if (!empty($selected_members)) {
                    // MEMBER_PROJECTS requires a join_date (NOT NULL) in the schema - include it
                    $member_query = "INSERT INTO MEMBER_PROJECTS (member_id, project_id, join_date) VALUES (:member_id, :project_id, :join_date)";
                    $today = date('Y-m-d');
                    foreach ($selected_members as $member_id) {
                        $db->query($member_query, [
                            ':member_id' => $member_id,
                            ':project_id' => $project_id,
                            ':join_date' => $today
                        ]);
                    }
                }

                redirectWithMessage('projects.php', 'Project created successfully!', 'success');
            } catch (Exception $e) {
                $error_message = "An error occurred while creating the project. Please try again.";
                logSecurityEvent("Project creation failed: " . $e->getMessage(), 'error');
            }
        }
    }
}

// Fetch all active members for assignment
$members = $db->fetchAll("SELECT member_id, full_name, matric_no FROM MEMBERS WHERE membership_status = 'active' ORDER BY full_name ASC");

// Generate CSRF token
$csrf_token = generateCSRFToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Project - NACOS Admin</title>
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
                        <h1 class="h3 mb-0">Add New Project</h1>
                        <p class="text-muted">Create a new project and assign team members</p>
                    </div>
                    <a href="projects.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Projects
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
                                
                                <form action="add_project.php" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Project Title *</label>
                                        <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($input_data['title'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description *</label>
                                        <textarea class="form-control" id="description" name="description" rows="5" required><?php echo htmlspecialchars($input_data['description'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="project_status" class="form-label">Status *</label>
                                            <select class="form-select" id="project_status" name="project_status" required>
                                                <option value="">-- Select Status --</option>
                                                <?php
                                                // Database schema uses these enum values: ideation, in_progress, completed, archived
                                                $status_options = [
                                                    'ideation' => 'Planned',
                                                    'in_progress' => 'In Progress',
                                                    'completed' => 'Completed',
                                                    'archived' => 'On Hold / Archived'
                                                ];
                                                foreach ($status_options as $val => $label) : ?>
                                                    <option value="<?php echo $val; ?>" <?php echo (($input_data['project_status'] ?? '') === $val) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="repository_link" class="form-label">Repository Link</label>
                                            <input type="url" class="form-control" id="repository_link" name="repository_link" value="<?php echo htmlspecialchars($input_data['repository_link'] ?? ''); ?>" placeholder="https://github.com/...">
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="start_date" class="form-label">Start Date *</label>
                                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($input_data['start_date'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="completion_date" class="form-label">Completion Date</label>
                                            <input type="date" class="form-control" id="completion_date" name="completion_date" value="<?php echo htmlspecialchars($input_data['completion_date'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Assign Team Members</label>
                                        <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                            <?php if (empty($members)): ?>
                                                <p class="text-muted mb-0">No active members available.</p>
                                            <?php else: ?>
                                                <?php foreach ($members as $member): ?>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="members[]" value="<?php echo $member['member_id']; ?>" id="member_<?php echo $member['member_id']; ?>" 
                                                        <?php echo (in_array($member['member_id'], $input_data['members'] ?? [])) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="member_<?php echo $member['member_id']; ?>">
                                                            <?php echo htmlspecialchars($member['full_name']); ?> (<?php echo htmlspecialchars($member['matric_no']); ?>)
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="technologies" class="form-label">Technologies & Tools (comma separated)</label>
                                        <input type="text" class="form-control" id="technologies" name="technologies" value="<?php echo htmlspecialchars($input_data['technologies'] ?? ''); ?>" placeholder="PHP, MySQL, JavaScript, Bootstrap">
                                    </div>

                                    <div class="mb-3">
                                        <label for="key_features" class="form-label">Key Features (one per line)</label>
                                        <textarea class="form-control" id="key_features" name="key_features" rows="4" placeholder="Responsive UI\nAuthentication\nAPI Integration"><?php echo htmlspecialchars($input_data['key_features'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Create Project
                                        </button>
                                        <a href="projects.php" class="btn btn-secondary">Cancel</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Project Guidelines</h5>
                            </div>
                            <div class="card-body">
                                <h6><i class="fas fa-info-circle text-primary"></i> Tips</h6>
                                <ul class="small">
                                    <li>Use clear and descriptive project titles</li>
                                    <li>Provide detailed descriptions to help team members understand goals</li>
                                    <li>Link your GitHub or GitLab repository for easy access</li>
                                    <li>Assign relevant members who will contribute to the project</li>
                                </ul>
                                
                                <h6 class="mt-3"><i class="fas fa-shield-alt text-success"></i> Status Guidelines</h6>
                                <ul class="small mb-0">
                                    <li><strong>Planned:</strong> Project is in planning phase</li>
                                    <li><strong>In Progress:</strong> Active development</li>
                                    <li><strong>Completed:</strong> Project is finished</li>
                                    <li><strong>On Hold:</strong> Temporarily paused</li>
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
