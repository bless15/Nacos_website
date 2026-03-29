<?php
/**
 * ============================================
 * NACOS DASHBOARD - AUTHENTICATION FUNCTIONS
 * ============================================
 * Purpose: Session management, login verification, role-based access
 * Security: Secure session handling, password verification
 * Created: November 2, 2025
 * ============================================
 */

// Prevent direct access
if (!defined('NACOS_ACCESS')) {
    die('Direct access not permitted');
}

// Session management delegated to includes/session.php
require_once __DIR__ . '/session.php';

// Ensure session is initialized when auth functions are used
if (php_sapi_name() !== 'cli') {
    try {
        initSession();
    } catch (Throwable $e) {
        error_log('initSession() failed on auth include: ' . $e->getMessage());
    }
}

// ============================================
// AUTHENTICATION FUNCTIONS
// ============================================

/**
 * Authenticate user with username and password
 * @param string $username
 * @param string $password
 * @return array|false User data on success, false on failure
 */
function authenticateUser($username, $password) {
    try {
        $db = getDB();
        
        $query = "SELECT admin_id, username, password_hash, role, full_name, email, status, last_login 
                  FROM administrators 
                  WHERE username = ? 
                  LIMIT 1";
        
        $user = $db->fetchOne($query, [$username]);
        
        if (!$user) {
            return false; // Username not found
        }
        
        // Check if account is active
        if ($user['status'] !== 'active') {
            return false; // Account is inactive
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            return false; // Invalid password
        }
        
        // Update last login timestamp
        $updateQuery = "UPDATE administrators SET last_login = NOW() WHERE admin_id = ?";
        $db->query($updateQuery, [$user['admin_id']]);
        
        return $user;
        
    } catch (Exception $e) {
        error_log("Authentication error: " . $e->getMessage());
        return false;
    }
}

/**
 * Login user and create session
 * @param array $user User data from database
 */
function loginUser($user) {
    initSession();
    
    // Store user data in session
    $_SESSION['logged_in'] = true;
    $_SESSION['admin_id'] = $user['admin_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    
    // Set user IP for additional security
    $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    initSession();
    
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return false;
    }
    
    // Check for session timeout (30 minutes of inactivity)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        destroySession();
        return false;
    }
    
    // Check if IP changed (potential session hijacking)
    $current_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (isset($_SESSION['user_ip']) && $_SESSION['user_ip'] !== $current_ip) {
        destroySession();
        return false;
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    
    return true;
}

/**
 * Require user to be logged in (redirect if not)
 * @param string $redirect_url URL to redirect to if not logged in
 */
function requireLogin($redirect_url = 'login.php') {
    if (!isLoggedIn()) {
        header("Location: $redirect_url");
        exit();
    }
}

/**
 * Require specific role(s)
 * @param array|string $allowed_roles Single role or array of allowed roles
 * @param string $redirect_url URL to redirect to if unauthorized
 */
function requireRole($allowed_roles, $redirect_url = 'index.php') {
    requireLogin();
    
    if (!is_array($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }
    
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        header("Location: $redirect_url");
        exit();
    }
}

/**
 * Check if user has specific role
 * @param string $role Role to check
 * @return bool
 */
function hasRole($role) {
    initSession();
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Check if user is super admin
 * @return bool
 */
function isSuperAdmin() {
    return hasRole('super_admin');
}

/**
 * Check if user is admin or super admin
 * @return bool
 */
function isAdmin() {
    return hasRole('admin') || hasRole('super_admin');
}

/**
 * Get current logged-in user's data
 * @return array|null
 */
function getCurrentUser() {
    initSession();
    
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'admin_id' => $_SESSION['admin_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'full_name' => $_SESSION['full_name'] ?? null,
        'email' => $_SESSION['email'] ?? null,
        'login_time' => $_SESSION['login_time'] ?? null,
    ];
}

/**
 * Get current user's full name
 * @return string
 */
function getCurrentUserName() {
    return $_SESSION['full_name'] ?? 'User';
}

/**
 * Get current user's role
 * @return string
 */
function getCurrentUserRole() {
    return $_SESSION['role'] ?? 'guest';
}

// ============================================
// MEMBER AUTHENTICATION FUNCTIONS
// ============================================

/**
 * Check if a member is logged in
 * @return bool
 */
function isMemberLoggedIn() {
    initSession();
    
    if (!isset($_SESSION['member_logged_in']) || $_SESSION['member_logged_in'] !== true) {
        return false;
    }
    
    // Check for session timeout (30 minutes of inactivity)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        destroySession();
        return false;
    }
    
    // Check if IP changed
    $current_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (isset($_SESSION['user_ip']) && $_SESSION['user_ip'] !== $current_ip) {
        destroySession();
        return false;
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    
    return true;
}

/**
 * Require member to be logged in (redirect if not)
 * @param string $redirect_url URL to redirect to if not logged in
 */
function requireMemberLogin($redirect_url = 'login.php') {
    if (!isMemberLoggedIn()) {
        redirectWithMessage($redirect_url, 'You must be logged in to access this page.', 'error');
    }
}

/**
 * Check if logged-in member has admin role
 * @return bool
 */
function isMemberAdmin() {
    initSession();
    return isset($_SESSION['member_role']) && $_SESSION['member_role'] === 'admin';
}

/**
 * Check if logged-in member has executive role
 * @return bool
 */
function isMemberExecutive() {
    initSession();
    return isset($_SESSION['member_role']) && $_SESSION['member_role'] === 'executive';
}

/**
 * Check if logged-in member has admin panel access (admin or executive)
 * @return bool
 */
function hasMemberAdminPanelAccess() {
    return isMemberAdmin() || isMemberExecutive();
}

/**
 * Get logged-in executive position
 * @return string|null
 */
function getMemberExecutivePosition() {
    initSession();

    if (isset($_SESSION['member_executive_position']) && $_SESSION['member_executive_position'] !== '') {
        return $_SESSION['member_executive_position'];
    }

    if (isset($_SESSION['member_id'])) {
        $db = getDB();
        $member = $db->fetchOne("SELECT executive_position FROM members WHERE member_id = ?", [$_SESSION['member_id']]);
        if ($member && !empty($member['executive_position'])) {
            $_SESSION['member_executive_position'] = $member['executive_position'];
            return $member['executive_position'];
        }
    }

    return null;
}

/**
 * Check if executive can access a specific admin page
 * @param string $page
 * @return bool
 */
function canExecutiveAccessPage($page) {
    $always_allowed = ['index.php', 'logout.php'];
    if (in_array($page, $always_allowed, true)) {
        return true;
    }

    $executive_position = getMemberExecutivePosition();
    if (!$executive_position) {
        return false;
    }

    $permission_map = [
        'social_director' => [
            'events.php', 'add_event.php', 'edit_event.php', 'view_event.php', 'delete_event.php', 'event_attendance.php',
            'documents.php', 'add_document.php', 'view_document.php'
        ],
        'general_secretary' => [
            'documents.php', 'add_document.php', 'edit_document.php', 'view_document.php', 'delete_document.php',
            'resources.php', 'add_resource.php', 'edit_resource.php', 'view_resource.php', 'delete_resource.php',
            'past_questions.php'
        ],
        'academic_director' => [
            'resources.php', 'add_resource.php', 'edit_resource.php', 'view_resource.php', 'delete_resource.php',
            'documents.php', 'add_document.php', 'view_document.php',
            'past_questions.php'
        ],
        'creative_innovative_director' => [
            'resources.php', 'add_resource.php', 'edit_resource.php', 'view_resource.php', 'delete_resource.php',
            'projects.php', 'add_project.php', 'edit_project.php', 'view_project.php', 'delete_project.php', 'toggle_featured.php',
            'documents.php', 'add_document.php', 'view_document.php',
            'past_questions.php'
        ],
        'public_relations_officer' => [
            'announcements.php', 'add_announcement.php', 'edit_announcement.php', 'delete_announcement.php',
            'events.php', 'add_event.php', 'edit_event.php', 'view_event.php', 'delete_event.php', 'event_attendance.php',
            'documents.php', 'add_document.php', 'view_document.php'
        ],
    ];

    if (!isset($permission_map[$executive_position])) {
        return false;
    }

    return in_array($page, $permission_map[$executive_position], true);
}

/**
 * Require member to have admin role (redirect if not)
 * @param string $redirect_url URL to redirect to if not admin
 */
function requireAdminRole($redirect_url = '../public/dashboard.php') {
    // Check if logged in as admin from administrators table
    if (isLoggedIn()) {
        return; // Admin from administrators table is allowed
    }
    
    // Otherwise, ensure member is logged in
    if (!isMemberLoggedIn()) {
        // If not logged in at all, send user to the admin login page (not the public/member login)
        redirectWithMessage('../admin/login.php', 'You must be logged in to access this page.', 'error');
    }
    
    // Then check if they have admin panel role
    if (!hasMemberAdminPanelAccess()) {
        redirectWithMessage($redirect_url, 'Access denied. Admin or executive privileges required.', 'error');
    }

    // Apply executive position-based restrictions
    if (isMemberExecutive()) {
        $current_page = basename($_SERVER['PHP_SELF'] ?? 'index.php');
        if (!canExecutiveAccessPage($current_page)) {
            redirectWithMessage('index.php', 'Access denied for your executive position.', 'error');
        }
    }
}

/**
 * Require full admin role (for sensitive admin operations)
 * @param string $redirect_url URL to redirect to if not full admin
 */
function requireFullAdminRole($redirect_url = '../public/dashboard.php') {
    // Admin from administrators table is always allowed
    if (isLoggedIn()) {
        return;
    }

    if (!isMemberLoggedIn()) {
        redirectWithMessage('../admin/login.php', 'You must be logged in to access this page.', 'error');
    }

    if (!isMemberAdmin()) {
        redirectWithMessage($redirect_url, 'Access denied. Full admin privileges required.', 'error');
    }
}

/**
 * Get current logged-in member's data
 * @return array|null
 */
function getCurrentMember() {
    initSession();
    
    // If logged in as admin from administrators table, return admin data formatted as member
    if (isLoggedIn() && isset($_SESSION['admin_id'])) {
        return [
            'member_id' => $_SESSION['admin_id'],
            'full_name' => $_SESSION['full_name'] ?? 'Admin',
            'email' => $_SESSION['email'] ?? '',
            'role' => 'admin',
            'username' => $_SESSION['username'] ?? '',
        ];
    }
    
    if (!isMemberLoggedIn() || !isset($_SESSION['member_id'])) {
        return null;
    }
    
    // Fetch the latest member data from the database to ensure it's always up-to-date
    $db = getDB();
    $member = $db->fetchOne("SELECT * FROM members WHERE member_id = ?", [$_SESSION['member_id']]);
    
    return $member;
}

// ============================================
// SECURITY HELPER FUNCTIONS
// ============================================

/**
 * Generate CSRF token
 * @return string
 */
function generateCSRFToken() {
    initSession();
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token Token to verify
 * @return bool
 */
function verifyCSRFToken($token) {
    initSession();
    
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input data
 * @param string $data
 * @return string
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate email format
 * @param string $email
 * @return bool
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate matric number format (XX/XXXX - e.g., 23/0223)
 * @param string $matric_no
 * @return bool
 */
function isValidMatricNumber($matric_no) {
    // Format: 2 digits / 4 digits (e.g., 23/0223)
    return preg_match('/^\d{2}\/\d{4}$/', $matric_no) === 1;
}

/**
 * Format matric number to standard format
 * @param string $matric_no
 * @return string|null Formatted matric number or null if invalid
 */
function formatMatricNumber($matric_no) {
    // Remove spaces and convert to uppercase
    $matric_no = strtoupper(trim($matric_no));
    
    // If already in correct format, return as is
    if (isValidMatricNumber($matric_no)) {
        return $matric_no;
    }
    
    // Try to extract digits and format
    $digits = preg_replace('/[^\d]/', '', $matric_no);
    if (strlen($digits) === 6) {
        return substr($digits, 0, 2) . '/' . substr($digits, 2, 4);
    }
    
    return null;
}

/**
 * Generate secure password hash
 * @param string $password
 * @return string
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Log security event
 * @param string $event Event description
 * @param string $level Severity level (info, warning, error)
 */
function logSecurityEvent($event, $level = 'info') {
    $timestamp = date('Y-m-d H:i:s');
    $user = $_SESSION['username'] ?? 'anonymous';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $log_message = "[$timestamp] [$level] User: $user | IP: $ip | Event: $event\n";
    
    $log_file = __DIR__ . '/../logs/security.log';
    error_log($log_message, 3, $log_file);
}

// ============================================
// REDIRECT HELPERS
// ============================================

/**
 * Redirect to URL
 * @param string $url
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Redirect with message
 * @param string $url
 * @param string $message
 * @param string $type (success, error, warning, info)
 */
function redirectWithMessage($url, $message, $type = 'info') {
    initSession();
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    redirect($url);
}

/**
 * Get and clear flash message
 * @return array|null ['message' => string, 'type' => string]
 */
function getFlashMessage() {
    initSession();
    
    if (isset($_SESSION['flash_message'])) {
        $message = [
            'message' => $_SESSION['flash_message'],
            'type' => $_SESSION['flash_type'] ?? 'info'
        ];
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        
        return $message;
    }
    
    return null;
}

// ============================================
// MEMBER ADMIN ROLE FUNCTIONS
// ============================================

/**
 * Check if current member is an admin
 * @return bool
 */
function isAdminMember() {
    initSession();
    if (!isset($_SESSION['member_logged_in']) || !$_SESSION['member_logged_in']) {
        return false;
    }
    
    // Check session role
    if (isset($_SESSION['member_role'])) {
        return $_SESSION['member_role'] === 'admin' || $_SESSION['member_role'] === 'executive';
    }
    
    // If not in session, check database
    if (isset($_SESSION['member_id'])) {
        $db = getDB();
        $member = $db->fetchOne("SELECT role FROM members WHERE member_id = ?", [$_SESSION['member_id']]);
        if ($member) {
            $_SESSION['member_role'] = $member['role'];
            return $member['role'] === 'admin' || $member['role'] === 'executive';
        }
    }
    
    return false;
}

/**
 * Require admin access (for admin dashboard pages)
 * Redirects to login or unauthorized page if not admin
 */
function requireAdminAccess() {
    initSession();
    
    // Check if logged in
    if (!isset($_SESSION['member_logged_in']) || !$_SESSION['member_logged_in']) {
        redirectWithMessage('../admin/login.php', 'Please login to access admin dashboard.', 'error');
    }
    
    // Check if admin or executive
    if (!isAdminMember()) {
        redirectWithMessage('../public/dashboard.php', 'Access denied. Admin privileges required.', 'error');
    }
}

// ============================================
// AUTHENTICATION COMPLETE
// ============================================
