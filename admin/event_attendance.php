<?php
/**
 * ============================================
 * NACOS DASHBOARD - EVENT ATTENDANCE TRACKING
 * ============================================
 * Purpose: Mark member attendance and collect feedback
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

// Handle AJAX attendance update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit;
    }
    
    $action = $_POST['action'];
    $member_id = intval($_POST['member_id'] ?? 0);
    
    try {
        if ($action === 'update_status') {
            $status = sanitizeInput($_POST['status'] ?? '');
            
            if (!in_array($status, ['registered', 'attended', 'absent', 'cancelled'])) {
                throw new Exception('Invalid status');
            }
            
            $query = "UPDATE member_events SET attendance_status = ? WHERE event_id = ? AND member_id = ?";
            $db->query($query, [$status, $event_id, $member_id]);
            
            echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
            
        } elseif ($action === 'update_rating') {
            $rating = intval($_POST['rating'] ?? 0);
            
            if ($rating < 1 || $rating > 5) {
                throw new Exception('Invalid rating');
            }
            
            $query = "UPDATE member_events SET feedback_rating = ? WHERE event_id = ? AND member_id = ?";
            $db->query($query, [$rating, $event_id, $member_id]);
            
            echo json_encode(['success' => true, 'message' => 'Rating updated successfully']);
            
        } elseif ($action === 'mark_all_attended') {
            $query = "UPDATE member_events SET attendance_status = 'attended' 
                      WHERE event_id = ? AND attendance_status = 'registered'";
            $db->query($query, [$event_id]);
            
            echo json_encode(['success' => true, 'message' => 'All registered members marked as attended']);
            
        } else {
            throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Get registered members
$members = $db->fetchAll(
    "SELECT m.member_id, m.full_name, m.matric_no, m.email, m.department, m.level,
            me.attendance_status, me.registration_date, me.feedback_rating, me.feedback_comment
     FROM member_events me
     JOIN members m ON me.member_id = m.member_id
     WHERE me.event_id = ?
     ORDER BY m.full_name ASC",
    [$event_id]
);

// Calculate statistics
$total_registered = count($members);
$attended_count = count(array_filter($members, fn($m) => $m['attendance_status'] === 'attended'));
$absent_count = count(array_filter($members, fn($m) => $m['attendance_status'] === 'absent'));
$pending_count = count(array_filter($members, fn($m) => $m['attendance_status'] === 'registered'));

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Get flash message
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - <?php echo htmlspecialchars($event['event_name']); ?></title>
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
    --success-gradient: linear-gradient(135deg, #0F6B3E, #1B8A56);
    --purple-gradient: linear-gradient(135deg, #0F6B3E, #1B8A56);
    --danger-gradient: linear-gradient(135deg, #dc3545, #b02a37);
    --blue-gradient: linear-gradient(135deg, #0F6B3E, #1B8A56);
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(180deg, #f7fbf8 0%, #edf6ef 100%);
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

.page-header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}

.page-header-info {
    flex: 1 1 280px;
    min-width: 0;
}

.page-header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    justify-content: flex-end;
    flex: 1 1 300px;
    min-width: 0;
}

.page-header-actions .btn {
    white-space: nowrap;
    min-height: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

@media (max-width: 1200px) {
    .page-header-content {
        align-items: flex-start;
    }

    .page-header-actions {
        width: 100%;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .page-header-actions .btn {
        width: 100%;
    }
}

/* Event Banner */
.event-banner {
    background: linear-gradient(135deg, #0F6B3E, #1B8A56);
    color: white;
    padding: 35px 40px;
    border-radius: 16px;
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(15, 107, 62, 0.3);
    animation: fadeInDown 0.6s ease;
}

.event-banner::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, transparent 70%);
    border-radius: 50%;
    animation: float 6s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translate(0, 0) rotate(0deg); }
    50% { transform: translate(20px, -20px) rotate(5deg); }
}

.event-banner h4 {
    position: relative;
    z-index: 1;
    font-size: 1.8rem;
    font-weight: 700;
    color: #ffffff !important;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
}

.event-banner p {
    position: relative;
    z-index: 1;
    font-size: 15px;
    color: #ffffff !important;
    opacity: 0.95;
}

.event-banner i {
    margin-right: 8px;
}
        
/* Statistics Cards */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

/* Compact stats row - reduce sizes by ~60% while keeping transitions */
.stats-row.compact {
    grid-template-columns: repeat(auto-fit, minmax(90px, 1fr));
    gap: 10px;
    margin-bottom: 12px;
}

.stats-row.compact .stat-box {
    padding: 10px !important;
    border-radius: 10px !important;
    min-height: 84px !important;
    box-shadow: 0 3px 10px rgba(0,0,0,0.06) !important;
    display: flex !important;
    flex-direction: column !important;
    justify-content: center !important;
}

.stats-row.compact .stat-box .icon {
    width: 34px !important;
    height: 34px !important;
    margin-bottom: 8px !important;
    font-size: 16px !important;
    border-radius: 8px !important;
}

.stats-row.compact .stat-box h3 {
    font-size: 18px !important;
    margin: 0 !important;
}

.stats-row.compact .stat-box p {
    font-size: 12px !important;
    margin-top: 6px !important;
}

.stats-row.compact .stat-box:hover {
    transform: translateY(-6px) !important;
    box-shadow: 0 8px 20px rgba(0,0,0,0.08) !important;
}

/* Extra forceful fallback: scale down boxes visually if other rules are overridden */
.stats-row.compact.force-scale {
    transform-origin: top left;
    transform: scale(0.45);
}

/* Compact Registered Members card */
.attendance-table.compact {
    padding: 6K/px !important;
    border-radius: 12px !important;
}

.attendance-table.compact .table-header {
    padding: 10px 14px !important;
    border-radius: 10px 10px 0 0 !important;
}

.attendance-table.compact .empty-state {
    padding: 24px 16px !important;
}

.attendance-table.compact .empty-state i {
    font-size: 48px !important;
    margin-bottom: 12px !important;
}

.attendance-table.compact .table thead th,
.attendance-table.compact .table tbody td {
    padding: 8px 10px !important;
}

.stat-box {
    background: white;
    padding: 12px 10px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    text-align: center;
    position: relative;
    overflow: hidden;
    min-height: 116px;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    animation: fadeInUp 0.6s ease-out backwards;
}

.stat-box::before {
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

.stat-box:hover {
    transform: translateY(-10px) scale(1.03);
    box-shadow: 0 15px 40px rgba(15, 107, 62, 0.2);
}

.stat-box:hover::before {
    opacity: 1;
}

.stat-box .icon {
    width: 40px;
    height: 40px;
    margin: 0 auto 8px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    color: white;
    box-shadow: 0 5px 12px rgba(0, 0, 0, 0.15);
    position: relative;
    z-index: 1;
    transition: all 0.3s ease;
}

.stat-box:hover .icon {
    transform: rotateY(360deg);
}

.stat-box h3 {
    margin: 0 0 4px;
    font-size: 22px;
    font-weight: 800;
    color: #2c3e50;
    position: relative;
    z-index: 1;
}

.stat-box p {
    margin: 0;
    color: #7f8c8d;
    font-size: 12px;
    font-weight: 600;
    position: relative;
    z-index: 1;
}
        
/* Quick Actions */
.quick-actions {
    display: flex;
    gap: 15px;
    margin-bottom: 25px;
    flex-wrap: wrap;
}

/* Attendance Table */
.attendance-table {
    background: white;
    border-radius: 16px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    animation: fadeInUp 0.8s ease-out backwards;
    animation-delay: 0.6s;
}

.table-header {
    padding: 25px 30px;
    background: linear-gradient(135deg, #0F6B3E, #1B8A56);
    color: white;
}

.table-header h5 {
    margin: 0;
    font-weight: 700;
    font-size: 1.3rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.table-header h5 i {
    font-size: 1.4rem;
}

.table {
    margin: 0;
}

.table thead th {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-bottom: 3px solid #dee2e6;
    color: #2c3e50;
    font-weight: 700;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    padding: 18px 15px;
}

.table tbody tr {
    transition: all 0.3s;
    border-bottom: 1px solid #f0f0f0;
}

.table tbody tr:hover {
    background: #f8f9fa;
    transform: scale(1.01);
}

.table tbody td {
    padding: 15px;
    vertical-align: middle;
}

/* Status Select */
.status-select {
    width: 160px;
    padding: 10px 14px;
    border-radius: 10px;
    border: 2px solid #e9ecef;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.3s;
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    cursor: pointer;
}

.status-select:focus {
    outline: none;
    border-color: var(--primary-color);
    background: white;
    box-shadow: 0 4px 12px rgba(15, 107, 62, 0.2);
}

.status-select:hover {
    border-color: var(--primary-color);
    background: white;
}

/* Rating Stars */
.rating-stars {
    display: inline-flex;
    gap: 6px;
}

.rating-stars i {
    cursor: pointer;
    color: #dee2e6;
    transition: all 0.2s;
    font-size: 19px;
}

.rating-stars i.active {
    color: #ffc107;
    text-shadow: 0 2px 4px rgba(255, 193, 7, 0.3);
    filter: drop-shadow(0 2px 3px rgba(255, 193, 7, 0.3));
}

.rating-stars i:hover {
    color: #ffc107;
    transform: scale(1.25) rotate(-10deg);
}
        
/* Empty State */
.empty-state {
    text-align: center;
    padding: 100px 40px;
    color: #adb5bd;
}

.empty-state i {
    font-size: 90px;
    margin-bottom: 30px;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    opacity: 0.6;
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 0.6; }
    50% { transform: scale(1.05); opacity: 0.8; }
}

.empty-state h4 {
    color: #2c3e50;
    font-weight: 700;
    font-size: 1.5rem;
    margin-bottom: 15px;
}

.empty-state p {
    color: #6c757d;
    font-size: 15px;
}

/* Loading Overlay */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    backdrop-filter: blur(5px);
}

.loading-overlay.show {
    display: flex;
}

.spinner {
    width: 60px;
    height: 60px;
    border: 6px solid rgba(255, 255, 255, 0.2);
    border-top: 6px solid var(--primary-color);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

/* Buttons */
.btn {
    padding: 12px 25px;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s;
    border: none;
    font-size: 14px;
}

.btn-success {
    background: var(--success-gradient);
    color: white;
    box-shadow: 0 4px 12px rgba(17, 153, 142, 0.3);
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(17, 153, 142, 0.4);
    color: white;
}

.btn-primary {
    background: var(--purple-gradient);
    color: white;
    box-shadow: 0 4px 12px rgba(15, 107, 62, 0.25);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(15, 107, 62, 0.35);
    color: white;
}

.btn-info {
    background: var(--blue-gradient);
    color: white;
    box-shadow: 0 4px 12px rgba(79, 172, 254, 0.3);
}

.btn-info:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(79, 172, 254, 0.4);
    color: white;
}

.btn-outline-secondary {
    background: transparent;
    color: #6c757d;
    border: 2px solid #6c757d;
}

.btn-outline-secondary:hover {
    background: #6c757d;
    color: white;
    transform: translateY(-2px);
}

/* Animations */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
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

.stat-box:nth-child(1) { animation-delay: 0.1s; }
.stat-box:nth-child(2) { animation-delay: 0.2s; }
.stat-box:nth-child(3) { animation-delay: 0.3s; }
.stat-box:nth-child(4) { animation-delay: 0.4s; }

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
        width: 100%;
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

    .page-header-actions {
        width: 100%;
        gap: 8px;
    }

    .page-header-actions .btn {
        width: 100%;
        text-align: center;
        padding: 10px 12px;
        font-size: 13px;
    }
    
    .stats-row {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .stat-box {
        min-height: 102px;
        padding: 10px 8px;
    }

    .stat-box .icon {
        width: 34px;
        height: 34px;
        font-size: 14px;
        margin-bottom: 6px;
    }

    .stat-box h3 {
        font-size: 20px;
    }

    .stat-box p {
        font-size: 11px;
    }
}

@media (max-width: 460px) {
    .page-header-actions {
        grid-template-columns: 1fr;
    }

    .event-banner {
        padding: 22px 16px;
    }

    .event-banner h4 {
        font-size: 1.2rem;
    }

    .event-banner p {
        font-size: 13px;
    }
}
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>
    
    <div class="wrapper">
        <!-- Sidebar -->
        <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-content">
                <div class="page-header-info">
                    <div class="d-flex align-items-center gap-2 mb-2 mb-md-0">
                        <button type="button" class="menu-toggle" id="menuToggle" aria-label="Toggle navigation menu">
                            <i class="fas fa-bars"></i>
                        </button>
                        <h1><i class="fas fa-check-square me-2"></i>Event Attendance</h1>
                    </div>
                    <p class="mb-0 text-muted">Track and manage event attendance</p>
                </div>
                <div class="page-header-actions">
                    <a href="view_event.php?id=<?php echo $event_id; ?>" class="btn btn-primary">
                        <i class="fas fa-eye me-2"></i>View Details
                    </a>
                    <a href="events.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Events
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
        
        <!-- Event Banner -->
        <div class="event-banner">
            <h4 class="mb-2"><?php echo htmlspecialchars($event['event_name']); ?></h4>
            <p class="mb-0">
                <i class="fas fa-calendar me-2"></i>
                <?php echo date('F d, Y', strtotime($event['event_date'])); ?> at 
                <?php $display_time = $event['start_time'] ?? $event['event_time'] ?? '00:00:00'; echo date('g:i A', strtotime($display_time)); ?>
                <?php if ($event['location']): ?>
                    <i class="fas fa-map-marker-alt ms-3 me-2"></i>
                    <?php echo htmlspecialchars($event['location']); ?>
                <?php endif; ?>
            </p>
        </div>
        
        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="icon" style="background: linear-gradient(135deg, #0F6B3E, #1B8A56);">
                    <i class="fas fa-users"></i>
                </div>
                <h3><?php echo $total_registered; ?></h3>
                <p>Total Registered</p>
            </div>
            
            <div class="stat-box">
                <div class="icon" style="background: linear-gradient(135deg, #11998e, #38ef7d);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3 id="attendedCount"><?php echo $attended_count; ?></h3>
                <p>Attended</p>
            </div>
            
            <div class="stat-box">
                <div class="icon" style="background: linear-gradient(135deg, #dc3545, #b02a37);">
                    <i class="fas fa-times-circle"></i>
                </div>
                <h3 id="absentCount"><?php echo $absent_count; ?></h3>
                <p>Absent</p>
            </div>
            
            <div class="stat-box">
                <div class="icon" style="background: linear-gradient(135deg, #0F6B3E, #1B8A56);">
                    <i class="fas fa-clock"></i>
                </div>
                <h3 id="pendingCount"><?php echo $pending_count; ?></h3>
                <p>Pending</p>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <button class="btn btn-success" onclick="markAllAttended()">
                <i class="fas fa-check-double me-2"></i>Mark All as Attended
            </button>
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print me-2"></i>Print Attendance Sheet
            </button>
        </div>
        
        <!-- Attendance Table -->
        <div class="attendance-table compact force-compact">
            <div class="table-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i> Registered Members</h5>
            </div>
            
            <?php if (empty($members)): ?>
                <div class="empty-state">
                    <i class="fas fa-user-slash"></i>
                    <h4>No Members Registered</h4>
                    <p>No members have registered for this event yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Member Details</th>
                                <th>Department</th>
                                <th>Attendance Status</th>
                                <th>Feedback Rating</th>
                                <th>Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $index => $member): ?>
                                <tr id="row-<?php echo $member['member_id']; ?>" style="animation: fadeInUp 0.5s ease backwards; animation-delay: <?php echo ($index * 0.05); ?>s;">
                                    <td style="padding: 18px 15px; font-weight: 600; color: #6c757d;"><?php echo $index + 1; ?></td>
                                    <td style="padding: 18px 15px;">
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <div style="width: 45px; height: 45px; background: linear-gradient(135deg, #0F6B3E, #1B8A56); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 16px; flex-shrink: 0;">
                                                <?php echo strtoupper(substr($member['full_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <strong style="color: #2c3e50; font-size: 15px;"><?php echo htmlspecialchars($member['full_name']); ?></strong><br>
                                                <small class="text-muted" style="font-size: 13px;"><?php echo htmlspecialchars($member['matric_no']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding: 18px 15px;">
                                        <strong style="color: #495057;"><?php echo htmlspecialchars($member['department']); ?></strong><br>
                                        <small class="text-muted" style="font-size: 13px;"><?php echo $member['level']; ?> Level</small>
                                    </td>
                                    <td style="padding: 18px 15px;">
                                        <select class="status-select" 
                                                onchange="updateStatus(<?php echo $member['member_id']; ?>, this.value)"
                                                data-member-id="<?php echo $member['member_id']; ?>">
                                            <option value="registered" <?php echo $member['attendance_status'] === 'registered' ? 'selected' : ''; ?>>📋 Registered</option>
                                            <option value="attended" <?php echo $member['attendance_status'] === 'attended' ? 'selected' : ''; ?>>✅ Attended</option>
                                            <option value="absent" <?php echo $member['attendance_status'] === 'absent' ? 'selected' : ''; ?>>❌ Absent</option>
                                            <option value="cancelled" <?php echo $member['attendance_status'] === 'cancelled' ? 'selected' : ''; ?>>🚫 Cancelled</option>
                                        </select>
                                    </td>
                                    <td style="padding: 18px 15px;">
                                        <div class="rating-stars" data-member-id="<?php echo $member['member_id']; ?>" data-rating="<?php echo $member['feedback_rating'] ?? 0; ?>">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?php echo $i <= ($member['feedback_rating'] ?? 0) ? 'active' : ''; ?>" 
                                                   onclick="updateRating(<?php echo $member['member_id']; ?>, <?php echo $i; ?>)"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                    <td style="padding: 18px 15px;">
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
    
    <script>
        const csrfToken = '<?php echo $csrf_token; ?>';
        const eventId = <?php echo $event_id; ?>;
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.querySelector('.sidebar');
        const sidebarOverlay = document.getElementById('sidebarBackdrop') || document.getElementById('sidebarOverlay');

        function closeSidebar() {
            if (!sidebar || !sidebarOverlay) return;
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
        
        function showLoading() {
            document.getElementById('loadingOverlay').classList.add('show');
        }
        
        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('show');
        }
        
        function showNotification(message, type = 'success') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 9998; min-width: 300px;';
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            
            setTimeout(() => {
                alertDiv.remove();
            }, 3000);
        }
        
        function updateStatus(memberId, status) {
            showLoading();
            
            fetch('event_attendance.php?id=' + eventId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_status&member_id=${memberId}&status=${status}&csrf_token=${csrfToken}`
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showNotification(data.message, 'success');
                    updateStatistics();
                } else {
                    showNotification(data.message, 'danger');
                }
            })
            .catch(error => {
                hideLoading();
                showNotification('Error updating status', 'danger');
            });
        }
        
        function updateRating(memberId, rating) {
            showLoading();
            
            fetch('event_attendance.php?id=' + eventId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_rating&member_id=${memberId}&rating=${rating}&csrf_token=${csrfToken}`
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    // Update star display
                    const stars = document.querySelector(`.rating-stars[data-member-id="${memberId}"]`);
                    stars.querySelectorAll('i').forEach((star, index) => {
                        if (index < rating) {
                            star.classList.add('active');
                        } else {
                            star.classList.remove('active');
                        }
                    });
                    showNotification(data.message, 'success');
                } else {
                    showNotification(data.message, 'danger');
                }
            })
            .catch(error => {
                hideLoading();
                showNotification('Error updating rating', 'danger');
            });
        }
        
        function markAllAttended() {
            // Use modal-based confirmation
            window.confirmModal('Mark all registered members as attended?').then(function(ok){
                if (!ok) return;

                showLoading();

                fetch('event_attendance.php?id=' + eventId, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=mark_all_attended&csrf_token=${csrfToken}`
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        // Update all dropdowns
                        document.querySelectorAll('.status-select').forEach(select => {
                            if (select.value === 'registered') {
                                select.value = 'attended';
                            }
                        });
                        updateStatistics();
                        showNotification(data.message, 'success');
                    } else {
                        showNotification(data.message, 'danger');
                    }
                })
                .catch(error => {
                    hideLoading();
                    showNotification('Error marking attendance', 'danger');
                });
            });
        }
        
        function updateStatistics() {
            const selects = document.querySelectorAll('.status-select');
            let attended = 0, absent = 0, pending = 0;
            
            selects.forEach(select => {
                const status = select.value;
                if (status === 'attended') attended++;
                else if (status === 'absent') absent++;
                else if (status === 'registered') pending++;
            });
            
            document.getElementById('attendedCount').textContent = attended;
            document.getElementById('absentCount').textContent = absent;
            document.getElementById('pendingCount').textContent = pending;
        }
        
        // Auto-dismiss alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                const closeBtn = alert.querySelector('.btn-close');
                if (closeBtn) closeBtn.click();
            });
        }, 5000);
    </script>

    <?php // Include admin footer which loads Bootstrap and confirmation modal ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>

