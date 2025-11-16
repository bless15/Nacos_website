<?php
/**
 * ============================================
 * NACOS DASHBOARD - PENDING FEEDBACK BLOCKER
 * ============================================
 * Purpose: Show pending feedback and block new registrations
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

// Get pending feedback events
$pending_feedback = $db->fetchAll(
    "SELECT e.event_id, e.event_name, e.event_date, e.event_type, e.summary
     FROM MEMBER_EVENTS me
     JOIN EVENTS e ON me.event_id = e.event_id
     WHERE me.member_id = ? 
     AND me.attendance_status = 'attended' 
     AND (me.feedback_rating IS NULL OR me.feedback_comment IS NULL OR me.feedback_comment = '')
     ORDER BY e.event_date DESC",
    [$member_id]
);

// If no pending feedback, redirect to my events
if (count($pending_feedback) === 0) {
    redirectWithMessage('my_events.php', 'All feedback submitted! You can now register for new events', 'success');
}

// Get the event they wanted to register for
$registration_intent_id = $_SESSION['registration_intent'] ?? null;
$intended_event = null;

if ($registration_intent_id) {
    $intended_event = $db->fetchOne(
        "SELECT event_name, event_date FROM EVENTS WHERE event_id = ?",
        [$registration_intent_id]
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Feedback Required - NACOS Dashboard</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/public.css">
    <style>
        .blocker-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .warning-header {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
            padding: 50px 30px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .warning-icon {
            font-size: 5rem;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .event-pending {
            border: 2px solid #ff6b6b;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            background: #fff5f5;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .event-pending:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.3);
        }
        
        .intended-event-card {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .progress-indicator {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .progress-bar-custom {
            height: 30px;
            border-radius: 15px;
            background: linear-gradient(90deg, #28a745 0%, #20c997 100%);
        }
    </style>
</head>
<body>
    <?php include '../includes/public_navbar.php'; ?>
    
    <div class="container my-5">
        <div class="blocker-container">
            <!-- Warning Header -->
            <div class="warning-header">
                <i class="fas fa-exclamation-triangle warning-icon"></i>
                <h1 class="display-4 fw-bold mb-3">Feedback Required!</h1>
                <p class="lead mb-0">Please complete your pending feedback before registering for new events</p>
            </div>
            
            <?php if ($intended_event): ?>
            <!-- Intended Event -->
            <div class="intended-event-card">
                <h5 class="mb-2"><i class="fas fa-info-circle me-2"></i>You tried to register for:</h5>
                <h3 class="mb-2"><?php echo htmlspecialchars($intended_event['event_name']); ?></h3>
                <p class="mb-0">
                    <i class="fas fa-calendar me-2"></i>
                    <?php echo date('F d, Y', strtotime($intended_event['event_date'])); ?>
                </p>
            </div>
            <?php endif; ?>
            
            <!-- Progress Indicator -->
            <div class="progress-indicator">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Feedback Progress</h5>
                    <span class="badge bg-danger fs-6"><?php echo count($pending_feedback); ?> Pending</span>
                </div>
                <div class="progress" style="height: 30px;">
                    <div class="progress-bar bg-danger" role="progressbar" style="width: 0%">
                        0% Complete
                    </div>
                </div>
                <p class="text-center text-muted mt-2 mb-0">Complete all feedback to unlock event registration</p>
            </div>
            
            <!-- Instructions -->
            <div class="alert alert-warning mb-4">
                <h5 class="alert-heading"><i class="fas fa-lightbulb me-2"></i>What you need to do:</h5>
                <ol class="mb-0">
                    <li>Review each event you attended below</li>
                    <li>Click "Submit Feedback" for each event</li>
                    <li>Provide a star rating (1-5 stars)</li>
                    <li>Write your feedback comment (minimum 10 characters)</li>
                    <li>Once all feedback is submitted, you can register for new events!</li>
                </ol>
            </div>
            
            <!-- Pending Feedback Events -->
            <h4 class="mb-4">
                <i class="fas fa-clipboard-list me-2"></i>
                Events Requiring Your Feedback (<?php echo count($pending_feedback); ?>)
            </h4>
            
            <?php foreach ($pending_feedback as $index => $event): ?>
            <div class="event-pending">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="flex-grow-1">
                        <div class="mb-2">
                            <span class="badge bg-danger me-2">#<?php echo $index + 1; ?></span>
                            <span class="badge bg-secondary">
                                <?php echo ucfirst(str_replace('_', ' ', $event['event_type'])); ?>
                            </span>
                        </div>
                        <h5 class="mb-2 fw-bold"><?php echo htmlspecialchars($event['event_name']); ?></h5>
                        <?php if (!empty($event['summary'])): ?>
                            <p class="text-muted mb-2">
                                <?php echo substr(htmlspecialchars($event['summary']), 0, 150) . '...'; ?>
                            </p>
                        <?php endif; ?>
                        <small class="text-muted">
                            <i class="fas fa-calendar me-1"></i>
                            Attended on <?php echo date('F d, Y', strtotime($event['event_date'])); ?>
                        </small>
                    </div>
                    <div class="text-end ms-3">
                        <i class="fas fa-star-half-alt text-warning fa-3x mb-2"></i>
                        <br>
                        <span class="badge bg-warning text-dark">Feedback Pending</span>
                    </div>
                </div>
                
                <a href="submit_feedback.php?event_id=<?php echo $event['event_id']; ?>" 
                   class="btn btn-danger btn-lg w-100">
                    <i class="fas fa-star me-2"></i>Submit Feedback Now
                </a>
            </div>
            <?php endforeach; ?>
            
            <!-- Action Buttons -->
            <div class="card bg-light">
                <div class="card-body text-center">
                    <h5 class="mb-3">After Submitting All Feedback</h5>
                    <div class="d-flex justify-content-center gap-3">
                        <a href="my_events.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-arrow-left me-2"></i>Back to My Events
                        </a>
                        <a href="events.php" class="btn btn-outline-primary btn-lg">
                            <i class="fas fa-calendar-alt me-2"></i>Browse Events
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
