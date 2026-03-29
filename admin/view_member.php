<?php
/**
 * ============================================
 * NACOS DASHBOARD - VIEW MEMBER PROFILE
 * ============================================
 * Purpose: View detailed member information
 * Access: Requires authentication
 * Created: November 3, 2025
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Bootstrap and includes
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Require full admin privileges
requireFullAdminRole();

// Get current user
$current_user = getCurrentMember();

// Initialize database
$db = getDB();

// Get member ID
$member_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($member_id <= 0) {
    redirectWithMessage('members.php', 'Invalid member ID', 'error');
}

// Get member data
$member = $db->fetchOne(
    "SELECT * FROM members WHERE member_id = ?", 
    [$member_id]
);

$member_executive_position = $member['executive_position'] ?? '';
$executive_position_labels = [
    'social_director' => 'Social Director',
    'general_secretary' => 'General Secretary',
    'academic_director' => 'Academic Director',
    'creative_innovative_director' => 'Creative and Innovative Director',
    'public_relations_officer' => 'PRO (Public Relations Officer)',
];

if (!$member) {
    redirectWithMessage('members.php', 'Member not found', 'error');
}

// Get member's projects
$projects = $db->fetchAll(
    "SELECT p.project_id, p.title, p.project_status, mp.role_on_project, mp.join_date
     FROM member_projects mp
     JOIN projects p ON mp.project_id = p.project_id
     WHERE mp.member_id = ?
     ORDER BY mp.join_date DESC",
    [$member_id]
);

// Get member's events
$events = $db->fetchAll(
    "SELECT e.event_id, e.event_name, e.event_date, e.event_type, me.attendance_status, me.feedback_rating
     FROM member_events me
     JOIN events e ON me.event_id = e.event_id
     WHERE me.member_id = ?
     ORDER BY e.event_date DESC",
    [$member_id]
);

// Calculate statistics
$total_projects = count($projects);
$active_projects = count(array_filter($projects, fn($p) => $p['project_status'] === 'in_progress' || $p['project_status'] === 'ideation'));
$total_events = count($events);
$attended_events = count(array_filter($events, fn($e) => $e['attendance_status'] === 'attended'));

// Get flash message
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($member['full_name']); ?> - Profile</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #0F6B3E;
            --secondary-color: #1B8A56;
            --sidebar-bg: #0B5D35;
            --sidebar-hover: #0F6B3E;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 260px;
            background: var(--sidebar-bg);
            color: white;
            overflow-y: auto;
            z-index: 1000;
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
        
        .main-content {
            margin-left: 260px;
            padding: 20px;
            min-height: 100vh;
        }
        
        .top-bar {
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
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
        
        .profile-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 16px 18px;
            border-radius: 10px;
            margin-bottom: 18px;
            position: relative;
            overflow: hidden;
            display: block;
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: -30%;
            right: -5%;
            width: 180px;
            height: 180px;
            background: rgba(255, 255, 255, 0.06);
            border-radius: 50%;
            filter: blur(6px);
        }

        .profile-avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: white;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 700;
            border: 3px solid rgba(255, 255, 255, 0.22);
            position: relative;
            z-index: 1;
            flex-shrink: 0;
        }

        .profile-info {
            position: relative;
            z-index: 1;
        }

        .profile-info h2 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
        }

        .profile-info p {
            margin: 4px 0;
            opacity: 0.95;
            font-size: 14px;
        }

        /* compact metadata row */
        .profile-meta {
            display: flex;
            gap: 12px;
            align-items: center;
            color: rgba(255,255,255,0.95);
            font-size: 13px;
        }

        .profile-meta i { opacity: 0.95; margin-right: 6px; }
        
        /* Match members overview stat card sizing and layout */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 20px;
        }

        .stat-box {
            background: white;
            padding: 14px 16px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.06);
            display: flex;
            align-items: center;
            gap: 13px;
            position: relative;
            overflow: hidden;
        }

        .stat-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 3px;
            height: 100%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }

        .stat-box .icon {
            width: 40px;
            height: 40px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: white;
            flex-shrink: 0;
            margin-left: 8px; /* visually offset from accent bar */
        }

        .stat-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .stat-info h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
            line-height: 1;
        }

        .stat-info p {
            margin: 4px 0 0;
            color: #6c757d;
            font-size: 12px;
        }

        @media (max-width: 991px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
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

            .top-bar {
                padding: 14px;
            }

            .top-actions {
                width: 100%;
                flex-direction: column;
            }

            .top-actions .btn {
                width: 100%;
            }

            .profile-header {
                padding: 14px;
            }

            .profile-header .d-flex {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 12px !important;
            }

            .content-card {
                padding: 16px;
            }

            .info-row {
                flex-direction: column;
                gap: 6px;
            }

            .info-label {
                width: auto;
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

            .stats-row {
                grid-template-columns: 1fr;
            }

            .profile-info h2 {
                font-size: 20px;
            }
        }
        
        .content-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }
        
        .content-card h5 {
            margin-bottom: 20px;
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .info-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
            width: 200px;
            flex-shrink: 0;
        }
        
        .info-value {
            color: #333;
        }

        .role-actions {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .role-actions .btn {
            margin-left: 0 !important;
        }
        
        .badge {
            padding: 5px 12px;
            border-radius: 5px;
            font-size: 12px;
        }
        
        .social-links a {
            display: inline-block;
            margin-right: 15px;
            padding: 8px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
        }
        
        .social-links a:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .table {
            margin: 0;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.3;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="menu-toggle" id="menuToggle" aria-label="Toggle navigation menu">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h3><i class="fas fa-user me-2"></i> Member Profile</h3>
                </div>
                <div class="d-flex gap-2 top-actions">
                    <a href="edit_member.php?id=<?php echo $member_id; ?>" class="btn btn-warning">
                        <i class="fas fa-edit me-2"></i> Edit Profile
                    </a>
                    <a href="members.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Members
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Flash Message -->
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'info'; ?> alert-dismissible fade show">
                <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check' : 'info'; ?>-circle me-2"></i>
                <?php echo htmlspecialchars($flash['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="d-flex align-items-center gap-4">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($member['full_name'], 0, 1)); ?>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($member['full_name']); ?></h2>
                    <p><i class="fas fa-id-card me-2"></i> <?php echo htmlspecialchars($member['matric_no']); ?></p>
                    <p><i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($member['email']); ?></p>
                    <?php if ($member['phone']): ?>
                        <p><i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($member['phone']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="icon" style="background: linear-gradient(135deg, #0F6B3E, #1B8A56);">
                    <i class="fas fa-project-diagram"></i>
                </div>
                <h4><?php echo $total_projects; ?></h4>
                <p>Total Projects</p>
            </div>
            <div class="stat-box">
                <div class="icon" style="background: linear-gradient(135deg, #1B8A56, #2FA66A);">
                    <i class="fas fa-tasks"></i>
                </div>
                <h4><?php echo $active_projects; ?></h4>
                <p>Active Projects</p>
            </div>
            <div class="stat-box">
                <div class="icon" style="background: linear-gradient(135deg, #2FA66A, #0F6B3E);">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h4><?php echo $total_events; ?></h4>
                <p>Events Registered</p>
            </div>
            <div class="stat-box">
                <div class="icon" style="background: linear-gradient(135deg, #1B8A56, #0B5D35);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h4><?php echo $attended_events; ?></h4>
                <p>Events Attended</p>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <!-- Personal Information -->
                <div class="content-card">
                    <h5><i class="fas fa-info-circle me-2"></i> Personal Information</h5>
                    
                    <div class="info-row">
                        <div class="info-label">Status:</div>
                        <div class="info-value">
                            <?php 
                            $status_colors = ['active' => 'success', 'inactive' => 'secondary', 'alumni' => 'warning'];
                            $color = $status_colors[$member['membership_status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $color; ?>">
                                <?php echo ucfirst($member['membership_status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Role:</div>
                        <div class="info-value role-actions">
                            <?php 
                            $role_colors = ['admin' => 'danger', 'executive' => 'warning', 'member' => 'success'];
                            $role_icons = ['admin' => 'crown', 'executive' => 'star', 'member' => 'user'];
                            $role_color = $role_colors[$member['role']] ?? 'info';
                            $role_icon = $role_icons[$member['role']] ?? 'user';
                            ?>
                            <span class="badge bg-<?php echo $role_color; ?>">
                                <i class="fas fa-<?php echo $role_icon; ?> me-1"></i>
                                <?php echo ucfirst($member['role']); ?>
                            </span>
                            <?php if (($member['role'] ?? '') === 'executive' && !empty($member_executive_position)): ?>
                                <span class="badge bg-dark-subtle text-dark border">
                                    <?php echo htmlspecialchars($executive_position_labels[$member_executive_position] ?? ucwords(str_replace('_', ' ', $member_executive_position))); ?>
                                </span>
                            <?php endif; ?>
                            
                            <!-- Role Management Button (Admin Only) -->
                            <?php if (isLoggedIn() || isMemberAdmin()): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                        data-bs-toggle="modal" data-bs-target="#roleModal">
                                    <i class="fas fa-user-shield me-1"></i> Manage Role
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Department:</div>
                        <div class="info-value"><?php echo htmlspecialchars($member['department']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Level:</div>
                        <div class="info-value">
                            <span class="badge bg-success"><?php echo $member['level']; ?> Level</span>
                        </div>
                    </div>
                    
                    <?php if ($member['gender']): ?>
                        <div class="info-row">
                            <div class="info-label">Gender:</div>
                            <div class="info-value"><?php echo htmlspecialchars($member['gender']); ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="info-row">
                        <div class="info-label">Registration Date:</div>
                        <div class="info-value"><?php echo date('F d, Y', strtotime($member['registration_date'])); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Member Since:</div>
                        <div class="info-value">
                            <?php 
                            $days = floor((time() - strtotime($member['registration_date'])) / 86400);
                            echo "$days days";
                            ?>
                        </div>
                    </div>
                </div>
                
                <!-- Skills & Bio -->
                <?php if ($member['skills'] || $member['bio']): ?>
                    <div class="content-card">
                        <h5><i class="fas fa-star me-2"></i> Skills & About</h5>
                        
                        <?php if ($member['skills']): ?>
                            <div class="mb-3">
                                <strong class="d-block mb-2">Skills:</strong>
                                <?php 
                                $skills = array_map('trim', explode(',', $member['skills']));
                                foreach ($skills as $skill): 
                                ?>
                                    <span class="badge bg-secondary me-2 mb-2"><?php echo htmlspecialchars($skill); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($member['bio']): ?>
                            <div>
                                <strong class="d-block mb-2">Bio:</strong>
                                <p class="text-muted mb-0"><?php echo nl2br(htmlspecialchars($member['bio'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Social Links -->
                <?php if ($member['github_username'] || $member['linkedin_url']): ?>
                    <div class="content-card">
                        <h5><i class="fas fa-link me-2"></i> Social Links</h5>
                        <div class="social-links">
                            <?php if ($member['github_username']): ?>
                                <a href="https://github.com/<?php echo htmlspecialchars($member['github_username']); ?>" target="_blank">
                                    <i class="fab fa-github me-2"></i> GitHub
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($member['linkedin_url']): ?>
                                <a href="<?php echo htmlspecialchars($member['linkedin_url']); ?>" target="_blank">
                                    <i class="fab fa-linkedin me-2"></i> LinkedIn
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="col-md-6">
                <!-- Projects -->
                <div class="content-card">
                    <h5><i class="fas fa-project-diagram me-2"></i> Projects (<?php echo count($projects); ?>)</h5>
                    
                    <?php if (empty($projects)): ?>
                        <div class="empty-state">
                            <i class="fas fa-folder-open"></i>
                            <p>No projects yet</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projects as $project): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($project['title']); ?></td>
                                            <td><?php echo htmlspecialchars($project['role_on_project'] ?? 'Member'); ?></td>
                                            <td>
                                                <?php 
                                                $status_colors = [
                                                    'ideation' => 'secondary',
                                                    'in_progress' => 'warning',
                                                    'completed' => 'success'
                                                ];
                                                $color = $status_colors[$project['project_status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $project['project_status'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Events -->
                <div class="content-card">
                    <h5><i class="fas fa-calendar-alt me-2"></i> Events (<?php echo count($events); ?>)</h5>
                    
                    <?php if (empty($events)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <p>No events registered</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Event</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($events as $event): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($event['event_name']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($event['event_date'])); ?></td>
                                            <td>
                                                <?php 
                                                $status_colors = [
                                                    'registered' => 'success',
                                                    'attended' => 'success',
                                                    'absent' => 'danger',
                                                    'cancelled' => 'secondary'
                                                ];
                                                $color = $status_colors[$event['attendance_status']] ?? 'success';
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>">
                                                    <?php echo ucfirst($event['attendance_status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <!-- Role Management Modal -->
    <div class="modal fade" id="roleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-shield me-2"></i> Manage Member Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Change role for <strong><?php echo htmlspecialchars($member['full_name']); ?></strong>:</p>
                    
                    <form id="roleForm" method="POST" action="toggle_admin_role.php">
                        <input type="hidden" name="member_id" value="<?php echo $member_id; ?>">
                        <input type="hidden" name="current_role" value="<?php echo $member['role']; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Select New Role:</label>
                            <select name="new_role" id="newRoleSelect" class="form-select" required>
                                <option value="member" <?php echo $member['role'] === 'member' ? 'selected' : ''; ?>>
                                    <i class="fas fa-user"></i> Member - Regular Access
                                </option>
                                <option value="executive" <?php echo $member['role'] === 'executive' ? 'selected' : ''; ?>>
                                    <i class="fas fa-star"></i> Executive - Enhanced Access
                                </option>
                                <option value="admin" <?php echo $member['role'] === 'admin' ? 'selected' : ''; ?>>
                                    <i class="fas fa-crown"></i> Admin - Full Access
                                </option>
                            </select>
                            <div class="form-text mt-2">
                                <strong>Roles explained:</strong><br>
                                • <strong>Member:</strong> Basic access to member dashboard<br>
                                • <strong>Executive:</strong> Can manage specific features<br>
                                • <strong>Admin:</strong> Full access to admin panel
                            </div>
                        </div>

                        <div class="mb-3" id="executivePositionGroup" style="display: none;">
                            <label class="form-label">Executive Position:</label>
                            <select name="executive_position" id="executivePositionSelect" class="form-select">
                                <option value="">Select executive position</option>
                                <option value="social_director" <?php echo $member_executive_position === 'social_director' ? 'selected' : ''; ?>>Social Director (1 slot)</option>
                                <option value="general_secretary" <?php echo $member_executive_position === 'general_secretary' ? 'selected' : ''; ?>>General Secretary (2 slots)</option>
                                <option value="academic_director" <?php echo $member_executive_position === 'academic_director' ? 'selected' : ''; ?>>Academic Director (1 slot)</option>
                                <option value="creative_innovative_director" <?php echo $member_executive_position === 'creative_innovative_director' ? 'selected' : ''; ?>>Creative and Innovative Director (1 slot)</option>
                                <option value="public_relations_officer" <?php echo $member_executive_position === 'public_relations_officer' ? 'selected' : ''; ?>>PRO - Public Relations Officer (1 slot)</option>
                            </select>
                            <div class="form-text mt-1">
                                Total executives allowed: 6 (1 Social, 2 General Secretary, 1 Academic, 1 Creative & Innovative, 1 PRO).
                            </div>
                            <div class="alert alert-light border mt-2 mb-0">
                                <strong>Permissions Preview</strong>
                                <ul class="mb-0 mt-2 ps-3">
                                    <li><strong>Social Director:</strong> full Events access; Documents = add/view only (no delete/edit).</li>
                                    <li><strong>General Secretary:</strong> full Documents + full Resources access.</li>
                                    <li><strong>Academic Director:</strong> full Resources access + full Past Questions access; Documents = add/view only (no delete/edit).</li>
                                    <li><strong>Creative &amp; Innovative Director:</strong> full Projects + full Resources access; Documents = add/view only (no delete/edit).</li>
                                    <li><strong>PRO:</strong> full Announcements + full Events access; Documents = add/view only (no delete/edit).</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> Changing roles affects access permissions immediately!
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="roleForm" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Update Role
                    </button>
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

        const newRoleSelect = document.getElementById('newRoleSelect');
        const executivePositionGroup = document.getElementById('executivePositionGroup');
        const executivePositionSelect = document.getElementById('executivePositionSelect');

        function toggleExecutivePositionField() {
            if (!newRoleSelect || !executivePositionGroup || !executivePositionSelect) {
                return;
            }

            const isExecutive = newRoleSelect.value === 'executive';
            executivePositionGroup.style.display = isExecutive ? 'block' : 'none';
            executivePositionSelect.required = isExecutive;

            if (!isExecutive) {
                executivePositionSelect.value = '';
            }
        }

        if (newRoleSelect) {
            newRoleSelect.addEventListener('change', toggleExecutivePositionField);
            toggleExecutivePositionField();
        }

        // Auto-dismiss alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                const closeBtn = alert.querySelector('.btn-close');
                if (closeBtn) closeBtn.click();
            });
        }, 5000);
    </script>
</body>
</html>

