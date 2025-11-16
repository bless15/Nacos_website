<?php
/**
 * ============================================
 * NACOS DASHBOARD - EDIT EVENT
 * ============================================
 * Purpose: Update existing event
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

// Initialize variables with existing data
$error_message = '';
$success_message = '';

$event_name = $event['event_name'];
$summary = $event['summary'] ?? '';
$full_description = $event['full_description'] ?? '';
$event_date = $event['event_date'];
$event_time = $event['event_time'];
$location = $event['location'];
$event_type = $event['event_type'];
$status = $event['status'];
$capacity = $event['capacity'] ?? 0;
$registration_link = $event['registration_link'] ?? '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        // Get and sanitize form data
        $event_name = sanitizeInput($_POST['event_name'] ?? '');
        $summary = sanitizeInput($_POST['summary'] ?? '');
        $full_description = sanitizeInput($_POST['full_description'] ?? '');
        $event_date = sanitizeInput($_POST['event_date'] ?? '');
        $event_time = sanitizeInput($_POST['event_time'] ?? '');
        $location = sanitizeInput($_POST['location'] ?? '');
        $event_type = sanitizeInput($_POST['event_type'] ?? '');
        $status = sanitizeInput($_POST['status'] ?? 'upcoming');
        $capacity = intval($_POST['capacity'] ?? 0);
        $registration_link = sanitizeInput($_POST['registration_link'] ?? '');
        
        // Validation
        if (empty($event_name)) {
            $error_message = 'Event name is required';
        } elseif (empty($summary)) {
            $error_message = 'Event summary is required';
        } elseif (empty($event_date)) {
            $error_message = 'Event date is required';
        } elseif (empty($event_time)) {
            $error_message = 'Event time is required';
        } elseif (empty($event_type)) {
            $error_message = 'Event type is required';
        } elseif (!in_array($event_type, ['workshop', 'bootcamp', 'nacos_week', 'seminar', 'competition', 'networking', 'other'])) {
            $error_message = 'Invalid event type';
        } elseif (!in_array($status, ['upcoming', 'ongoing', 'completed', 'cancelled'])) {
            $error_message = 'Invalid event status';
        } else {
            // Check for duplicate event name on same date (excluding current event)
            $check_duplicate = $db->fetchOne(
                "SELECT event_id FROM EVENTS WHERE event_name = ? AND event_date = ? AND event_id != ?",
                [$event_name, $event_date, $event_id]
            );
            
            if ($check_duplicate) {
                $error_message = 'Another event with this name already exists on this date';
            } else {
                // Update event
                try {
                    $query = "
                        UPDATE EVENTS SET
                            event_name = ?,
                            summary = ?,
                            full_description = ?,
                            event_date = ?,
                            event_time = ?,
                            location = ?,
                            event_type = ?,
                            status = ?,
                            capacity = ?,
                            registration_link = ?
                        WHERE event_id = ?
                    ";
                    
                    $db->query($query, [
                        $event_name,
                        $summary,
                        $full_description,
                        $event_date,
                        $event_time,
                        $location,
                        $event_type,
                        $status,
                        $capacity > 0 ? $capacity : null,
                        !empty($registration_link) ? $registration_link : null,
                        $event_id
                    ]);
                    
                    redirectWithMessage('events.php', 'Event updated successfully', 'success');
                } catch (Exception $e) {
                    $error_message = 'Error updating event: ' . $e->getMessage();
                }
            }
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Get registration count
$registration_count = $db->fetchOne(
    "SELECT COUNT(*) as count FROM MEMBER_EVENTS WHERE event_id = ?",
    [$event_id]
)['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Event - <?php echo htmlspecialchars($event['event_name']); ?></title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/admin.css">
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
                <h3><i class="fas fa-edit me-2"></i> Edit Event</h3>
                <div class="d-flex gap-2">
                    <a href="view_event.php?id=<?php echo $event_id; ?>" class="btn btn-info">
                        <i class="fas fa-eye me-2"></i> View Details
                    </a>
                    <a href="events.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Events
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Event Info Banner -->
        <div class="alert alert-info">
            <div class="d-flex align-items-center">
                <i class="fas fa-info-circle fa-2x me-3"></i>
                <div>
                    <h6 class="mb-1">Editing Event</h6>
                    <p class="mb-0">
                        <strong><?php echo htmlspecialchars($event['event_name']); ?></strong> - 
                        <?php echo date('F d, Y', strtotime($event['event_date'])); ?>
                        <?php if ($registration_count > 0): ?>
                            <span class="badge bg-warning text-dark ms-2">
                                <i class="fas fa-users me-1"></i> <?php echo $registration_count; ?> registered
                            </span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Form Card -->
        <div class="card">
            <div class="card-body">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="edit_event.php?id=<?php echo $event_id; ?>" id="editEventForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i> Event Information</h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="event_name" class="form-label">Event Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="event_name" name="event_name" 
                                   value="<?php echo htmlspecialchars($event_name); ?>" required maxlength="150">
                            <div class="form-text">Give your event a clear, descriptive name</div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="event_type" class="form-label">Event Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="event_type" name="event_type" required>
                                <option value="">Select Type</option>
                                <option value="workshop" <?php echo $event_type === 'workshop' ? 'selected' : ''; ?>>Workshop</option>
                                <option value="bootcamp" <?php echo $event_type === 'bootcamp' ? 'selected' : ''; ?>>Bootcamp</option>
                                <option value="nacos_week" <?php echo $event_type === 'nacos_week' ? 'selected' : ''; ?>>NACOS Week</option>
                                <option value="seminar" <?php echo $event_type === 'seminar' ? 'selected' : ''; ?>>Seminar</option>
                                <option value="competition" <?php echo $event_type === 'competition' ? 'selected' : ''; ?>>Competition</option>
                                <option value="networking" <?php echo $event_type === 'networking' ? 'selected' : ''; ?>>Networking</option>
                                <option value="other" <?php echo $event_type === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="upcoming" <?php echo $status === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                <option value="ongoing" <?php echo $status === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="summary" class="form-label">Summary <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="summary" name="summary" rows="3" required><?php echo htmlspecialchars($summary); ?></textarea>
                        <div class="form-text">Brief description of the event (required)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="full_description" class="form-label">Full Description</label>
                        <textarea class="form-control" id="full_description" name="full_description" rows="4"><?php echo htmlspecialchars($full_description); ?></textarea>
                        <div class="form-text">Detailed information about the event, agenda, speakers, etc. (optional)</div>
                    </div>
                    
                    <hr class="my-4">
                    <h5 class="mb-3"><i class="fas fa-calendar-alt me-2"></i> Date & Time</h5>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="event_date" class="form-label">Event Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="event_date" name="event_date" 
                                   value="<?php echo htmlspecialchars($event_date); ?>" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="event_time" class="form-label">Event Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="event_time" name="event_time" 
                                   value="<?php echo htmlspecialchars($event_time); ?>" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="registration_link" class="form-label">Registration Link</label>
                            <input type="url" class="form-control" id="registration_link" name="registration_link" 
                                   value="<?php echo htmlspecialchars($registration_link); ?>" maxlength="500">
                            <div class="form-text">Optional external registration URL</div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    <h5 class="mb-3"><i class="fas fa-map-marker-alt me-2"></i> Location & Capacity</h5>
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="location" class="form-label">Location</label>
                            <input type="text" class="form-control" id="location" name="location" 
                                   value="<?php echo htmlspecialchars($location); ?>" maxlength="255">
                            <div class="form-text">Venue address or online meeting link</div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="capacity" class="form-label">Maximum Capacity</label>
                            <input type="number" class="form-control" id="capacity" name="capacity" 
                                   value="<?php echo htmlspecialchars($capacity); ?>" min="0" max="10000">
                            <div class="form-text">Leave 0 for unlimited</div>
                            <?php if ($registration_count > 0 && $capacity > 0 && $registration_count > $capacity): ?>
                                <div class="alert alert-warning mt-2 mb-0 py-1">
                                    <small><i class="fas fa-exclamation-triangle me-1"></i> Warning: Current registrations (<?php echo $registration_count; ?>) exceed new capacity</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Update Event
                        </button>
                        <a href="view_event.php?id=<?php echo $event_id; ?>" class="btn btn-info">
                            <i class="fas fa-eye me-2"></i> View Event
                        </a>
                        <a href="events.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php // Include admin footer which loads Bootstrap and confirmation modal ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>
    
    <script>
        // Form validation
        document.getElementById('editEventForm').addEventListener('submit', function(e) {
            const eventDate = document.getElementById('event_date').value;
            const registrationDeadline = document.getElementById('registration_deadline').value;
            
            // Check if registration deadline is before event date
            if (registrationDeadline && eventDate) {
                if (new Date(registrationDeadline) >= new Date(eventDate)) {
                    e.preventDefault();
                    alert('Registration deadline must be before the event date');
                    return false;
                }
            }
        });
    </script>
</body>
</html>

