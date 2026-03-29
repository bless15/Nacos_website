<?php
/**
 * ============================================
 * NACOS DASHBOARD - events MANAGEMENT
 * ============================================
 * Purpose: List and manage all events
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

// Get current user
$current_user = getCurrentMember();

// Initialize database
$db = getDB();

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'event_date';
$sort_order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'asc' : 'desc';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(event_name LIKE ? OR description LIKE ? OR location LIKE ? )";
    $search_term = "%{$search}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($type_filter)) {
    $where_conditions[] = "event_type = ?";
    $params[] = $type_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_query = "SELECT COUNT(*) as total FROM events {$where_clause}";
$total_events = $db->fetchOne($count_query, $params)['total'];
$total_pages = ceil($total_events / $per_page);

// Get events with registration count
$query = "
    SELECT 
        e.*,
        COUNT(DISTINCT me.member_id) as registered_count,
        SUM(CASE WHEN me.attendance_status = 'attended' THEN 1 ELSE 0 END) as attended_count
    FROM events e
    LEFT JOIN member_events me ON e.event_id = me.event_id
    {$where_clause}
    GROUP BY e.event_id
    ORDER BY {$sort_by} {$sort_order}
    LIMIT {$per_page} OFFSET {$offset}
";

$events = $db->fetchAll($query, $params);

// Get statistics (count using status column but also fall back to event_date for completed/upcoming when appropriate)
$stats = $db->fetchOne(
    "SELECT
        COUNT(*) as total_events,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        -- Compute completed/upcoming purely from event datetime (ignore possibly stale status values)
        SUM(CASE WHEN (CONCAT(event_date, ' ', COALESCE(start_time, '00:00:00')) <= NOW() AND status != 'cancelled') THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN (CONCAT(event_date, ' ', COALESCE(start_time, '00:00:00')) > NOW() AND status != 'cancelled') THEN 1 ELSE 0 END) as upcoming
     FROM events"
);

// Get flash message
$flash = getFlashMessage();
$delete_csrf_token = generateCSRFToken();

// Helper function to determine event status based on date
function getEventStatus($event) {
    $now = time();
    $event_time = $event['start_time'] ?? '00:00:00';
    $event_date = strtotime($event['event_date'] . ' ' . $event_time);
    
    if ($event['status'] === 'cancelled') {
        return ['status' => 'cancelled', 'color' => 'danger', 'icon' => 'ban'];
    }
    
    // If the event is scheduled for the future, it's upcoming
    if ($event_date > $now) {
        return ['status' => 'upcoming', 'color' => 'primary', 'icon' => 'clock'];
    }

    // For past dates (event_date <= now), consider the event completed unless explicitly cancelled
    // This aligns the per-event badge with the statistics which count past dates as completed
    if ($event_date <= $now) {
        return ['status' => 'completed', 'color' => 'success', 'icon' => 'check-circle'];
    }

    // Fallback: treat as ongoing
    return ['status' => 'ongoing', 'color' => 'warning', 'icon' => 'spinner'];
}

// Helper function to format date
function formatEventDate($date, $time) {
    $time = $time ?: '00:00:00';
    $datetime = strtotime($date . ' ' . $time);
    $now = time();
    $diff = $datetime - $now;
    
    if ($diff < 0) {
        return date('M d, Y', $datetime) . ' <small class="text-muted">(Past)</small>';
    } elseif ($diff < 86400) {
        return '<strong class="text-danger">Today</strong> at ' . date('g:i A', $datetime);
    } elseif ($diff < 172800) {
        return '<strong class="text-warning">Tomorrow</strong> at ' . date('g:i A', $datetime);
    } else {
        $days = floor($diff / 86400);
        return date('M d, Y', $datetime) . ' <small class="text-muted">(in ' . $days . ' days)</small>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events Management - NACOS Dashboard</title>
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
        /* Hamburger toggle */
        .menu-toggle {
            display: none;
            border: none;
            background: transparent;
            color: #667eea;
            font-size: 22px;
            padding: 6px 10px;
            border-radius: 8px;
        }
        .menu-toggle:focus {
            outline: 2px solid rgba(102,126,234,0.35);
            outline-offset: 2px;
        }
        .sidebar-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.35);
            z-index: 900;
            pointer-events: none;
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
            animation: zoomLiftIn 1s cubic-bezier(0.22, 1, 0.36, 1) 0.15s backwards;
        }
        .top-bar h3 {
            margin: 0;
            color: #333;
            font-size: 24px;
        }

        .header-right-icon {
            display: none;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            align-items: center;
            justify-content: center;
            background: rgba(102,126,234,0.08);
            color: var(--primary-color);
            font-size: 18px;
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
        .bg-gradient-primary { background: linear-gradient(135deg, #667eea, #764ba2); }
        .bg-gradient-success { background: linear-gradient(135deg, #11998e, #38ef7d); }
        .bg-gradient-warning { background: linear-gradient(135deg, #f093fb, #f5576c); }
        .bg-gradient-info { background: linear-gradient(135deg, #4facfe, #00f2fe); }
        /* Content Cards */
        .content-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
            animation: zoomLiftIn 1s cubic-bezier(0.22, 1, 0.36, 1) 1.00s backwards;
        }
        .content-card h5 {
            margin-bottom: 20px;
            font-weight: 600;
            color: #333;
        }
        .table { margin: 0; }
        .badge { padding: 5px 10px; border-radius: 5px; }
        .event-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            transition: all 0.3s;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        .event-header {
            padding: 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            position: relative;
            overflow: hidden;
        }
        .event-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        .event-type-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
        }
        .event-icon {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 10px;
        }
        .event-title {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            position: relative;
            z-index: 1;
        }
        .event-body {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        .event-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .event-meta {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 15px;
        }
        .meta-item { display: flex; align-items: center; font-size: 14px; color: #666; }
        .meta-item i { width: 20px; color: var(--primary-color); margin-right: 8px; }
        .event-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 15px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }
        .stat-item { text-align: center; padding: 8px; background: #f8f9fa; border-radius: 8px; }
        .stat-item .number { font-size: 20px; font-weight: 700; color: var(--primary-color); }
        .stat-item .label { font-size: 12px; color: #666; }
        .event-actions { display: flex; gap: 5px; margin-top: auto; }
        .event-actions .btn { flex: 1; font-size: 13px; padding: 8px 10px; }
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
            animation: zoomLiftIn 1s cubic-bezier(0.22, 1, 0.36, 1) 0.40s backwards;
        }
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        .stat-info h3 { margin: 0; font-size: 28px; font-weight: 700; }
        .stat-info p { margin: 0; color: #666; font-size: 14px; }
        .filters-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
            animation: zoomLiftIn 1s cubic-bezier(0.22, 1, 0.36, 1) 0.70s backwards;
        }
        @keyframes zoomLiftIn {
            from {
                opacity: 0;
                transform: translateY(16px) scale(0.97);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        .mobile-filter-toggle { display: none; }
        .mobile-collapsible { display: block; }
        .filters-card .filter-group { min-width: 0; }
        .filter-dropdown .btn { width: 100%; justify-content: space-between; align-items: center; }
        .filter-dropdown .dropdown-menu { width: 100%; max-height: 260px; overflow-y: auto; }
        .empty-state {
            background: white;
            padding: 60px 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .empty-state i { font-size: 64px; color: #ddd; margin-bottom: 20px; }
        .empty-state h4 { color: #666; margin-bottom: 10px; }
        .empty-state p { color: #999; }
        /* Responsive */
        @media (max-width: 992px) {
            .menu-toggle { display: inline-flex; align-items: center; justify-content: center; }
            .main-content { margin-left: 0; padding: 15px; min-height: auto; }
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
            body.sidebar-open .sidebar { transform: translateX(0); }
            .sidebar-backdrop { display: block; opacity: 0; transition: opacity 0.25s ease; }
            body.sidebar-open .sidebar-backdrop { opacity: 1; pointer-events: auto; }
            body.sidebar-open { overflow: hidden; }
            .top-bar { flex-direction: column; align-items: flex-start; gap: 12px; }
            footer.mt-5 { margin-top: 1rem !important; }

            .page-header-row { flex-direction: column; align-items: stretch !important; gap: 10px; }
            .header-left { width: 100%; display: flex; align-items: center; justify-content: space-between; }
            .header-left .header-title { display: none; }
            .header-right-icon { display: inline-flex; }
            .page-header-row .btn-primary,
            .top-bar .btn-primary.add-event-btn {
                width: 100%;
                align-self: stretch;
                justify-content: center;
            }

            .mobile-filter-toggle {
                display: flex;
                width: 100%;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 10px;
            }

            .mobile-collapsible { display: none; }
            .mobile-collapsible.show { display: block; }
        }

        /* Small-screen header adjustments: keep action button at the side (right) */
        @media (max-width: 480px) {
            .page-header-row .btn-primary, .top-bar .btn-primary.add-event-btn { font-size: 14px; border-radius: 10px; }
        }

        @media (max-width: 360px) {
            .page-header-row .btn-primary, .top-bar .btn-primary.add-event-btn { width: 100%; align-self: stretch; }
        }

        /* Stat boxes — compact members style: icon left, number at right */
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .stat-box {
            position: relative;
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px;
            border-radius: 14px;
            background: white;
            box-shadow: 0 8px 24px rgba(0,0,0,0.06);
            transition: transform .25s ease, box-shadow .25s ease;
        }

        .stat-box .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
            flex: 0 0 56px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        }

        /* Icon + left-aligned text stacked vertically */
        .stat-box .stat-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            margin-left: 8px;
            text-align: left;
        }

        .stat-box .stat-info h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
            color: #2c3e50;
            line-height: 1.1;
        }

        .stat-box .stat-info p {
            margin: 4px 0 0;
            font-size: 13px;
            color: #7f8c8d;
            font-weight: 600;
        }

        .stat-box:hover { transform: translateY(-6px); box-shadow: 0 12px 30px rgba(0,0,0,0.08); }

        @media (min-width: 992px) {
            .stats-overview { grid-template-columns: repeat(4, 1fr); }
        }

        @media (max-width: 600px) {
            /* Force two columns (2x2) on small screens for compact side-by-side stats */
            .stats-overview { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .stat-box { padding: 12px; }
            .stat-box .stat-icon { width: 44px; height: 44px; flex: 0 0 44px; }
            .stat-box .stat-info h3 { font-size: 18px; }
            /* Keep text aligned next to icon (don't push to far right) */
            .stat-box .stat-info { margin-left: 8px; text-align: left; }
        }

        .stat-box h3 {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
            color: #2c3e50;
        }

        .stat-box p {
            margin: 0;
            color: #7f8c8d;
            font-size: 12px;
            font-weight: 600;
        }

        /* Keep the stat boxes as 2 columns (2x2) on narrow viewports */
        @media (max-width: 600px) {
            .stats-overview { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .stat-box { padding: 14px; }
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
            <div class="d-flex justify-content-between align-items-center w-100 page-header-row">
                <div class="d-flex align-items-center gap-3 header-left">
                    <button class="menu-toggle" type="button" aria-label="Toggle sidebar" aria-expanded="false">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="header-title">
                        <h3><i class="fas fa-calendar-alt me-2"></i> Events Management</h3>
                        <p class="text-muted mb-0">Manage all NACOS events and track attendance</p>
                    </div>
                    <span class="header-right-icon" aria-hidden="true"><i class="fas fa-calendar-alt"></i></span>
                </div>
                <a href="add_event.php" class="btn btn-primary add-event-btn">
                    <i class="fas fa-plus me-2"></i> Add New Event
                </a>
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
        
        <!-- Statistics Overview -->
        <div class="stats-overview">
            <div class="stat-box">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_events']; ?></h3>
                    <p>Total Events</p>
                </div>
            </div>
            
            <div class="stat-box">
                <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['upcoming']; ?></h3>
                    <p>Upcoming</p>
                </div>
            </div>
            
            <div class="stat-box">
                <div class="stat-icon" style="background: linear-gradient(135deg, #11998e, #38ef7d);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['completed']; ?></h3>
                    <p>Completed</p>
                </div>
            </div>
            
            <div class="stat-box">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                    <i class="fas fa-ban"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['cancelled']; ?></h3>
                    <p>Cancelled</p>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <button type="button" class="btn btn-outline-primary mobile-filter-toggle" id="mobileFilterToggle" aria-expanded="false" aria-controls="mobileFilterCard">
            <span><i class="fas fa-filter me-2"></i>Filter Events</span>
            <i class="fas fa-chevron-down" id="mobileFilterChevron"></i>
        </button>

        <div class="filters-card mobile-collapsible" id="mobileFilterCard">
            <form method="GET" action="events.php" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search Events</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search by name, description, or location..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Event Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="workshop" <?php echo $type_filter === 'workshop' ? 'selected' : ''; ?>>Workshop</option>
                        <option value="seminar" <?php echo $type_filter === 'seminar' ? 'selected' : ''; ?>>Seminar</option>
                        <option value="hackathon" <?php echo $type_filter === 'hackathon' ? 'selected' : ''; ?>>Hackathon</option>
                        <option value="competition" <?php echo $type_filter === 'competition' ? 'selected' : ''; ?>>Competition</option>
                        <option value="meeting" <?php echo $type_filter === 'meeting' ? 'selected' : ''; ?>>Meeting</option>
                        <option value="social" <?php echo $type_filter === 'social' ? 'selected' : ''; ?>>Social</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="upcoming" <?php echo $status_filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Sort By</label>
                    <select name="sort" class="form-select">
                        <option value="event_date" <?php echo $sort_by === 'event_date' ? 'selected' : ''; ?>>Date</option>
                        <option value="event_name" <?php echo $sort_by === 'event_name' ? 'selected' : ''; ?>>Name</option>
                        <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Created</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Order</label>
                    <select name="order" class="form-select">
                        <option value="desc" <?php echo $sort_order === 'desc' ? 'selected' : ''; ?>>Desc</option>
                        <option value="asc" <?php echo $sort_order === 'asc' ? 'selected' : ''; ?>>Asc</option>
                    </select>
                </div>
                
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-2"></i> Apply Filters
                    </button>
                    <a href="events.php" class="btn btn-outline-secondary">
                        <i class="fas fa-redo me-2"></i> Reset
                    </a>
                    <span class="text-muted ms-3">
                        Showing <?php echo min($offset + 1, $total_events); ?> 
                        to <?php echo min($offset + $per_page, $total_events); ?> 
                        of <?php echo $total_events; ?> events
                    </span>
                </div>
            </form>
        </div>
        
        <!-- Events Grid -->
        <?php if (empty($events)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h4>No Events Found</h4>
                <p>No events match your current filters. Try adjusting your search criteria.</p>
                <a href="add_event.php" class="btn btn-primary mt-3">
                    <i class="fas fa-plus me-2"></i> Create Your First Event
                </a>
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4">
                <?php foreach ($events as $event): 
                    $status = getEventStatus($event);
                    $event_type_icons = [
                        'workshop' => 'fa-chalkboard-teacher',
                        'seminar' => 'fa-presentation',
                        'hackathon' => 'fa-code',
                        'competition' => 'fa-trophy',
                        'meeting' => 'fa-users',
                        'social' => 'fa-glass-cheers'
                    ];
                    $icon = $event_type_icons[$event['event_type']] ?? 'fa-calendar';
                    $capacity_percentage = !empty($event['capacity']) && $event['capacity'] > 0 ? 
                        round(($event['registered_count'] / $event['capacity']) * 100) : 0;
                ?>
                    <div class="col">
                        <div class="event-card">
                            <div class="event-header">
                                <div class="event-type-badge">
                                    <?php echo ucfirst($event['event_type']); ?>
                                </div>
                                <div class="event-icon">
                                    <i class="fas <?php echo $icon; ?>"></i>
                                </div>
                                <h5 class="event-title">
                                    <?php echo htmlspecialchars($event['event_name']); ?>
                                </h5>
                            </div>
                            
                            <div class="event-body">
                                <div class="event-description">
                                    <?php echo htmlspecialchars($event['description'] ?? 'No description available.'); ?>
                                </div>
                                
                                <div class="event-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-calendar-day"></i>
                                        <span><?php echo formatEventDate($event['event_date'], $event['start_time'] ?? $event['event_time'] ?? '00:00:00'); ?></span>
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
                                        <i class="fas fa-<?php echo $status['icon']; ?>"></i>
                                        <span class="badge bg-<?php echo $status['color']; ?>">
                                            <?php echo ucfirst($status['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="event-stats">
                                    <div class="stat-item">
                                        <div class="number"><?php echo $event['registered_count']; ?></div>
                                        <div class="label">Registered</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="number"><?php echo $event['attended_count']; ?></div>
                                        <div class="label">Attended</div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($event['capacity']) && $event['capacity'] > 0): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">Capacity: <?php echo $event['registered_count']; ?>/<?php echo $event['capacity']; ?></small>
                                        <div class="progress" style="height: 5px;">
                                            <div class="progress-bar <?php echo $capacity_percentage >= 90 ? 'bg-danger' : ($capacity_percentage >= 70 ? 'bg-warning' : 'bg-success'); ?>" 
                                                 style="width: <?php echo min($capacity_percentage, 100); ?>%"></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="event-actions">
                                    <a href="view_event.php?id=<?php echo $event['event_id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="event_attendance.php?id=<?php echo $event['event_id']; ?>" 
                                       class="btn btn-sm btn-outline-success" title="Attendance">
                                        <i class="fas fa-check"></i>
                                    </a>
                                    <a href="edit_event.php?id=<?php echo $event['event_id']; ?>" 
                                       class="btn btn-sm btn-outline-warning" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button"
                                       class="btn btn-sm btn-outline-danger" title="Delete" onclick="deleteEvent(<?php echo (int)$event['event_id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Events pagination">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>&sort=<?php echo urlencode($sort_by); ?>&order=<?php echo urlencode($sort_order); ?>">
                                    Previous
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>&sort=<?php echo urlencode($sort_by); ?>&order=<?php echo urlencode($sort_order); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>&sort=<?php echo urlencode($sort_by); ?>&order=<?php echo urlencode($sort_order); ?>">
                                    Next
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- Footer -->
    <footer class="bg-light text-center py-3 mt-5">
        <div class="container">
            <small class="text-muted">&copy; <?php echo date('Y'); ?> NACOSAU. All Rights Reserved.</small><br>
            <small class="text-muted">Developed by <a href="https://johnicity.com.ng/portfolio" target="_blank" class="text-decoration-none">Johnicity</a></small>
        </div>
    </footer>

    <form id="deleteEventForm" method="POST" action="" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?php echo $delete_csrf_token; ?>">
    </form>
    
    <?php // Include admin footer which loads Bootstrap and confirmation modal ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script>
        function deleteEvent(eventId) {
            const form = document.getElementById('deleteEventForm');
            if (!form || !eventId) return;

            const submitDelete = function() {
                form.action = 'delete_event.php?id=' + encodeURIComponent(eventId);
                form.submit();
            };

            if (typeof window.confirmModal !== 'function') {
                if (confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
                    submitDelete();
                }
                return;
            }

            window.confirmModal('Are you sure you want to delete this event? This action cannot be undone.', {
                title: 'Delete Event',
                okLabel: 'Delete'
            }).then(function(confirmed) {
                if (confirmed) submitDelete();
            });
        }

        (() => {
            const toggleBtn = document.querySelector('.menu-toggle');
            const backdrop = document.querySelector('.sidebar-backdrop');
            const body = document.body;
            if (!toggleBtn) return;

            const closeMenu = () => {
                body.classList.remove('sidebar-open');
                toggleBtn.setAttribute('aria-expanded', 'false');
            };

            const toggleMenu = () => {
                const isOpen = body.classList.toggle('sidebar-open');
                toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            };

            toggleBtn.addEventListener('click', toggleMenu);
            if (backdrop) {
                backdrop.addEventListener('click', closeMenu);
            }
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeMenu();
            });
        })();

        (() => {
            const filterToggle = document.getElementById('mobileFilterToggle');
            const filterCard = document.getElementById('mobileFilterCard');
            const chevron = document.getElementById('mobileFilterChevron');

            if (!filterToggle || !filterCard || !chevron) return;

            filterToggle.addEventListener('click', () => {
                const isVisible = filterCard.classList.toggle('show');
                filterToggle.setAttribute('aria-expanded', isVisible ? 'true' : 'false');
                chevron.classList.toggle('fa-chevron-up', isVisible);
                chevron.classList.toggle('fa-chevron-down', !isVisible);
            });
        })();
    </script>
    
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

