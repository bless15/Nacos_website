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

// Fetch project team members
$team_members = $db->fetchAll(
    "SELECT m.* FROM members m
     JOIN member_projects mp ON m.member_id = mp.member_id
     WHERE mp.project_id = :id
     ORDER BY m.full_name ASC",
    [':id' => $project_id]
);
$delete_csrf_token = generateCSRFToken();

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
    <style>
        :root {
            --primary-color: #0F6B3E;
            --secondary-color: #0b5a34;
            --sidebar-bg: #2c3e50;
            --sidebar-hover: #34495e;
            --success-start: #11998e;
            --success-end: #38ef7d;
            --danger-start: #dc3545;
            --danger-end: #c82333;
            --warning-start: #ffc107;
            --warning-end: #ff6b6b;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
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
            box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1);
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
        
        /* Page Header (reduced size) */
        .page-header {
            background: linear-gradient(135deg, #0F6B3E, #0b5a34);
            color: white;
            padding: 20px 22px;
            border-radius: 12px;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 6px 18px rgba(15, 107, 62, 0.22);
            animation: fadeInDown 0.45s ease;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -30%;
            right: -5%;
            width: 220px;
            height: 220px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.12) 0%, transparent 70%);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(20px, -20px) rotate(5deg); }
        }
        
        .page-header h1 {
            position: relative;
            z-index: 1;
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 8px;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.12);
            color: #ffffff !important;
        }

        .page-header p {
            position: relative;
            z-index: 1;
            font-size: 13px;
            opacity: 0.95;
            margin: 0;
            color: rgba(255, 255, 255, 0.95) !important;
        }

        .menu-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 10px;
            border: 2px solid rgba(255,255,255,0.4);
            background: rgba(255,255,255,0.2);
            color: white;
            font-size: 18px;
            backdrop-filter: blur(8px);
            position: relative;
            z-index: 1;
        }

        .menu-toggle:hover {
            background: rgba(255,255,255,0.3);
            color: white;
        }

        .sidebar-overlay {
            display: none;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border: none;
            margin-bottom: 30px;
            transition: all 0.3s ease;
            animation: fadeInUp 0.6s ease backwards;
        }
        
        .card:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            transform: translateY(-3px);
        }
        
        .card-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-bottom: 3px solid #dee2e6;
            border-radius: 12px 12px 0 0 !important;
            padding: 12px 16px;
        }

        .card-header h5 {
            margin: 0;
            font-weight: 700;
            color: #2c3e50;
            font-size: 1.05rem;
        }

        .card-body {
            padding: 16px;
        }
        
        /* Buttons (smaller) */
        .btn {
            border-radius: 8px;
            padding: 8px 14px;
            font-weight: 600;
            transition: all 0.2s ease;
            border: none;
            font-size: 0.95rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #0F6B3E, #0b5a34);
            color: white;
            box-shadow: 0 4px 12px rgba(15, 107, 62, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(15, 107, 62, 0.4);
            color: white;
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, var(--danger-start), var(--danger-end));
            color: white;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(240, 147, 251, 0.4);
            color: white;
        }
        
        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
        }
        
        .btn-outline-primary:hover {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-color: var(--primary-color);
        }
        
        /* Badges */
        .badge {
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
        }
        
        .badge.bg-success {
            background: linear-gradient(135deg, var(--success-start), var(--success-end)) !important;
        }
        
        .badge.bg-primary {
            background: linear-gradient(135deg, #0F6B3E, #0b5a34) !important;
        }
        
        .badge.bg-warning {
            background: linear-gradient(135deg, var(--warning-start), var(--warning-end)) !important;
        }
        
        .badge.bg-secondary {
            background: linear-gradient(135deg, #6c757d, #495057) !important;
        }
        
        /* Animations */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
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
        
        .card:nth-child(1) { animation-delay: 0.1s; }
        .card:nth-child(2) { animation-delay: 0.2s; }
        .card:nth-child(3) { animation-delay: 0.3s; }
        
        /* Info Items */
        .info-item {
            padding: 10px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
            margin-bottom: 12px;
            transition: all 0.2s ease;
        }
        
        .info-item:hover {
            background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
            transform: translateX(5px);
        }
        
        /* Team Member Items */
        .team-member-item {
            padding: 12px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            margin-bottom: 12px;
            transition: all 0.2s ease;
            border-left: 4px solid var(--primary-color);
        }
        
        .team-member-item:hover {
            background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
            transform: translateX(5px);
        }
        
        /* Quick Stats Card */
        .stat-item {
            padding: 10px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 8px;
            margin-bottom: 12px;
            border-left: 4px solid var(--primary-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s ease;
        }
        
        .stat-item:hover {
            background: linear-gradient(135deg, #e9ecef, #dee2e6);
            transform: translateX(3px);
        }
        
        .stat-item .label {
            color: #6c757d;
            font-weight: 600;
        }
        
        .stat-item .value {
            color: #2c3e50;
            font-weight: 700;
            font-size: 16px;
        }

        /* Compact Team Members card */
        .card.team-compact {
            padding: 0; /* keep header/body spacing controlled */
        }

        .card.team-compact .card-header {
            padding: 8px 12px;
        }

        .card.team-compact .card-header h5 {
            font-size: 0.98rem;
        }

        .card.team-compact .card-body {
            padding: 12px;
        }

        .card.team-compact .card-body > div[style*="text-align: center"] {
            padding: 28px 18px !important;
        }

        .card.team-compact .card-body .fa-user-slash {
            font-size: 48px !important;
            margin-bottom: 12px !important;
        }

        .card.team-compact .team-member-item {
            padding: 8px !important;
            border-radius: 8px !important;
        }

        .card.team-compact .team-member-item div[style*="width: 50px"] {
            width: 40px !important;
            height: 40px !important;
            font-size: 14px !important;
            border-radius: 8px !important;
        }

        .card.team-compact .team-member-item h6 {
            font-size: 14px;
            margin-bottom: 4px;
        }

        .card.team-compact .team-member-item small {
            font-size: 12px;
        }

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
                padding: 18px;
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
                padding: 16px;
            }

            .page-header h1 {
                font-size: 1.2rem;
            }

            .page-header-actions {
                width: 100%;
                flex-direction: column;
            }

            .page-header-actions .btn {
                width: 100%;
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

            .card-header {
                padding: 10px 12px;
            }

            .card-body {
                padding: 12px;
            }
        }


    </style>
</head>
<body>
    	     <?php require_once __DIR__ . '/includes/sidebar.php'; ?>





    <div class="wrapper">
        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Content -->
            <div class="container-fluid px-4 py-4">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                        <div>
                            <button type="button" class="menu-toggle mb-2" id="menuToggle" aria-label="Toggle navigation menu">
                                <i class="fas fa-bars"></i>
                            </button>
                            <h1><?php echo htmlspecialchars($project['title']); ?></h1>
                            <p class="mb-0">Complete project details and team information</p>
                        </div>
                        <div class="d-flex gap-2 page-header-actions" style="position: relative; z-index: 1;">
                            <a href="edit_project.php?id=<?php echo $project_id; ?>" class="btn" style="background: rgba(255,255,255,0.25); color: white; backdrop-filter: blur(10px); border: 2px solid rgba(255,255,255,0.3);">
                                <i class="fas fa-edit me-2"></i> Edit Project
                            </a>
                            <a href="projects.php" class="btn" style="background: rgba(255,255,255,0.25); color: white; backdrop-filter: blur(10px); border: 2px solid rgba(255,255,255,0.3);">
                                <i class="fas fa-arrow-left me-2"></i> Back to Projects
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Project Details -->
                <div class="row">
                    <div class="col-lg-8">
                        <!-- Main Details Card -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Project Information</h5>
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
                            </div>
                            <div class="card-body">
                                <div class="info-item">
                                    <h6 class="text-muted mb-2"><strong>Description</strong></h6>
                                    <p class="mb-0" style="color: #495057;"><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-md-6 mb-3">
                                        <div class="info-item">
                                            <h6 class="text-muted mb-2"><strong>Start Date</strong></h6>
                                            <p class="mb-0" style="color: #2c3e50; font-weight: 600;">
                                                <i class="fas fa-calendar-alt" style="color: #0F6B3E; margin-right: 8px;"></i>
                                                <?php echo date('F d, Y', strtotime($project['start_date'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="info-item">
                                            <h6 class="text-muted mb-2"><strong>Completion Date</strong></h6>
                                            <p class="mb-0" style="color: #2c3e50; font-weight: 600;">
                                                <i class="fas fa-calendar-check" style="color: #11998e; margin-right: 8px;"></i>
                                                <?php echo $project['completion_date'] ? date('F d, Y', strtotime($project['completion_date'])) : 'Not set'; ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($project['repository_link']): ?>
                                    <div class="info-item">
                                        <h6 class="text-muted mb-2"><strong>Repository Link</strong></h6>
                                        <a href="<?php echo htmlspecialchars($project['repository_link']); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                            <i class="fab fa-github me-2"></i> View Repository
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Team Members Card -->
                        <div class="card team-compact">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-users me-2"></i>Team Members (<?php echo count($team_members); ?>)</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($team_members)): ?>
                                    <div style="text-align: center; padding: 60px 30px; color: #adb5bd;">
                                        <i class="fas fa-user-slash" style="font-size: 64px; margin-bottom: 20px; opacity: 0.4; background: linear-gradient(135deg, #0F6B3E, #0b5a34); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"></i>
                                        <h5 style="color: #2c3e50; font-weight: 700; margin-bottom: 10px;">No Team Members</h5>
                                        <p style="color: #6c757d;">No team members assigned to this project yet.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($team_members as $index => $member): ?>
                                        <div class="team-member-item" style="animation: fadeInUp 0.5s ease backwards; animation-delay: <?php echo ($index * 0.1); ?>s;">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="d-flex align-items-center gap-3">
                                                    <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #0F6B3E, #0b5a34); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 18px; flex-shrink: 0;">
                                                        <?php echo strtoupper(substr($member['full_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-1" style="color: #2c3e50; font-weight: 700;"><?php echo htmlspecialchars($member['full_name']); ?></h6>
                                                        <small class="text-muted">
                                                            <i class="fas fa-id-card"></i> <?php echo htmlspecialchars($member['matric_no']); ?> | 
                                                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($member['email']); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                <a href="view_member.php?id=<?php echo $member['member_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye me-1"></i>View Profile
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sidebar -->
                    <div class="col-lg-4">
                        <!-- Quick Stats -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Quick Stats</h5>
                            </div>
                            <div class="card-body">
                                <div class="stat-item">
                                    <span class="label">Project ID</span>
                                    <span class="value">#<?php echo $project_id; ?></span>
                                </div>
                                <div class="stat-item">
                                    <span class="label">Team Size</span>
                                    <span class="value"><?php echo count($team_members); ?> members</span>
                                </div>
                                <div class="stat-item" style="margin-bottom: 0;">
                                    <span class="label">Duration</span>
                                    <span class="value">
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
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Actions</h5>
                            </div>
                            <div class="card-body">
                                <a href="edit_project.php?id=<?php echo $project_id; ?>" class="btn btn-primary w-100 mb-3">
                                    <i class="fas fa-edit me-2"></i> Edit Project
                                </a>
                                <button type="button" class="btn btn-danger w-100" onclick="deleteProject(<?php echo (int)$project_id; ?>)">
                                    <i class="fas fa-trash me-2"></i> Delete Project
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form id="deleteProjectForm" method="POST" action="" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?php echo $delete_csrf_token; ?>">
    </form>
    
    <?php include __DIR__ . '/includes/footer.php'; ?>
    <script>
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

        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.querySelector('.sidebar');
        const sidebarOverlay = document.getElementById('sidebarBackdrop') || document.getElementById('sidebarOverlay');

        function closeSidebar() {
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
