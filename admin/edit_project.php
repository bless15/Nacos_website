<?php
/**
 * ============================================
 * NACOS DASHBOARD - EDIT PROJECT
 * ============================================
 * Purpose: Edit existing project details
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

// Get project ID
$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch project details
$project = $db->fetchOne("SELECT * FROM projects WHERE project_id = :id", [':id' => $project_id]);

if (!$project) {
    redirectWithMessage('projects.php', 'Project not found.', 'error');
}

// Fetch current project members
$current_members = $db->fetchAll(
    "SELECT member_id FROM member_projects WHERE project_id = :id",
    [':id' => $project_id]
);
$current_member_ids = array_column($current_members, 'member_id');

$error_message = '';

// Helper to parse tech_stack field into form-friendly fields
function parseTechStack($raw) {
    if (empty($raw)) return ['technologies' => '', 'key_features' => ''];
    $decoded = json_decode($raw, true);
    if (is_array($decoded) && (isset($decoded['technologies']) || isset($decoded['features']))) {
        $techs = isset($decoded['technologies']) ? implode(', ', $decoded['technologies']) : '';
        $feats = isset($decoded['features']) ? implode("\n", $decoded['features']) : '';
        return ['technologies' => $techs, 'key_features' => $feats];
    }
    // Fallback: treat as comma-separated list of technologies
    return ['technologies' => $raw, 'key_features' => ''];
}

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
    $technologies_input = isset($_POST['technologies']) ? trim($_POST['technologies']) : '';
    $features_input = isset($_POST['key_features']) ? trim($_POST['key_features']) : '';
        $selected_members = isset($_POST['members']) ? $_POST['members'] : [];

        // Validation
        if (empty($title) || empty($description) || empty($project_status) || empty($start_date)) {
            $error_message = "Title, description, status, and start date are required.";
        } elseif ($completion_date && strtotime($completion_date) < strtotime($start_date)) {
            $error_message = "Completion date cannot be before start date.";
        } else {
            try {
                // Normalize status mapping similar to add_project
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

                // Prepare tech_stack JSON payload
                $technologies = [];
                if ($technologies_input !== '') {
                    $technologies = array_values(array_filter(array_map('trim', preg_split('/[,\n]+/', $technologies_input))));
                }
                $features = [];
                if ($features_input !== '') {
                    $features = array_values(array_filter(array_map('trim', preg_split('/[\n]+/', $features_input))));
                }
                $tech_stack_payload = json_encode(['technologies' => $technologies, 'features' => $features]);

                // Update project (include tech_stack)
                $query = "
                    UPDATE projects 
                    SET title = :title, 
                        description = :description, 
                        project_status = :project_status, 
                        start_date = :start_date, 
                        completion_date = :completion_date, 
                        repository_link = :repository_link,
                        tech_stack = :tech_stack
                    WHERE project_id = :project_id
                ";
                $params = [
                    ':title' => $title,
                    ':description' => $description,
                    ':project_status' => $project_status,
                    ':start_date' => $start_date,
                    ':completion_date' => $completion_date,
                    ':repository_link' => $repository_link,
                    ':tech_stack' => $tech_stack_payload,
                    ':project_id' => $project_id
                ];

                $db->query($query, $params);

                // Update project members
                // First, remove all existing members
                $db->query("DELETE FROM member_projects WHERE project_id = :id", [':id' => $project_id]);

                // Then, add selected members
                if (!empty($selected_members)) {
                    $member_query = "INSERT INTO member_projects (member_id, project_id) VALUES (:member_id, :project_id)";
                    foreach ($selected_members as $member_id) {
                        $db->query($member_query, [
                            ':member_id' => $member_id,
                            ':project_id' => $project_id
                        ]);
                    }
                }

                redirectWithMessage('projects.php', 'Project updated successfully!', 'success');
            } catch (Exception $e) {
                $error_message = "An error occurred while updating the project. Please try again.";
                logSecurityEvent("Project update failed: " . $e->getMessage(), 'error');
            }
        }
    }
} else {
    // Pre-populate form with existing data
    $tech_fields = parseTechStack($project['tech_stack'] ?? '');
    $_POST = [
        'title' => $project['title'],
        'description' => $project['description'],
        'project_status' => $project['project_status'],
        'start_date' => $project['start_date'],
        'completion_date' => $project['completion_date'],
        'repository_link' => $project['repository_link'],
        'members' => $current_member_ids,
        'technologies' => $tech_fields['technologies'],
        'key_features' => $tech_fields['key_features']
    ];
}

// Fetch all active members
$members = $db->fetchAll("SELECT member_id, full_name, matric_no FROM members WHERE membership_status = 'active' ORDER BY full_name ASC");

// Generate CSRF token
$csrf_token = generateCSRFToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Project - NACOS Admin</title>
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
    --success-gradient: linear-gradient(135deg, #0F6B3E, #1B8A56);
    --purple-gradient: linear-gradient(135deg, #0F6B3E, #1B8A56);
    --danger-gradient: linear-gradient(135deg, #dc3545, #b02a37);
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(180deg, #f7fbf8 0%, #edf6ef 100%);
    min-height: 100vh;
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

.menu-toggle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    border: 2px solid #dee2e6;
    border-radius: 10px;
    background: #ffffff;
    color: var(--primary-color);
    cursor: pointer;
}

.menu-toggle:hover,
.menu-toggle:focus {
    background: #f8f9fa;
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

.page-header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
}

.page-header-main {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.page-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.page-header-actions .btn {
    white-space: nowrap;
    flex-shrink: 0;
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
    min-height: 120px;
}

/* Member Selection */
.member-selection {
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 20px;
    max-height: 350px;
    overflow-y: auto;
    background: #f8f9fa;
}

.form-check {
    padding: 12px 15px;
    margin-bottom: 8px;
    background: white;
    border-radius: 8px;
    transition: all 0.3s;
}

.form-check:hover {
    background: #eef7f1;
    transform: translateX(5px);
}

.form-check-input:checked ~ .form-check-label {
    color: var(--primary-color);
    font-weight: 600;
}

/* Sticky Info Card */
.info-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    position: sticky;
    top: 20px;
    overflow: hidden;
}

.info-header {
    background: var(--purple-gradient);
    color: white;
    padding: 20px;
    text-align: center;
}

.info-header h5 {
    margin: 0;
    font-weight: 700;
    font-size: 18px;
    color: white;
}

.info-body {
    padding: 25px;
}

.info-item {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
    margin-bottom: 15px;
    border-left: 4px solid var(--primary-color);
}

.info-item:last-child {
    margin-bottom: 0;
}

.info-label {
    font-size: 12px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 5px;
    font-weight: 600;
}

.info-value {
    font-size: 16px;
    color: #2c3e50;
    font-weight: 700;
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
    box-shadow: 0 4px 12px rgba(15, 107, 62, 0.25);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(15, 107, 62, 0.35);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

.btn-danger {
    background: var(--danger-gradient);
    color: white;
    box-shadow: 0 4px 12px rgba(238, 90, 111, 0.3);
}

.btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(238, 90, 111, 0.4);
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
    .main-content {
        margin-left: 0;
        padding: 15px;
    }

    .sidebar {
        width: var(--sidebar-width, 240px);
        position: fixed;
        left: 0;
        top: 0;
        height: 100vh;
        transform: translateX(-100%);
        transition: transform .28s ease;
        z-index: 1000;
    }

    body.sidebar-open .sidebar {
        transform: translateX(0);
    }

    .sidebar-backdrop {
        display: block;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.35);
        z-index: 900;
        opacity: 0;
        transition: opacity .25s ease;
        pointer-events: none;
    }

    body.sidebar-open .sidebar-backdrop {
        opacity: 1;
        pointer-events: auto;
    }

    body.sidebar-open {
        overflow: hidden;
    }

    .page-header {
        padding: 18px;
    }

    .page-header h1 {
        font-size: 24px;
    }

    .page-header-content {
        align-items: flex-start;
    }
    
    .info-card {
        position: relative;
        top: 0;
        margin-top: 25px;
    }
}

@media (max-width: 767.98px) {
    .page-header-content {
        flex-direction: column;
        align-items: flex-start;
    }

    .page-header-actions {
        width: 100%;
    }

    .page-header-actions .btn {
        width: 100%;
        text-align: center;
    }

    .header-back-btn {
        width: 100%;
        align-self: stretch;
        text-align: center;
        justify-content: center;
    }
}
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Content -->
            <div class="container-fluid">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="page-header-content">
                        <div class="page-header-main">
                            <button class="menu-toggle" id="menuToggle" type="button" aria-label="Toggle navigation" aria-expanded="false">
                                <i class="fas fa-bars"></i>
                            </button>
                            <div>
                                <h1><i class="fas fa-edit me-2"></i>Edit Project</h1>
                                <p class="mb-0 text-muted">Update project details and manage team members</p>
                            </div>
                        </div>
                        <div class="page-header-actions">
                            <a href="projects.php" class="btn btn-secondary header-back-btn">
                                <i class="fas fa-arrow-left me-2"></i>Back to Projects
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Form -->
                <div class="row">
                    <div class="col-lg-8">
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form action="edit_project.php?id=<?php echo $project_id; ?>" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <!-- Basic Information Section -->
                            <div class="form-section">
                                <div class="section-header">
                                    <div class="section-icon">
                                        <i class="fas fa-info-circle"></i>
                                    </div>
                                    <div>
                                        <h3 class="section-title">Basic Information</h3>
                                        <p class="section-subtitle">Essential project details</p>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="title" class="form-label">Project Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($_POST['title']); ?>" placeholder="Enter project title" required>
                                </div>
                                
                                <div class="mb-0">
                                    <label for="description" class="form-label">Description *</label>
                                    <textarea class="form-control" id="description" name="description" rows="5" placeholder="Describe the project goals, objectives, and key deliverables..." required><?php echo htmlspecialchars($_POST['description']); ?></textarea>
                                </div>
                            </div>
                                    
                            <!-- Project Details Section -->
                            <div class="form-section">
                                <div class="section-header">
                                    <div class="section-icon">
                                        <i class="fas fa-cog"></i>
                                    </div>
                                    <div>
                                        <h3 class="section-title">Project Details</h3>
                                        <p class="section-subtitle">Status, timeline, and repository</p>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="project_status" class="form-label">Status *</label>
                                        <select class="form-select" id="project_status" name="project_status" required>
                                            <?php
                                            $status_options = [
                                                'ideation' => 'Planned',
                                                'in_progress' => 'In Progress',
                                                'completed' => 'Completed',
                                                'archived' => 'On Hold / Archived'
                                            ];
                                            foreach ($status_options as $val => $label) : ?>
                                                <option value="<?php echo $val; ?>" <?php echo ($_POST['project_status'] === $val) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="repository_link" class="form-label">Repository Link</label>
                                        <input type="url" class="form-control" id="repository_link" name="repository_link" value="<?php echo htmlspecialchars($_POST['repository_link'] ?? ''); ?>" placeholder="https://github.com/username/repo">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-0">
                                        <label for="start_date" class="form-label">Start Date *</label>
                                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($_POST['start_date']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-0">
                                        <label for="completion_date" class="form-label">Completion Date</label>
                                        <input type="date" class="form-control" id="completion_date" name="completion_date" value="<?php echo htmlspecialchars($_POST['completion_date'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                                    
                            <!-- Team Assignment Section -->
                            <div class="form-section">
                                <div class="section-header">
                                    <div class="section-icon">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div>
                                        <h3 class="section-title">Team Members</h3>
                                        <p class="section-subtitle">Select members who will work on this project</p>
                                    </div>
                                </div>
                                
                                <div class="member-selection">
                                    <?php if (empty($members)): ?>
                                        <p class="text-muted mb-0 text-center"><i class="fas fa-user-slash me-2"></i>No active members available.</p>
                                    <?php else: ?>
                                        <?php foreach ($members as $member): ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="members[]" value="<?php echo $member['member_id']; ?>" id="member_<?php echo $member['member_id']; ?>" 
                                                <?php echo (in_array($member['member_id'], $_POST['members'])) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="member_<?php echo $member['member_id']; ?>">
                                                    <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($member['full_name']); ?> <span class="text-muted">(<?php echo htmlspecialchars($member['matric_no']); ?>)</span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Technical Details Section -->
                            <div class="form-section">
                                <div class="section-header">
                                    <div class="section-icon">
                                        <i class="fas fa-code"></i>
                                    </div>
                                    <div>
                                        <h3 class="section-title">Technical Details</h3>
                                        <p class="section-subtitle">Technologies and key features</p>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="technologies" class="form-label">Technologies & Tools (comma separated)</label>
                                    <input type="text" class="form-control" id="technologies" name="technologies" value="<?php echo htmlspecialchars($_POST['technologies'] ?? ''); ?>" placeholder="PHP, MySQL, JavaScript, Bootstrap">
                                    <small class="text-muted"><i class="fas fa-info-circle me-1"></i>List all technologies, frameworks, and tools used</small>
                                </div>

                                <div class="mb-0">
                                    <label for="key_features" class="form-label">Key Features (one per line)</label>
                                    <textarea class="form-control" id="key_features" name="key_features" rows="4" placeholder="Responsive UI&#10;User Authentication&#10;API Integration&#10;Real-time Updates"><?php echo htmlspecialchars($_POST['key_features'] ?? ''); ?></textarea>
                                    <small class="text-muted"><i class="fas fa-info-circle me-1"></i>List the main features and functionalities</small>
                                </div>
                            </div>
                                    
                            <!-- Form Actions -->
                            <div class="d-flex gap-3 align-items-center">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Project
                                </button>
                                <a href="projects.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <button type="button" class="btn btn-danger ms-auto" onclick="deleteProject(<?php echo (int)$project_id; ?>)">
                                    <i class="fas fa-trash me-2"></i>Delete Project
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="info-card">
                            <div class="info-header">
                                <i class="fas fa-info-circle fa-2x mb-2"></i>
                                <h5>Project Information</h5>
                            </div>
                            <div class="info-body">
                                <div class="info-item">
                                    <div class="info-label">Project ID</div>
                                    <div class="info-value">#<?php echo $project_id; ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Created Date</div>
                                    <div class="info-value"><?php echo date('M d, Y', strtotime($project['start_date'])); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Team Size</div>
                                    <div class="info-value">
                                        <i class="fas fa-users me-2"></i><?php echo count($current_member_ids); ?> Member<?php echo count($current_member_ids) != 1 ? 's' : ''; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
        <form id="deleteProjectForm" method="POST" action="" style="display:none;">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        </form>

        <?php include __DIR__ . '/includes/footer.php'; ?>
        <script>
            (function () {
                const menuToggle = document.getElementById('menuToggle');
                const backdrop = document.getElementById('sidebarBackdrop');
                const sidebarLinks = document.querySelectorAll('.sidebar-menu a');
                if (!menuToggle) return;

                const closeSidebar = function () {
                    document.body.classList.remove('sidebar-open');
                    menuToggle.setAttribute('aria-expanded', 'false');
                };

                menuToggle.addEventListener('click', function () {
                    const open = !document.body.classList.contains('sidebar-open');
                    document.body.classList.toggle('sidebar-open', open);
                    menuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                });

                if (backdrop) backdrop.addEventListener('click', closeSidebar);
                sidebarLinks.forEach(function (a) { a.addEventListener('click', closeSidebar); });
            })();

            function deleteProject(projectId) {
                const form = document.getElementById('deleteProjectForm');
                if (!form || !projectId) return;

                const submitDelete = function() {
                    form.action = 'delete_project.php?id=' + encodeURIComponent(projectId);
                    form.submit();
                };

                if (typeof window.confirmModal !== 'function') {
                    if (confirm('Are you sure you want to delete this project? This action cannot be undone.')) {
                        submitDelete();
                    }
                    return;
                }

                window.confirmModal('Are you sure you want to delete this project? This action cannot be undone.', {
                    title: 'Delete Project',
                    okLabel: 'Delete'
                }).then(function(confirmed) {
                    if (confirmed) submitDelete();
                });
            }
        </script>
</body>
</html>
