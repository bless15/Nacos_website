<?php
/**
 * ============================================
 * NACOS DASHBOARD - MY EVENTS
 * ============================================
 * Purpose: Display member's registered and attended events
 * Access: Logged-in members only
 * Created: November 4, 2025
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Include required files
require_once '../config/database.php';
require_once '../includes/auth.php';

// Require member login
requireMemberLogin();

// Get current member
$member = getCurrentMember();
$member_id = $member['member_id'];

// Initialize database
$db = getDB();

// Get member's events with details
$my_events = $db->fetchAll(
    "SELECT e.*, me.attendance_status, me.registration_date, me.feedback_rating, me.feedback_comment,
            (SELECT COUNT(*) FROM MEMBER_EVENTS me2 WHERE me2.event_id = e.event_id) as total_registered
     FROM MEMBER_EVENTS me
     JOIN EVENTS e ON me.event_id = e.event_id
     WHERE me.member_id = ?
     ORDER BY e.event_date DESC",
    [$member_id]
);

// Count pending feedback
$pending_feedback = $db->fetchAll(
    "SELECT e.event_id, e.event_name, e.event_date
     FROM MEMBER_EVENTS me
     JOIN EVENTS e ON me.event_id = e.event_id
     WHERE me.member_id = ? 
     AND me.attendance_status = 'attended' 
     AND (me.feedback_rating IS NULL OR me.feedback_comment IS NULL OR me.feedback_comment = '')
     ORDER BY e.event_date DESC",
    [$member_id]
);

$has_pending_feedback = count($pending_feedback) > 0;

// Get statistics
$stats = $db->fetchOne(
    "SELECT 
        COUNT(*) as total_registered,
        SUM(CASE WHEN attendance_status = 'attended' THEN 1 ELSE 0 END) as total_attended,
        SUM(CASE WHEN attendance_status = 'registered' THEN 1 ELSE 0 END) as upcoming,
        SUM(CASE WHEN attendance_status = 'attended' AND feedback_rating IS NOT NULL THEN 1 ELSE 0 END) as feedback_given
     FROM MEMBER_EVENTS
     WHERE member_id = ?",
    [$member_id]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Events - NACOS Dashboard</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/public.css">
    <style>
        .event-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid #e0e0e0;
            height: 100%;
        }
        
        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .status-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-registered { background: #0d6efd; color: white; }
        .status-attended { background: #198754; color: white; }
        .status-absent { background: #dc3545; color: white; }
        .status-cancelled { background: #6c757d; color: white; }
        
        .feedback-stars {
            color: #ffc107;
            font-size: 1.2rem;
        }
        
        .feedback-stars i {
            margin: 0 2px;
        }
        
        .pending-feedback-alert {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
            border: none;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card h3 {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
        }
        
        .stat-card p {
            margin: 0;
            opacity: 0.9;
        }
        
        .feedback-needed {
            border: 3px solid #ff6b6b;
            background: #fff5f5;
        }
    </style>
</head>
<body>
    <?php include '../includes/public_navbar.php'; ?>
    
    <div class="container my-5">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-md-12">
                <h1 class="display-5 fw-bold">
                    <i class="fas fa-calendar-check me-3"></i>My Events
                </h1>
                <!-- Statistics Cards -->
            </div>
        </div>
        
        <?php if ($has_pending_feedback): ?>
        <!-- Pending Feedback Alert -->
        <div class="alert pending-feedback-alert alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-triangle fa-3x me-3"></i>
                <div>
                    <h5 class="mb-1">⚠️ Pending Feedback Required!</h5>
                    <p class="mb-2">You have <strong><?php echo count($pending_feedback); ?> event(s)</strong> that need your feedback. Please submit feedback before registering for new events.</p>
                    <ul class="mb-0">
                        <?php foreach ($pending_feedback as $event): ?>
                            <li>
                                <strong><?php echo htmlspecialchars($event['event_name']); ?></strong> 
                                (<?php echo date('M d, Y', strtotime($event['event_date'])); ?>)
                                <a href="#event-<?php echo $event['event_id']; ?>" class="text-white text-decoration-underline ms-2">
                                    Submit Feedback
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <h3><?php echo $stats['total_registered']; ?></h3>
                    <p><i class="fas fa-calendar-plus me-2"></i>Total Registered</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #28a745 0%, #20873a 100%);">
                    <h3><?php echo $stats['total_attended']; ?></h3>
                    <p><i class="fas fa-check-circle me-2"></i>Events Attended</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);">
                    <h3><?php echo $stats['upcoming']; ?></h3>
                    <p><i class="fas fa-clock me-2"></i>Upcoming Events</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);">
                    <h3><?php echo $stats['feedback_given']; ?></h3>
                    <p><i class="fas fa-star me-2"></i>Feedback Given</p>
                </div>
            </div>
        </div>
        
        <!-- Events List -->
        <div class="row">
            <?php if (empty($my_events)): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle fa-3x mb-3"></i>
                        <h5>No Events Yet</h5>
                        <p>You haven't registered for any events. Check out our <a href="events.php" class="alert-link">upcoming events</a>!</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($my_events as $event): ?>
                    <?php 
                    $needs_feedback = ($event['attendance_status'] === 'attended' && 
                                      (empty($event['feedback_rating']) || empty($event['feedback_comment'])));
                    $event_date = strtotime($event['event_date']);
                    $is_past = $event_date < time();
                    ?>
                    <div class="col-md-6 mb-4" id="event-<?php echo $event['event_id']; ?>">
                        <div class="card event-card <?php echo $needs_feedback ? 'feedback-needed' : ''; ?>">
                            <div class="card-body position-relative">
                                <!-- Status Badge -->
                                <span class="status-badge status-<?php echo $event['attendance_status']; ?>">
                                    <?php echo ucfirst($event['attendance_status']); ?>
                                </span>
                                
                                <!-- Event Type -->
                                <div class="mb-2">
                                    <span class="badge bg-secondary">
                                        <?php echo ucfirst(str_replace('_', ' ', $event['event_type'])); ?>
                                    </span>
                                </div>
                                
                                <!-- Event Name -->
                                <h5 class="card-title fw-bold"><?php echo htmlspecialchars($event['event_name']); ?></h5>
                                
                                <!-- Event Summary -->
                                <?php if (!empty($event['summary'])): ?>
                                    <p class="card-text text-muted">
                                        <?php echo substr(htmlspecialchars($event['summary']), 0, 100) . '...'; ?>
                                    </p>
                                <?php endif; ?>
                                
                                <!-- Event Details -->
                                <div class="mb-3">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo date('F d, Y', strtotime($event['event_date'])); ?>
                                        <?php if ($event['event_time']): ?>
                                            • <i class="fas fa-clock me-1"></i><?php echo date('g:i A', strtotime($event['event_time'])); ?>
                                        <?php endif; ?>
                                    </small>
                                    <br>
                                    <?php if ($event['location']): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?php echo htmlspecialchars($event['location']); ?>
                                        </small>
                                    <?php endif; ?>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-user-check me-1"></i>
                                        <?php echo $event['total_registered']; ?> registered
                                    </small>
                                </div>
                                
                                <!-- Registration Date -->
                                <div class="mb-3">
                                    <small class="text-success">
                                        <i class="fas fa-check-circle me-1"></i>
                                        Registered on <?php echo date('M d, Y', strtotime($event['registration_date'])); ?>
                                    </small>
                                </div>
                                
                                <!-- Feedback Section -->
                                <?php if ($event['attendance_status'] === 'attended'): ?>
                                    <?php if (!empty($event['feedback_rating']) && !empty($event['feedback_comment'])): ?>
                                        <!-- Feedback Already Given -->
                                        <div class="alert alert-success mb-0">
                                            <strong><i class="fas fa-check-circle me-1"></i>Feedback Submitted</strong>
                                            <div class="feedback-stars mt-2">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?php echo $i <= $event['feedback_rating'] ? '' : 'text-muted'; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <p class="mb-0 mt-2 small">
                                                <em>"<?php echo htmlspecialchars($event['feedback_comment']); ?>"</em>
                                            </p>
                                        </div>
                                    <?php else: ?>
                                        <!-- Feedback Needed -->
                                        <div class="alert alert-warning mb-0">
                                            <strong><i class="fas fa-exclamation-triangle me-1"></i>Feedback Required</strong>
                                            <p class="mb-2 small">Please rate this event and share your experience</p>
                                            <a href="submit_feedback.php?event_id=<?php echo $event['event_id']; ?>" 
                                               class="btn btn-sm btn-warning">
                                                <i class="fas fa-star me-1"></i>Submit Feedback
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php if ($event['attendance_status'] === 'registered' && !$is_past): ?>
                                    <div class="alert alert-info mb-0">
                                        <i class="fas fa-info-circle me-1"></i>
                                        <strong>Registered</strong> - Waiting for event
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($event['attendance_status'] === 'absent'): ?>
                                    <div class="alert alert-secondary mb-0">
                                        <i class="fas fa-times-circle me-1"></i>
                                        <strong>Marked Absent</strong>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
