<?php
/**
 * ============================================
 * NACOS DASHBOARD - DELETE MEMBER
 * ============================================
 * Purpose: Delete or deactivate member
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

// Get member ID
$member_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($member_id <= 0) {
    redirectWithMessage('members.php', 'Invalid member ID', 'error');
}

// Get member data
$member = $db->fetchOne(
    "SELECT * FROM MEMBERS WHERE member_id = ?", 
    [$member_id]
);

if (!$member) {
    redirectWithMessage('members.php', 'Member not found', 'error');
}

// Check if member has projects
$project_count = $db->fetchOne(
    "SELECT COUNT(*) as count FROM MEMBER_PROJECTS WHERE member_id = ?",
    [$member_id]
)['count'];

// Check if member has events
$event_count = $db->fetchOne(
    "SELECT COUNT(*) as count FROM MEMBER_EVENTS WHERE member_id = ?",
    [$member_id]
)['count'];

$has_associations = ($project_count > 0 || $event_count > 0);

// Handle form submission
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'deactivate') {
            // Deactivate member (soft delete)
            try {
                $query = "UPDATE MEMBERS SET membership_status = 'inactive' WHERE member_id = ?";
                $db->query($query, [$member_id]);
                
                redirectWithMessage('members.php', 'Member deactivated successfully', 'success');
            } catch (Exception $e) {
                $error_message = 'Error deactivating member: ' . $e->getMessage();
            }
            
        } elseif ($action === 'delete') {
            // Hard delete - first delete associations, then member
            try {
                $db->beginTransaction();
                
                // Delete from MEMBER_PROJECTS
                $db->query("DELETE FROM MEMBER_PROJECTS WHERE member_id = ?", [$member_id]);
                
                // Delete from MEMBER_EVENTS
                $db->query("DELETE FROM MEMBER_EVENTS WHERE member_id = ?", [$member_id]);
                
                // Delete member
                $db->query("DELETE FROM MEMBERS WHERE member_id = ?", [$member_id]);
                
                $db->commit();
                
                redirectWithMessage('members.php', 'Member deleted permanently', 'success');
            } catch (Exception $e) {
                $db->rollBack();
                $error_message = 'Error deleting member: ' . $e->getMessage();
            }
        } else {
            $error_message = 'Invalid action specified';
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
    <title>Delete Member - <?php echo htmlspecialchars($member['full_name']); ?></title>
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
            max-width: 600px;
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
        
        .member-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .member-info .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .member-info .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
        }
        
        .info-value {
            color: #333;
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
        
        .btn-deactivate {
            background: #ffc107;
            color: #000;
        }
        
        .btn-deactivate:hover {
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
        
        .btn-cancel {
            background: #6c757d;
            color: white;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
            text-decoration: none;
            color: white;
        }
        
        .association-list {
            list-style: none;
            padding: 0;
            margin: 10px 0;
        }
        
        .association-list li {
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .association-list li:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="delete-container">
        <div class="delete-card">
            <div class="delete-header">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Delete Member</h3>
                <p class="mb-0">This action requires careful consideration</p>
            </div>
            
            <div class="delete-body">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle me-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Member Information -->
                <div class="member-info">
                    <h5 class="mb-3">Member Details</h5>
                    <div class="info-row">
                        <span class="info-label">Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars($member['full_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Matric No:</span>
                        <span class="info-value"><?php echo htmlspecialchars($member['matric_no']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?php echo htmlspecialchars($member['email']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Department:</span>
                        <span class="info-value"><?php echo htmlspecialchars($member['department']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status:</span>
                        <span class="info-value">
                            <span class="badge bg-<?php echo $member['membership_status'] === 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($member['membership_status']); ?>
                            </span>
                        </span>
                    </div>
                </div>
                
                <!-- Associations Warning -->
                <?php if ($has_associations): ?>
                    <div class="warning-box">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div>
                                <h6 class="mb-2">This member has associations</h6>
                                <ul class="association-list">
                                    <?php if ($project_count > 0): ?>
                                        <li><i class="fas fa-project-diagram me-2"></i> <?php echo $project_count; ?> project(s)</li>
                                    <?php endif; ?>
                                    <?php if ($event_count > 0): ?>
                                        <li><i class="fas fa-calendar-alt me-2"></i> <?php echo $event_count; ?> event(s)</li>
                                    <?php endif; ?>
                                </ul>
                                <p class="mb-0 mt-2"><strong>Deleting will remove all associations permanently!</strong></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Action Options -->
                <div class="mb-4">
                    <h6 class="mb-3">Choose an action:</h6>
                    
                    <!-- Deactivate Option -->
                    <div class="border rounded p-3 mb-3">
                        <h6 class="text-warning"><i class="fas fa-pause-circle me-2"></i> Option 1: Deactivate (Recommended)</h6>
                        <p class="text-muted mb-2">
                            Member will be marked as inactive but all data will be preserved. 
                            This is reversible and maintains historical records.
                        </p>
                        <form method="POST" class="d-inline confirm-action-form" data-message="Mark this member as inactive?">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="deactivate">
                            <button type="submit" class="action-btn btn-deactivate">
                                <i class="fas fa-pause-circle"></i>
                                Deactivate Member
                            </button>
                        </form>
                    </div>
                    
                    <!-- Delete Option -->
                    <div class="border rounded p-3">
                        <h6 class="text-danger"><i class="fas fa-trash-alt me-2"></i> Option 2: Delete Permanently</h6>
                        <p class="text-muted mb-2">
                            Permanently removes member and all associated records. 
                            <strong>This action cannot be undone!</strong>
                        </p>
                        <div class="danger-box mb-2">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-skull-crossbones"></i>
                                <strong>Warning: This will delete all project and event associations!</strong>
                            </div>
                        </div>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="button" class="action-btn btn-delete confirm-action-btn" data-action="delete" data-message="<?php echo htmlspecialchars('⚠️ WARNING ⚠️\n\nThis will PERMANENTLY DELETE:\n- Member profile\n- All project associations ('.$project_count.')\n- All event registrations ('.$event_count.')\n\nThis action CANNOT be undone!\n\nType YES in your mind if you understand and want to proceed.'); ?>">
                                <i class="fas fa-trash-alt"></i>
                                Delete Permanently
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Cancel Button -->
                <a href="members.php" class="action-btn btn-cancel">
                    <i class="fas fa-times"></i>
                    Cancel
                </a>
            </div>
        </div>
    </div>
    
    <?php // Include admin footer which loads Bootstrap and confirmation modal ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
