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

// Bootstrap and includes
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

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
$event = $db->fetchOne("SELECT * FROM events WHERE event_id = ?", [$event_id]);

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
$start_time = $event['start_time'] ?? $event['event_time'] ?? '';
$end_time = $event['end_time'] ?? '';
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
            // Check for duplicate event name on same date (excluding current event)
            $check_duplicate = $db->fetchOne(
                "SELECT event_id FROM events WHERE event_name = ? AND event_date = ? AND event_id != ?",
                [$event_name, $event_date, $event_id]
            );
            
            if ($check_duplicate) {
                $error_message = 'Another event with this name already exists on this date';
            } else {
                // Update event
                try {
                    $query = "
                        UPDATE events SET
                            event_name = ?,
                            summary = ?,
                            full_description = ?,
                            event_date = ?,
                            start_time = ?,
                            end_time = ?,
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
                        $start_time,
                        $end_time,
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
    "SELECT COUNT(*) as count FROM member_events WHERE event_id = ?",
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
    <style>
        :root {
            --primary-color: #0F6B3E;
            --secondary-color: #1B8A56;
            --sidebar-bg: #0b5d35;
            --sidebar-hover: #0d6f40;
            --success-start: #0F6B3E;
            --success-end: #1B8A56;
            --info-start: #0F6B3E;
            --info-end: #1B8A56;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
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
            box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1);
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
        
        /* Top Bar */
        .top-bar {
            background: white;
            padding: 25px 30px;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
            animation: fadeInDown 0.6s ease;
        }
        
        .top-bar h3 {
            color: #2c3e50;
            font-weight: 700;
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
        
        /* Info Banner */
        .alert-info {
            background: linear-gradient(135deg, var(--info-start), var(--info-end));
            border: none;
            color: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(15, 107, 62, 0.3);
            animation: fadeInDown 0.6s ease;
            animation-delay: 0.1s;
            animation-fill-mode: backwards;
        }
        
        .alert-info h6 {
            color: white;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .alert-info .badge {
            background: rgba(255, 255, 255, 0.25) !important;
            color: white !important;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        /* Card */
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border: none;
            margin-bottom: 30px;
            animation: fadeInUp 0.6s ease backwards;
            animation-delay: 0.2s;
        }
        
        .card-body {
            padding: 40px;
        }
        
        /* Form Sections */
        .form-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            border-left: 5px solid var(--primary-color);
            position: relative;
            overflow: hidden;
        }
        
        .form-section::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 150px;
            height: 150px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            opacity: 0.05;
            border-radius: 50%;
            transform: translate(30%, -30%);
        }
        
        .form-section h5 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.3rem;
        }
        
        .form-section h5 i {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            box-shadow: 0 4px 12px rgba(15, 107, 62, 0.25);
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
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(15, 107, 62, 0.2);
        }
        
        textarea.form-control {
            resize: vertical;
        }
        
        .form-text {
            color: #6c757d;
            font-size: 13px;
            margin-top: 6px;
        }
        
        /* Buttons */
        .btn {
            border-radius: 10px;
            padding: 12px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            box-shadow: 0 4px 12px rgba(15, 107, 62, 0.25);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(15, 107, 62, 0.35);
            color: white;
        }
        
        .btn-info {
            background: linear-gradient(135deg, var(--info-start), var(--info-end));
            color: white;
            box-shadow: 0 4px 12px rgba(15, 107, 62, 0.25);
        }
        
        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(15, 107, 62, 0.35);
            color: white;
        }
        
        .btn-outline-secondary {
            border: 2px solid #6c757d;
            color: #6c757d;
            background: transparent;
        }
        
        .btn-outline-secondary:hover {
            background: #6c757d;
            color: white;
            transform: translateY(-2px);
        }
        
        /* Alerts */
        .alert-danger, .alert-success {
            border-radius: 12px;
            padding: 18px 20px;
            border: none;
            margin-bottom: 25px;
        }
        
        /* Animations */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
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

        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
                box-shadow: 6px 0 20px rgba(0, 0, 0, 0.2);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
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

            .top-bar {
                padding: 16px;
            }

            .top-actions {
                width: 100%;
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
            }

            .top-actions .btn {
                width: 100%;
                text-align: center;
            }

            .card-body {
                padding: 20px;
            }

            .form-section {
                padding: 20px;
                margin-bottom: 20px;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions .btn {
                width: 100%;
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

            .top-actions {
                grid-template-columns: 1fr;
            }

            .form-section h5 {
                font-size: 1.1rem;
            }

            .form-section h5 i {
                width: 38px;
                height: 38px;
                font-size: 16px;
            }

            .alert-info {
                padding: 16px;
            }
        }


    </style>
</head>
<body>
    <div class="wrapper">
        <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="container-fluid">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="menu-toggle" id="menuToggle" aria-label="Toggle navigation menu">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h3><i class="fas fa-edit me-2"></i> Edit Event</h3>
                </div>
                <div class="d-flex gap-2 top-actions">
                    <a href="view_event.php?id=<?php echo $event_id; ?>" class="btn btn-primary">
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
                    
                    <!-- Event Information Section -->
                    <div class="form-section">
                        <h5>
                            <i class="fas fa-info-circle"></i>
                            <span>Event Information</span>
                        </h5>
                        
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
                        
                        <div class="mb-0">
                            <label for="full_description" class="form-label">Full Description</label>
                            <textarea class="form-control" id="full_description" name="full_description" rows="4"><?php echo htmlspecialchars($full_description); ?></textarea>
                            <div class="form-text">Detailed information about the event, agenda, speakers, etc. (optional)</div>
                        </div>
                    </div>
                    
                    <!-- Date & Time Section -->
                    <div class="form-section">
                        <h5>
                            <i class="fas fa-calendar-alt"></i>
                            <span>Date & Time</span>
                        </h5>
                        
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
                                       value="<?php echo htmlspecialchars($registration_link); ?>" maxlength="500">
                                <div class="form-text">Optional external registration URL</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Location & Capacity Section -->
                    <div class="form-section">
                        <h5>
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Location & Capacity</span>
                        </h5>
                        
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="location" name="location" 
                                       value="<?php echo htmlspecialchars($location); ?>" maxlength="255">
                                <div class="form-text">Venue address or online meeting link</div>
                            </div>
                            
                            <div class="col-md-4 mb-0">
                                <label for="capacity" class="form-label">Maximum Capacity</label>
                                <input type="number" class="form-control" id="capacity" name="capacity" 
                                       value="<?php echo htmlspecialchars($capacity); ?>" min="0" max="10000">
                                <div class="form-text">Leave 0 for unlimited</div>
                                <?php if ($registration_count > 0 && $capacity > 0 && $registration_count > $capacity): ?>
                                    <div class="alert alert-warning mt-2 mb-0 py-2">
                                        <small><i class="fas fa-exclamation-triangle me-1"></i> Warning: Current registrations (<?php echo $registration_count; ?>) exceed new capacity</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2 form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Update Event
                        </button>
                        <a href="view_event.php?id=<?php echo $event_id; ?>" class="btn btn-primary">
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
    </div>
    
    <?php // Include admin footer which loads Bootstrap and confirmation modal ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>
    
    <script>
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.querySelector('.sidebar');
        const sidebarOverlay = document.getElementById('sidebarBackdrop') || document.getElementById('sidebarOverlay');

        function closeSidebar() {
            if (!sidebar || !sidebarOverlay) return;
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        }

        if (menuToggle && sidebar && sidebarOverlay) {
            menuToggle.addEventListener('click', function () {
                sidebar.classList.toggle('show');
                sidebarOverlay.classList.toggle('show');
            });

            sidebarOverlay.addEventListener('click', closeSidebar);

            window.addEventListener('resize', function () {
                if (window.innerWidth > 991.98) {
                    closeSidebar();
                }
            });
        }

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

