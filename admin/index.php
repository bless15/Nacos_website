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

// Bootstrap and security
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';

// Require login
requireAdminRole();

// Get current user (member with admin role)
$current_user = getCurrentMember();

// Fetch dashboard metrics
$db = getDB();

try {
    // Total members count
    $total_members = $db->fetchOne("SELECT COUNT(*) as count FROM members WHERE membership_status = 'active'")['count'];
    
    // Total projects count
    $total_projects = $db->fetchOne("SELECT COUNT(*) as count FROM projects WHERE project_status != 'archived'")['count'];
    
    // Upcoming events count
    $upcoming_events = $db->fetchOne("SELECT COUNT(*) as count FROM events WHERE status = 'upcoming'")['count'];
    
    // Active partners count
    $active_partners = $db->fetchOne("SELECT COUNT(*) as count FROM partners WHERE status = 'active'")['count'];
    
    // Recent members (last 5)
    $recent_members = $db->fetchAll(
        "SELECT member_id, full_name, department, level, registration_date 
         FROM members 
         ORDER BY registration_date DESC 
         LIMIT 5"
    );
    
    // Featured projects
    $featured_projects = $db->fetchAll(
        "SELECT project_id, title, project_status, tech_stack 
         FROM projects 
         WHERE featured = 1 AND visibility = 'public'
         ORDER BY updated_at DESC 
         LIMIT 5"
    );
    
    // Upcoming events
    $events_list = $db->fetchAll(
        "SELECT event_id, event_name, event_date, event_type, location 
         FROM events 
         WHERE status = 'upcoming' 
         ORDER BY event_date ASC 
         LIMIT 5"
    );
    
    // Department breakdown
    $dept_stats = $db->fetchAll(
        "SELECT department, COUNT(*) as count 
         FROM members 
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
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --sidebar-width: 240px;
            --purple-start: #5B6FD8;
            --purple-end: #7E57C2;
            --green-start: #26C281;
            --green-end: #48E5A5;
            --pink-start: #F093FB;
            --pink-end: #F5576C;
            --cyan-start: #4FC3F7;
            --cyan-end: #29B6F6;
            --sidebar-gradient: linear-gradient(180deg, #4A5BD8 0%, #7E57C2 100%);
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #F5F7FA;
            color: #2D3748;
            font-size: 14px;
            transition: overflow 0.3s ease;
        }
        
        /* Modern Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: var(--sidebar-gradient);
            color: white;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-header {
            padding: 28px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-header img {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            margin-bottom: 12px;
            background: rgba(255, 255, 255, 0.1);
            padding: 8px;
        }
        
        .sidebar-header h4 {
            font-size: 18px;
            font-weight: 700;
            margin: 0 0 4px 0;
            color: white;
        }
        
        .sidebar-header small {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 24px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s;
            font-size: 14px;
            font-weight: 500;
            border-left: 3px solid transparent;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: white;
        }
        
        .sidebar-menu a i {
            width: 20px;
            margin-right: 12px;
            font-size: 16px;
        }
        
        .sidebar-menu hr {
            border-color: rgba(255, 255, 255, 0.1);
            margin: 12px 24px;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 24px;
            min-height: 100vh;
        }

        .sidebar-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.35);
            z-index: 900;
            pointer-events: none;
        }
        
        /* Top Bar */
        .top-bar {
            background: white;
            padding: 18px 24px;
            border-radius: 10px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .menu-toggle {
            display: none;
            border: none;
            background: transparent;
            color: #4A5BD8;
            font-size: 22px;
            padding: 6px 10px;
            border-radius: 8px;
        }

        .menu-toggle:focus {
            outline: 2px solid rgba(74, 91, 216, 0.3);
            outline-offset: 2px;
        }
        
        .top-bar h3 {
            margin: 0;
            color: #2D3748;
            font-size: 20px;
            font-weight: 700;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info div {
            text-align: right;
        }

        .user-info > div:first-child {
            margin-top: 3px;
        }
        
        .user-info strong {
            display: block;
            font-size: 15px;
            font-weight: 600;
            color: #2D3748;
            line-height: 1.15;
        }
        
        .user-info small {
            display: block;
            margin-top: 1px;
            color: #718096;
            font-size: 13px;
            line-height: 1.15;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--purple-start), var(--purple-end));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 18px;
        }
        
        /* Gradient Stats Cards */
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            overflow: hidden;
            height: 100%;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .stats-card-gradient {
            padding: 20px;
            color: white;
            position: relative;
        }
        
        .stats-card-gradient.purple {
            background: linear-gradient(135deg, var(--purple-start), var(--purple-end));
        }
        
        .stats-card-gradient.green {
            background: linear-gradient(135deg, var(--green-start), var(--green-end));
        }
        
        .stats-card-gradient.pink {
            background: linear-gradient(135deg, var(--pink-start), var(--pink-end));
        }
        
        .stats-card-gradient.cyan {
            background: linear-gradient(135deg, var(--cyan-start), var(--cyan-end));
        }
        
        .stats-card-icon {
            font-size: 24px;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        
        .stats-card h3 {
            font-size: 32px;
            font-weight: 700;
            margin: 0 0 2px 0;
            color: white;
        }
        
        .stats-card p {
            margin: 0;
            font-size: 13px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.95);
        }
        
        .stats-card-footer {
            padding: 10px 20px;
            background: white;
            border-top: 1px solid #E2E8F0;
        }
        
        .stats-card-footer a {
            color: #4A5BD8;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .stats-card-footer a:hover {
            color: #3A4BC8;
        }
        
        /* Content Cards */
        .content-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .content-card h5 {
            margin-bottom: 16px;
            font-weight: 700;
            color: #2D3748;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .content-card h5 i {
            color: #4A5BD8;
        }
        
        .card-content {
            flex: 1;
            overflow: auto;
        }
        
        /* Table Styling */
        .table {
            margin: 0;
        }
        
        .table thead th {
            border-bottom: 2px solid #E2E8F0;
            color: #718096;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px;
        }
        
        .table tbody td {
            border-bottom: 1px solid #E2E8F0;
            padding: 12px 10px;
            color: #2D3748;
            font-size: 13px;
        }
        
        .table tbody tr:hover {
            background: #F7FAFC;
        }
        
        /* Modern Badges */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        
        .badge.bg-primary {
            background: #26C281 !important;
            color: white;
        }
        
        .badge.bg-info {
            background: #4FC3F7 !important;
            color: white;
        }
        
        .badge.bg-success {
            background: #26C281 !important;
            color: white;
        }
        
        .badge.bg-warning {
            background: #FFA726 !important;
            color: white;
        }
        
        .badge.bg-secondary {
            background: #94A3B8 !important;
            color: white;
        }
        
        /* Buttons */
        .btn-outline-primary {
            border: 1px solid #4A5BD8;
            color: #4A5BD8;
            font-weight: 600;
            font-size: 13px;
            padding: 8px 16px;
            border-radius: 6px;
            transition: all 0.2s;
            width: 100%;
            text-align: center;
        }
        
        .btn-outline-primary:hover {
            background: #4A5BD8;
            color: white;
            border-color: #4A5BD8;
            box-shadow: 0 2px 8px rgba(74, 91, 216, 0.25);
        }
        
        /* Member Avatar */
        .member-avatar {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--purple-start), var(--purple-end));
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 13px;
            margin-right: 10px;
        }
        
        .member-info {
            display: inline-flex;
            align-items: center;
        }
        
        .member-name {
            font-weight: 600;
            color: #2D3748;
        }
        
        .dept-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            margin-right: 10px;
        }
        
        .dept-icon.cs {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .dept-icon.cyber {
            background: linear-gradient(135deg, #f093fb, #f5576c);
            color: white;
        }
        
        .dept-icon.software {
            background: linear-gradient(135deg, #4facfe, #00f2fe);
            color: white;
        }
        
        .dept-icon.it {
            background: linear-gradient(135deg, #43e97b, #38f9d7);
            color: white;
        }
        
        /* Department Progress Bars */
        .dept-progress {
            height: 8px;
            background: #E2E8F0;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 8px;
        }
        
        .dept-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--purple-start), var(--purple-end));
            border-radius: 10px;
            transition: width 0.6s ease;
        }
        
        .dept-progress-bar.green {
            background: linear-gradient(90deg, var(--green-start), var(--green-end));
        }
        
        .dept-progress-bar.cyan {
            background: linear-gradient(90deg, var(--cyan-start), var(--cyan-end));
        }
        
        .dept-progress-bar.pink {
            background: linear-gradient(90deg, var(--pink-start), var(--pink-end));
        }
        
        .dept-row {
            padding: 14px 0;
            border-bottom: 1px solid #E2E8F0;
            transition: background 0.3s;
        }
        
        .dept-row:hover {
            background: #F7FAFC;
        }
        
        .dept-row:last-child {
            border-bottom: none;
        }
        
        .dept-header {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .dept-name {
            flex: 1;
            font-weight: 600;
            color: #2D3748;
            font-size: 13px;
        }
        
        .dept-count {
            font-size: 18px;
            font-weight: 700;
            color: #4A5BD8;
        }
        
        .dept-percentage {
            font-size: 11px;
            color: #718096;
            margin-left: 8px;
        }
        
        /* Project Cards */
        .project-item {
            padding: 14px;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.3s;
            background: white;
        }
        
        .project-item:hover {
            border-color: #4A5BD8;
            box-shadow: 0 4px 12px rgba(74, 91, 216, 0.1);
            transform: translateY(-2px);
        }
        
        .project-header {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 10px;
        }
        
        .project-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--purple-start), var(--purple-end));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
            flex-shrink: 0;
        }
        
        .project-title {
            flex: 1;
            font-weight: 600;
            color: #2D3748;
            font-size: 15px;
            margin: 0;
        }
        
        .project-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .project-status.ideation {
            background: #E2E8F0;
            color: #64748B;
        }
        
        .project-status.in_progress {
            background: #FEF3C7;
            color: #D97706;
        }
        
        .project-status.completed {
            background: #D1FAE5;
            color: #059669;
        }
        
        .project-tech {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 10px;
        }
        
        .tech-tag {
            display: inline-block;
            padding: 4px 10px;
            background: #F7FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 6px;
            font-size: 11px;
            color: #4A5BD8;
            font-weight: 600;
        }
        
        /* Alert */
        .alert {
            border-radius: 10px;
            border: none;
            padding: 16px 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        
        /* Timeline Styles */
        .timeline-container {
            position: relative;
            padding: 10px 0;
        }
        
        .timeline-item {
            position: relative;
            padding-left: 65px;
            padding-bottom: 24px;
        }
        
        .timeline-item:last-child {
            padding-bottom: 0;
        }
        
        .timeline-item:not(:last-child)::before {
            content: '';
            position: absolute;
            left: 24px;
            top: 52px;
            bottom: -24px;
            width: 2px;
            background: linear-gradient(180deg, currentColor 0%, currentColor 100%);
            opacity: 0.3;
        }
        
        .timeline-icon {
            position: absolute;
            left: 0;
            top: 0;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }
        
        .timeline-icon.cyan {
            background: linear-gradient(135deg, #4FC3F7, #29B6F6);
        }
        
        .timeline-icon.pink {
            background: linear-gradient(135deg, #F093FB, #F5576C);
        }
        
        .timeline-icon.purple {
            background: linear-gradient(135deg, #5B6FD8, #7E57C2);
        }
        
        .timeline-icon.green {
            background: linear-gradient(135deg, #26C281, #48E5A5);
        }
        
        .timeline-content {
            background: #F7FAFC;
            padding: 12px 14px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .timeline-content:hover {
            background: #EDF2F7;
            transform: translateX(5px);
        }
        
        .timeline-date {
            font-size: 12px;
            color: #718096;
            font-weight: 600;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        
        .timeline-title {
            font-size: 15px;
            color: #2D3748;
            font-weight: 600;
            margin: 0;
        }
        
        .timeline-type {
            display: inline-block;
            margin-top: 8px;
            padding: 4px 10px;
            background: white;
            border-radius: 12px;
            font-size: 11px;
            color: #4A5BD8;
            font-weight: 600;
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .main-content {
                margin-left: 0;
                padding: 16px;
            }
            .sidebar {
                width: var(--sidebar-width);
                height: 100vh;
                position: fixed;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            body.sidebar-open .sidebar {
                transform: translateX(0);
                box-shadow: 6px 0 20px rgba(0,0,0,0.18);
            }
            .sidebar-backdrop {
                display: block;
                opacity: 0;
                transition: opacity 0.25s ease;
            }
            body.sidebar-open .sidebar-backdrop {
                opacity: 1;
                pointer-events: auto;
            }
            body.sidebar-open {
                overflow: hidden;
            }
            .menu-toggle {
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            .top-bar {
                gap: 12px;
            }

            .top-bar h3 {
                display: none;
            }

            .stats-grid {
                --bs-gutter-x: 0.75rem;
                --bs-gutter-y: 0.75rem;
            }

            .stats-grid > [class*="col-"] {
                width: 50%;
                flex: 0 0 auto;
            }

            .stats-card {
                padding: 12px;
                border-radius: 12px;
            }

            .stats-card-gradient {
                background: transparent !important;
                color: inherit;
                padding: 0;
                display: grid;
                grid-template-columns: 38px 1fr;
                grid-template-areas:
                    "icon value"
                    "icon label";
                align-items: center;
                column-gap: 10px;
                row-gap: 2px;
            }

            .stats-card-icon {
                grid-area: icon;
                width: 38px;
                height: 38px;
                border-radius: 9px;
                margin-bottom: 0;
                font-size: 14px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
            }

            .stats-card-gradient.purple .stats-card-icon { background: linear-gradient(135deg, var(--purple-start), var(--purple-end)); }
            .stats-card-gradient.green .stats-card-icon { background: linear-gradient(135deg, var(--green-start), var(--green-end)); }
            .stats-card-gradient.pink .stats-card-icon { background: linear-gradient(135deg, var(--pink-start), var(--pink-end)); }
            .stats-card-gradient.cyan .stats-card-icon { background: linear-gradient(135deg, var(--cyan-start), var(--cyan-end)); }

            .stats-card h3 {
                grid-area: value;
                margin: 0;
                font-size: 28px;
                line-height: 1;
                color: #2D3748;
            }

            .stats-card p {
                grid-area: label;
                margin: 0;
                color: #6B7280;
                font-size: 12px;
                font-weight: 500;
            }

            .stats-card-footer {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Admin Sidebar (Dynamic with role-based permissions) -->
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="d-flex align-items-center gap-2">
                <button class="menu-toggle" id="menuToggle" type="button" aria-label="Toggle navigation" aria-expanded="false">
                    <i class="fas fa-bars"></i>
                </button>
                <h3 class="mb-0"><i class="fas fa-chart-line me-2"></i> Dashboard Overview</h3>
            </div>
            <div class="user-info">
                <div>
                    <strong><?php echo htmlspecialchars($current_user['full_name']); ?></strong>
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
        <div class="row mb-3 g-3 stats-grid">
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-card-gradient purple">
                        <div class="stats-card-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3><?php echo number_format($total_members); ?></h3>
                        <p>Active Members</p>
                    </div>
                    <div class="stats-card-footer">
                        <a href="members.php">
                            <i class="fas fa-users"></i> View Members
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-card-gradient green">
                        <div class="stats-card-icon">
                            <i class="fas fa-project-diagram"></i>
                        </div>
                        <h3><?php echo number_format($total_projects); ?></h3>
                        <p>Active Projects</p>
                    </div>
                    <div class="stats-card-footer">
                        <a href="projects.php">
                            <i class="fas fa-folder-open"></i> View Projects
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-card-gradient pink">
                        <div class="stats-card-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h3><?php echo number_format($upcoming_events); ?></h3>
                        <p>Upcoming Events</p>
                    </div>
                    <div class="stats-card-footer">
                        <a href="events.php">
                            <i class="fas fa-calendar-check"></i> View Events
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-card-gradient cyan">
                        <div class="stats-card-icon">
                            <i class="fas fa-handshake"></i>
                        </div>
                        <h3><?php echo number_format($active_partners); ?></h3>
                        <p>Active Partners</p>
                    </div>
                    <div class="stats-card-footer">
                        <a href="partners.php">
                            <i class="fas fa-handshake"></i> View >
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Content Row -->
        <div class="row">
            <!-- Recent Members -->
            <div class="col-lg-6 mb-3">
                <div class="content-card">
                    <h5><i class="fas fa-user-plus me-2"></i> Recent Members</h5>
                    <div class="card-content">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Department</th>
                                        <th>Level</th>
                                        <th>Joined</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_members as $member): ?>
                                        <tr>
                                            <td>
                                                <div class="member-info">
                                                    <div class="member-avatar">
                                                        <?php echo strtoupper(substr($member['full_name'], 0, 1)); ?>
                                                    </div>
                                                    <span class="member-name"><?php echo htmlspecialchars($member['full_name']); ?></span>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($member['department']); ?></td>
                                            <td><span class="badge bg-primary"><?php echo (int)$member['level']; ?>L</span></td>
                                            <td style="color: #718096; font-size: 13px;"><?php echo date('M d, Y', strtotime($member['registration_date'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <a href="members.php" class="btn btn-sm btn-outline-primary mt-2">View All Members →</a>
                </div>
            </div>
            
            <!-- Upcoming Events Timeline -->
            <div class="col-lg-6 mb-3">
                <div class="content-card">
                    <h5><i class="fas fa-calendar-alt me-2"></i> Upcoming Events Timeline</h5>
                    <div class="card-content">
                        <div class="timeline-container">
                            <?php 
                            $timeline_colors = ['cyan', 'pink', 'purple', 'green'];
                            $timeline_icons = ['fa-laptop-code', 'fa-python', 'fa-calendar', 'fa-graduation-cap'];
                            $color_index = 0;
                            foreach ($events_list as $event): 
                                $color = $timeline_colors[$color_index % count($timeline_colors)];
                                $icon = $timeline_icons[$color_index % count($timeline_icons)];
                                $color_index++;
                            ?>
                                <div class="timeline-item" style="color: var(--<?php echo $color; ?>-start);">
                                    <div class="timeline-icon <?php echo $color; ?>">
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="timeline-date"><?php echo date('M d', strtotime($event['event_date'])); ?></div>
                                        <h6 class="timeline-title"><?php echo htmlspecialchars($event['event_name']); ?></h6>
                                        <span class="timeline-type"><?php echo ucfirst($event['event_type']); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <a href="events.php" class="btn btn-sm btn-outline-primary mt-2">View All Events →</a>
                </div>
            </div>
        </div>
        
        <!-- Department Stats & Projects -->
        <div class="row">
            <!-- Department Breakdown -->
            <div class="col-lg-6 mb-3">
                <div class="content-card">
                    <h5><i class="fas fa-chart-pie me-2"></i> Members by Department</h5>
                    <div class="card-content">
                        <?php 
                        $progress_colors = ['purple', 'green', 'cyan', 'pink'];
                        $dept_icons = [
                            'Computer Science' => 'fa-laptop-code',
                            'Cyber Security' => 'fa-shield-alt',
                            'Software Engineering' => 'fa-code',
                            'Information Technology' => 'fa-server'
                        ];
                        $color_idx = 0;
                        foreach ($dept_stats as $dept): 
                            $percentage = ($dept['count'] / $total_members) * 100;
                            $color = $progress_colors[$color_idx % count($progress_colors)];
                            $icon = $dept_icons[$dept['department']] ?? 'fa-users';
                            $color_idx++;
                        ?>
                            <div class="dept-row">
                                <div class="dept-header">
                                    <div class="dept-icon <?php echo strtolower(str_replace(' ', '', $dept['department'])); ?>">
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="dept-name"><?php echo htmlspecialchars($dept['department']); ?></div>
                                    <div class="dept-count"><?php echo number_format($dept['count']); ?></div>
                                    <span class="dept-percentage"><?php echo number_format($percentage, 1); ?>%</span>
                                </div>
                                <div class="dept-progress">
                                    <div class="dept-progress-bar <?php echo $color; ?>" style="width: <?php echo $percentage; ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Featured Projects -->
            <div class="col-lg-6 mb-3">
                <div class="content-card">
                    <h5><i class="fas fa-star me-2"></i> Featured Projects</h5>
                    <div class="card-content">
                        <?php 
                        $status_icons = [
                            'ideation' => 'fa-lightbulb',
                            'in_progress' => 'fa-spinner',
                            'completed' => 'fa-check-circle'
                        ];
                        foreach ($featured_projects as $project): 
                            $status_icon = $status_icons[$project['project_status']] ?? 'fa-circle';
                            $tech_array = !empty($project['tech_stack']) ? explode(',', $project['tech_stack']) : [];
                        ?>
                            <div class="project-item">
                                <div class="project-header">
                                    <div class="project-icon">
                                        <i class="fas fa-code"></i>
                                    </div>
                                    <div style="flex: 1;">
                                        <h6 class="project-title"><?php echo htmlspecialchars($project['title']); ?></h6>
                                        <span class="project-status <?php echo $project['project_status']; ?>">
                                            <i class="fas <?php echo $status_icon; ?>"></i>
                                            <?php echo ucfirst(str_replace('_', ' ', $project['project_status'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php if (!empty($tech_array)): ?>
                                    <div class="project-tech">
                                        <?php foreach (array_slice($tech_array, 0, 4) as $tech): ?>
                                            <span class="tech-tag"><?php echo htmlspecialchars(trim($tech)); ?></span>
                                        <?php endforeach; ?>
                                        <?php if (count($tech_array) > 4): ?>
                                            <span class="tech-tag">+<?php echo count($tech_array) - 4; ?> more</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="projects.php" class="btn btn-sm btn-outline-primary mt-2">View All Projects →</a>
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

        // Sidebar toggle for small screens
        const menuToggle = document.getElementById('menuToggle');
        const sidebarBackdrop = document.getElementById('sidebarBackdrop');
        const sidebarLinks = document.querySelectorAll('.sidebar-menu a');
        const closeSidebar = () => {
            document.body.classList.remove('sidebar-open');
            if (menuToggle) menuToggle.setAttribute('aria-expanded', 'false');
        };

        if (menuToggle) {
            menuToggle.addEventListener('click', () => {
                const open = !document.body.classList.contains('sidebar-open');
                document.body.classList.toggle('sidebar-open', open);
                menuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }
        if (sidebarBackdrop) {
            sidebarBackdrop.addEventListener('click', closeSidebar);
        }

        sidebarLinks.forEach(link => {
            link.addEventListener('click', closeSidebar);
        });
    </script>
</body>
</html>
