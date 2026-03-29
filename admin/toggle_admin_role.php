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

// Bootstrap and includes
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';

// Initialize session
initSession();

// Require full admin privileges
requireFullAdminRole();

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithMessage('members.php', 'Invalid request method.', 'error');
}

// Get and validate input
$member_id = filter_input(INPUT_POST, 'member_id', FILTER_VALIDATE_INT);
$current_role = sanitizeInput($_POST['current_role'] ?? '');
$new_role = sanitizeInput($_POST['new_role'] ?? '');
$executive_position = sanitizeInput($_POST['executive_position'] ?? '');

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

    // Ensure executive_position column exists
    $exec_position_column = $db->fetchOne(
        "SHOW COLUMNS FROM members LIKE 'executive_position'"
    );
    if (!$exec_position_column) {
        redirectWithMessage(
            'view_member.php?id=' . $member_id,
            'Database update required: run executive_position migration before assigning executive titles.',
            'error'
        );
    }
    
    // Get member details
    $member = $db->fetchOne(
        "SELECT member_id, full_name, role, executive_position FROM members WHERE member_id = ?",
        [$member_id]
    );
    
    if (!$member) {
        redirectWithMessage('members.php', 'Member not found.', 'error');
    }
    
    // Check if role/position is actually changing
    if (
        $member['role'] === $new_role
        && ($new_role !== 'executive' || (($member['executive_position'] ?? '') === $executive_position))
    ) {
        redirectWithMessage('view_member.php?id=' . $member_id, 
            'Member already has this role.', 'info');
    }

    $position_limits = [
        'social_director' => 1,
        'general_secretary' => 2,
        'academic_director' => 1,
        'creative_innovative_director' => 1,
        'public_relations_officer' => 1,
    ];

    // Executive role validations and limits
    if ($new_role === 'executive') {
        if (!isset($position_limits[$executive_position])) {
            redirectWithMessage(
                'view_member.php?id=' . $member_id,
                'Select a valid executive position.',
                'error'
            );
        }

        // Enforce total executive limit (maximum 6 executives at any time)
        $executive_count_row = $db->fetchOne(
            "SELECT COUNT(*) AS total FROM members WHERE role = 'executive'"
        );
        $executive_count = (int)($executive_count_row['total'] ?? 0);

        if ($executive_count >= 6 && $member['role'] !== 'executive') {
            redirectWithMessage(
                'view_member.php?id=' . $member_id,
                'Executive limit reached. Only 6 members can have executive access.',
                'error'
            );
        }

        // Enforce per-position limits
        $position_count_row = $db->fetchOne(
            "SELECT COUNT(*) AS total FROM members WHERE role = 'executive' AND executive_position = ? AND member_id != ?",
            [$executive_position, $member_id]
        );
        $position_count = (int)($position_count_row['total'] ?? 0);

        if ($position_count >= $position_limits[$executive_position]) {
            $position_names = [
                'social_director' => 'Social Director',
                'general_secretary' => 'General Secretary',
                'academic_director' => 'Academic Director',
                'creative_innovative_director' => 'Creative and Innovative Director',
                'public_relations_officer' => 'PRO (Public Relations Officer)',
            ];

            redirectWithMessage(
                'view_member.php?id=' . $member_id,
                $position_names[$executive_position] . ' slot is full.',
                'error'
            );
        }
    }

    $new_executive_position = $new_role === 'executive' ? $executive_position : null;
    
    // Update the member's role
    $updated = $db->query(
        "UPDATE members SET role = ?, executive_position = ?, updated_at = NOW() WHERE member_id = ?",
        [$new_role, $new_executive_position, $member_id]
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

        if ($new_role === 'executive') {
            $position_label_map = [
                'social_director' => 'Social Director',
                'general_secretary' => 'General Secretary',
                'academic_director' => 'Academic Director',
                'creative_innovative_director' => 'Creative and Innovative Director',
                'public_relations_officer' => 'PRO (Public Relations Officer)',
            ];
            $message .= ' Position: ' . $position_label_map[$executive_position] . '.';
        }

        // Refresh member role session if current user updated themselves
        if (isset($_SESSION['member_id']) && (int)$_SESSION['member_id'] === (int)$member_id) {
            $_SESSION['member_role'] = $new_role;
            $_SESSION['member_executive_position'] = $new_executive_position;
        }
        
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
