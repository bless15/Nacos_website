<?php
/**
 * ============================================
 * NACOS DASHBOARD - EVENTS MANAGEMENT
 * ============================================
 * Purpose: List and manage all events
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

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'event_date';
$sort_order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'asc' : 'desc';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(event_name LIKE ? OR description LIKE ? OR location LIKE ?)";
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
$count_query = "SELECT COUNT(*) as total FROM EVENTS {$where_clause}";
$total_events = $db->fetchOne($count_query, $params)['total'];
$total_pages = ceil($total_events / $per_page);

// Get events with registration count
$query = "
    SELECT 
        e.*,
        COUNT(DISTINCT me.member_id) as registered_count,
        SUM(CASE WHEN me.attendance_status = 'attended' THEN 1 ELSE 0 END) as attended_count
    FROM EVENTS e
    LEFT JOIN MEMBER_EVENTS me ON e.event_id = me.event_id
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
        SUM(CASE WHEN (CONCAT(event_date, ' ', COALESCE(event_time, '00:00:00')) <= NOW() AND status != 'cancelled') THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN (CONCAT(event_date, ' ', COALESCE(event_time, '00:00:00')) > NOW() AND status != 'cancelled') THEN 1 ELSE 0 END) as upcoming
     FROM EVENTS"
);

// Get flash message
$flash = getFlashMessage();

// Helper function to determine event status based on date
function getEventStatus($event) {
    $now = time();
    $event_date = strtotime($event['event_date'] . ' ' . $event['event_time']);
    
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
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/admin.css">
    
    <style>
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
        
        .meta-item {
            display: flex;
            align-items: center;
            font-size: 14px;
            color: #666;
        }
        
        .meta-item i {
            width: 20px;
            color: var(--primary-color);
            margin-right: 8px;
        }
        
        .event-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 15px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }
        
        .stat-item {
            text-align: center;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .stat-item .number {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .stat-item .label {
            font-size: 12px;
            color: #666;
        }
        
        .event-actions {
            display: flex;
            gap: 5px;
            margin-top: auto;
        }
        
        .event-actions .btn {
            flex: 1;
            font-size: 13px;
            padding: 8px 10px;
        }
        
        .stats-overview {
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
        
        .stat-info h3 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }
        
        .stat-info p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }
        
        .filters-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }
        
        .empty-state {
            background: white;
            padding: 60px 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-state h4 {
            color: #666;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #999;
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
            <a href="members.php">
                <i class="fas fa-users"></i> Members
            </a>
            <a href="projects.php">
                <i class="fas fa-project-diagram"></i> Projects
            </a>
            <a href="events.php" class="active">
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
                <div>
                    <h3><i class="fas fa-calendar-alt me-2"></i> Events Management</h3>
                    <p class="text-muted mb-0">Manage all NACOS events and track attendance</p>
                </div>
                <a href="add_event.php" class="btn btn-primary">
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
        <div class="filters-card">
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
                                        <span><?php echo formatEventDate($event['event_date'], $event['event_time']); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-clock"></i>
                                        <span><?php echo date('g:i A', strtotime($event['event_time'])); ?></span>
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
                                    <a href="delete_event.php?id=<?php echo $event['event_id']; ?>" 
                                       class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
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

