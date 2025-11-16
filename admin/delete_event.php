<?php
/**
 * ============================================
 * NACOS DASHBOARD - DELETE EVENT
 * ============================================
 * Purpose: Delete or cancel event
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

// Check if event has registrations
$registration_count = $db->fetchOne(
    "SELECT COUNT(*) as count FROM MEMBER_EVENTS WHERE event_id = ?",
    [$event_id]
)['count'];

$has_registrations = ($registration_count > 0);

// Handle form submission
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'cancel') {
            // Cancel event (soft delete)
            try {
                $query = "UPDATE EVENTS SET status = 'cancelled' WHERE event_id = ?";
                $db->query($query, [$event_id]);
                
                redirectWithMessage('events.php', 'Event cancelled successfully', 'success');
            } catch (Exception $e) {
                $error_message = 'Error cancelling event: ' . $e->getMessage();
            }
            
        } elseif ($action === 'delete') {
            // Hard delete - first delete registrations, then event
            try {
                $db->beginTransaction();
                
                // Delete from MEMBER_EVENTS
                $db->query("DELETE FROM MEMBER_EVENTS WHERE event_id = ?", [$event_id]);
                
                // Delete event
                $db->query("DELETE FROM EVENTS WHERE event_id = ?", [$event_id]);
                
                $db->commit();
                    // Verify deletion and log
                    try {
                        $still = $db->fetchOne("SELECT COUNT(*) as c FROM EVENTS WHERE event_id = ?", [$event_id]);
                        $stillCount = intval($still['c'] ?? 0);
                    } catch (Exception $e) {
                        $stillCount = 1; // assume still exists if check fails
                    }

                    $logMsg = date('[Y-m-d H:i:s]') . " Delete event attempt: event_id={$event_id}, admin_id=" . ($current_user['member_id'] ?? 'unknown') . ", remaining=" . $stillCount . "\n";
                    @file_put_contents(__DIR__ . '/../logs/actions.log', $logMsg, FILE_APPEND | LOCK_EX);

                    if ($stillCount === 0) {
                        redirectWithMessage('events.php', 'Event deleted permanently', 'success');
                    } else {
                        // Something went wrong: deletion did not remove event row
                        redirectWithMessage('events.php', 'Event delete attempted but the event still exists. Check logs.', 'error');
                    }
            } catch (Exception $e) {
                $db->rollBack();
                    $error_message = 'Error deleting event: ' . $e->getMessage();
                    // Log exception
                    $errLog = date('[Y-m-d H:i:s]') . " Delete event ERROR: event_id={$event_id}, admin_id=" . ($current_user['member_id'] ?? 'unknown') . ", error=" . $e->getMessage() . "\n";
                    @file_put_contents(__DIR__ . '/../logs/actions.log', $errLog, FILE_APPEND | LOCK_EX);
            }
        } else {
            $error_message = 'Invalid action specified';
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Helper function to determine if event is in the past
function isPastEvent($event) {
    $event_datetime = strtotime($event['event_date'] . ' ' . $event['event_time']);
    return $event_datetime < time();
}

$is_past_event = isPastEvent($event);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Event - <?php echo htmlspecialchars($event['event_name']); ?></title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --danger-color: #dc3545;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .delete-container {
            max-width: 700px;
            width: 100%;
        }
        
        .delete-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        
        .delete-header {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .delete-header i {
            font-size: 60px;
            margin-bottom: 15px;
        }
        
        .delete-header h3 {
            margin: 0;
            font-weight: 600;
        }
        
        .delete-body {
            padding: 30px;
        }
        
        .event-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .event-info .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .event-info .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
        }
        
        .info-value {
            color: #333;
            text-align: right;
        }
        
        .warning-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .warning-box i {
            color: #ffc107;
            font-size: 24px;
            margin-right: 10px;
        }
        
        .danger-box {
            background: #f8d7da;
            border: 2px solid #dc3545;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .danger-box i {
            color: #dc3545;
            font-size: 24px;
            margin-right: 10px;
        }
        
        .info-box {
            background: #d1ecf1;
            border: 2px solid #17a2b8;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .info-box i {
            color: #17a2b8;
            font-size: 24px;
            margin-right: 10px;
        }
        
        .action-buttons {
            display: grid;
            gap: 15px;
        }
        
        .action-btn {
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        
        .action-btn i {
            margin-right: 10px;
        }
        
        .btn-cancel-event {
            background: #ffc107;
            color: #000;
        }
        
        .btn-cancel-event:hover {
            background: #e0a800;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 193, 7, 0.3);
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        
        .btn-delete:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
        }
        
        .btn-back:hover {
            background: #5a6268;
            text-decoration: none;
            color: white;
        }
    </style>
</head>
<body>
    <div class="delete-container">
        <div class="delete-card">
            <div class="delete-header">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Delete Event</h3>
                <p class="mb-0">This action requires careful consideration</p>
            </div>
            
            <div class="delete-body">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle me-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Event Information -->
                <div class="event-info">
                    <h5 class="mb-3">Event Details</h5>
                    <div class="info-row">
                        <span class="info-label">Event Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars($event['event_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Type:</span>
                        <span class="info-value">
                            <span class="badge bg-secondary"><?php echo ucfirst($event['event_type']); ?></span>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Date & Time:</span>
                        <span class="info-value">
                            <?php echo date('F d, Y', strtotime($event['event_date'])); ?> at 
                            <?php echo date('g:i A', strtotime($event['event_time'])); ?>
                        </span>
                    </div>
                    <?php if ($event['location']): ?>
                        <div class="info-row">
                            <span class="info-label">Location:</span>
                            <span class="info-value"><?php echo htmlspecialchars($event['location']); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="info-label">Status:</span>
                        <span class="info-value">
                            <?php 
                            $status_colors = ['upcoming' => 'primary', 'completed' => 'success', 'cancelled' => 'danger'];
                            $color = $status_colors[$event['status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $color; ?>">
                                <?php echo ucfirst($event['status']); ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Registered Members:</span>
                        <span class="info-value">
                            <strong><?php echo $registration_count; ?></strong>
                        </span>
                    </div>
                </div>
                
                <!-- Past Event Info -->
                <?php if ($is_past_event && $event['status'] === 'completed'): ?>
                    <div class="info-box">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-info-circle"></i>
                            <div>
                                <h6 class="mb-2">Completed Event</h6>
                                <p class="mb-0">
                                    This event has already taken place. If you delete it, all attendance records 
                                    and feedback will be permanently lost. Consider keeping it for historical purposes.
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Registrations Warning -->
                <?php if ($has_registrations): ?>
                    <div class="warning-box">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div>
                                <h6 class="mb-2">This event has registrations</h6>
                                <p class="mb-2">
                                    <strong><?php echo $registration_count; ?> member(s)</strong> have registered for this event.
                                </p>
                                <p class="mb-0">
                                    <strong>Deleting will remove all registration and attendance records permanently!</strong>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Action Options -->
                <div class="mb-4">
                    <h6 class="mb-3">Choose an action:</h6>
                    
                    <!-- Cancel Option -->
                    <?php if ($event['status'] !== 'cancelled'): ?>
                        <div class="border rounded p-3 mb-3">
                            <h6 class="text-warning"><i class="fas fa-ban me-2"></i> Option 1: Cancel Event (Recommended)</h6>
                            <p class="text-muted mb-2">
                                Event will be marked as cancelled but all data will be preserved. 
                                This is reversible and maintains historical records.
                                <?php if ($has_registrations): ?>
                                    <br><strong>Registered members will be notified of cancellation.</strong>
                                <?php endif; ?>
                            </p>
                            <form method="POST" class="d-inline confirm-action-form" data-message="Mark this event as cancelled?">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="cancel">
                                <button type="submit" class="action-btn btn-cancel-event">
                                    <i class="fas fa-ban"></i>
                                    Cancel Event
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Delete Option -->
                    <div class="border rounded p-3">
                        <h6 class="text-danger"><i class="fas fa-trash-alt me-2"></i> Option <?php echo $event['status'] !== 'cancelled' ? '2' : '1'; ?>: Delete Permanently</h6>
                        <p class="text-muted mb-2">
                            Permanently removes event and all associated records. 
                            <strong>This action cannot be undone!</strong>
                        </p>
                        <div class="danger-box mb-2">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-skull-crossbones"></i>
                                <strong>Warning: This will delete all registration and attendance records!</strong>
                            </div>
                        </div>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="button" class="action-btn btn-delete confirm-action-btn" data-action="delete" data-message="<?php echo htmlspecialchars('⚠️ WARNING ⚠️\n\nThis will PERMANENTLY DELETE:\n- Event details\n- All registrations ('.$registration_count.')\n- All attendance records\n- All feedback\n\nThis action CANNOT be undone!\n\nAre you absolutely sure?'); ?>">
                                <i class="fas fa-trash-alt"></i>
                                Delete Permanently
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Back Button -->
                <a href="events.php" class="action-btn btn-back">
                    <i class="fas fa-times"></i>
                    Cancel - Go Back
                </a>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?php // Include admin footer which loads Bootstrap and confirmation modal ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
