<?php
/**
 * ============================================
 * NACOS DASHBOARD - MEMBER LOGOUT
 * ============================================
 * Purpose: Handle member logout
 * Access: Members only
 * Created: November 3, 2025
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Include required files
require_once '../includes/auth.php';

// Destroy the session
destroySession();

// Redirect to the login page with a message
redirectWithMessage('login.php', 'You have been successfully logged out.', 'success');
