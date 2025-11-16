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

// Include required files
require_once '../config/database.php';
require_once '../includes/auth.php';

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
$event = $db->fetchOne("SELECT * FROM EVENTS WHERE event_id = ?", [$event_id]);

if (!$event) {
    redirectWithMessage('events.php', 'Event not found', 'error');
}

// Get registered members with attendance status
$registered_members = $db->fetchAll(
    "SELECT m.member_id, m.full_name, m.matric_no, m.email, m.department, m.level,
            me.attendance_status, me.registration_date, me.feedback_rating, me.feedback_comment
     FROM MEMBER_EVENTS me
     JOIN MEMBERS m ON me.member_id = m.member_id
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
    $event_date = strtotime($event['event_date'] . ' ' . $event['event_time']);
    
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
        .event-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .event-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .event-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }
        
        .event-header h2 {
            position: relative;
            z-index: 1;
            margin-bottom: 15px;
        }
        
        .event-meta {
            position: relative;
            z-index: 1;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            font-size: 15px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 15px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
        }
        
        .stat-card h3 {
            font-size: 36px;
            font-weight: 700;
            margin: 0;
        }
        
        .stat-card p {
            color: #666;
            margin: 5px 0 0;
        }
        
        .content-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        
        .content-card h5 {
            color: var(--primary-color);
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .info-grid {
            display: grid;
            gap: 15px;
        }
        
        .info-item {
            display: flex;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
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
        
        .rating-stars {
            color: #ffc107;
        }
        
        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
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
                <h3><i class="fas fa-calendar-alt me-2"></i> Event Details</h3>
                <div class="d-flex gap-2">
                    <a href="event_attendance.php?id=<?php echo $event_id; ?>" class="btn btn-success">
                        <i class="fas fa-check-square me-2"></i> Manage Attendance
                    </a>
                    <a href="edit_event.php?id=<?php echo $event_id; ?>" class="btn btn-warning">
                        <i class="fas fa-edit me-2"></i> Edit Event
                    </a>
                    <a href="events.php" class="btn btn-outline-secondary">
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
                    <span><?php echo date('g:i A', strtotime($event['event_time'])); ?></span>
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
        <div class="stats-grid">
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
                <div class="content-card">
                    <h5><i class="fas fa-users me-2"></i> Registered Members (<?php echo $total_registered; ?>)</h5>
                    
                    <?php if (empty($registered_members)): ?>
                        <div class="empty-state">
                            <i class="fas fa-user-slash"></i>
                            <p>No members registered yet</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Department</th>
                                        <th>Status</th>
                                        <th>Rating</th>
                                        <th>Registered</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($registered_members as $member): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($member['full_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($member['matric_no']); ?></small>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($member['department']); ?><br>
                                                <small class="text-muted"><?php echo $member['level']; ?> Level</small>
                                            </td>
                                            <td>
                                                <?php 
                                                $status_colors = [
                                                    'registered' => 'info',
                                                    'attended' => 'success',
                                                    'absent' => 'danger',
                                                    'cancelled' => 'secondary'
                                                ];
                                                $color = $status_colors[$member['attendance_status']] ?? 'info';
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>">
                                                    <?php echo ucfirst($member['attendance_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($member['feedback_rating']): ?>
                                                    <div class="rating-stars">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star<?php echo $i <= $member['feedback_rating'] ? '' : '-o'; ?>"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?php echo date('M d, Y', strtotime($member['registration_date'])); ?></small>
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

