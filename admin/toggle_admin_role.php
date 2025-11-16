<?php
/**
 * ============================================
 * NACOS DASHBOARD - TOGGLE MEMBER ROLE
 * ============================================
 * Purpose: Allow admins to change member roles
 * Security: Admin-only access, validation
 * Created: November 3, 2025
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Include required files
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/flash.php';

// Initialize session
initSession();

// Require admin privileges
requireAdminRole();

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithMessage('members.php', 'Invalid request method.', 'error');
}

// Get and validate input
$member_id = filter_input(INPUT_POST, 'member_id', FILTER_VALIDATE_INT);
$current_role = sanitizeInput($_POST['current_role'] ?? '');
$new_role = sanitizeInput($_POST['new_role'] ?? '');

// Validate inputs
if (!$member_id || !$new_role) {
    redirectWithMessage('members.php', 'Invalid input data.', 'error');
}

// Validate role values
$valid_roles = ['admin', 'executive', 'member'];
if (!in_array($new_role, $valid_roles)) {
    redirectWithMessage('view_member.php?id=' . $member_id, 'Invalid role selected.', 'error');
}

// Get current admin's member ID
$current_admin = getCurrentMember();
$current_admin_id = $current_admin['member_id'];

// Prevent self-demotion
if ($member_id == $current_admin_id && $new_role !== 'admin') {
    redirectWithMessage('view_member.php?id=' . $member_id, 
        'You cannot remove your own admin privileges!', 'error');
}

try {
    $db = getDB();
    
    // Get member details
    $member = $db->fetchOne(
        "SELECT member_id, full_name, role FROM MEMBERS WHERE member_id = ?",
        [$member_id]
    );
    
    if (!$member) {
        redirectWithMessage('members.php', 'Member not found.', 'error');
    }
    
    // Check if role is actually changing
    if ($member['role'] === $new_role) {
        redirectWithMessage('view_member.php?id=' . $member_id, 
            'Member already has this role.', 'info');
    }
    
    // Update the member's role
    $updated = $db->query(
        "UPDATE MEMBERS SET role = ?, updated_at = NOW() WHERE member_id = ?",
        [$new_role, $member_id]
    );
    
    if ($updated) {
        // Log the role change
        logSecurityEvent(
            "Role changed for member #{$member_id} ({$member['full_name']}): {$member['role']} → {$new_role} by admin #{$current_admin_id}",
            'info'
        );
        
        // Create success message
        $role_names = [
            'admin' => 'Administrator',
            'executive' => 'Executive',
            'member' => 'Regular Member'
        ];
        
        $message = sprintf(
            'Successfully updated %s\'s role to %s!',
            htmlspecialchars($member['full_name']),
            $role_names[$new_role]
        );
        
        redirectWithMessage('view_member.php?id=' . $member_id, $message, 'success');
    } else {
        redirectWithMessage('view_member.php?id=' . $member_id, 
            'Failed to update role. Please try again.', 'error');
    }
    
} catch (Exception $e) {
    // Log the error
    error_log("Role update error: " . $e->getMessage());
    
    redirectWithMessage('view_member.php?id=' . $member_id, 
        'An error occurred while updating the role.', 'error');
}
?>
