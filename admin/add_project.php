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

// Bootstrap and includes
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

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
                    INSERT INTO projects 
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
                    // member_projects requires a join_date (NOT NULL) in the schema - include it
                    $member_query = "INSERT INTO member_projects (member_id, project_id, join_date) VALUES (:member_id, :project_id, :join_date)";
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
$members = $db->fetchAll("SELECT member_id, full_name, matric_no FROM members WHERE membership_status = 'active' ORDER BY full_name ASC");

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
    

    <style>
:root {
    --primary-color: #0F6B3E;
    --secondary-color: #0b5a34;
    --sidebar-bg: #2c3e50;
    --sidebar-hover: #34495e;
    --success-gradient: linear-gradient(135deg, #11998e, #38ef7d);
    --purple-gradient: linear-gradient(135deg, #0F6B3E, #0b5a34);
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
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
    box-shadow: 0 6px 25px rgba(15, 107, 62, 0.18);
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
    box-shadow: 0 4px 12px rgba(15, 107, 62, 0.28);
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
    box-shadow: 0 0 0 0.2rem rgba(15, 107, 62, 0.16);
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
    background: rgba(15, 107, 62, 0.08);
    transform: translateX(5px);
}

.form-check-input:checked ~ .form-check-label {
    color: var(--primary-color);
    font-weight: 600;
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
    color: #ffffff !important;
}

.guidelines-header i {
    color: #ffffff !important;
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

.status-item {
    padding: 8px 12px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 8px;
    border-left: 3px solid var(--primary-color);
}

.status-item strong {
    color: #2c3e50;
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
    box-shadow: 0 4px 12px rgba(15, 107, 62, 0.28);
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

    .page-header {
        padding: 18px;
    }

    .page-header h1 {
        font-size: 22px;
    }

    .form-section {
        padding: 20px;
    }

    .section-title {
        font-size: 18px;
    }

    .section-icon {
        width: 40px;
        height: 40px;
        font-size: 17px;
    }

    .form-actions {
        flex-direction: column;
    }

    .form-actions .btn {
        width: 100%;
    }

    .header-back-btn {
        width: 100%;
        align-self: stretch;
        text-align: center;
        justify-content: center;
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

    .guidelines-body {
        padding: 18px;
    }
}
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
        
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navigation -->
            <!-- ?php include 'includes/navbar.php'; ?-->
            
            <!-- Page Content -->
            <div class="container-fluid">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-2 mb-md-0">
                                <button type="button" class="menu-toggle" id="menuToggle" aria-label="Toggle navigation menu">
                                    <i class="fas fa-bars"></i>
                                </button>
                                <h1><i class="fas fa-plus-circle me-2"></i>Add New Project</h1>
                            </div>
                            <p class="mb-0 text-muted">Create a new project and assign team members</p>
                        </div>
                        <a href="projects.php" class="btn btn-secondary header-back-btn">
                            <i class="fas fa-arrow-left me-2"></i>Back to Projects
                        </a>
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
                        
                        <form action="add_project.php" method="POST">
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
                                    <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($input_data['title'] ?? ''); ?>" placeholder="Enter project title" required>
                                </div>
                                
                                <div class="mb-0">
                                    <label for="description" class="form-label">Description *</label>
                                    <textarea class="form-control" id="description" name="description" rows="5" placeholder="Describe the project goals, objectives, and key deliverables..." required><?php echo htmlspecialchars($input_data['description'] ?? ''); ?></textarea>
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
                                            <option value="">-- Select Status --</option>
                                            <?php
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
                                        <input type="url" class="form-control" id="repository_link" name="repository_link" value="<?php echo htmlspecialchars($input_data['repository_link'] ?? ''); ?>" placeholder="https://github.com/username/repo">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-0">
                                        <label for="start_date" class="form-label">Start Date *</label>
                                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($input_data['start_date'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-0">
                                        <label for="completion_date" class="form-label">Completion Date</label>
                                        <input type="date" class="form-control" id="completion_date" name="completion_date" value="<?php echo htmlspecialchars($input_data['completion_date'] ?? ''); ?>">
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
                                                <?php echo (in_array($member['member_id'], $input_data['members'] ?? [])) ? 'checked' : ''; ?>>
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
                                    <input type="text" class="form-control" id="technologies" name="technologies" value="<?php echo htmlspecialchars($input_data['technologies'] ?? ''); ?>" placeholder="PHP, MySQL, JavaScript, Bootstrap">
                                    <small class="text-muted"><i class="fas fa-info-circle me-1"></i>List all technologies, frameworks, and tools used</small>
                                </div>

                                <div class="mb-0">
                                    <label for="key_features" class="form-label">Key Features (one per line)</label>
                                    <textarea class="form-control" id="key_features" name="key_features" rows="4" placeholder="Responsive UI&#10;User Authentication&#10;API Integration&#10;Real-time Updates"><?php echo htmlspecialchars($input_data['key_features'] ?? ''); ?></textarea>
                                    <small class="text-muted"><i class="fas fa-info-circle me-1"></i>List the main features and functionalities</small>
                                </div>
                            </div>
                                    
                            <!-- Form Actions -->
                            <div class="d-flex gap-3 form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Create Project
                                </button>
                                <a href="projects.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="guidelines-card">
                            <div class="guidelines-header">
                                <i class="fas fa-lightbulb fa-2x mb-2"></i>
                                <h5>Project Guidelines</h5>
                            </div>
                            <div class="guidelines-body">
                                <div class="guideline-section">
                                    <h6><i class="fas fa-check-circle text-success"></i>Best Practices</h6>
                                    <ul>
                                        <li>Use clear and descriptive project titles</li>
                                        <li>Provide detailed descriptions to help team members understand goals</li>
                                        <li>Link your GitHub or GitLab repository for easy access</li>
                                        <li>Assign relevant members who will contribute to the project</li>
                                    </ul>
                                </div>
                                
                                <div class="guideline-section">
                                    <h6><i class="fas fa-flag text-primary"></i>Status Guidelines</h6>
                                    <div class="status-item mb-2">
                                        <strong>Planned:</strong> Project is in planning phase
                                    </div>
                                    <div class="status-item mb-2">
                                        <strong>In Progress:</strong> Active development
                                    </div>
                                    <div class="status-item mb-2">
                                        <strong>Completed:</strong> Project is finished
                                    </div>
                                    <div class="status-item">
                                        <strong>On Hold:</strong> Temporarily paused
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include __DIR__ . '/includes/footer.php'; ?>
    <script>
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.querySelector('.sidebar');
        const sidebarOverlay = document.getElementById('sidebarBackdrop') || document.getElementById('sidebarOverlay');

        function closeSidebar() {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        }

        if (menuToggle && sidebar && sidebarOverlay) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('show');
                sidebarOverlay.classList.toggle('show');
            });

            sidebarOverlay.addEventListener('click', closeSidebar);

            window.addEventListener('resize', function() {
                if (window.innerWidth > 992) {
                    closeSidebar();
                }
            });
        }
    </script>
</body>
</html>
