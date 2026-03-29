<?php
/**
 * ============================================
 * NACOS DASHBOARD - ADD EVENT
 * ============================================
 * Purpose: Create new event
 * Access: Requires authentication
 * Created: November 3, 2025
 * ============================================
 */

// Bootstrap and includes
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';

// Require login
requireAdminRole();

// Get current user
$current_user = getCurrentMember();

// Initialize database
$db = getDB();

// Initialize variables
$error_message = '';
$success_message = '';

$event_name = '';
$summary = '';
$full_description = '';
$event_date = '';
$start_time = '';
$end_time = '';
$location = '';
$event_type = '';
$status = 'upcoming';
$capacity = '';
$registration_link = '';

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
        $start_time = sanitizeInput($_POST['start_time'] ?? '');
        $end_time = sanitizeInput($_POST['end_time'] ?? '');
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
        } elseif (empty($start_time)) {
            $error_message = 'Start time is required';
        } elseif (empty($end_time)) {
            $error_message = 'End time is required';
        } elseif (empty($event_type)) {
            $error_message = 'Event type is required';
        } elseif (!in_array($event_type, ['workshop', 'bootcamp', 'nacos_week', 'seminar', 'competition', 'networking', 'other'])) {
            $error_message = 'Invalid event type';
        } elseif (!in_array($status, ['upcoming', 'ongoing', 'completed', 'cancelled'])) {
            $error_message = 'Invalid event status';
        } else {
            // Check for duplicate event name on same date
            $check_duplicate = $db->fetchOne(
                "SELECT event_id FROM events WHERE event_name = ? AND event_date = ?",
                [$event_name, $event_date]
            );
            
            if ($check_duplicate) {
                $error_message = 'An event with this name already exists on this date';
            } else {
                // Insert event
                try {
                    $query = "
                        INSERT INTO events (
                            event_name, summary, full_description, event_date, start_time, end_time, location,
                            event_type, status, capacity, registration_link
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ";
                    
                    $db->query($query, [
                        $event_name,
                        $summary,
                        $full_description,
                        $event_date,
                        $start_time,
                        $end_time,
                        $location,
                        $event_type,
                        $status,
                        $capacity > 0 ? $capacity : null,
                        !empty($registration_link) ? $registration_link : null
                    ]);
                    
                    redirectWithMessage('events.php', 'Event created successfully', 'success');
                } catch (Exception $e) {
                    $error_message = 'Error creating event: ' . $e->getMessage();
                }
            }
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Event - NACOS Dashboard</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
   <style>
:root {
    --primary-color: #0F6B3E;
    --secondary-color: #1B8A56;
    --sidebar-bg: #0b5d35;
    --sidebar-hover: #0d6f40;
    --success-gradient: linear-gradient(135deg, #0F6B3E, #1B8A56);
    --purple-gradient: linear-gradient(135deg, #0F6B3E, #1B8A56);
    --orange-gradient: linear-gradient(135deg, #dc3545, #b02a37);
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

/* Form Sections */
.form-section {
    background: white;
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 25px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    transition: all 0.3s;
}

.form-section:hover {
    box-shadow: 0 6px 25px rgba(15, 107, 62, 0.15);
}

.section-header {
    display: flex;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.section-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    background: var(--purple-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
    margin-right: 15px;
    box-shadow: 0 4px 12px rgba(15, 107, 62, 0.25);
}

.section-title {
    font-size: 20px;
    font-weight: 700;
    color: #2c3e50;
    margin: 0;
}

.section-subtitle {
    font-size: 13px;
    color: #6c757d;
    margin: 0;
}

/* Form Controls */
.form-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
    font-size: 14px;
}

.form-control, .form-select {
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 14px;
    transition: all 0.3s;
    background: #f8f9fa;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(15, 107, 62, 0.15);
    background: white;
}

textarea.form-control {
    resize: vertical;
    min-height: 100px;
}

.form-text {
    font-size: 12px;
    color: #6c757d;
    margin-top: 5px;
}

/* Sticky Guidelines Card */
.guidelines-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    position: sticky;
    top: 20px;
    overflow: hidden;
}

.guidelines-header {
    background: var(--purple-gradient);
    color: white;
    padding: 20px;
    text-align: center;
}

.guidelines-header h5 {
    margin: 0;
    font-weight: 700;
    font-size: 18px;
    color: white;
}

.guidelines-body {
    padding: 25px;
}

.guideline-section {
    margin-bottom: 25px;
}

.guideline-section:last-child {
    margin-bottom: 0;
}

.guideline-section h6 {
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    font-size: 15px;
}

.guideline-section h6 i {
    margin-right: 8px;
    font-size: 16px;
}

.guideline-section ul {
    margin: 0;
    padding-left: 20px;
}

.guideline-section li {
    margin-bottom: 8px;
    color: #6c757d;
    font-size: 13px;
    line-height: 1.6;
}

.event-type-item {
    padding: 8px 12px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 8px;
    border-left: 3px solid var(--primary-color);
}

.event-type-item strong {
    color: #2c3e50;
}

/* Alert */
.alert {
    border-radius: 10px;
    border: none;
    padding: 15px 20px;
    margin-bottom: 25px;
}

.alert-danger {
    background: linear-gradient(135deg, #ff6b6b, #ee5a6f);
    color: white;
}

.alert-success {
    background: var(--success-gradient);
    color: white;
}

/* Buttons */
.btn {
    padding: 12px 30px;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s;
    border: none;
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

.form-section {
    animation: fadeInUp 0.6s ease-out;
}

.form-section:nth-child(1) { animation-delay: 0.1s; }
.form-section:nth-child(2) { animation-delay: 0.2s; }
.form-section:nth-child(3) { animation-delay: 0.3s; }

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

    .page-header h1 {
        font-size: 22px;
    }

    .page-header-actions {
        width: 100%;
    }

    .page-header-actions .btn {
        width: 100%;
    }

    .header-back-btn {
        width: 100%;
        align-self: stretch;
        text-align: center;
        justify-content: center;
    }

    .form-actions {
        flex-direction: column;
    }

    .form-actions .btn {
        width: 100%;
    }

    .form-section {
        padding: 20px;
    }
    
    .guidelines-card {
        position: relative;
        top: 0;
        margin-top: 25px;
    }
}

@media (max-width: 767.98px) {
    .sidebar-header {
        padding: 16px;
    }

    .sidebar-header h4 {
        font-size: 18px;
    }

    .sidebar-menu a {
        padding: 10px 16px;
    }

    .sidebar-menu a:hover,
    .sidebar-menu a.active {
        padding-left: 20px;
    }

    .main-content {
        padding: 12px;
    }

    .section-header {
        margin-bottom: 18px;
        padding-bottom: 12px;
    }

    .section-icon {
        width: 40px;
        height: 40px;
        font-size: 16px;
    }

    .section-title {
        font-size: 18px;
    }

    .guidelines-body {
        padding: 18px;
    }
}
   </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2 mb-md-0">
                        <button type="button" class="menu-toggle" id="menuToggle" aria-label="Toggle navigation menu">
                            <i class="fas fa-bars"></i>
                        </button>
                        <h1><i class="fas fa-calendar-plus me-2"></i>Add New Event</h1>
                    </div>
                    <p class="mb-0 text-muted">Create a new event and schedule it for members</p>
                </div>
                <div class="page-header-actions">
                    <a href="events.php" class="btn btn-secondary header-back-btn">
                        <i class="fas fa-arrow-left me-2"></i>Back to Events
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Form Content -->
        <div class="row">
            <div class="col-lg-8">
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
                
                <form method="POST" action="add_event.php" id="addEventForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <!-- Event Information Section -->
                    <div class="form-section">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            <div>
                                <h3 class="section-title">Event Information</h3>
                                <p class="section-subtitle">Basic details about the event</p>
                            </div>
                        </div>
                    
                        <div class="mb-3">
                            <label for="event_name" class="form-label">Event Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="event_name" name="event_name" 
                                   value="<?php echo htmlspecialchars($event_name); ?>" placeholder="Enter event name" required maxlength="150">
                            <div class="form-text"><i class="fas fa-info-circle me-1"></i>Give your event a clear, descriptive name</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
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
                            
                            <div class="col-md-6 mb-3">
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
                            <textarea class="form-control" id="summary" name="summary" rows="3" placeholder="Brief description of the event..." required><?php echo htmlspecialchars($summary); ?></textarea>
                            <div class="form-text"><i class="fas fa-info-circle me-1"></i>Brief description of the event (required)</div>
                        </div>
                        
                        <div class="mb-0">
                            <label for="full_description" class="form-label">Full Description</label>
                            <textarea class="form-control" id="full_description" name="full_description" rows="4" placeholder="Detailed information about the event, agenda, speakers, etc..."><?php echo htmlspecialchars($full_description); ?></textarea>
                            <div class="form-text"><i class="fas fa-info-circle me-1"></i>Detailed information about the event, agenda, speakers, etc. (optional)</div>
                        </div>
                    </div>
                    
                    <!-- Date & Time Section -->
                    <div class="form-section">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div>
                                <h3 class="section-title">Date & Time</h3>
                                <p class="section-subtitle">Schedule your event</p>
                            </div>
                        </div>
                    
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="event_date" class="form-label">Event Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="event_date" name="event_date" 
                                       value="<?php echo htmlspecialchars($event_date); ?>" required>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="start_time" class="form-label">Start Time <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="start_time" name="start_time" 
                                       value="<?php echo htmlspecialchars($start_time); ?>" required>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="end_time" class="form-label">End Time <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="end_time" name="end_time" 
                                       value="<?php echo htmlspecialchars($end_time); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 mb-0">
                                <label for="registration_link" class="form-label">Registration Link</label>
                                <input type="url" class="form-control" id="registration_link" name="registration_link" 
                                       value="<?php echo htmlspecialchars($registration_link); ?>" placeholder="https://forms.google.com/..." maxlength="500">
                                <div class="form-text"><i class="fas fa-info-circle me-1"></i>Optional external registration URL</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Location & Capacity Section -->
                    <div class="form-section">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div>
                                <h3 class="section-title">Location & Capacity</h3>
                                <p class="section-subtitle">Venue details and attendance limits</p>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8 mb-0">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="location" name="location" 
                                       value="<?php echo htmlspecialchars($location); ?>" placeholder="Venue address or online meeting link" maxlength="255">
                                <div class="form-text"><i class="fas fa-info-circle me-1"></i>Venue address or online meeting link</div>
                            </div>
                            
                            <div class="col-md-4 mb-0">
                                <label for="capacity" class="form-label">Maximum Capacity</label>
                                <input type="number" class="form-control" id="capacity" name="capacity" 
                                       value="<?php echo htmlspecialchars($capacity); ?>" placeholder="0" min="0" max="10000">
                                <div class="form-text"><i class="fas fa-info-circle me-1"></i>Leave 0 for unlimited</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="d-flex gap-3 form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Create Event
                        </button>
                        <a href="events.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Guidelines Sidebar -->
            <div class="col-lg-4">
                <div class="guidelines-card">
                    <div class="guidelines-header">
                        <i class="fas fa-lightbulb fa-2x mb-2"></i>
                        <h5>Event Guidelines</h5>
                    </div>
                    <div class="guidelines-body">
                        <div class="guideline-section">
                            <h6><i class="fas fa-check-circle text-success"></i>Best Practices</h6>
                            <ul>
                                <li>Use clear, descriptive event names</li>
                                <li>Include detailed agenda in description</li>
                                <li>Set realistic capacity limits</li>
                                <li>Provide complete location details</li>
                                <li>Add registration links when available</li>
                            </ul>
                        </div>
                        
                        <div class="guideline-section">
                            <h6><i class="fas fa-tag text-primary"></i>Event Types</h6>
                            <div class="event-type-item mb-2">
                                <strong>Workshop:</strong> Hands-on training sessions
                            </div>
                            <div class="event-type-item mb-2">
                                <strong>Bootcamp:</strong> Intensive learning programs
                            </div>
                            <div class="event-type-item mb-2">
                                <strong>Seminar:</strong> Educational presentations
                            </div>
                            <div class="event-type-item">
                                <strong>NACOS Week:</strong> Annual celebration events
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php // Include admin footer which loads Bootstrap and confirmation modal ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>
    
    <script>
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.querySelector('.sidebar');
        const sidebarOverlay = document.getElementById('sidebarBackdrop') || document.getElementById('sidebarOverlay');

        function closeSidebar() {
            if (sidebar) sidebar.classList.remove('show');
            if (sidebarOverlay) sidebarOverlay.classList.remove('show');
        }

        if (menuToggle && sidebar && sidebarOverlay) {
            menuToggle.addEventListener('click', function () {
                sidebar.classList.toggle('show');
                sidebarOverlay.classList.toggle('show');
            });

            sidebarOverlay.addEventListener('click', closeSidebar);

            window.addEventListener('resize', function () {
                if (window.innerWidth > 992) {
                    closeSidebar();
                }
            });
        }

        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('event_date').setAttribute('min', today);
    </script>
</body>
</html>

