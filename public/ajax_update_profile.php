<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

if (!isMemberLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$db = getDB();
$member = getCurrentMember();
$member_id = $member['member_id'];

$full_name = isset($_POST['full_name']) ? sanitizeInput($_POST['full_name']) : '';
$email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
$phone = isset($_POST['phone']) ? sanitizeInput($_POST['phone']) : '';
$department = isset($_POST['department']) ? sanitizeInput($_POST['department']) : '';
$level = isset($_POST['level']) ? sanitizeInput($_POST['level']) : '';

$errors = [];
if (empty($full_name) || empty($email) || empty($phone) || empty($department) || empty($level)) {
    $errors[] = 'All fields are required.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email address.';
}

// Check monthly edit quota
$start_of_month = date('Y-m-01 00:00:00');
$end_of_month = date('Y-m-t 23:59:59');
$edit_count_result = $db->fetchOne(
    "SELECT COUNT(*) as edit_count FROM profile_edit_logs WHERE member_id = ? AND edit_timestamp BETWEEN ? AND ?",
    [$member_id, $start_of_month, $end_of_month]
);
$edit_count = $edit_count_result['edit_count'] ?? 0;
if ($edit_count >= 2) {
    $errors[] = 'You have reached your monthly profile edits limit.';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// Ensure email not taken by another user
$existing = $db->fetchOne("SELECT member_id FROM members WHERE email = ? AND member_id != ?", [$email, $member_id]);
if ($existing) {
    echo json_encode(['success' => false, 'message' => 'Email already in use']);
    exit;
}

$ok = $db->query("UPDATE members SET full_name = ?, email = ?, phone = ?, department = ?, level = ? WHERE member_id = ?", [$full_name, $email, $phone, $department, $level, $member_id]);
if ($ok) {
    $db->query("INSERT INTO profile_edit_logs (member_id) VALUES (?)", [$member_id]);
    echo json_encode(['success' => true, 'message' => 'Profile updated', 'data' => ['full_name' => $full_name, 'email' => $email]]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
