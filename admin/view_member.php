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

// Include required files
require_once '../config/database.php';
require_once '../includes/auth.php';

// Require login
requireAdminRole();

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
    "SELECT * FROM MEMBERS WHERE member_id = ?", 
    [$member_id]
);

if (!$member) {
    redirectWithMessage('members.php', 'Member not found', 'error');
}

// Get member's projects
$projects = $db->fetchAll(
    "SELECT p.project_id, p.title, p.project_status, mp.role_on_project, mp.join_date
     FROM MEMBER_PROJECTS mp
     JOIN PROJECTS p ON mp.project_id = p.project_id
     WHERE mp.member_id = ?
     ORDER BY mp.join_date DESC",
    [$member_id]
);

// Get member's events
$events = $db->fetchAll(
    "SELECT e.event_id, e.event_name, e.event_date, e.event_type, me.attendance_status, me.feedback_rating
     FROM MEMBER_EVENTS me
     JOIN EVENTS e ON me.event_id = e.event_id
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
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --sidebar-bg: #2c3e50;
            --sidebar-hover: #34495e;
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
        
        .profile-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: white;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            font-weight: 700;
            border: 4px solid rgba(255, 255, 255, 0.3);
            position: relative;
            z-index: 1;
        }
        
        .profile-info {
            position: relative;
            z-index: 1;
        }
        
        .profile-info h2 {
            margin: 0;
            font-size: 32px;
        }
        
        .profile-info p {
            margin: 5px 0;
            opacity: 0.9;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
        }
        
        .stat-box .icon {
            width: 50px;
            height: 50px;
            margin: 0 auto 10px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        
        .stat-box h3 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }
        
        .stat-box p {
            margin: 5px 0 0;
            color: #666;
            font-size: 14px;
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
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="../assets/images/nacos_logo.jpg" alt="NACOS Logo" style="height: 60px; margin-bottom: 10px;">
            <h4>NACOS Dashboard</h4>
            <small>Admin Panel</small>
        </div>
        
        <div class="sidebar-menu">
            <a href="index.php">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="members.php" class="active">
                <i class="fas fa-users"></i> Members
            </a>
            <a href="projects.php">
                <i class="fas fa-project-diagram"></i> Projects
            </a>
            <a href="events.php">
                <i class="fas fa-calendar-alt"></i> Events
            </a>
            <a href="resources.php">
                <i class="fas fa-book"></i> Resources
            </a>
            <a href="partners.php">
                <i class="fas fa-handshake"></i> Partners
            </a>
            <a href="documents.php">
                <i class="fas fa-folder"></i> Documents
            </a>
            <hr style="border-color: rgba(255,255,255,0.1);">
            <a href="../public/index.php" target="_blank">
                <i class="fas fa-external-link-alt"></i> View Public Site
            </a>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="d-flex justify-content-between align-items-center">
                <h3><i class="fas fa-user me-2"></i> Member Profile</h3>
                <div class="d-flex gap-2">
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
                <div class="icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                    <i class="fas fa-project-diagram"></i>
                </div>
                <h3><?php echo $total_projects; ?></h3>
                <p>Total Projects</p>
            </div>
            <div class="stat-box">
                <div class="icon" style="background: linear-gradient(135deg, #11998e, #38ef7d);">
                    <i class="fas fa-tasks"></i>
                </div>
                <h3><?php echo $active_projects; ?></h3>
                <p>Active Projects</p>
            </div>
            <div class="stat-box">
                <div class="icon" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3><?php echo $total_events; ?></h3>
                <p>Events Registered</p>
            </div>
            <div class="stat-box">
                <div class="icon" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3><?php echo $attended_events; ?></h3>
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
                            $status_colors = ['active' => 'success', 'inactive' => 'secondary', 'alumni' => 'info'];
                            $color = $status_colors[$member['membership_status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $color; ?>">
                                <?php echo ucfirst($member['membership_status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Role:</div>
                        <div class="info-value">
                            <?php 
                            $role_colors = ['admin' => 'danger', 'executive' => 'warning', 'member' => 'info'];
                            $role_icons = ['admin' => 'crown', 'executive' => 'star', 'member' => 'user'];
                            $role_color = $role_colors[$member['role']] ?? 'info';
                            $role_icon = $role_icons[$member['role']] ?? 'user';
                            ?>
                            <span class="badge bg-<?php echo $role_color; ?>">
                                <i class="fas fa-<?php echo $role_icon; ?> me-1"></i>
                                <?php echo ucfirst($member['role']); ?>
                            </span>
                            
                            <!-- Role Management Button (Admin Only) -->
                            <?php if (isMemberAdmin()): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary ms-2" 
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
                            <span class="badge bg-primary"><?php echo $member['level']; ?> Level</span>
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
                                                    'registered' => 'info',
                                                    'attended' => 'success',
                                                    'absent' => 'danger',
                                                    'cancelled' => 'secondary'
                                                ];
                                                $color = $status_colors[$event['attendance_status']] ?? 'info';
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
                            <select name="new_role" class="form-select" required>
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

