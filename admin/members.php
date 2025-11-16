<?php
/**
 * ============================================
 * NACOS DASHBOARD - MEMBERS MANAGEMENT
 * ============================================
 * Purpose: View, search, filter, and manage all members
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

// Handle member approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $member_id = intval($_POST['member_id']);

    // current admin id (who performs the approval)
    $admin_id = getCurrentMember()['member_id'] ?? null;

    if ($action === 'approve') {
        try {
            // Approve member: set both membership_status and is_approved, record approver and timestamp
            $query = "UPDATE MEMBERS SET membership_status = 'active', is_approved = 1, approved_by = ?, approval_date = NOW() WHERE member_id = ?";
            $db->query($query, [$admin_id, $member_id]);
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
            $query = "UPDATE MEMBERS SET membership_status = 'inactive', is_approved = 0 WHERE member_id = ?";
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
$count_query = "SELECT COUNT(*) as total FROM MEMBERS $where_clause";
$total_members = $db->fetchOne($count_query, $params)['total'];
$total_pages = ceil($total_members / $items_per_page);

// Get members
$members_query = "SELECT member_id, matric_no, full_name, email, department, level, 
                         membership_status, registration_date, phone, is_approved
                  FROM MEMBERS 
                  $where_clause 
                  ORDER BY $sort_by $sort_order 
                  LIMIT $items_per_page OFFSET $offset";

$members = $db->fetchAll($members_query, $params);

// Get filter options
$departments = $db->fetchAll("SELECT DISTINCT department FROM MEMBERS ORDER BY department");
$levels = ['100', '200', '300', '400'];
$statuses = ['active', 'inactive', 'pending', 'alumni'];

// Get statistics
$stats = [
    'total' => $db->fetchOne("SELECT COUNT(*) as count FROM MEMBERS")['count'],
    'active' => $db->fetchOne("SELECT COUNT(*) as count FROM MEMBERS WHERE membership_status = 'active'")['count'],
    'inactive' => $db->fetchOne("SELECT COUNT(*) as count FROM MEMBERS WHERE membership_status = 'inactive'")['count'],
    'pending' => $db->fetchOne("SELECT COUNT(*) as count FROM MEMBERS WHERE is_approved = 0")['count'],
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
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --sidebar-bg: #2c3e50;
            --sidebar-hover: #34495e;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
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
            padding: 20px;
            min-height: 100vh;
        }
        
        /* Top Bar */
        .top-bar {
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        /* Stats Cards */
        .stats-mini {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card-mini {
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            flex: 1;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        
        .stat-info h4 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }
        
        .stat-info p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }
        
        /* Filters */
        .filters-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        .filters-row {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        /* Table */
        .table-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
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
            padding: 15px;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .table thead th a {
            color: white;
            text-decoration: none;
        }
        
        .table thead th a:hover {
            text-decoration: underline;
        }
        
        .table tbody td {
            padding: 12px 15px;
            vertical-align: middle;
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .btn-action {
            padding: 5px 10px;
            font-size: 12px;
            border-radius: 5px;
            margin: 0 2px;
        }
        
        /* Pagination */
        .pagination-wrapper {
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .pagination {
            margin: 0;
        }
        
        .page-link {
            color: var(--primary-color);
        }
        
        .page-item.active .page-link {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-color: var(--primary-color);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="../assets/images/nacos_logo.jpg" alt="NACOS Logo" style="height: 60px; margin-bottom: 10px;">
            <h4>NACOS Dashboard</h4>
            <small>Admin Panel</small>
        </div>
        
        <div class="sidebar-menu">
            <a href="index.php">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="members.php" class="active">
                <i class="fas fa-users"></i> Members
            </a>
            <a href="projects.php">
                <i class="fas fa-project-diagram"></i> Projects
            </a>
            <a href="events.php">
                <i class="fas fa-calendar-alt"></i> Events
            </a>
            <a href="resources.php">
                <i class="fas fa-book"></i> Resources
            </a>
            <a href="partners.php">
                <i class="fas fa-handshake"></i> Partners
            </a>
            <a href="documents.php">
                <i class="fas fa-folder"></i> Documents
            </a>
            <hr style="border-color: rgba(255,255,255,0.1);">
            <a href="../public/index.php" target="_blank">
                <i class="fas fa-external-link-alt"></i> View Public Site
            </a>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <h3><i class="fas fa-users me-2"></i> Members Management</h3>
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
        <div class="filters-card">
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
                            <th>Actions</th>
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
                                    <td>
                                        <?php if (isset($member['is_approved']) && !$member['is_approved']): ?>
                                            <form method="POST" class="confirm-action-form" data-message="Are you sure you want to approve this member?" style="display: inline;">
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="member_id" value="<?php echo $member['member_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success btn-action" title="Approve Member">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="confirm-action-form" data-message="Are you sure you want to reject this member?" style="display: inline;">
                                                <input type="hidden" name="action" value="reject">
                                                <input type="hidden" name="member_id" value="<?php echo $member['member_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger btn-action" title="Reject Member">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="view_member.php?id=<?php echo $member['member_id']; ?>" 
                                           class="btn btn-sm btn-info btn-action" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_member.php?id=<?php echo $member['member_id']; ?>" 
                                           class="btn btn-sm btn-warning btn-action" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="delete_member.php?id=<?php echo $member['member_id']; ?>" 
                                           class="btn btn-sm btn-danger btn-action confirm-action-link" 
                                           title="Delete"
                                           data-message="Are you sure you want to delete this member?">
                                            <i class="fas fa-trash"></i>
                                        </a>
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
        // Auto-dismiss alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                const closeBtn = alert.querySelector('.btn-close');
                if (closeBtn) closeBtn.click();
            });
        }, 5000);
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

