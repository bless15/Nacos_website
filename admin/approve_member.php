<?php
/**
 * ============================================
 * NACOS DASHBOARD - APPROVE MEMBER
 * ============================================
 * Purpose: Approve or reject pending member registrations
 * Access: Admin only
 * Created: November 3, 2025
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Include required files
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../config/email.php';

// Require admin login
requireAdminRole();

// Initialize database
$db = getDB();

// Get member ID
$member_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch member details
$member = $db->fetchOne("SELECT * FROM MEMBERS WHERE member_id = :id", [':id' => $member_id]);

if (!$member) {
    redirectWithMessage('members.php', 'Member not found.', 'error');
}

// Check if already approved
if ($member['is_approved']) {
    redirectWithMessage('members.php', 'This member is already approved.', 'info');
}

$error_message = '';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = "Invalid request. Please try again.";
    } else {
        $action = $_POST['action'] ?? '';
        $admin_id = getCurrentMember()['member_id'];
        
        if ($action === 'approve') {
            try {
                // Approve member
                $db->query("
                    UPDATE MEMBERS 
                    SET is_approved = 1, 
                        approved_by = :admin_id, 
                        approval_date = NOW() 
                    WHERE member_id = :member_id
                ", [
                    ':admin_id' => $admin_id,
                    ':member_id' => $member_id
                ]);
                
                // Send approval email
                sendApprovalEmail($member['email'], $member['full_name']);
                
                redirectWithMessage('members.php', 'Member approved successfully! Approval email sent.', 'success');
            } catch (Exception $e) {
                $error_message = "An error occurred while approving the member. Please try again.";
                logSecurityEvent("Member approval failed: " . $e->getMessage(), 'error');
            }
        } elseif ($action === 'reject') {
            try {
                // Send rejection email before deleting
                sendRejectionEmail($member['email'], $member['full_name']);
                
                // Delete member (rejected)
                $db->query("DELETE FROM MEMBERS WHERE member_id = :id", [':id' => $member_id]);
                
                redirectWithMessage('members.php', 'Member registration rejected. Notification email sent.', 'success');
            } catch (Exception $e) {
                $error_message = "An error occurred while rejecting the member. Please try again.";
                logSecurityEvent("Member rejection failed: " . $e->getMessage(), 'error');
            }
        } else {
            redirectWithMessage('members.php', 'Action cancelled.', 'info');
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
    <title>Approve Member - NACOS Admin</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    
    <style>
        .approval-card {
            background: #fff;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .member-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .info-row {
            display: flex;
            padding: 0.75rem 0;
            border-bottom: 1px solid #dee2e6;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            width: 150px;
            color: #495057;
        }
        .info-value {
            flex: 1;
            color: #212529;
        }
        .action-section {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }
        .approve-btn {
            background: linear-gradient(135deg, #11998e, #38ef7d);
            border: none;
            padding: 12px 40px;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .approve-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(17, 153, 142, 0.4);
        }
        .reject-btn {
            background: linear-gradient(135deg, #f093fb, #f5576c);
            border: none;
            padding: 12px 40px;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .reject-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 87, 108, 0.4);
        }
        .warning-icon {
            font-size: 4rem;
            color: #ffc107;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navigation -->
            <?php include 'includes/navbar.php'; ?>
            
            <!-- Page Content -->
            <div class="container-fluid px-4 py-4">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-0">Approve Member Registration</h1>
                        <p class="text-muted">Review and approve new member application</p>
                    </div>
                    <a href="members.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Members
                    </a>
                </div>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="approval-card">
                            <div class="text-center">
                                <i class="fas fa-user-clock warning-icon"></i>
                                <h4 class="mb-3">Pending Approval</h4>
                                <p class="text-muted mb-4">
                                    Review the details below and decide whether to approve or reject this registration.
                                </p>
                            </div>
                            
                            <div class="member-info">
                                <h5 class="mb-3"><i class="fas fa-id-card me-2"></i> Member Details</h5>
                                
                                <div class="info-row">
                                    <div class="info-label">Full Name:</div>
                                    <div class="info-value"><strong><?php echo htmlspecialchars($member['full_name']); ?></strong></div>
                                </div>
                                
                                <div class="info-row">
                                    <div class="info-label">Matric Number:</div>
                                    <div class="info-value"><strong><?php echo htmlspecialchars($member['matric_no']); ?></strong></div>
                                </div>
                                
                                <div class="info-row">
                                    <div class="info-label">Email:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($member['email']); ?></div>
                                </div>
                                
                                <?php if (!empty($member['phone'])): ?>
                                <div class="info-row">
                                    <div class="info-label">Phone:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($member['phone']); ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="info-row">
                                    <div class="info-label">Department:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($member['department']); ?></div>
                                </div>
                                
                                <div class="info-row">
                                    <div class="info-label">Level:</div>
                                    <div class="info-value"><span class="badge bg-primary"><?php echo $member['level']; ?> Level</span></div>
                                </div>
                                
                                <div class="info-row">
                                    <div class="info-label">Registration Date:</div>
                                    <div class="info-value"><?php echo date('F j, Y \a\t g:i A', strtotime($member['created_at'])); ?></div>
                                </div>
                                
                                <div class="info-row">
                                    <div class="info-label">Status:</div>
                                    <div class="info-value">
                                        <span class="badge bg-warning text-dark">
                                            <i class="fas fa-clock"></i> Pending Approval
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <form action="approve_member.php?id=<?php echo $member_id; ?>" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                
                                <div class="action-section">
                                    <button type="submit" name="action" value="approve" class="btn btn-success approve-btn">
                                        <i class="fas fa-check-circle"></i> Approve Member
                                    </button>
                                    
                                    <button type="button" name="action" value="reject" class="btn btn-danger reject-btn confirm-action-btn" data-action="reject" data-message="Are you sure you want to reject this registration? The member record will be deleted.">
                                        <i class="fas fa-times-circle"></i> Reject & Delete
                                    </button>
                                </div>
                                
                                <div class="text-center mt-3">
                                    <a href="members.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Cancel & Go Back
                                    </a>
                                </div>
                            </form>
                            
                            <div class="alert alert-info mt-4">
                                <i class="fas fa-info-circle"></i> <strong>Note:</strong> 
                                Once approved, the member will be able to log in using their matriculation number and password.
                                If rejected, their registration will be permanently deleted.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php // Include admin footer which loads Bootstrap and confirmation modal ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
