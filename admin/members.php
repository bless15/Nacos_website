<?php
/**
 * ============================================
 * NACOS DASHBOARD - members MANAGEMENT
 * ============================================
 * Purpose: View, search, filter, and manage all members
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

// Handle member approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $member_id = intval($_POST['member_id']);

    // current admin id (who performs the approval)
    $admin_id = getCurrentMember()['member_id'] ?? null;

    // Verify admin exists in database if we have an ID
    $approved_by = null;
    if ($admin_id) {
        $admin_exists = $db->fetchOne("SELECT member_id FROM members WHERE member_id = ?", [$admin_id]);
        if ($admin_exists) {
            $approved_by = $admin_id;
        }
    }

    if ($action === 'approve') {
        try {
            // Approve member: set both membership_status and is_approved, record approver and timestamp
            $query = "UPDATE members SET membership_status = 'active', is_approved = 1, approved_by = ?, approval_date = NOW() WHERE member_id = ?";
            $db->query($query, [$approved_by, $member_id]);
            $_SESSION['flash_message'] = "Member approved successfully! They can now log in to access the dashboard.";
            $_SESSION['flash_type'] = 'success';
            logSecurityEvent("Admin approved member ID: $member_id by admin ID: $admin_id", 'info');
        } catch (Exception $e) {
            $_SESSION['flash_message'] = "An error occurred while approving the member.";
            $_SESSION['flash_type'] = 'error';
            logSecurityEvent("Failed to approve member ID: $member_id - " . $e->getMessage(), 'error');
        }

        header("Location: members.php");
        exit();
    } elseif ($action === 'reject') {
        try {
            // Reject member: mark inactive and clear approval flags (or delete if preferred)
            $query = "UPDATE members SET membership_status = 'inactive', is_approved = 0 WHERE member_id = ?";
            $db->query($query, [$member_id]);
            $_SESSION['flash_message'] = "Member rejected. Account has been set to inactive.";
            $_SESSION['flash_type'] = 'error';
            logSecurityEvent("Admin rejected member ID: $member_id", 'info');
        } catch (Exception $e) {
            $_SESSION['flash_message'] = "An error occurred while rejecting the member.";
            $_SESSION['flash_type'] = 'error';
            logSecurityEvent("Failed to reject member ID: $member_id - " . $e->getMessage(), 'error');
        }

        header("Location: members.php");
        exit();
    }
}

// Pagination settings
$items_per_page = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $items_per_page;

// Search and filter parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$department_filter = isset($_GET['department']) ? sanitizeInput($_GET['department']) : '';
$level_filter = isset($_GET['level']) ? sanitizeInput($_GET['level']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$approval_filter = isset($_GET['approval']) ? sanitizeInput($_GET['approval']) : '';
$sort_by = isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'registration_date';
$sort_order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'asc' : 'desc';

// Build WHERE clause
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(full_name LIKE ? OR matric_no LIKE ? OR email LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($department_filter)) {
    $where_conditions[] = "department = ?";
    $params[] = $department_filter;
}

if (!empty($level_filter)) {
    $where_conditions[] = "level = ?";
    $params[] = $level_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "membership_status = ?";
    $params[] = $status_filter;
}

if ($approval_filter === 'pending') {
    $where_conditions[] = "is_approved = 0";
} elseif ($approval_filter === 'approved') {
    $where_conditions[] = "is_approved = 1";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Validate sort column
$allowed_sort = ['full_name', 'matric_no', 'department', 'level', 'registration_date', 'membership_status'];
if (!in_array($sort_by, $allowed_sort)) {
    $sort_by = 'registration_date';
}

// Get total count
$count_query = "SELECT COUNT(*) as total FROM members $where_clause";
$total_members = $db->fetchOne($count_query, $params)['total'];
$total_pages = ceil($total_members / $items_per_page);

// Get members
$members_query = "SELECT member_id, matric_no, full_name, email, department, level, 
                         membership_status, registration_date, phone, is_approved
                  FROM members 
                  $where_clause 
                  ORDER BY $sort_by $sort_order 
                  LIMIT $items_per_page OFFSET $offset";

$members = $db->fetchAll($members_query, $params);

// Get filter options
$departments = $db->fetchAll("SELECT DISTINCT department FROM members ORDER BY department");
$levels = ['100', '200', '300', '400'];
$statuses = ['active', 'inactive', 'pending', 'alumni'];

// Get statistics
$stats = [
    'total' => $db->fetchOne("SELECT COUNT(*) as count FROM members")['count'],
    'active' => $db->fetchOne("SELECT COUNT(*) as count FROM members WHERE membership_status = 'active'")['count'],
    'inactive' => $db->fetchOne("SELECT COUNT(*) as count FROM members WHERE membership_status = 'inactive'")['count'],
    'pending' => $db->fetchOne("SELECT COUNT(*) as count FROM members WHERE is_approved = 0")['count'],
];

// Get flash message
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members Management - NACOS Dashboard</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --sidebar-bg: #2c3e50;
            --sidebar-hover: #34495e;
            --success-start: #11998e;
            --success-end: #38ef7d;
            --danger-start: #f093fb;
            --danger-end: #f5576c;
            --warning-start: #fa709a;
            --warning-end: #fee140;
        }

        * {
            box-sizing: border-box;
        }
        
        body {
            background: #f0f2f5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .sidebar-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.35);
            z-index: 900;
            pointer-events: none;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 260px;
            background: var(--sidebar-bg);
            color: white;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-header {
            padding: 20px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            text-align: center;
        }
        
        .sidebar-header h4 {
            margin: 10px 0 5px;
            font-size: 20px;
            font-weight: 600;
        }
        
        .sidebar-menu a {
            display: block;
            padding: 12px 20px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: var(--sidebar-hover);
            color: white;
            padding-left: 30px;
        }
        
        .sidebar-menu a i {
            width: 25px;
            margin-right: 10px;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 30px;
            min-height: auto;
            flex: 1 0 auto;
        }
        
        /* Top Bar */
        .top-bar {
            background: white;
            padding: 25px 30px;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: fadeInDown 0.9s ease;
        }
        
        .top-bar h3 {
            color: #2c3e50;
            font-weight: 700;
            margin: 0;
            font-size: 2rem;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-right-icon {
            display: none;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            align-items: center;
            justify-content: center;
            background: rgba(102,126,234,0.08);
            color: var(--primary-color);
            font-size: 18px;
        }
        
        .btn {
            border-radius: 10px;
            padding: 12px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
            color: white;
        }
        
        /* Stats Cards */
        .stats-mini {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 24px;
        }
        
        .stat-card-mini {
            background: white;
            padding: 14px 16px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.07);
            display: flex;
            align-items: center;
            gap: 13px;
            transition: all 0.3s ease;
            animation: fadeInUp 0.9s ease backwards;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card-mini:nth-child(1) { animation-delay: 0.15s; }
        .stat-card-mini:nth-child(2) { animation-delay: 0.30s; }
        .stat-card-mini:nth-child(3) { animation-delay: 0.45s; }
        .stat-card-mini:nth-child(4) { animation-delay: 0.60s; }
        
        .stat-card-mini:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.11);
        }
        
        .stat-card-mini::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 3px;
            height: 100%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }
        
        .stat-card-mini:nth-child(2)::before {
            background: linear-gradient(135deg, var(--success-start), var(--success-end));
        }
        
        .stat-card-mini:nth-child(3)::before {
            background: linear-gradient(135deg, var(--danger-start), var(--danger-end));
        }
        
        .stat-card-mini:nth-child(4)::before {
            background: linear-gradient(135deg, var(--warning-start), var(--warning-end));
        }
        
        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: white;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }
        
        .stat-card-mini:hover .stat-icon {
            transform: scale(1.1) rotate(5deg);
        }
        
        .stat-info h4 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
            line-height: 1.2;
        }
        
        .stat-info p {
            margin: 2px 0 0;
            color: #6c757d;
            font-size: 12px;
        }
        
        /* Filters */
        .filters-card {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
            animation: fadeInUp 0.9s ease backwards;
            animation-delay: 0.75s;
        }

        .mobile-filter-toggle { display: none; }
        .mobile-collapsible { display: block; }
        
        .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .filter-dropdown .btn {
            width: 100%;
            justify-content: space-between;
            align-items: center;
        }

        .filter-dropdown .dropdown-menu {
            width: 100%;
            max-height: 260px;
            overflow-y: auto;
        }
        
        .filter-group label {
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
        }
        
        .filter-group input,
        .filter-group select {
            width: 100%;
            min-width: 0;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-outline-secondary {
            border: 2px solid #6c757d;
            color: #6c757d;
            background: transparent;
        }
        
        .btn-outline-secondary:hover {
            background: #6c757d;
            color: white;
            transform: translateY(-2px);
        }
        
        /* Table */
        .table-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            animation: fadeInUp 0.9s ease backwards;
            animation-delay: 0.95s;
        }
        
        .table {
            margin: 0;
        }
        
        .table thead {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }
        
        .table thead th {
            border: none;
            padding: 18px 20px;
            font-weight: 600;
            white-space: nowrap;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .table thead th a {
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .table thead th a:hover {
            opacity: 0.9;
            text-decoration: underline;
        }
        
        .table tbody td {
            padding: 16px 20px;
            vertical-align: middle;
            font-size: 14px;
            color: #495057;
        }
        
        .table tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #f0f0f0;
            position: relative;
            z-index: 1;
        }

        .table tbody tr.dropdown-open-row {
            z-index: 30;
        }
        
        .table tbody tr:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
            transform: scale(1.01);
        }
        
        .table tbody tr:last-child {
            border-bottom: none;
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge.bg-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
        }
        
        .badge.bg-success {
            background: linear-gradient(135deg, var(--success-start), var(--success-end)) !important;
        }
        
        .badge.bg-danger {
            background: linear-gradient(135deg, var(--danger-start), var(--danger-end)) !important;
        }
        
        .badge.bg-warning {
            background: linear-gradient(135deg, var(--warning-start), var(--warning-end)) !important;
        }
        
        .badge.bg-info {
            background: linear-gradient(135deg, #4facfe, #00f2fe) !important;
        }
        
        .badge.bg-secondary {
            background: linear-gradient(135deg, #6c757d, #495057) !important;
        }
        
        /* Action dropdown button */
        .action-menu-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            padding: 0;
            border-radius: 9px;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            transition: all 0.18s ease;
        }

        .action-menu-btn:hover,
        .action-menu-btn:focus,
        .action-menu-btn:active,
        .action-menu-btn.show {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            color: white !important;
            box-shadow: 0 4px 14px rgba(102, 126, 234, 0.28);
            transform: translateY(-1px);
        }

        /* Hide default Bootstrap caret */
        .action-menu-btn::after { display: none; }

        .action-dropdown-menu {
            border: none;
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
            padding: 6px;
            min-width: 165px;
        }

        .action-dropdown-menu .dropdown-item {
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 500;
            transition: background 0.18s ease;
        }

        .action-dropdown-menu .dropdown-item:hover {
            background: rgba(102, 126, 234, 0.06);
        }

        .action-dropdown-menu .dropdown-item.text-danger:hover {
            background: rgba(245, 87, 108, 0.08);
        }

        .action-dropdown-menu .dropdown-item.text-success:hover {
            background: rgba(17, 153, 142, 0.08);
        }

        /* Form inside dropdown item */
        .action-dropdown-menu form {
            margin: 0;
            padding: 0;
        }

        .action-dropdown-menu form .dropdown-item {
            width: 100%;
            background: none;
            border: none;
            text-align: left;
            cursor: pointer;
            display: block;
        }

        /* Force all inline action buttons in table to match stat icon size */
        .table tbody td a.btn,
        .table tbody td button.btn,
        .table tbody td .btn-action {
            width: 40px !important;
            height: 40px !important;
            padding: 0 !important;
            border-radius: 9px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 14px !important;
            margin: 4px 0 !important;
        }

        .table tbody td a.btn i.fas,
        .table tbody td button.btn i.fas {
            font-size: 16px !important;
            line-height: 1 !important;
        }
        
        /* Pagination */
        .pagination-wrapper {
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
        }
        
        .pagination {
            margin: 0;
        }
        
        .page-link {
            color: var(--primary-color);
            border-radius: 8px;
            margin: 0 3px;
            border: 2px solid transparent;
            transition: all 0.3s ease;
            font-weight: 600;
        }
        
        .page-link:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }
        
        .page-item.active .page-link {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        /* Flash Messages */
        .alert {
            border-radius: 12px;
            border: none;
            padding: 15px 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            animation: fadeInDown 0.5s ease;
        }
        
        .alert-success {
            background: linear-gradient(135deg, rgba(17, 153, 142, 0.1), rgba(56, 239, 125, 0.1));
            border-left: 5px solid var(--success-start);
        }
        
        .alert-danger {
            background: linear-gradient(135deg, rgba(240, 147, 251, 0.1), rgba(245, 87, 108, 0.1));
            border-left: 5px solid var(--danger-start);
        }
        
        .alert-info {
            background: linear-gradient(135deg, rgba(79, 172, 254, 0.1), rgba(0, 242, 254, 0.1));
            border-left: 5px solid #4facfe;
        }
        
        /* Footer */
        footer {
            margin-left: 260px;
            background: white !important;
            border-radius: 16px 16px 0 0;
            box-shadow: 0 -5px 20px rgba(0, 0, 0, 0.05);
            margin-top: auto !important;
        }

        footer.mt-5 {
            margin-top: auto !important;
        }
        
        /* Empty State */
        .table tbody td i.fa-users {
            opacity: 0.3;
        }
        
        /* Animations */
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
        
        /* Responsive */
        @media (max-width: 991px) {
            .menu-toggle { display: inline-flex; align-items: center; justify-content: center; }
            .main-content { margin-left: 0; padding: 15px; min-height: auto; }
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
            body.sidebar-open .sidebar { transform: translateX(0); }
            .sidebar-backdrop { display: block; opacity: 0; transition: opacity 0.25s ease; }
            body.sidebar-open .sidebar-backdrop { opacity: 1; pointer-events: auto; }
            body.sidebar-open { overflow: hidden; }
            .stats-mini { grid-template-columns: repeat(2, 1fr); }
            .filters-row { grid-template-columns: 1fr; }
            footer { margin-left: 0; }
            footer.mt-5 { margin-top: 1rem !important; }

            .page-header-row {
                flex-direction: column;
                align-items: stretch !important;
                gap: 12px;
            }

            .header-left {
                width: 100%;
                justify-content: space-between;
            }

            .header-left h3 {
                display: none;
            }

            .header-right-icon {
                display: inline-flex;
            }

            .top-bar .btn-primary {
                width: 100%;
                justify-content: center;
            }

            .mobile-filter-toggle {
                display: flex;
                width: 100%;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 10px;
            }

            .mobile-collapsible { display: none; }
            .mobile-collapsible.show { display: block; }
        }
        
        @media (max-width: 480px) {
            .header-left {
                width: 100%;
                justify-content: space-between;
            }
            .top-bar .btn-primary {
                width: 100%;
                justify-content: center;
            }
        }
        /* Compact hamburger styling to match design (small, subtle rounded icon) */
        .menu-toggle {
            background: rgba(102,126,234,0.06);
            border: none;
            color: var(--primary-color);
            width: 36px;
            height: 36px;
            padding: 0;
            display: none;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            box-shadow: none;
            font-size: 18px;
        }

        .menu-toggle i { font-size: 18px; line-height: 1; }

        .menu-toggle:focus { outline: 2px solid rgba(102,126,234,0.18); outline-offset: 2px; }

        .header-left .menu-toggle { margin-right: 8px; }

        @media (max-width: 991px) {
            .menu-toggle { display: inline-flex; }
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar page-header-row">
            <div class="header-left">
                <button class="menu-toggle" id="menuToggle" type="button" aria-label="Toggle navigation" aria-expanded="false"><i class="fas fa-bars"></i></button>
                <h3><i class="fas fa-users me-2"></i> Members Management</h3>
                <span class="header-right-icon" aria-hidden="true"><i class="fas fa-users"></i></span>
            </div>
            <a href="add_member.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i> Add New Member
            </a>
        </div>
        
        <!-- Flash Message -->
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'error' ? 'danger' : 'info'); ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check' : ($flash['type'] === 'error' ? 'exclamation' : 'info'); ?>-circle me-2"></i>
                <?php echo htmlspecialchars($flash['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="stats-mini">
            <div class="stat-card-mini">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h4><?php echo number_format($stats['total']); ?></h4>
                    <p>Total Members</p>
                </div>
            </div>
            <div class="stat-card-mini">
                <div class="stat-icon" style="background: linear-gradient(135deg, #11998e, #38ef7d);">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-info">
                    <h4><?php echo number_format($stats['active']); ?></h4>
                    <p>Active Members</p>
                </div>
            </div>
            <div class="stat-card-mini">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stat-info">
                    <h4><?php echo number_format($stats['inactive']); ?></h4>
                    <p>Inactive Members</p>
                </div>
            </div>
            <div class="stat-card-mini">
                <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a, #fee140);">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="stat-info">
                    <h4><?php echo number_format($stats['pending']); ?></h4>
                    <p>Pending Approval</p>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <button type="button" class="btn btn-outline-primary mobile-filter-toggle" id="mobileFilterToggle" aria-expanded="false" aria-controls="mobileFilterCard">
            <span><i class="fas fa-filter me-2"></i>Filter Members</span>
            <i class="fas fa-chevron-down" id="mobileFilterChevron"></i>
        </button>

        <div class="filters-card mobile-collapsible" id="mobileFilterCard">
            <form method="GET" action="members.php" id="filterForm">
                <div class="filters-row">
                    <div class="filter-group">
                        <label><i class="fas fa-search me-1"></i> Search</label>
                        <input type="text" name="search" placeholder="Name, matric number, or email" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-building me-1"></i> Department</label>
                        <select name="department">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept['department']); ?>" 
                                    <?php echo $department_filter === $dept['department'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['department']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-layer-group me-1"></i> Level</label>
                        <select name="level">
                            <option value="">All Levels</option>
                            <?php foreach ($levels as $level): ?>
                                <option value="<?php echo $level; ?>" <?php echo $level_filter === $level ? 'selected' : ''; ?>>
                                    <?php echo $level; ?> Level
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-toggle-on me-1"></i> Status</label>
                        <select name="status">
                            <option value="">All Statuses</option>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo $status; ?>" <?php echo $status_filter === $status ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($status); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-check-circle me-1"></i> Approval</label>
                        <select name="approval">
                            <option value="">All Members</option>
                            <option value="pending" <?php echo $approval_filter === 'pending' ? 'selected' : ''; ?>>Pending Approval</option>
                            <option value="approved" <?php echo $approval_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-1"></i> Apply Filters
                        </button>
                    </div>
                    
                    <div class="filter-group">
                        <a href="members.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-redo me-1"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Members Table -->
        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'matric_no', 'order' => $sort_by === 'matric_no' && $sort_order === 'asc' ? 'desc' : 'asc'])); ?>">
                                    Matric No <?php if ($sort_by === 'matric_no') echo $sort_order === 'asc' ? '↑' : '↓'; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'full_name', 'order' => $sort_by === 'full_name' && $sort_order === 'asc' ? 'desc' : 'asc'])); ?>">
                                    Full Name <?php if ($sort_by === 'full_name') echo $sort_order === 'asc' ? '↑' : '↓'; ?>
                                </a>
                            </th>
                            <th>Email</th>
                            <th>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'department', 'order' => $sort_by === 'department' && $sort_order === 'asc' ? 'desc' : 'asc'])); ?>">
                                    Department <?php if ($sort_by === 'department') echo $sort_order === 'asc' ? '↑' : '↓'; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'level', 'order' => $sort_by === 'level' && $sort_order === 'asc' ? 'desc' : 'asc'])); ?>">
                                    Level <?php if ($sort_by === 'level') echo $sort_order === 'asc' ? '↑' : '↓'; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'membership_status', 'order' => $sort_by === 'membership_status' && $sort_order === 'asc' ? 'desc' : 'asc'])); ?>">
                                    Status <?php if ($sort_by === 'membership_status') echo $sort_order === 'asc' ? '↑' : '↓'; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'registration_date', 'order' => $sort_by === 'registration_date' && $sort_order === 'asc' ? 'desc' : 'asc'])); ?>">
                                    Registered <?php if ($sort_by === 'registration_date') echo $sort_order === 'asc' ? '↑' : '↓'; ?>
                                </a>
                            </th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($members)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No members found. Try adjusting your filters.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($members as $member): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($member['matric_no']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($member['email']); ?></td>
                                    <td><?php echo htmlspecialchars($member['department']); ?></td>
                                    <td><span class="badge bg-primary"><?php echo $member['level']; ?>L</span></td>
                                    <td>
                                        <?php 
                                        // Map visual colors for known membership_status values
                                        $status_colors = [
                                            'active' => 'success',
                                            'inactive' => 'secondary',
                                            'alumni' => 'info'
                                        ];

                                        // If the account hasn't been approved by an admin, show Pending Approval
                                        if (isset($member['is_approved']) && !$member['is_approved']) {
                                            $color = 'warning';
                                            $label = 'Pending Approval';
                                        } else {
                                            $color = $status_colors[$member['membership_status']] ?? 'secondary';
                                            $label = ucfirst($member['membership_status']);
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>">
                                            <?php echo htmlspecialchars($label); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($member['registration_date'])); ?></td>
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <button class="btn action-menu-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end action-dropdown-menu">
                                                <?php if (isset($member['is_approved']) && !$member['is_approved']): ?>
                                                <li>
                                                    <form method="POST" class="confirm-action-form" data-message="Are you sure you want to approve this member?">
                                                        <input type="hidden" name="action" value="approve">
                                                        <input type="hidden" name="member_id" value="<?php echo $member['member_id']; ?>">
                                                        <button type="submit" class="dropdown-item text-success">
                                                            <i class="fas fa-check me-2"></i> Approve
                                                        </button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form method="POST" class="confirm-action-form" data-message="Are you sure you want to reject this member?">
                                                        <input type="hidden" name="action" value="reject">
                                                        <input type="hidden" name="member_id" value="<?php echo $member['member_id']; ?>">
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="fas fa-times me-2"></i> Reject
                                                        </button>
                                                    </form>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <?php endif; ?>
                                                <li>
                                                    <a href="view_member.php?id=<?php echo $member['member_id']; ?>" class="dropdown-item">
                                                        <i class="fas fa-eye me-2 text-info"></i> View Details
                                                    </a>
                                                </li>
                                                <li>
                                                    <a href="edit_member.php?id=<?php echo $member['member_id']; ?>" class="dropdown-item">
                                                        <i class="fas fa-edit me-2 text-warning"></i> Edit Member
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a href="delete_member.php?id=<?php echo $member['member_id']; ?>" 
                                                       class="dropdown-item text-danger confirm-action-link"
                                                       data-message="Are you sure you want to delete this member?">
                                                        <i class="fas fa-trash me-2"></i> Delete
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-wrapper">
                    <div class="text-muted">
                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $items_per_page, $total_members); ?> 
                        of <?php echo number_format($total_members); ?> members
                    </div>
                    
                    <nav>
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-light text-center py-3 mt-5">
        <div class="container">
            <small class="text-muted">&copy; <?php echo date('Y'); ?> NACOSAU. All Rights Reserved.</small><br>
            <small class="text-muted">Developed by <a href="https://johnicity.com.ng/portfolio" target="_blank" class="text-decoration-none">Johnicity</a></small>
        </div>
    </footer>
    
    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="confirmModalLabel">Please confirm</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="confirmModalBody">Are you sure?</div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" id="confirmModalOk">OK</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Bootstrap JS -->
    <?php // Include admin footer which loads Bootstrap and confirmation modal ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script>
    // Explicitly initialize all Bootstrap dropdowns
    document.addEventListener('DOMContentLoaded', function () {
        var dropdownEls = document.querySelectorAll('[data-bs-toggle="dropdown"]');
        dropdownEls.forEach(function (el) {
            new bootstrap.Dropdown(el);

            el.addEventListener('show.bs.dropdown', function () {
                var row = el.closest('tr');
                if (row) row.classList.add('dropdown-open-row');
            });

            el.addEventListener('hide.bs.dropdown', function () {
                var row = el.closest('tr');
                if (row) row.classList.remove('dropdown-open-row');
            });
        });
    });
    </script>

    <script>
        // Auto-dismiss alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                const closeBtn = alert.querySelector('.btn-close');
                if (closeBtn) closeBtn.click();
            });
        }, 5000);
    </script>

    <script>
    // Sidebar hamburger toggle
    (function(){
        const menuToggle = document.getElementById('menuToggle');
        const sidebarBackdrop = document.getElementById('sidebarBackdrop');
        const sidebarLinks = document.querySelectorAll('.sidebar-menu a');
        if (!menuToggle) return;

        const closeSidebar = () => {
            document.body.classList.remove('sidebar-open');
            menuToggle.setAttribute('aria-expanded', 'false');
        };

        menuToggle.addEventListener('click', () => {
            const open = !document.body.classList.contains('sidebar-open');
            document.body.classList.toggle('sidebar-open', open);
            menuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        if (sidebarBackdrop) sidebarBackdrop.addEventListener('click', closeSidebar);
        sidebarLinks.forEach(link => link.addEventListener('click', closeSidebar));
    })();
    </script>

    <script>
    // Filter dropdown handler to sync hidden input + label
    (function(){
        const items = document.querySelectorAll('.filter-dropdown .dropdown-item');
        items.forEach(item => {
            item.addEventListener('click', function(e){
                e.preventDefault();
                const targetId = this.dataset.targetInput;
                const labelId = this.dataset.label;
                const value = this.dataset.value;
                const target = document.getElementById(targetId);
                const label = document.getElementById(labelId)?.querySelector('span');
                if (target) target.value = value;
                if (label) label.textContent = this.textContent.trim();
            });
        });
    })();
    </script>

    <script>
    // Mobile filters collapse toggle (members)
    (() => {
        const filterToggle = document.getElementById('mobileFilterToggle');
        const filterCard = document.getElementById('mobileFilterCard');
        const chevron = document.getElementById('mobileFilterChevron');

        if (!filterToggle || !filterCard || !chevron) return;

        filterToggle.addEventListener('click', () => {
            const isVisible = filterCard.classList.toggle('show');
            filterToggle.setAttribute('aria-expanded', isVisible ? 'true' : 'false');
            chevron.classList.toggle('fa-chevron-up', isVisible);
            chevron.classList.toggle('fa-chevron-down', !isVisible);
        });
    })();
    </script>

    <script>
    // Confirmation modal handler
    document.addEventListener('DOMContentLoaded', function() {
        var modalEl = document.getElementById('confirmModal');
        var bsModal = new bootstrap.Modal(modalEl);
        var bodyEl = document.getElementById('confirmModalBody');
        var okBtn = document.getElementById('confirmModalOk');
        var pending = null;

        // Handle form-based actions
        document.querySelectorAll('.confirm-action-form').forEach(function(form){
            form.addEventListener('submit', function(e){
                e.preventDefault();
                bodyEl.textContent = form.dataset.message || 'Are you sure?';
                pending = { type: 'form', form: form };
                bsModal.show();
            });
        });

        // Handle link-based actions
        document.querySelectorAll('.confirm-action-link').forEach(function(link){
            link.addEventListener('click', function(e){
                e.preventDefault();
                bodyEl.textContent = link.dataset.message || 'Are you sure?';
                pending = { type: 'link', href: link.href };
                bsModal.show();
            });
        });

        okBtn.addEventListener('click', function(){
            if (!pending) { bsModal.hide(); return; }
            if (pending.type === 'form') {
                // submit the original form
                pending.form.submit();
            } else if (pending.type === 'link') {
                // navigate to the link
                window.location.href = pending.href;
            }
            pending = null;
            bsModal.hide();
        });
    });
    </script>
</body>
</html>

