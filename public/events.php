<?php
/**
 * ============================================
 * NACOS DASHBOARD - PUBLIC events HUB
 * ============================================
 * Purpose: Display all upcoming and past events
 * Access: Public
 * Created: November 3, 2025
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Include required files
require_once __DIR__ . '/../config/config.php';
require_once '../includes/auth.php';

// Initialize database
$db = getDB();

// Check if member is logged in
$is_logged_in = isset($_SESSION['member_id']);
$member_id = $is_logged_in ? $_SESSION['member_id'] : null;

// Get member's registered events if logged in
$registered_events = [];
if ($is_logged_in) {
    $registered_events = $db->fetchAll(
        "SELECT event_id, attendance_status FROM member_events WHERE member_id = ?",
        [$member_id]
    );
    // Convert to associative array for quick lookup
    $registered_lookup = [];
    foreach ($registered_events as $reg) {
        $registered_lookup[$reg['event_id']] = $reg['attendance_status'];
    }
}

// --- Filtering and Searching Logic ---
$search_term = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$filter_type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : 'all';
$filter_status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'all';

// Base query
$query = "SELECT * FROM events";
$conditions = [];
$params = [];

// Add search condition
if (!empty($search_term)) {
    $conditions[] = "(event_name LIKE :search OR description LIKE :search)";
    $params[':search'] = '%' . $search_term . '%';
}

// Add type condition
if ($filter_type !== 'all') {
    $conditions[] = "event_type = :type";
    $params[':type'] = $filter_type;
}

// Add status condition
if ($filter_status === 'upcoming') {
    $conditions[] = "event_date >= CURDATE()";
} elseif ($filter_status === 'past') {
    $conditions[] = "event_date < CURDATE()";
}

// Append conditions to query
if (!empty($conditions)) {
    $query .= " WHERE " . implode(' AND ', $conditions);
}

// Add ordering
$query .= " ORDER BY event_date " . ($filter_status === 'upcoming' ? 'ASC' : 'DESC');

// Fetch events
$events = $db->fetchAll($query, $params);

// Get all unique event types for the filter dropdown
$event_types = $db->fetchAll("SELECT DISTINCT event_type FROM events ORDER BY event_type ASC");
// Global event statistics (total / upcoming / past) to surface admin counts to public users
$global_stats = $db->fetchOne(
    "SELECT
        COUNT(*) as total_events,
        SUM(CASE WHEN event_date >= CURDATE() THEN 1 ELSE 0 END) as upcoming,
        SUM(CASE WHEN event_date < CURDATE() THEN 1 ELSE 0 END) as past
     FROM events"
);

?>
<?php if ($is_logged_in): ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>NACOS — Events</title>
        <link rel="icon" href="../assets/images/favicon.png">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            :root{
                --nacos-green: #008000;
                --nacos-white: #ffffff;
                --nacos-bg-light: #f4f7ff;
                --nacos-green-dark: #006600;
                --nacos-green-fade: rgba(0,128,0,0.08);
                --nacos-green-border: #008000;
                --nacos-shadow: 0 8px 32px rgba(0,128,0,0.08);
            }
            body{
                background-color:var(--nacos-bg-light);
                font-family:'Poppins',sans-serif;
                margin:0;
                padding:28px;
                overflow-x: hidden;
            }

            /* Mobile Hamburger Menu Styles */
            .mobile-hamburger {
                display: none;
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 100;
                background: none;
                border: none;
                padding: 0;
                cursor: pointer;
                width: 50px;
                height: 50px;
                align-items: center;
                justify-content: center;
                border-radius: 8px;
                transition: all 0.3s ease;
                opacity: 1;
            }

            /* Hide hamburger while scrolling */
            .mobile-hamburger.scrolling {
                opacity: 0;
                pointer-events: none;
            }

            .mobile-hamburger:hover {
                background-color: rgba(0, 128, 0, 0.1);
            }

            .hamburger-icon {
                width: 28px;
                height: 24px;
                position: relative;
                display: flex;
                flex-direction: column;
                justify-content: space-around;
            }

            .hamburger-line {
                width: 100%;
                height: 3px;
                background-color: var(--nacos-green);
                border-radius: 2px;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                transform-origin: center;
            }

            /* Hamburger to X animation */
            .mobile-hamburger.active .hamburger-line:nth-child(1) {
                transform: translateY(10.5px) rotate(45deg);
            }

            .mobile-hamburger.active .hamburger-line:nth-child(2) {
                opacity: 0;
                transform: translateX(-10px);
            }

            .mobile-hamburger.active .hamburger-line:nth-child(3) {
                transform: translateY(-10.5px) rotate(-45deg);
            }

            /* Mobile Sidebar Styles */
            .mobile-sidebar {
                position: fixed;
                left: -100%;
                top: 0;
                width: 280px;
                height: 100vh;
                background: #0f1724;
                color: #fff;
                z-index: 99;
                padding: 0;
                overflow-y: auto;
                transition: left 0.35s cubic-bezier(0.4, 0, 0.2, 1);
                box-shadow: 2px 0 15px rgba(0, 0, 0, 0.3);
            }

            /* Sidebar open state - slides in from left */
            .mobile-sidebar.open {
                left: 0;
            }

            .mobile-sidebar-content {
                padding: 30px 0;
            }

            .mobile-sidebar-brand {
                display: flex;
                align-items: center;
                gap: 14px;
                padding: 24px 20px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                margin-bottom: 0;
            }

            .mobile-sidebar-brand img {
                height: 40px;
                width: 40px;
                border-radius: 8px;
                object-fit: cover;
            }

            .mobile-sidebar-brand strong {
                font-weight: 700;
                color: #fff;
                font-size: 14px;
                letter-spacing: 0.3px;
            }

            .mobile-sidebar-menu {
                margin-top: 0;
            }

            .mobile-sidebar-menu a {
                display: flex;
                align-items: center;
                gap: 16px;
                padding: 14px 20px;
                border-radius: 0;
                color: rgba(255, 255, 255, 0.85);
                text-decoration: none;
                margin-bottom: 0;
                transition: all 0.25s ease;
                font-size: 15px;
                font-weight: 500;
                position: relative;
            }

            .mobile-sidebar-menu a:hover {
                background: rgba(0, 128, 0, 0.12);
                color: var(--nacos-green);
                transform: none;
                padding-left: 24px;
            }

            .mobile-sidebar-menu a.active {
                background: rgba(0, 128, 0, 0.18);
                color: var(--nacos-green);
                font-weight: 600;
                border-left: 3px solid var(--nacos-green);
                padding-left: 17px;
            }

            .mobile-sidebar-menu i {
                width: 20px;
                text-align: center;
                font-size: 16px;
            }

            /* Mobile Overlay */
            .mobile-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 98;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.3s ease;
            }

            .mobile-overlay.visible {
                opacity: 1;
                pointer-events: auto;
            }

            .shell{
                max-width:1200px;
                margin:0 auto;
                background:var(--nacos-white);
                border-radius:28px;
                overflow:hidden;
                box-shadow:0 20px 60px rgba(13,22,39,0.12);
                display:flex;
            }
            .dash-sidebar{
                width:64px;
                background:#0b1220;
                color:var(--nacos-white);
                padding:18px 10px;
                transition:width .26s ease,padding .2s ease;
                overflow:visible;
            }
            .dash-sidebar.expanded{width:220px;padding:26px 18px}
            .dash-brand img{height:32px}
            .dash-brand strong{display:none}
            .dash-sidebar.expanded .dash-brand strong{display:inline-block;color:var(--nacos-white);margin-left:8px}
            .dash-menu a{
                display:flex;align-items:center;gap:12px;padding:10px;border-radius:10px;
                color:rgba(255,255,255,0.9);text-decoration:none;margin-bottom:8px;
                transition:background .18s, color .18s;
            }
            .dash-menu a .link-text{display:none}
            .dash-sidebar.expanded .dash-menu a .link-text{display:inline-block}
            .dash-menu a.active{
                background:rgba(0,128,0,0.12);
                color:var(--nacos-green);
                font-weight:600;
            }
            .dash-menu a:hover{
                background:var(--nacos-green-fade);
                color:var(--nacos-green);
            }
            .dash-main{flex:1;display:flex;padding:28px;gap:18px}
            .content{flex:1}
            .panel{
                background:var(--nacos-white);
                padding:18px 16px;
                border-radius:16px;
                box-shadow:var(--nacos-shadow);
                margin-top:18px;
                border:2px solid var(--nacos-green-border);
                position:relative;
                transition:box-shadow .18s, border-color .18s;
            }
            .panel h6 {
                color:var(--nacos-green);
                font-weight:700;
                margin-bottom:12px;
            }
            .panel form .form-label {
                color:var(--nacos-green-dark);
                font-weight:500;
            }
            .event-card{
                background:var(--nacos-white);
                border-radius:14px;
                padding:18px 14px 16px 14px;
                height:100%;
                display:flex;
                flex-direction:column;
                box-shadow:0 6px 18px rgba(0,128,0,0.07);
                border:2px solid var(--nacos-green-border);
                transition:box-shadow .18s, border-color .18s, transform .18s;
                position:relative;
            }
            .event-card:hover{
                box-shadow:0 12px 32px rgba(0,128,0,0.13);
                border-color:var(--nacos-green-dark);
                transform:translateY(-2px) scale(1.012);
                z-index:2;
            }
            .event-card-header{
                display:flex;justify-content:space-between;font-size:13px;color:#6b7280;margin-bottom:8px
            }
            .event-title{
                font-weight:700;
                color:var(--nacos-green-dark);
                margin-bottom:6px;
            }
            .btn-primary{
                background:var(--nacos-green);
                border-color:var(--nacos-green);
                color:var(--nacos-white);
                font-weight:600;
                letter-spacing:0.5px;
                box-shadow:0 2px 8px rgba(0,128,0,0.08);
                transition:background .18s, border-color .18s;
            }
            .btn-primary:hover{
                background:var(--nacos-green-dark);
                border-color:var(--nacos-green-dark);
            }
            .btn-success{
                background:var(--nacos-green-dark);
                border-color:var(--nacos-green-dark);
                color:var(--nacos-white);
            }
            .btn-danger{
                background:#e3342f;
                border-color:#e3342f;
                color:#fff;
            }
            .btn-secondary{
                background:#64748b;
                border-color:#64748b;
                color:#fff;
            }
            .event-card .btn{
                margin-top:10px;
                font-size:15px;
                border-radius:8px;
            }
            .event-card .btn i{
                margin-right:6px;
            }
            .event-card p{
                margin-bottom:8px;
            }
            .event-card .event-description{
                color:#374151;
                font-size:14px;
                margin-bottom:0;
            }
            .event-card .fa-map-marker-alt{
                color:var(--nacos-green-dark);
                margin-right:4px;
            }
            .event-card .fa-calendar-times,
            .event-card .fa-ban,
            .event-card .fa-check-circle{
                color:#e3342f;
            }
            .event-card .fa-user-plus{
                color:var(--nacos-green);
            }
            .event-card .fa-user-plus.me-2{
                margin-right:8px;
            }
            .event-card .fa-check-circle{
                color:var(--nacos-green-dark);
            }
            .event-card .fa-calendar-times{
                color:#e3342f;
            }
            .event-card .fa-ban{
                color:#e3342f;
            }
            .event-card .fa-user-plus{
                color:var(--nacos-green);
            }
            .event-card .fa-map-marker-alt{
                color:var(--nacos-green-dark);
            }
            .event-card .fa-search{
                color:var(--nacos-green);
            }
            .event-card .fa-calendar{
                color:var(--nacos-green);
            }
            .event-card .fa-briefcase{
                color:var(--nacos-green);
            }
            .event-card .fa-book{
                color:var(--nacos-green);
            }
            .event-card .fa-user{
                color:var(--nacos-green);
            }
            /* Responsive tweaks */
            @media (max-width:1000px){
                .shell{flex-direction:column}
                aside[style]{width:100%!important;max-width:100%!important;margin-top:18px}
            }
            @media (max-width:700px){
                .dash-main{flex-direction:column;padding:14px}
                .content{padding:0}
                .panel{padding:12px 6px}
                .event-card{padding:12px 6px}
            }

            /* Responsive Design */
            @media (max-width: 768px) {
                .mobile-hamburger {
                    display: flex;
                }

                body {
                    padding: 16px;
                }

                .shell {
                    border-radius: 16px;
                }

                .dash-sidebar {
                    display: none;
                }
            }

            /* Hide desktop sidebar on very small screens */
            @media (max-width: 600px) {
                .dash-sidebar {
                    display: none !important;
                }
            }
        </style>
    </head>
    <body>
        <?php
            // reuse some helper variables from dashboard
            $member = getCurrentMember();
            $member_details = $db->fetchOne("SELECT * FROM members WHERE member_id = :id", [':id' => $member_id]);
            $avatarUrl = !empty($member_details['profile_picture']) ? '../' . $member_details['profile_picture'] : '../assets/images/default_avatar.png';
            $count_events = count($events);
        ?>

        <!-- Mobile Hamburger Menu Button -->
        <button class="mobile-hamburger" id="mobileHamburger" aria-label="Toggle menu">
            <div class="hamburger-icon">
                <div class="hamburger-line"></div>
                <div class="hamburger-line"></div>
                <div class="hamburger-line"></div>
            </div>
        </button>

        <!-- Mobile Overlay -->
        <div class="mobile-overlay" id="mobileOverlay"></div>

        <!-- Mobile Sidebar -->
        <nav class="mobile-sidebar" id="mobileSidebar">
            <div class="mobile-sidebar-brand">
                <img src="../assets/images/nacos_logo.jpg" alt="logo">
                <strong>NACOS, AU Chapter.</strong>
            </div>
            <div class="mobile-sidebar-content">
                <div class="mobile-sidebar-menu">
                    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="events.php" class="active"><i class="fas fa-calendar"></i> Events</a>
                    <a href="projects.php"><i class="fas fa-briefcase"></i> Projects</a>
                    <a href="resources.php"><i class="fas fa-book"></i> Resources</a>
                    <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                </div>
            </div>
        </nav>

        <div class="shell">
            <aside class="dash-sidebar">
                <div class="dash-brand"><img src="../assets/images/nacos_logo.jpg" alt="logo" style="height:34px;border-radius:6px"><strong> NACOS, AU Chapter.</strong></div>
                <div class="dash-menu">
                    <a href="dashboard.php"><i class="fas fa-home"></i><span class="link-text"> Dashboard</span></a>
                    <a href="events.php" class="active"><i class="fas fa-calendar"></i><span class="link-text"> Events</span></a>
                    <a href="projects.php"><i class="fas fa-briefcase"></i><span class="link-text"> Projects</span></a>
                    <a href="resources.php"><i class="fas fa-book"></i><span class="link-text"> Resources</span></a>
                    <a href="profile.php"><i class="fas fa-user"></i><span class="link-text"> Profile</span></a>
                </div>

                <div style="position:absolute;left:18px;right:18px;bottom:20px">
                    <div style="display:flex;align-items:center;gap:12px">
                        <img src="<?php echo htmlspecialchars($avatarUrl); ?>" style="width:44px;height:44px;border-radius:8px;object-fit:cover">
                        <div>
                            <div style="font-weight:700"><?php echo htmlspecialchars($member_details['full_name']); ?></div>
                            <div style="font-size:12px;color:#94a3b8"><?php echo htmlspecialchars($member_details['matric_no']); ?></div>
                        </div>
                    </div>
                </div>
            </aside>

            <main class="dash-main">
                <div class="content">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px">
                        <div>
                            <h2 style="margin:0">Events</h2>
                            <p style="margin:6px 0 0 0;color:#6b7280">Browse upcoming and past events. You are seeing <?php echo $count_events; ?> results.</p>
                        </div>
                        <div>
                            <form action="events.php" method="GET" class="d-flex" style="gap:8px">
                                <input type="text" name="search" class="form-control" placeholder="Search events..." value="<?php echo htmlspecialchars($search_term); ?>">
                                <button class="btn btn-primary">Search</button>
                            </form>
                        </div>
                    </div>

                    <div class="panel">
                        <?php if (empty($events)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-times" style="font-size:48px;color:#cbd5e1"></i>
                                <h4 class="mt-3">No Events Found</h4>
                                <p class="text-muted">Try adjusting your filters or check back later.</p>
                            </div>
                        <?php else: ?>
                            <div class="row" id="events-list">
                                <?php $eventCount = 0; foreach ($events as $event): $eventCount++; ?>
                                    <div class="col-md-6 col-lg-4 mb-4 event-box<?php if ($eventCount > 6) echo ' d-none'; ?>">
                                        <div class="event-card">
                                            <div class="event-card-header">
                                                <span><?php echo ucfirst($event['event_type']); ?></span>
                                                <span><?php echo date('M d, Y', strtotime($event['event_date'])); ?></span>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h5 class="event-title"><?php echo htmlspecialchars($event['event_name']); ?></h5>
                                                <p style="color:#6b7280;font-size:13px"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['location'] ?? 'TBD'); ?></p>
                                                <p style="color:#6b7280;font-size:13px">
                                                    <i class="fas fa-clock"></i>
                                                    <?php
                                                        $display_start = !empty($event['start_time']) ? $event['start_time'] : ($event['event_time'] ?? null);
                                                        $display_end = !empty($event['end_time']) ? $event['end_time'] : null;
                                                        if ($display_start) {
                                                            echo date('g:i A', strtotime($display_start));
                                                            if ($display_end) {
                                                                echo ' - ' . date('g:i A', strtotime($display_end));
                                                            }
                                                        } else {
                                                            echo 'Time TBA';
                                                        }
                                                    ?>
                                                </p>
                                                <p style="font-size:14px;color:#374151">
                                                    <?php $desc = !empty($event['summary']) ? $event['summary'] : $event['full_description']; echo substr(htmlspecialchars($desc),0,140) . (strlen($desc) > 140 ? '...' : ''); ?>
                                                </p>
                                            </div>

                                            <div>
                                                <?php
                                                    // Get current datetime
                                                    $current_datetime = new DateTime('now');
                                                    
                                                    // Event start datetime (use start_time if available, fallback to event_time for backwards compatibility)
                                                    $start_time = !empty($event['start_time']) ? $event['start_time'] : ($event['event_time'] ?? '00:00:00');
                                                    $event_start = new DateTime($event['event_date'] . ' ' . $start_time);
                                                    
                                                    // Event end datetime (use end_time if available, otherwise add 2 hours to start)
                                                    $end_time = !empty($event['end_time']) ? $event['end_time'] : date('H:i:s', strtotime($start_time . ' +2 hours'));
                                                    $event_end = new DateTime($event['event_date'] . ' ' . $end_time);
                                                    if ($event_end < $event_start) {
                                                        $event_end->modify('+1 day');
                                                    }
                                                    
                                                    // Determine event status
                                                    $is_ongoing = ($current_datetime >= $event_start && $current_datetime <= $event_end);
                                                    $is_ended = ($current_datetime > $event_end);
                                                    $is_upcoming = ($current_datetime < $event_start);
                                                    
                                                    $is_cancelled = ($event['status'] === 'cancelled');
                                                    $is_registered = $is_logged_in && isset($registered_lookup[$event['event_id']]);
                                                    $attendance_status = $is_registered ? $registered_lookup[$event['event_id']] : null;
                                                ?>
                                                <?php if ($is_cancelled): ?>
                                                    <button class="btn btn-danger w-100" disabled><i class="fas fa-ban me-2"></i>Cancelled</button>
                                                <?php elseif ($is_ended): ?>
                                                    <button class="btn btn-secondary w-100" disabled><i class="fas fa-flag-checkered me-2"></i>Event Ended</button>
                                                <?php elseif ($is_ongoing): ?>
                                                    <button class="btn btn-warning w-100" disabled><i class="fas fa-circle-notch fa-spin me-2"></i>Ongoing</button>
                                                <?php elseif ($is_registered): ?>
                                                    <?php if ($attendance_status === 'cancelled'): ?>
                                                        <a href="register_event.php?event_id=<?php echo $event['event_id']; ?>" class="btn btn-outline-primary w-100">Register Again</a>
                                                    <?php else: ?>
                                                        <button class="btn btn-success w-100" disabled><i class="fas fa-check-circle me-2"></i>Registered</button>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <a href="register_event.php?event_id=<?php echo $event['event_id']; ?>" class="btn btn-primary w-100"><i class="fas fa-calendar-plus me-2"></i>Register Now</a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($events) > 6): ?>
                                <div class="text-center mt-3">
                                    <button id="viewMoreEventsBtn" class="btn btn-primary">View More</button>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <aside style="width:320px">
                    <!--<div class="panel">
                        <h6>Your profile</h6>
                        <div style="display:flex;align-items:center;gap:12px">
                            <img src="<?php echo htmlspecialchars($avatarUrl); ?>" style="width:56px;height:56px;border-radius:8px;object-fit:cover">
                            <div>
                                <div style="font-weight:700"><?php echo htmlspecialchars($member_details['full_name']); ?></div>
                                <div style="font-size:13px;color:#6b7280"><?php echo htmlspecialchars($member_details['email']); ?></div>
                            </div>
                        </div>
                    </div>-->

                    <div class="panel">
                        <h6>Filters</h6>
                        <form action="events.php" method="GET">
                            <div class="mb-2">
                                <label class="form-label">Type</label>
                                <select name="type" class="form-select">
                                    <option value="all">All</option>
                                    <?php foreach ($event_types as $type): ?>
                                        <option value="<?php echo htmlspecialchars($type['event_type']); ?>" <?php echo ($filter_type === $type['event_type']) ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($type['event_type'])); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="upcoming" <?php echo ($filter_status === 'upcoming') ? 'selected' : ''; ?>>Upcoming</option>
                                    <option value="past" <?php echo ($filter_status === 'past') ? 'selected' : ''; ?>>Past</option>
                                    <option value="all" <?php echo ($filter_status === 'all') ? 'selected' : ''; ?>>All</option>
                                </select>
                            </div>
                            <button class="btn btn-primary w-100">Apply</button>
                        </form>
                    </div>
                </aside>
            </main>
        </div>

        <!-- Coming Soon Popup -->
        <div id="comingSoonOverlay" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:10000;align-items:center;justify-content:center;">
            <div style="background:#fff;border-radius:12px;padding:40px;max-width:400px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                <i class="fas fa-rocket" style="font-size:48px;color:var(--nacos-green);margin-bottom:16px;display:block;"></i>
                <h3 style="color:var(--nacos-green);margin:16px 0;font-weight:700;">Coming Soon</h3>
                <p style="color:#666;margin:0 0 24px 0;font-size:15px;">This feature is under development and will be available soon!</p>
                <button id="comingSoonCloseBtn" style="background:var(--nacos-green);color:#fff;border:none;padding:10px 24px;border-radius:6px;cursor:pointer;font-weight:600;">Got it</button>
            </div>
        </div>

        <style>
            /* Sidebar (dark) */
            .dash-sidebar{
                width:220px;
                background:#0f1724;
                color:#fff;
                padding:26px 18px;
                position:relative;
                transition:width .26s cubic-bezier(.4,0,.2,1), padding .26s cubic-bezier(.4,0,.2,1);
                overflow:visible;
            }
            .shell.collapsed .dash-sidebar {
                width:64px !important;
                padding:18px 10px !important;
            }
            .dash-brand{display:flex;align-items:center;gap:12px;margin-bottom:18px}
            .dash-brand img{height:34px;border-radius:6px}
            .dash-brand strong{display:inline-block;transition:opacity .2s}
            .shell.collapsed .dash-brand strong{display:none}
            .dash-menu{margin-top:18px}
            .dash-menu a{display:flex;align-items:center;gap:12px;padding:10px;border-radius:10px;color:rgba(255,255,255,0.9);text-decoration:none;margin-bottom:8px;transition:background .2s,color .2s}
            .dash-menu a.active{background:rgba(0,128,0,0.12);color:var(--nacos-green);font-weight:600}
            .dash-menu a .link-text{display:inline-block;transition:opacity .2s}
            .shell.collapsed .dash-menu a .link-text{display:none}
            .sidebar-bottom{position:absolute;bottom:20px;left:18px;right:18px}
            .shell.collapsed .sidebar-bottom{left:10px;right:10px}
        </style>

        <script>
            // Sidebar collapse/expand and inactivity logic
            (function(){
                const sidebar = document.querySelector('.dash-sidebar');
                const shell = document.querySelector('.shell');
                if (!sidebar || !shell) return;
                // Collapse by default
                shell.classList.add('collapsed');
                const INACTIVITY_TIMEOUT = 10000;
                let inactivityTimer = null;
                function scheduleAutoCollapse(){
                    clearTimeout(inactivityTimer);
                    inactivityTimer = setTimeout(() => {
                        shell.classList.add('collapsed');
                    }, INACTIVITY_TIMEOUT);
                }
                function expandSidebar(){
                    shell.classList.remove('collapsed');
                    scheduleAutoCollapse();
                }
                function collapseSidebar(){
                    shell.classList.add('collapsed');
                }
                sidebar.addEventListener('mouseenter', function(){
                    expandSidebar();
                    clearTimeout(inactivityTimer);
                });
                sidebar.addEventListener('mouseleave', function(){
                    scheduleAutoCollapse();
                });
                sidebar.addEventListener('focusin', function(){
                    expandSidebar();
                    clearTimeout(inactivityTimer);
                });
                sidebar.addEventListener('focusout', function(){
                    scheduleAutoCollapse();
                });
                // User activity expands sidebar
                const activityEvents = ['mousemove','mousedown','keydown','touchstart','scroll'];
                function handleUserActivity(){
                    expandSidebar();
                }
                activityEvents.forEach(evt => {
                    window.addEventListener(evt, handleUserActivity, { passive: true });
                });
                // Start the timer on page load
                scheduleAutoCollapse();
                window.addEventListener('unload', function(){
                    clearTimeout(inactivityTimer);
                    activityEvents.forEach(evt => window.removeEventListener(evt, handleUserActivity));
                });
            })();
            // View More for events
            document.addEventListener('DOMContentLoaded', function() {
                var viewMoreEventsBtn = document.getElementById('viewMoreEventsBtn');
                if (viewMoreEventsBtn) {
                    viewMoreEventsBtn.addEventListener('click', function() {
                        var hiddenBoxes = document.querySelectorAll('.event-box.d-none');
                        hiddenBoxes.forEach(function(box) { box.classList.remove('d-none'); });
                        viewMoreEventsBtn.style.display = 'none';
                    });
                }
            });

            // Mobile Hamburger Menu & Sidebar Controller
            document.addEventListener('DOMContentLoaded', function() {
                const hamburger = document.getElementById('mobileHamburger');
                const sidebar = document.getElementById('mobileSidebar');
                const overlay = document.getElementById('mobileOverlay');
                const sidebarLinks = document.querySelectorAll('.mobile-sidebar-menu a');
                
                // Check if elements exist
                if (!hamburger || !sidebar || !overlay) {
                    console.error('Mobile menu elements not found');
                    return;
                }

                // Scroll handling - hide hamburger while scrolling, show when stopped
                let scrollTimeout;
                let isScrolling = false;

                window.addEventListener('scroll', function() {
                    hamburger.classList.add('scrolling');
                    isScrolling = true;

                    // Clear previous timeout
                    clearTimeout(scrollTimeout);

                    // Set timeout to remove scrolling class after 1.5 seconds of no scrolling
                    scrollTimeout = setTimeout(function() {
                        hamburger.classList.remove('scrolling');
                        isScrolling = false;
                    }, 1500);
                }, { passive: true });

                // Toggle sidebar open/close
                function toggleSidebar() {
                    hamburger.classList.toggle('active');
                    sidebar.classList.toggle('open');
                    overlay.classList.toggle('visible');
                    // Remove scrolling class when opening sidebar
                    hamburger.classList.remove('scrolling');
                }

                // Close sidebar
                function closeSidebar() {
                    hamburger.classList.remove('active');
                    sidebar.classList.remove('open');
                    overlay.classList.remove('visible');
                }

                // Event listeners
                hamburger.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleSidebar();
                });
                
                overlay.addEventListener('click', closeSidebar);

                // Close sidebar when a link is clicked
                sidebarLinks.forEach(link => {
                    link.addEventListener('click', function(e) {
                        closeSidebar();
                    });
                });

                // Close sidebar on Escape key
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                        closeSidebar();
                    }
                });

                // Coming Soon - Resources and Projects links
                const comingSoonLinks = document.querySelectorAll('a[href="resources.php"], a[href="projects.php"]');
                const comingSoonOverlay = document.getElementById('comingSoonOverlay');
                const comingSoonCloseBtn = document.getElementById('comingSoonCloseBtn');

                function showComingSoon(e) {
                    e.preventDefault();
                    comingSoonOverlay.style.display = 'flex';
                }

                function hideComingSoon() {
                    comingSoonOverlay.style.display = 'none';
                }

                comingSoonLinks.forEach(link => {
                    link.addEventListener('click', showComingSoon);
                });

                comingSoonCloseBtn.addEventListener('click', hideComingSoon);
                comingSoonOverlay.addEventListener('click', function(e) {
                    if (e.target === comingSoonOverlay) hideComingSoon();
                });
            });
        </script>
    </body>
    </html>
<?php else: ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Events - NACOS</title>
        <link rel="icon" href="../assets/images/favicon.png" type="image/png">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="../assets/css/public.css">
    </head>
    <body>
        <?php include __DIR__ . '/../includes/public_navbar.php'; ?>
        <section class="page-header" style="background:var(--gradient);color:#fff;padding:80px 0;text-align:center">
            <div class="container"><h1>Our Events</h1><p class="lead">Discover workshops, seminars, competitions, and social gatherings.</p></div>
        </section>

        <main class="container py-5">
            <?php if (!empty($global_stats)): ?>
            <div class="row mb-4">
                <div class="col-md-4"><div class="card text-center shadow-sm"><div class="card-body"><h5 class="card-title">Total Events</h5><p class="display-6 mb-0"><?php echo intval($global_stats['total_events']); ?></p></div></div></div>
                <div class="col-md-4"><div class="card text-center shadow-sm"><div class="card-body"><h5 class="card-title">Upcoming</h5><p class="display-6 mb-0"><?php echo intval($global_stats['upcoming']); ?></p></div></div></div>
                <div class="col-md-4"><div class="card text-center shadow-sm"><div class="card-body"><h5 class="card-title">Past</h5><p class="display-6 mb-0"><?php echo intval($global_stats['past']); ?></p></div></div></div>
            </div>
            <?php endif; ?>

            <div class="filters-bar mb-4" style="background:var(--light-gray);padding:20px;border-radius:10px">
                <form action="events.php" method="GET">
                    <div class="row g-3 align-items-center">
                        <div class="col-lg-5"><input type="text" name="search" class="form-control" placeholder="Search by name or keyword..." value="<?php echo htmlspecialchars($search_term); ?>"></div>
                        <div class="col-lg-2"><select name="type" class="form-select"><option value="all">All Types</option><?php foreach ($event_types as $type): ?><option value="<?php echo $type['event_type']; ?>" <?php echo ($filter_type === $type['event_type']) ? 'selected' : ''; ?>><?php echo ucfirst($type['event_type']); ?></option><?php endforeach; ?></select></div>
                        <div class="col-lg-2"><select name="status" class="form-select"><option value="upcoming" <?php echo ($filter_status === 'upcoming') ? 'selected' : ''; ?>>Upcoming</option><option value="past" <?php echo ($filter_status === 'past') ? 'selected' : ''; ?>>Past</option><option value="all" <?php echo ($filter_status === 'all') ? 'selected' : ''; ?>>All</option></select></div>
                        <div class="col-lg-3"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Filter Events</button></div>
                    </div>
                </form>
            </div>

            <?php if (empty($events)): ?>
                <div class="no-events text-center py-5" style="background:var(--light-gray);border-radius:10px">
                    <i class="fas fa-calendar-times" style="font-size:48px;color:#ccc"></i>
                    <h2 class="mt-3">No Events Found</h2>
                    <p class="lead">Your search or filter criteria did not match any events. Try adjusting your search.</p>
                    <a href="events.php" class="btn btn-primary mt-3">Clear Filters</a>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($events as $event): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="event-card" style="background:#fff;border-radius:10px;padding:14px;box-shadow:0 6px 18px rgba(0,0,0,0.04)">
                                <div class="event-card-header" style="display:flex;justify-content:space-between;font-size:13px;color:#6b7280;margin-bottom:8px"><span><?php echo ucfirst($event['event_type']); ?></span><span><?php echo date('M d, Y', strtotime($event['event_date'])); ?></span></div>
                                <h5 class="event-title"><?php echo htmlspecialchars($event['event_name']); ?></h5>
                                <p style="color:#6b7280;font-size:13px"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['location'] ?? 'TBD'); ?></p>
                                <p style="color:#6b7280;font-size:13px">
                                    <i class="fas fa-clock"></i>
                                    <?php
                                        $display_start = !empty($event['start_time']) ? $event['start_time'] : ($event['event_time'] ?? null);
                                        $display_end = !empty($event['end_time']) ? $event['end_time'] : null;
                                        if ($display_start) {
                                            echo date('g:i A', strtotime($display_start));
                                            if ($display_end) {
                                                echo ' - ' . date('g:i A', strtotime($display_end));
                                            }
                                        } else {
                                            echo 'Time TBA';
                                        }
                                    ?>
                                </p>
                                <p class="event-description" style="font-size:14px;color:#374151">
                                    <?php $desc = !empty($event['summary']) ? $event['summary'] : $event['full_description']; echo substr(htmlspecialchars($desc), 0, 100) . '...'; ?>
                                </p>
                                <?php
                                    $current_datetime = new DateTime('now');
                                    $start_time = !empty($event['start_time']) ? $event['start_time'] : ($event['event_time'] ?? '00:00:00');
                                    $event_start = new DateTime($event['event_date'] . ' ' . $start_time);
                                    $end_time = !empty($event['end_time']) ? $event['end_time'] : date('H:i:s', strtotime($start_time . ' +2 hours'));
                                    $event_end = new DateTime($event['event_date'] . ' ' . $end_time);
                                    if ($event_end < $event_start) {
                                        $event_end->modify('+1 day');
                                    }

                                    $is_ongoing = ($current_datetime >= $event_start && $current_datetime <= $event_end);
                                    $is_ended = ($current_datetime > $event_end);
                                    $is_cancelled = ($event['status'] === 'cancelled');
                                ?>
                                <?php if ($is_cancelled): ?>
                                    <button class="btn btn-danger w-100 mt-3" disabled><i class="fas fa-ban me-2"></i>Event Cancelled</button>
                                <?php elseif ($is_ended): ?>
                                    <button class="btn btn-secondary w-100 mt-3" disabled><i class="fas fa-check-circle me-2"></i>Event Concluded</button>
                                <?php elseif ($is_ongoing): ?>
                                    <button class="btn btn-warning w-100 mt-3" disabled><i class="fas fa-circle-notch fa-spin me-2"></i>Ongoing</button>
                                <?php else: ?>
                                    <a href="<?php echo $is_logged_in ? 'register_event.php?event_id='.$event['event_id'] : 'login.php?redirect=register_event.php?event_id='.$event['event_id']; ?>" class="btn btn-primary w-100 mt-3"><i class="fas fa-user-plus me-2"></i><?php echo $is_logged_in ? 'Register Now' : 'Login to Register'; ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>

        <?php include __DIR__ . '/../includes/public_footer.php'; ?>
    </body>
    </html>
<?php endif; ?>
