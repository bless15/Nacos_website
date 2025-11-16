<?php
/**
 * ============================================
 * NACOS DASHBOARD - PUBLIC EVENTS HUB
 * ============================================
 * Purpose: Display all upcoming and past events
 * Access: Public
 * Created: November 3, 2025
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Include required files
require_once '../config/database.php';
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
        "SELECT event_id, attendance_status FROM MEMBER_EVENTS WHERE member_id = ?",
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
$query = "SELECT * FROM EVENTS";
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
$event_types = $db->fetchAll("SELECT DISTINCT event_type FROM EVENTS ORDER BY event_type ASC");
// Global event statistics (total / upcoming / past) to surface admin counts to public users
$global_stats = $db->fetchOne(
    "SELECT
        COUNT(*) as total_events,
        SUM(CASE WHEN event_date >= CURDATE() THEN 1 ELSE 0 END) as upcoming,
        SUM(CASE WHEN event_date < CURDATE() THEN 1 ELSE 0 END) as past
     FROM EVENTS"
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - NACOS</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/public.css">
    <style>
        .page-header {
            background: var(--gradient);
            color: #fff;
            padding: 80px 0;
            text-align: center;
        }
        .page-header h1 {
            color: #fff;
            font-size: 3rem;
        }
        .filters-bar {
            background: var(--light-gray);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 40px;
        }
        .no-events {
            text-align: center;
            padding: 80px 20px;
            background: var(--light-gray);
            border-radius: 10px;
        }
        .no-events i {
            font-size: 4rem;
            color: #ccc;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Header (shared) -->
    <?php include __DIR__ . '/../includes/public_navbar.php'; ?>
    
    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1>Our Events</h1>
            <p class="lead">Discover workshops, seminars, competitions, and social gatherings.</p>
        </div>
    </section>
    
    <!-- Main Content -->
    <main class="container py-5">
        <!-- Global Stats -->
        <?php if (!empty($global_stats)): ?>
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-center shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Total Events</h5>
                        <p class="display-6 mb-0"><?php echo intval($global_stats['total_events']); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Upcoming</h5>
                        <p class="display-6 mb-0"><?php echo intval($global_stats['upcoming']); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Past</h5>
                        <p class="display-6 mb-0"><?php echo intval($global_stats['past']); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <!-- Filters -->
        <div class="filters-bar">
            <form action="events.php" method="GET">
                <div class="row g-3 align-items-center">
                    <div class="col-lg-5">
                        <input type="text" name="search" class="form-control" placeholder="Search by name or keyword..." value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                    <div class="col-lg-2">
                        <select name="type" class="form-select">
                            <option value="all">All Types</option>
                            <?php foreach ($event_types as $type): ?>
                                <option value="<?php echo $type['event_type']; ?>" <?php echo ($filter_type === $type['event_type']) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($type['event_type']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <select name="status" class="form-select">
                            <option value="upcoming" <?php echo ($filter_status === 'upcoming') ? 'selected' : ''; ?>>Upcoming</option>
                            <option value="past" <?php echo ($filter_status === 'past') ? 'selected' : ''; ?>>Past</option>
                            <option value="all" <?php echo ($filter_status === 'all') ? 'selected' : ''; ?>>All</option>
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Filter Events
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Events Grid -->
        <?php if (empty($events)): ?>
            <div class="no-events">
                <i class="fas fa-calendar-times"></i>
                <h2>No Events Found</h2>
                <p class="lead">Your search or filter criteria did not match any events. Try adjusting your search.</p>
                <a href="events.php" class="btn btn-primary mt-3">Clear Filters</a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($events as $event): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="event-card">
                            <div class="event-card-header">
                                <span class="event-type-badge"><?php echo ucfirst($event['event_type']); ?></span>
                                <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                            </div>
                            <div class="event-card-body d-flex flex-column">
                                <h5 class="event-title"><?php echo htmlspecialchars($event['event_name']); ?></h5>
                                <p class="event-location">
                                    <i class="fas fa-map-marker-alt"></i> 
                                    <?php echo htmlspecialchars($event['location'] ?? 'TBD'); ?>
                                </p>
                                <p class="event-description flex-grow-1">
                                    <?php 
                                    $desc = !empty($event['summary']) ? $event['summary'] : $event['full_description'];
                                    echo substr(htmlspecialchars($desc), 0, 100) . '...'; 
                                    ?>
                                </p>
                                
                                <?php 
                                // Determine button to show
                                $is_upcoming = (new DateTime($event['event_date']) >= new DateTime('today'));
                                $is_cancelled = ($event['status'] === 'cancelled');
                                $is_registered = $is_logged_in && isset($registered_lookup[$event['event_id']]);
                                $attendance_status = $is_registered ? $registered_lookup[$event['event_id']] : null;
                                ?>
                                
                                <?php if ($is_cancelled): ?>
                                    <button class="btn btn-danger w-100 mt-3" disabled>
                                        <i class="fas fa-ban me-2"></i>Event Cancelled
                                    </button>
                                <?php elseif (!$is_upcoming): ?>
                                    <button class="btn btn-secondary w-100 mt-3" disabled>
                                        <i class="fas fa-check-circle me-2"></i>Event Concluded
                                    </button>
                                <?php elseif ($is_registered): ?>
                                    <?php if ($attendance_status === 'cancelled'): ?>
                                        <a href="register_event.php?event_id=<?php echo $event['event_id']; ?>" class="btn btn-outline-primary w-100 mt-3">
                                            <i class="fas fa-redo me-2"></i>Register Again
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-success w-100 mt-3" disabled>
                                            <i class="fas fa-check-circle me-2"></i>Already Registered
                                        </button>
                                    <?php endif; ?>
                                <?php elseif ($is_logged_in): ?>
                                    <a href="register_event.php?event_id=<?php echo $event['event_id']; ?>" class="btn btn-primary w-100 mt-3">
                                        <i class="fas fa-user-plus me-2"></i>Register Now
                                    </a>
                                <?php else: ?>
                                    <a href="login.php?redirect=register_event.php?event_id=<?php echo $event['event_id']; ?>" class="btn btn-outline-primary w-100 mt-3">
                                        <i class="fas fa-sign-in-alt me-2"></i>Login to Register
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    
    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
