<?php
/**
 * ============================================
 * NACOS DASHBOARD - EVENT ATTENDANCE TRACKING
 * ============================================
 * Purpose: Mark member attendance and collect feedback
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

// Handle AJAX attendance update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit;
    }
    
    $action = $_POST['action'];
    $member_id = intval($_POST['member_id'] ?? 0);
    
    try {
        if ($action === 'update_status') {
            $status = sanitizeInput($_POST['status'] ?? '');
            
            if (!in_array($status, ['registered', 'attended', 'absent', 'cancelled'])) {
                throw new Exception('Invalid status');
            }
            
            $query = "UPDATE MEMBER_EVENTS SET attendance_status = ? WHERE event_id = ? AND member_id = ?";
            $db->query($query, [$status, $event_id, $member_id]);
            
            echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
            
        } elseif ($action === 'update_rating') {
            $rating = intval($_POST['rating'] ?? 0);
            
            if ($rating < 1 || $rating > 5) {
                throw new Exception('Invalid rating');
            }
            
            $query = "UPDATE MEMBER_EVENTS SET feedback_rating = ? WHERE event_id = ? AND member_id = ?";
            $db->query($query, [$rating, $event_id, $member_id]);
            
            echo json_encode(['success' => true, 'message' => 'Rating updated successfully']);
            
        } elseif ($action === 'mark_all_attended') {
            $query = "UPDATE MEMBER_EVENTS SET attendance_status = 'attended' 
                      WHERE event_id = ? AND attendance_status = 'registered'";
            $db->query($query, [$event_id]);
            
            echo json_encode(['success' => true, 'message' => 'All registered members marked as attended']);
            
        } else {
            throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Get registered members
$members = $db->fetchAll(
    "SELECT m.member_id, m.full_name, m.matric_no, m.email, m.department, m.level,
            me.attendance_status, me.registration_date, me.feedback_rating, me.feedback_comment
     FROM MEMBER_EVENTS me
     JOIN MEMBERS m ON me.member_id = m.member_id
     WHERE me.event_id = ?
     ORDER BY m.full_name ASC",
    [$event_id]
);

// Calculate statistics
$total_registered = count($members);
$attended_count = count(array_filter($members, fn($m) => $m['attendance_status'] === 'attended'));
$absent_count = count(array_filter($members, fn($m) => $m['attendance_status'] === 'absent'));
$pending_count = count(array_filter($members, fn($m) => $m['attendance_status'] === 'registered'));

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Get flash message
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - <?php echo htmlspecialchars($event['event_name']); ?></title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/admin.css">
    
    <style>
        .event-banner {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        
        .stats-row {
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
            text-align: center;
        }
        
        .stat-box .icon {
            width: 50px;
            height: 50px;
            margin: 0 auto 10px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        
        .stat-box h3 {
            margin: 0;
            font-size: 32px;
            font-weight: 700;
        }
        
        .stat-box p {
            margin: 5px 0 0;
            color: #666;
        }
        
        .attendance-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .table-header {
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }
        
        .status-select {
            width: 140px;
            padding: 5px 10px;
            border-radius: 5px;
            border: 1px solid #ddd;
            font-size: 13px;
        }
        
        .status-select:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .rating-stars {
            display: inline-flex;
            gap: 3px;
        }
        
        .rating-stars i {
            cursor: pointer;
            color: #ddd;
            transition: color 0.2s;
        }
        
        .rating-stars i.active,
        .rating-stars i:hover {
            color: #ffc107;
        }
        
        .quick-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .loading-overlay.show {
            display: flex;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>
    
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
                <h3><i class="fas fa-check-square me-2"></i> Event Attendance</h3>
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
        
        <!-- Flash Message -->
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'info'; ?> alert-dismissible fade show">
                <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check' : 'info'; ?>-circle me-2"></i>
                <?php echo htmlspecialchars($flash['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Event Banner -->
        <div class="event-banner">
            <h4 class="mb-2"><?php echo htmlspecialchars($event['event_name']); ?></h4>
            <p class="mb-0">
                <i class="fas fa-calendar me-2"></i>
                <?php echo date('F d, Y', strtotime($event['event_date'])); ?> at 
                <?php echo date('g:i A', strtotime($event['event_time'])); ?>
                <?php if ($event['location']): ?>
                    <i class="fas fa-map-marker-alt ms-3 me-2"></i>
                    <?php echo htmlspecialchars($event['location']); ?>
                <?php endif; ?>
            </p>
        </div>
        
        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                    <i class="fas fa-users"></i>
                </div>
                <h3><?php echo $total_registered; ?></h3>
                <p>Total Registered</p>
            </div>
            
            <div class="stat-box">
                <div class="icon" style="background: linear-gradient(135deg, #11998e, #38ef7d);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3 id="attendedCount"><?php echo $attended_count; ?></h3>
                <p>Attended</p>
            </div>
            
            <div class="stat-box">
                <div class="icon" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                    <i class="fas fa-times-circle"></i>
                </div>
                <h3 id="absentCount"><?php echo $absent_count; ?></h3>
                <p>Absent</p>
            </div>
            
            <div class="stat-box">
                <div class="icon" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
                    <i class="fas fa-clock"></i>
                </div>
                <h3 id="pendingCount"><?php echo $pending_count; ?></h3>
                <p>Pending</p>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <button class="btn btn-success" onclick="markAllAttended()">
                <i class="fas fa-check-double me-2"></i> Mark All as Attended
            </button>
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print me-2"></i> Print Attendance Sheet
            </button>
        </div>
        
        <!-- Attendance Table -->
        <div class="attendance-table">
            <div class="table-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i> Registered Members</h5>
            </div>
            
            <?php if (empty($members)): ?>
                <div class="empty-state">
                    <i class="fas fa-user-slash"></i>
                    <h4>No Members Registered</h4>
                    <p>No members have registered for this event yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Member Details</th>
                                <th>Department</th>
                                <th>Attendance Status</th>
                                <th>Feedback Rating</th>
                                <th>Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $index => $member): ?>
                                <tr id="row-<?php echo $member['member_id']; ?>">
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($member['full_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($member['matric_no']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($member['department']); ?><br>
                                        <small class="text-muted"><?php echo $member['level']; ?> Level</small>
                                    </td>
                                    <td>
                                        <select class="status-select" 
                                                onchange="updateStatus(<?php echo $member['member_id']; ?>, this.value)"
                                                data-member-id="<?php echo $member['member_id']; ?>">
                                            <option value="registered" <?php echo $member['attendance_status'] === 'registered' ? 'selected' : ''; ?>>Registered</option>
                                            <option value="attended" <?php echo $member['attendance_status'] === 'attended' ? 'selected' : ''; ?>>Attended</option>
                                            <option value="absent" <?php echo $member['attendance_status'] === 'absent' ? 'selected' : ''; ?>>Absent</option>
                                            <option value="cancelled" <?php echo $member['attendance_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                    </td>
                                    <td>
                                        <div class="rating-stars" data-member-id="<?php echo $member['member_id']; ?>" data-rating="<?php echo $member['feedback_rating'] ?? 0; ?>">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?php echo $i <= ($member['feedback_rating'] ?? 0) ? 'active' : ''; ?>" 
                                                   onclick="updateRating(<?php echo $member['member_id']; ?>, <?php echo $i; ?>)"></i>
                                            <?php endfor; ?>
                                        </div>
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
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const csrfToken = '<?php echo $csrf_token; ?>';
        const eventId = <?php echo $event_id; ?>;
        
        function showLoading() {
            document.getElementById('loadingOverlay').classList.add('show');
        }
        
        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('show');
        }
        
        function showNotification(message, type = 'success') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 9998; min-width: 300px;';
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            
            setTimeout(() => {
                alertDiv.remove();
            }, 3000);
        }
        
        function updateStatus(memberId, status) {
            showLoading();
            
            fetch('event_attendance.php?id=' + eventId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_status&member_id=${memberId}&status=${status}&csrf_token=${csrfToken}`
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showNotification(data.message, 'success');
                    updateStatistics();
                } else {
                    showNotification(data.message, 'danger');
                }
            })
            .catch(error => {
                hideLoading();
                showNotification('Error updating status', 'danger');
            });
        }
        
        function updateRating(memberId, rating) {
            showLoading();
            
            fetch('event_attendance.php?id=' + eventId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_rating&member_id=${memberId}&rating=${rating}&csrf_token=${csrfToken}`
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    // Update star display
                    const stars = document.querySelector(`.rating-stars[data-member-id="${memberId}"]`);
                    stars.querySelectorAll('i').forEach((star, index) => {
                        if (index < rating) {
                            star.classList.add('active');
                        } else {
                            star.classList.remove('active');
                        }
                    });
                    showNotification(data.message, 'success');
                } else {
                    showNotification(data.message, 'danger');
                }
            })
            .catch(error => {
                hideLoading();
                showNotification('Error updating rating', 'danger');
            });
        }
        
        function markAllAttended() {
            // Use modal-based confirmation
            window.confirmModal('Mark all registered members as attended?').then(function(ok){
                if (!ok) return;

                showLoading();

                fetch('event_attendance.php?id=' + eventId, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=mark_all_attended&csrf_token=${csrfToken}`
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        // Update all dropdowns
                        document.querySelectorAll('.status-select').forEach(select => {
                            if (select.value === 'registered') {
                                select.value = 'attended';
                            }
                        });
                        updateStatistics();
                        showNotification(data.message, 'success');
                    } else {
                        showNotification(data.message, 'danger');
                    }
                })
                .catch(error => {
                    hideLoading();
                    showNotification('Error marking attendance', 'danger');
                });
            });
        }
        
        function updateStatistics() {
            const selects = document.querySelectorAll('.status-select');
            let attended = 0, absent = 0, pending = 0;
            
            selects.forEach(select => {
                const status = select.value;
                if (status === 'attended') attended++;
                else if (status === 'absent') absent++;
                else if (status === 'registered') pending++;
            });
            
            document.getElementById('attendedCount').textContent = attended;
            document.getElementById('absentCount').textContent = absent;
            document.getElementById('pendingCount').textContent = pending;
        }
        
        // Auto-dismiss alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                const closeBtn = alert.querySelector('.btn-close');
                if (closeBtn) closeBtn.click();
            });
        }, 5000);
    </script>

    <?php // Include admin footer which loads Bootstrap and confirmation modal ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>

