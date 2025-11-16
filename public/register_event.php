<?php
/**
 * ============================================
 * NACOS DASHBOARD - EVENT REGISTRATION
 * ============================================
 * Purpose: Handle event registrations with pending feedback check
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

// Get event ID
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

if (!$event_id) {
    redirectWithMessage('events.php', 'Invalid event', 'error');
}

// Verify event exists and is upcoming
$event = $db->fetchOne(
    "SELECT * FROM EVENTS WHERE event_id = ?",
    [$event_id]
);

if (!$event) {
    redirectWithMessage('events.php', 'Event not found', 'error');
}

// Check if event is in the past
$event_date = strtotime($event['event_date']);
if ($event_date < strtotime('today')) {
    redirectWithMessage('events.php', 'Cannot register for past events', 'error');
}

// Check if event is cancelled
if ($event['status'] === 'cancelled') {
    redirectWithMessage('events.php', 'This event has been cancelled', 'error');
}

// CRITICAL: Check for pending feedback from attended events
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

if (count($pending_feedback) > 0) {
    // Store the event they wanted to register for in session
    $_SESSION['registration_intent'] = $event_id;
    
    // Redirect to pending feedback page
    redirectWithMessage('pending_feedback.php', 'Please submit feedback for previous events before registering for new ones', 'warning');
}

// Check if already registered
$existing_registration = $db->fetchOne(
    "SELECT * FROM MEMBER_EVENTS WHERE event_id = ? AND member_id = ?",
    [$event_id, $member_id]
);

if ($existing_registration) {
    redirectWithMessage('my_events.php', 'You are already registered for this event', 'info');
}

// Check capacity if set
if (!empty($event['capacity']) && $event['capacity'] > 0) {
    $current_registrations = $db->fetchOne(
        "SELECT COUNT(*) as count FROM MEMBER_EVENTS WHERE event_id = ?",
        [$event_id]
    )['count'];
    
    if ($current_registrations >= $event['capacity']) {
        redirectWithMessage('events.php', 'Sorry, this event is at full capacity', 'error');
    }
}

// Handle POST request (actual registration)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('events.php', 'Invalid security token', 'error');
    }
    
    try {
        // Insert registration
        $query = "INSERT INTO MEMBER_EVENTS (member_id, event_id, attendance_status) 
                  VALUES (?, ?, 'registered')";
        
        $db->query($query, [$member_id, $event_id]);
        
        redirectWithMessage('my_events.php', 'Successfully registered for ' . $event['event_name'] . '!', 'success');
    } catch (Exception $e) {
        redirectWithMessage('events.php', 'Error registering for event: ' . $e->getMessage(), 'error');
    }
}

// Generate CSRF token for confirmation page
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Registration - NACOS Dashboard</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/public.css">
    <style>
        .confirmation-container {
            max-width: 700px;
            margin: 0 auto;
        }
        
        .event-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .confirmation-icon {
            font-size: 5rem;
            color: white;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include '../includes/public_navbar.php'; ?>
    
    <div class="container my-5">
        <div class="confirmation-container">
            <div class="text-center">
                <i class="fas fa-calendar-check confirmation-icon"></i>
                <h1 class="display-5 fw-bold mb-3">Confirm Registration</h1>
            </div>
            
            <div class="event-card text-center">
                <span class="badge bg-light text-dark mb-3">
                    <?php echo ucfirst(str_replace('_', ' ', $event['event_type'])); ?>
                </span>
                <h2 class="mb-3"><?php echo htmlspecialchars($event['event_name']); ?></h2>
                
                <?php if (!empty($event['summary'])): ?>
                    <p class="mb-4"><?php echo htmlspecialchars($event['summary']); ?></p>
                <?php endif; ?>
                
                <div class="row text-start">
                    <div class="col-md-6 mb-3">
                        <i class="fas fa-calendar me-2"></i>
                        <strong>Date:</strong><br>
                        <?php echo date('F d, Y', strtotime($event['event_date'])); ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <i class="fas fa-clock me-2"></i>
                        <strong>Time:</strong><br>
                        <?php echo $event['event_time'] ? date('g:i A', strtotime($event['event_time'])) : 'TBA'; ?>
                    </div>
                    <?php if ($event['location']): ?>
                        <div class="col-md-12 mb-3">
                            <i class="fas fa-map-marker-alt me-2"></i>
                            <strong>Location:</strong><br>
                            <?php echo htmlspecialchars($event['location']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($event['capacity'])): ?>
                        <div class="col-md-12">
                            <i class="fas fa-users me-2"></i>
                            <strong>Capacity:</strong> <?php echo $event['capacity']; ?> seats
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-info-circle me-2"></i>Important Information</h5>
                    <ul class="mb-0">
                        <li>You will receive confirmation once registered</li>
                        <li>Attendance will be marked by event organizers</li>
                        <li>You will be required to provide feedback after attending</li>
                        <li>Check "My Events" page for your registration status</li>
                    </ul>
                </div>
            </div>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="d-flex justify-content-between">
                    <a href="events.php" class="btn btn-secondary btn-lg">
                        <i class="fas fa-arrow-left me-2"></i>Cancel
                    </a>
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="fas fa-check-circle me-2"></i>Confirm Registration
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
