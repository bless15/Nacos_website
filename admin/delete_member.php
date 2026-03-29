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

// Bootstrap and includes
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Require full admin privileges
requireFullAdminRole();

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
    "SELECT * FROM members WHERE member_id = ?", 
    [$member_id]
);

if (!$member) {
    redirectWithMessage('members.php', 'Member not found', 'error');
}

// Check if member has projects
$project_count = $db->fetchOne(
    "SELECT COUNT(*) as count FROM member_projects WHERE member_id = ?",
    [$member_id]
)['count'];

// Check if member has events
$event_count = $db->fetchOne(
    "SELECT COUNT(*) as count FROM member_events WHERE member_id = ?",
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
                $query = "UPDATE members SET membership_status = 'inactive' WHERE member_id = ?";
                $db->query($query, [$member_id]);
                
                redirectWithMessage('members.php', 'Member deactivated successfully', 'success');
            } catch (Exception $e) {
                $error_message = 'Error deactivating member: ' . $e->getMessage();
            }
            
        } elseif ($action === 'delete') {
            // Hard delete - first delete associations, then member
            try {
                $db->beginTransaction();
                
                // Delete from member_projects
                $db->query("DELETE FROM member_projects WHERE member_id = ?", [$member_id]);
                
                // Delete from member_events
                $db->query("DELETE FROM member_events WHERE member_id = ?", [$member_id]);
                
                // Delete member
                $db->query("DELETE FROM members WHERE member_id = ?", [$member_id]);
                
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
            --danger-start: #f093fb;
            --danger-end: #f5576c;
            --warning-start: #ffc107;
            --warning-end: #ff6b6b;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px;
        }
        
        .delete-container {
            max-width: 700px;
            width: 100%;
            animation: fadeInUp 0.6s ease;
        }
        
        .delete-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            border: 3px solid #dc3545;
        }
        
        .delete-header {
            background: linear-gradient(135deg, var(--danger-start), var(--danger-end));
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .delete-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .delete-header i {
            font-size: 70px;
            margin-bottom: 20px;
            animation: pulse 2s ease-in-out infinite;
            display: inline-block;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.9; }
        }
        
        .delete-header h3 {
            margin: 0 0 10px;
            font-weight: 700;
            font-size: 2rem;
        }
        
        .delete-header p {
            opacity: 0.95;
            font-size: 15px;
        }
        
        .delete-body {
            padding: 40px;
        }
        
        .member-info {
            background: linear-gradient(135deg, rgba(79, 172, 254, 0.08), rgba(0, 242, 254, 0.08));
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            border-left: 5px solid #4facfe;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        
        .member-info h5 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 20px;
            font-size: 1.2rem;
        }
        
        .member-info .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        }
        
        .member-info .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
            font-size: 14px;
        }
        
        .info-value {
            color: #2c3e50;
            font-weight: 500;
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge.bg-success {
            background: linear-gradient(135deg, #11998e, #38ef7d) !important;
        }
        
        .badge.bg-secondary {
            background: linear-gradient(135deg, #6c757d, #495057) !important;
        }
        
        .warning-box {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.1), rgba(255, 107, 107, 0.1));
            border: 3px solid var(--warning-start);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.2);
        }
        
        .warning-box i {
            color: var(--warning-start);
            font-size: 28px;
            margin-right: 12px;
            animation: pulse 2s ease-in-out infinite;
        }
        
        .warning-box h6 {
            color: #856404;
            font-weight: 700;
        }
        
        .danger-box {
            background: linear-gradient(135deg, rgba(240, 147, 251, 0.1), rgba(245, 87, 108, 0.1));
            border: 3px solid var(--danger-start);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 4px 15px rgba(240, 147, 251, 0.2);
        }
        
        .danger-box i {
            color: var(--danger-start);
            font-size: 24px;
            margin-right: 12px;
            animation: pulse 2s ease-in-out infinite;
        }
        
        .action-buttons {
            display: grid;
            gap: 15px;
        }
        
        .action-btn {
            padding: 16px 25px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 15px;
        }
        
        .action-btn i {
            margin-right: 10px;
            font-size: 16px;
        }
        
        .btn-deactivate {
            background: linear-gradient(135deg, var(--warning-start), var(--warning-end));
            color: white;
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
        }
        
        .btn-deactivate:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 193, 7, 0.5);
            animation: shake 0.5s;
        }
        
        .btn-delete {
            background: linear-gradient(135deg, var(--danger-start), var(--danger-end));
            color: white;
            box-shadow: 0 4px 15px rgba(240, 147, 251, 0.3);
        }
        
        .btn-delete:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(240, 147, 251, 0.5);
            animation: shake 0.5s;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateY(-3px) translateX(0); }
            25% { transform: translateY(-3px) translateX(-5px); }
            75% { transform: translateY(-3px) translateX(5px); }
        }
        
        .btn-cancel {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }
        
        .btn-cancel:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.5);
            text-decoration: none;
            color: white;
        }
        
        .association-list {
            list-style: none;
            padding: 0;
            margin: 15px 0;
        }
        
        .association-list li {
            padding: 10px 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
            color: #495057;
            font-weight: 500;
            display: flex;
            align-items: center;
        }
        
        .association-list li i {
            color: var(--warning-start);
        }
        
        .association-list li:last-child {
            border-bottom: none;
        }
        
        /* Option Boxes */
        .border.rounded {
            border-radius: 16px !important;
            border: 2px solid #e9ecef !important;
            padding: 20px !important;
            margin-bottom: 20px !important;
            transition: all 0.3s ease;
            background: white;
        }
        
        .border.rounded:hover {
            border-color: #dee2e6 !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        
        .border.rounded h6 {
            font-weight: 700;
            margin-bottom: 12px;
        }
        
        .border.rounded p {
            font-size: 14px;
            line-height: 1.6;
        }
        
        /* Alert */
        .alert {
            border-radius: 12px;
            border: none;
            padding: 15px 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            animation: fadeInDown 0.5s ease;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, rgba(240, 147, 251, 0.15), rgba(245, 87, 108, 0.15));
            border-left: 5px solid var(--danger-start);
            color: #721c24;
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
        
        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            
            .delete-body {
                padding: 25px;
            }
            
            .delete-header {
                padding: 30px 20px;
            }
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
