<?php
/**
 * ============================================
 * NACOS DASHBOARD - ADMIN LOGOUT
 * ============================================
 * Purpose: Securely log out administrator and destroy session
 * Security: Session cleanup, security logging
 * Created: November 2, 2025
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Include required files
require_once '../config/database.php';
require_once '../includes/auth.php';

// Initialize session
initSession();

// Log logout event - handle both admin and member sessions
if (isLoggedIn()) {
    // Administrator logout
    $username = $_SESSION['username'] ?? 'unknown';
    logSecurityEvent("Administrator logged out: $username", 'info');
} elseif (isMemberLoggedIn()) {
    // Member logout
    $member_name = $_SESSION['member_full_name'] ?? 'unknown';
    $member_matric = $_SESSION['member_matric_no'] ?? 'unknown';
    logSecurityEvent("Member logged out: $member_name ($member_matric)", 'info');
}

// Destroy session
destroySession();

// Redirect to appropriate login page
session_start();
$_SESSION['flash_message'] = 'You have been successfully logged out.';
$_SESSION['flash_type'] = 'success';

// Redirect to public login (members will use this)
header("Location: ../public/login.php");
exit();
