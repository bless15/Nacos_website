<?php
/**
 * ============================================
 * NACOS DASHBOARD - ADMIN CONTROL PANEL
 * ============================================
 * Purpose: Main administrator dashboard with live metrics
 * Access: Requires authentication
 * Created: November 2, 2025
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Include required files
require_once '../config/database.php';
require_once '../includes/auth.php';

// Require login
requireAdminRole();

// Get current user (member with admin role)
$current_user = getCurrentMember();

// Fetch dashboard metrics
$db = getDB();

try {
    // Total members count
    $total_members = $db->fetchOne("SELECT COUNT(*) as count FROM MEMBERS WHERE membership_status = 'active'")['count'];
    
    // Total projects count
    $total_projects = $db->fetchOne("SELECT COUNT(*) as count FROM PROJECTS WHERE project_status != 'archived'")['count'];
    
    // Upcoming events count
    $upcoming_events = $db->fetchOne("SELECT COUNT(*) as count FROM EVENTS WHERE status = 'upcoming'")['count'];
    
    // Active partners count
    $active_partners = $db->fetchOne("SELECT COUNT(*) as count FROM PARTNERS WHERE status = 'active'")['count'];
    
    // Recent members (last 5)
    $recent_members = $db->fetchAll(
        "SELECT member_id, full_name, department, level, registration_date 
         FROM MEMBERS 
         ORDER BY registration_date DESC 
         LIMIT 5"
    );
    
    // Featured projects
    $featured_projects = $db->fetchAll(
        "SELECT project_id, title, project_status, tech_stack 
         FROM PROJECTS 
         WHERE featured = 1 AND visibility = 'public'
         ORDER BY updated_at DESC 
         LIMIT 5"
    );
    
    // Upcoming events
    $events_list = $db->fetchAll(
        "SELECT event_id, event_name, event_date, event_type, location 
         FROM EVENTS 
         WHERE status = 'upcoming' 
         ORDER BY event_date ASC 
         LIMIT 5"
    );
    
    // Department breakdown
    $dept_stats = $db->fetchAll(
        "SELECT department, COUNT(*) as count 
         FROM MEMBERS 
         WHERE membership_status = 'active' 
         GROUP BY department 
         ORDER BY count DESC"
    );
    
} catch (Exception $e) {
    $error_message = "Error loading dashboard data: " . $e->getMessage();
}

// Get flash message
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - NACOS</title>
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
            padding: 20px;
            min-height: 100vh;
        }
        
        /* Top Bar */
        .top-bar {
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .top-bar h3 {
            margin: 0;
            color: #333;
            font-size: 24px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        /* Stats Cards */
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .stats-card .icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            margin-bottom: 15px;
        }
        
        .stats-card h3 {
            font-size: 32px;
            font-weight: 700;
            margin: 0;
            color: #333;
        }
        
        .stats-card p {
            margin: 5px 0 0;
            color: #666;
            font-size: 14px;
        }
        
        .bg-gradient-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        
        .bg-gradient-success {
            background: linear-gradient(135deg, #11998e, #38ef7d);
        }
        
        .bg-gradient-warning {
            background: linear-gradient(135deg, #f093fb, #f5576c);
        }
        
        .bg-gradient-info {
            background: linear-gradient(135deg, #4facfe, #00f2fe);
        }
        
        /* Content Cards */
        .content-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }
        
        .content-card h5 {
            margin-bottom: 20px;
            font-weight: 600;
            color: #333;
        }
        
        .table {
            margin: 0;
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 5px;
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
            <a href="index.php" class="active">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="members.php">
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
            <h3><i class="fas fa-chart-line me-2"></i> Dashboard Overview</h3>
            <div class="user-info">
                <div>
                    <strong><?php echo htmlspecialchars($current_user['full_name']); ?></strong><br>
                    <small class="text-muted"><?php echo ucfirst($current_user['role']); ?></small>
                </div>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($current_user['full_name'], 0, 1)); ?>
                </div>
            </div>
        </div>
        
        <!-- Flash Message -->
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'info'; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check' : 'info'; ?>-circle me-2"></i>
                <?php echo htmlspecialchars($flash['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon bg-gradient-primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3><?php echo number_format($total_members); ?></h3>
                    <p>Active Members</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon bg-gradient-success">
                        <i class="fas fa-project-diagram"></i>
                    </div>
                    <h3><?php echo number_format($total_projects); ?></h3>
                    <p>Active Projects</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon bg-gradient-warning">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3><?php echo number_format($upcoming_events); ?></h3>
                    <p>Upcoming Events</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon bg-gradient-info">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <h3><?php echo number_format($active_partners); ?></h3>
                    <p>Active Partners</p>
                </div>
            </div>
        </div>
        
        <!-- Content Row -->
        <div class="row">
            <!-- Recent Members -->
            <div class="col-md-6">
                <div class="content-card">
                    <h5><i class="fas fa-user-plus me-2"></i> Recent Members</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Department</th>
                                    <th>Level</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_members as $member): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($member['department']); ?></td>
                                        <td><span class="badge bg-primary"><?php echo $member['level']; ?>L</span></td>
                                        <td><?php echo date('M d', strtotime($member['registration_date'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="members.php" class="btn btn-sm btn-outline-primary">View All Members <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
            
            <!-- Upcoming Events -->
            <div class="col-md-6">
                <div class="content-card">
                    <h5><i class="fas fa-calendar-alt me-2"></i> Upcoming Events</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Type</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($events_list as $event): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($event['event_name']); ?></td>
                                        <td><span class="badge bg-info"><?php echo ucfirst($event['event_type']); ?></span></td>
                                        <td><?php echo date('M d, Y', strtotime($event['event_date'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="events.php" class="btn btn-sm btn-outline-primary">View All Events <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>
        
        <!-- Department Stats & Projects -->
        <div class="row">
            <!-- Department Breakdown -->
            <div class="col-md-6">
                <div class="content-card">
                    <h5><i class="fas fa-chart-pie me-2"></i> Members by Department</h5>
                    <div class="table-responsive">
                        <table class="table">
                            <tbody>
                                <?php foreach ($dept_stats as $dept): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($dept['department']); ?></td>
                                        <td class="text-end">
                                            <strong><?php echo number_format($dept['count']); ?></strong>
                                            <small class="text-muted">members</small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Featured Projects -->
            <div class="col-md-6">
                <div class="content-card">
                    <h5><i class="fas fa-star me-2"></i> Featured Projects</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($featured_projects as $project): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($project['title']); ?></td>
                                        <td>
                                            <?php 
                                            $status_color = [
                                                'ideation' => 'secondary',
                                                'in_progress' => 'warning',
                                                'completed' => 'success'
                                            ];
                                            $color = $status_color[$project['project_status']] ?? 'secondary';
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
                    <a href="projects.php" class="btn btn-sm btn-outline-primary">View All Projects <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-light text-center py-3 mt-5">
        <div class="container">
            <small class="text-muted">&copy; <?php echo date('Y'); ?> NACOSAU. All Rights Reserved.</small><br>
            <small class="text-muted">Developed by <a href="https://johnicity.com.ng/portfolio" target="_blank" class="text-decoration-none">Johnicity</a></small>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
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
