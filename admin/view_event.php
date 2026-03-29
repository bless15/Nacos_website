<?php
/**
 * ============================================
 * NACOS DASHBOARD - VIEW EVENT DETAILS
 * ============================================
 * Purpose: View detailed event information
 * Access: Requires authentication
 * Created: November 3, 2025
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Bootstrap and includes
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Require login
requireAdminRole();

// Get current user
$current_user = getCurrentMember();

// Initialize database
$db = getDB();

// Get event ID
$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($event_id <= 0) {
    redirectWithMessage('events.php', 'Invalid event ID', 'error');
}

// Get event data
$event = $db->fetchOne("SELECT * FROM events WHERE event_id = ?", [$event_id]);

if (!$event) {
    redirectWithMessage('events.php', 'Event not found', 'error');
}

// Get registered members with attendance status
$registered_members = $db->fetchAll(
    "SELECT m.member_id, m.full_name, m.matric_no, m.email, m.department, m.level,
            me.attendance_status, me.registration_date, me.feedback_rating, me.feedback_comment
     FROM member_events me
     JOIN members m ON me.member_id = m.member_id
     WHERE me.event_id = ?
     ORDER BY me.registration_date DESC",
    [$event_id]
);

// Calculate statistics
$total_registered = count($registered_members);
$attended_count = count(array_filter($registered_members, fn($m) => $m['attendance_status'] === 'attended'));
$absent_count = count(array_filter($registered_members, fn($m) => $m['attendance_status'] === 'absent'));
$cancelled_count = count(array_filter($registered_members, fn($m) => $m['attendance_status'] === 'cancelled'));
$attendance_rate = $total_registered > 0 ? round(($attended_count / $total_registered) * 100) : 0;

// Calculate average rating
$ratings = array_filter(array_column($registered_members, 'feedback_rating'));
$average_rating = !empty($ratings) ? round(array_sum($ratings) / count($ratings), 1) : 0;

// Get flash message
$flash = getFlashMessage();

// Helper function to get event status
function getEventStatusBadge($event) {
    $now = time();
    $event_time = $event['start_time'] ?? $event['event_time'] ?? '00:00:00';
    $event_date = strtotime($event['event_date'] . ' ' . $event_time);
    
    if ($event['status'] === 'cancelled') {
        return '<span class="badge bg-danger"><i class="fas fa-ban me-1"></i> Cancelled</span>';
    }
    
    if ($event_date > $now) {
        $diff = $event_date - $now;
        if ($diff < 86400) {
            return '<span class="badge bg-danger"><i class="fas fa-clock me-1"></i> Today</span>';
        } elseif ($diff < 172800) {
            return '<span class="badge bg-warning"><i class="fas fa-clock me-1"></i> Tomorrow</span>';
        } else {
            return '<span class="badge bg-primary"><i class="fas fa-clock me-1"></i> Upcoming</span>';
        }
    } elseif ($event['status'] === 'completed') {
        return '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Completed</span>';
    } else {
        return '<span class="badge bg-secondary"><i class="fas fa-spinner me-1"></i> Ongoing</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['event_name']); ?> - Event Details</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/admin.css">
    
    <style>
        :root {
            --primary-color: #0F6B3E;
            --secondary-color: #1B8A56;
            --sidebar-bg: #0b5d35;
            --sidebar-hover: #0d6f40;
            --success-start: #11998e;
            --success-end: #38ef7d;
            --danger-start: #dc3545;
            --danger-end: #b02a37;
            --warning-start: #ffc107;
            --warning-end: #ff6b6b;
            --info-start: #4facfe;
            --info-end: #00f2fe;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(180deg, #f7fbf8 0%, #edf6ef 100%);
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
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
            padding: 20px;
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


        .event-header {
            background: linear-gradient(135deg, #0F6B3E, #1B8A56);
            color: white;
            padding: 18px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 6px 18px rgba(15, 107, 62, 0.22);
            animation: fadeInDown 0.45s ease;
        }
        
        .event-header::before {
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
        
        .event-icon {
            width: 56px;
            height: 56px;
            background: rgba(255, 255, 255, 0.25);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 12px;
            position: relative;
            z-index: 1;
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.12);
            backdrop-filter: blur(6px);
            transition: all 0.2s ease;
        }
        
        .event-icon:hover {
            transform: scale(1.1) rotate(5deg);
        }
        
        .event-header h2 {
            position: relative;
            z-index: 1;
            margin-bottom: 8px;
            font-size: 1.4rem;
            font-weight: 700;
            color: #ffffff !important;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.12);
        }

        .event-header .meta-item,
        .event-header .meta-item span,
        .event-header .meta-item i {
            color: #ffffff !important;
        }
        
        .event-meta {
            position: relative;
            z-index: 1;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 13px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.15);
            padding: 8px 16px;
            border-radius: 20px;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        
        .meta-item:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }
        
        .meta-item i {
            font-size: 18px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }

        /* Compact stats grid - increased by 10% */
        .stats-grid.compact {
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }

        .stats-grid.compact .stat-card {
            padding: 8px !important;
            border-radius: 10px !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.06) !important;
            min-height: 100px !important;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .stats-grid.compact .stat-icon {
            width: 32px !important;
            height: 32px !important;
            margin-bottom: 8px !important;
            font-size: 15px !important;
            border-radius: 8px !important;
        }

        .stats-grid.compact .stat-card h3 {
            font-size: 18px !important;
            font-weight: 800 !important;
            margin: 0 !important;
            line-height: 1 !important;
        }

        .stats-grid.compact .stat-card p {
            color: #7f8c8d !important;
            margin: 5px 0 0 !important;
            font-size: 13px !important;
            font-weight: 600 !important;
        }

        /* Force hover to still show a small lift but not grow large */
        .stats-grid.compact .stat-card:hover {
            transform: translateY(-6px) !important;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08) !important;
        }
        
        .stat-card {
            background: white;
            padding: 14px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: all 0.25s ease;
            animation: fadeInUp 0.45s ease backwards;
        }
        
        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }
        .stat-card:nth-child(5) { animation-delay: 0.5s; }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(15, 107, 62, 0.05) 0%, rgba(27, 138, 86, 0.05) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-10px) scale(1.03);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }
        
        .stat-card:hover::before {
            opacity: 1;
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            margin: 0 auto 12px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            position: relative;
            z-index: 1;
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.12);
            transition: all 0.2s ease;
        }
        
        .stat-card:hover .stat-icon {
            transform: rotateY(360deg);
        }
        
        .stat-card h3 {
            font-size: 28px;
            font-weight: 800;
            margin: 0;
            color: #2c3e50;
            position: relative;
            z-index: 1;
        }
        
        .stat-card p {
            color: #7f8c8d;
            margin: 8px 0 0;
            font-size: 15px;
            font-weight: 500;
            position: relative;
            z-index: 1;
        }
        
        .content-card {
            background: white;
            padding: 16px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            margin-bottom: 20px;
            transition: all 0.2s ease;
            animation: fadeInUp 0.45s ease backwards;
            animation-delay: 0.25s;
        }
        
        .content-card:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            transform: translateY(-3px);
        }
        
        .content-card h5 {
            color: var(--primary-color);
            margin-bottom: 12px;
            font-weight: 700;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            gap: 8px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .content-card h5 i {
            font-size: 1.5rem;
        }
        
        .info-grid {
            display: grid;
            gap: 15px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            padding: 12px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            border-left: 4px solid var(--primary-color);
            transition: all 0.2s ease;
            overflow: hidden;
            min-width: 0; /* allow children to shrink */
        }
        
        .info-item:hover {
            background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
            transform: translateX(5px);
        }
        
        .info-label {
            font-weight: 700;
            color: #495057;
            width: 140px;
            flex-shrink: 0;
            font-size: 14px;
        }
        
        .info-value {
            color: #2c3e50;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            flex: 1 1 auto;
            min-width: 0; /* allow ellipsis */
        }

        .info-value > * {
            min-width: 0;
        }

        .info-value .text-ellipsis {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .info-value .progress {
            flex: 1 1 auto;
            margin-left: 8px;
            min-width: 0;
        }
        
        .rating-stars {
            color: #ffc107;
            font-size: 16px;
        }
        
        .rating-stars i {
            margin-right: 2px;
            filter: drop-shadow(0 2px 3px rgba(255, 193, 7, 0.3));
        }
        
        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .progress {
            height: 12px;
            border-radius: 6px;
            overflow: hidden;
            background: #e9ecef;
        }
        
        .progress-bar {
            transition: width 0.6s ease;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 40px;
            color: #adb5bd;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.4;
            background: linear-gradient(135deg, #0F6B3E, #1B8A56);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .empty-state p {
            font-size: 16px;
            font-weight: 500;
        }
        
        /* Top Bar Enhancements */
        .top-bar {
            background: white;
            padding: 25px 30px;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
            animation: fadeInDown 0.6s ease;
        }
        
        .top-bar h3 {
            color: #2c3e50;
            font-weight: 700;
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
        
        .btn {
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #11998e, #38ef7d);
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(17, 153, 142, 0.3);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #ffc107, #ff6b6b);
            color: white;
        }
        
        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 193, 7, 0.3);
        }
        
        .btn-outline-secondary {
            border: 2px solid #6c757d;
            color: #6c757d;
            background: transparent;
        }
        
        .btn-outline-secondary:hover {
            background: #6c757d;
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-sm.btn-outline-primary {
            border: 2px solid #0F6B3E;
            color: #0F6B3E;
            background: transparent;
            border-radius: 8px;
            padding: 6px 14px;
            font-size: 13px;
        }
        
        .btn-sm.btn-outline-primary:hover {
            background: linear-gradient(135deg, #0F6B3E, #1B8A56);
            color: white;
            border-color: #0F6B3E;
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
        
        /* Badge Enhancements */
        .badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 12px;
        }
        
        .badge.bg-primary {
            background: linear-gradient(135deg, #0F6B3E, #1B8A56) !important;
        }
        
        .badge.bg-success {
            background: linear-gradient(135deg, #11998e, #38ef7d) !important;
        }
        
        .badge.bg-danger {
            background: linear-gradient(135deg, #dc3545, #b02a37) !important;
        }
        
        .badge.bg-warning {
            background: linear-gradient(135deg, #ffc107, #ff6b6b) !important;
        }
        
        .badge.bg-info {
            background: linear-gradient(135deg, #4facfe, #00f2fe) !important;
        }
        
        .badge.bg-secondary {
            background: linear-gradient(135deg, #6c757d, #495057) !important;
        }

        /* Smaller Registered Members card adjustments */
        .content-card.registered-members {
            padding: 12px;
        }

        .content-card.registered-members h5 {
            font-size: 0.98rem;
            padding-bottom: 8px;
        }

        .content-card.registered-members .empty-state {
            padding: 30px 20px;
        }

        .content-card.registered-members table tbody tr td,
        .content-card.registered-members table thead th {
            padding: 10px !important;
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
                padding: 16px;
            }

            .top-actions {
                width: 100%;
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
            }

            .top-actions .btn {
                width: 100%;
            }

            .top-actions .btn-full {
                grid-column: 1 / -1;
            }

            .event-header {
                padding: 14px;
            }

            .event-header h2 {
                font-size: 1.2rem;
            }

            .meta-item {
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

            .stats-grid.compact {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .content-card {
                padding: 12px;
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
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="menu-toggle" id="menuToggle" aria-label="Toggle navigation menu">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h3><i class="fas fa-calendar-alt me-2"></i> Event Details</h3>
                </div>
                <div class="d-flex gap-2 top-actions">
                    <a href="event_attendance.php?id=<?php echo $event_id; ?>" class="btn btn-success">
                        <i class="fas fa-check-square me-2"></i> Manage Attendance
                    </a>
                    <a href="edit_event.php?id=<?php echo $event_id; ?>" class="btn btn-warning">
                        <i class="fas fa-edit me-2"></i> Edit Event
                    </a>
                    <a href="events.php" class="btn btn-secondary btn-full">
                        <i class="fas fa-arrow-left me-2"></i> Back to Events
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
        
        <!-- Event Header -->
        <div class="event-header">
            <div class="event-icon">
                <?php
                $type_icons = [
                    'workshop' => 'fa-chalkboard-teacher',
                    'seminar' => 'fa-presentation',
                    'hackathon' => 'fa-code',
                    'competition' => 'fa-trophy',
                    'meeting' => 'fa-users',
                    'social' => 'fa-glass-cheers'
                ];
                $icon = $type_icons[$event['event_type']] ?? 'fa-calendar';
                ?>
                <i class="fas <?php echo $icon; ?>"></i>
            </div>
            <h2><?php echo htmlspecialchars($event['event_name']); ?></h2>
            <div class="event-meta">
                <div class="meta-item">
                    <i class="fas fa-calendar-day"></i>
                    <span><?php echo date('F d, Y', strtotime($event['event_date'])); ?></span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-clock"></i>
                    <span><?php $display_time = $event['start_time'] ?? $event['event_time'] ?? '00:00:00'; echo date('g:i A', strtotime($display_time)); ?></span>
                </div>
                <?php if ($event['location']): ?>
                    <div class="meta-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?php echo htmlspecialchars($event['location']); ?></span>
                    </div>
                <?php endif; ?>
                <div class="meta-item">
                    <i class="fas fa-tag"></i>
                    <span><?php echo ucfirst($event['event_type']); ?></span>
                </div>
                <div class="meta-item">
                    <?php echo getEventStatusBadge($event); ?>
                </div>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid compact">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                    <i class="fas fa-users"></i>
                </div>
                <h3><?php echo $total_registered; ?></h3>
                <p>Total Registered</p>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #11998e, #38ef7d);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3><?php echo $attended_count; ?></h3>
                <p>Attended</p>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                    <i class="fas fa-times-circle"></i>
                </div>
                <h3><?php echo $absent_count; ?></h3>
                <p>Absent</p>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
                    <i class="fas fa-percentage"></i>
                </div>
                <h3><?php echo $attendance_rate; ?>%</h3>
                <p>Attendance Rate</p>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ffc107, #ff6b6b);">
                    <i class="fas fa-star"></i>
                </div>
                <h3><?php echo $average_rating; ?></h3>
                <p>Average Rating</p>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-4">
                <!-- Event Information -->
                <div class="content-card">
                    <h5><i class="fas fa-info-circle me-2"></i> Event Information</h5>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Status:</div>
                            <div class="info-value"><?php echo getEventStatusBadge($event); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Type:</div>
                            <div class="info-value">
                                <span class="badge bg-secondary"><?php echo ucfirst($event['event_type']); ?></span>
                            </div>
                        </div>
                        
                        <?php if (!empty($event['capacity'])): ?>
                            <div class="info-item">
                                <div class="info-label">Capacity:</div>
                                <div class="info-value">
                                    <?php echo $total_registered; ?> / <?php echo $event['capacity']; ?>
                                    <?php 
                                    $capacity_pct = round(($total_registered / $event['capacity']) * 100);
                                    ?>
                                    <div class="progress mt-2" style="height: 8px;">
                                        <div class="progress-bar <?php echo $capacity_pct >= 90 ? 'bg-danger' : ($capacity_pct >= 70 ? 'bg-warning' : 'bg-success'); ?>" 
                                             style="width: <?php echo min($capacity_pct, 100); ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($event['registration_link'])): ?>
                            <div class="info-item">
                                <div class="info-label">Registration Link:</div>
                                <div class="info-value">
                                    <a href="<?php echo htmlspecialchars($event['registration_link']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-external-link-alt me-1"></i>Open Registration Form
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="info-item">
                            <div class="info-label">Created:</div>
                            <div class="info-value">
                                <?php echo date('M d, Y', strtotime($event['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Description -->
                <?php if (!empty($event['summary']) || !empty($event['full_description'])): ?>
                    <div class="content-card">
                        <h5><i class="fas fa-align-left me-2"></i> Description</h5>
                        <?php if (!empty($event['summary'])): ?>
                            <p class="fw-bold mb-2"><?php echo nl2br(htmlspecialchars($event['summary'])); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($event['full_description'])): ?>
                            <p class="text-muted mb-0"><?php echo nl2br(htmlspecialchars($event['full_description'])); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="col-md-8">
                <!-- Registered Members -->
                <div class="content-card registered-members">
                    <h5><i class="fas fa-users me-2"></i> Registered Members (<?php echo $total_registered; ?>)</h5>
                    
                    <?php if (empty($registered_members)): ?>
                        <div class="empty-state">
                            <i class="fas fa-user-slash"></i>
                            <p>No members registered yet</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                                        <th style="border: none; padding: 15px;">Member</th>
                                        <th style="border: none; padding: 15px;">Department</th>
                                        <th style="border: none; padding: 15px;">Status</th>
                                        <th style="border: none; padding: 15px;">Rating</th>
                                        <th style="border: none; padding: 15px;">Registered</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($registered_members as $index => $member): ?>
                                        <tr style="animation: fadeInUp 0.5s ease backwards; animation-delay: <?php echo ($index * 0.05); ?>s; border-bottom: 1px solid #f0f0f0;">
                                            <td style="padding: 18px;">
                                                <div style="display: flex; align-items: center; gap: 12px;">
                                                    <div style="width: 42px; height: 42px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 16px;">
                                                        <?php echo strtoupper(substr($member['full_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <strong style="color: #2c3e50; font-size: 15px;"><?php echo htmlspecialchars($member['full_name']); ?></strong><br>
                                                        <small class="text-muted" style="font-size: 13px;"><?php echo htmlspecialchars($member['matric_no']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td style="padding: 18px;">
                                                <strong style="color: #495057;"><?php echo htmlspecialchars($member['department']); ?></strong><br>
                                                <small class="text-muted" style="font-size: 13px;"><?php echo $member['level']; ?> Level</small>
                                            </td>
                                            <td style="padding: 18px;">
                                                <?php 
                                                $status_colors = [
                                                    'registered' => 'info',
                                                    'attended' => 'success',
                                                    'absent' => 'danger',
                                                    'cancelled' => 'secondary'
                                                ];
                                                $color = $status_colors[$member['attendance_status']] ?? 'info';
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>" style="padding: 8px 14px; font-size: 13px; font-weight: 600;">
                                                    <?php echo ucfirst($member['attendance_status']); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 18px;">
                                                <?php if ($member['feedback_rating']): ?>
                                                    <div class="rating-stars">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star<?php echo $i <= $member['feedback_rating'] ? '' : '-o'; ?>"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted" style="font-size: 14px;">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 18px;">
                                                <small style="color: #6c757d; font-size: 13px; font-weight: 500;"><?php echo date('M d, Y', strtotime($member['registration_date'])); ?></small>
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

