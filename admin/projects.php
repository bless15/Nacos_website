<?php
/**
 * ============================================
 * NACOS DASHBOARD - ADMIN projects MANAGEMENT
 * ============================================
 * Purpose: Display and manage all projects
 * Access: Admin only
 * Created: November 3, 2025
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Bootstrap and includes
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Require admin login
requireAdminRole();

// Get current user
$current_user = getCurrentMember();

// Initialize database
$db = getDB();

// --- Filtering, Searching, and Sorting Logic ---
$search_term = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'all';
$sort_by = isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'start_date';
$sort_order = isset($_GET['order']) ? sanitizeInput($_GET['order']) : 'DESC';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 6; // show 6 projects per page
$offset = ($page - 1) * $per_page;

// Base query with member count
$query = "
    SELECT p.*, COUNT(DISTINCT mp.member_id) as member_count
    FROM projects p
    LEFT JOIN member_projects mp ON p.project_id = mp.project_id
";
$conditions = [];
$params = [];

// Add search condition
if (!empty($search_term)) {
    $conditions[] = "(p.title LIKE :search OR p.description LIKE :search)";
    $params[':search'] = '%' . $search_term . '%';
}

// Add status filter
if ($filter_status !== 'all') {
    $conditions[] = "p.project_status = :status";
    $params[':status'] = $filter_status;
}

// Append conditions to query
if (!empty($conditions)) {
    $query .= " WHERE " . implode(' AND ', $conditions);
}

// Add grouping
$query .= " GROUP BY p.project_id";

// Add sorting
$allowed_sorts = ['title', 'start_date', 'completion_date', 'project_status'];
$sort_by = in_array($sort_by, $allowed_sorts) ? $sort_by : 'start_date';
$sort_order = ($sort_order === 'ASC') ? 'ASC' : 'DESC';
$query .= " ORDER BY p.$sort_by $sort_order";

// Get total count for pagination
$count_query = "SELECT COUNT(DISTINCT p.project_id) as total FROM projects p";
if (!empty($conditions)) {
    $count_query .= " WHERE " . implode(' AND ', array_map(function($c) {
        return str_replace('p.', 'p.', $c);
    }, $conditions));
}
$total_projects = $db->fetchOne($count_query, $params)['total'];
$total_pages = ceil($total_projects / $per_page);

// Add pagination to query
$query .= " LIMIT $per_page OFFSET $offset";

// Fetch projects
$projects = $db->fetchAll($query, $params);

// Get flash message
$flash = getFlashMessage();
$delete_csrf_token = generateCSRFToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Management - NACOS Admin</title>
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
    --success-gradient: linear-gradient(135deg, #11998e, #38ef7d);
    --warning-gradient: linear-gradient(135deg, #f093fb, #f5576c);
    --info-gradient: linear-gradient(135deg, #4facfe, #00f2fe);
    --purple-gradient: linear-gradient(135deg, #667eea, #764ba2);
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
    transition: all 0.3s;
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

.sidebar-header small {
    opacity: 0.9;
}

.sidebar-menu {
    padding: 20px 0;
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

/* Hamburger toggle */
.menu-toggle {
    display: none;
    border: none;
    background: transparent;
    color: #667eea;
    font-size: 22px;
    padding: 6px 10px;
    border-radius: 8px;
}

.menu-toggle:focus {
    outline: 2px solid rgba(102,126,234,0.35);
    outline-offset: 2px;
}

.sidebar-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.35);
    z-index: 900;
    pointer-events: none;
}

/* Main Content */
.main-content {
    margin-left: 260px;
    padding: 30px;
    min-height: 100vh;
}

/* Page Header */
.page-header {
    background: white;
    padding: 25px 30px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 30px;
}

.page-header h1 {
    font-size: 28px;
    font-weight: 700;
    color: #2c3e50;
    margin: 0;
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

/* Filter Card */
.filter-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    padding: 25px;
    margin-bottom: 30px;
}

/* Responsive filter tweaks */
.filter-card .filter-group { min-width: 0; }
.filter-dropdown .btn { width: 100%; justify-content: space-between; align-items: center; }
.filter-dropdown .dropdown-menu { width: 100%; max-height: 260px; overflow-y: auto; }
.filter-action-col {
    display: flex;
    align-items: flex-end;
}
.filter-action-col .btn {
    height: 46px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    white-space: nowrap;
}

.mobile-filter-toggle {
    display: none;
}

.filter-card.mobile-collapsible {
    display: block;
}

.form-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
    font-size: 14px;
}

.form-control, .form-select {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 10px 15px;
    font-size: 14px;
    transition: all 0.3s;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
}

/* Project Cards */
.project-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    border: none;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.project-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 12px 35px rgba(102, 126, 234, 0.2);
}

.project-card-header {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    padding: 20px;
    color: white;
    position: relative;
    overflow: hidden;
}

.project-card-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: pulse 3s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 0.5; }
    50% { transform: scale(1.1); opacity: 0.8; }
}

.project-card-title {
    font-size: 18px;
    font-weight: 700;
    margin: 0;
    position: relative;
    z-index: 1;
}

.project-card-title {
    font-size: 18px;
    font-weight: 700;
    margin: 0;
    position: relative;
    z-index: 1;
    /* give room for the status badge on the right so long titles don't overlap */
    padding-right: 120px;
}

.project-card-status {
    position: absolute;
    top: 15px;
    right: 15px;
    z-index: 3;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    white-space: nowrap;
    display: inline-block;
}

.status-badge {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.status-completed {
    background: var(--success-gradient);
    color: white;
}

.status-in-progress {
    background: var(--info-gradient);
    color: white;
}

.status-planned {
    background: #ffeaa7;
    color: #2c3e50;
}

.status-on-hold {
    background: #dfe6e9;
    color: #2c3e50;
}

.project-card-body {
    padding: 25px;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
}

.project-description {
    color: #6c757d;
    font-size: 14px;
    line-height: 1.6;
    flex-grow: 1;
    margin-bottom: 20px;
}

.project-meta {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 15px 0;
    border-top: 1px solid #e9ecef;
    border-bottom: 1px solid #e9ecef;
    margin-bottom: 20px;
}

.meta-item {
    display: flex;
    align-items: center;
    font-size: 13px;
    color: #6c757d;
}

.meta-item i {
    width: 20px;
    margin-right: 8px;
    color: var(--primary-color);
}

.project-actions {
    display: flex;
    gap: 8px;
}

.action-btn {
    flex: 1;
    padding: 10px;
    border-radius: 8px;
    border: none;
    font-weight: 600;
    font-size: 13px;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    text-decoration: none;
}

.btn-view {
    background: linear-gradient(135deg, #0F6B3E, #0b5a34);
    color: white;
}

.btn-view:hover {
    background: linear-gradient(135deg, #0b5a34, #09472a);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(15, 107, 62, 0.28);
    color: white;
}

.btn-edit {
    background: #f8f9fa;
    color: #495057;
    border: 1px solid #dee2e6;
}

.btn-edit:hover {
    background: #e9ecef;
    color: #495057;
    transform: translateY(-2px);
}

.btn-delete {
    background: #fff5f5;
    color: #dc3545;
    border: 1px solid #ffcccc;
}

.btn-delete:hover {
    background: #dc3545;
    color: white;
    transform: translateY(-2px);
}

/* Empty State */
.empty-state {
    background: white;
    border-radius: 15px;
    padding: 60px 30px;
    text-align: center;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.empty-state i {
    font-size: 80px;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 20px;
}

/* Buttons */
.btn-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    border: none;
    padding: 12px 30px;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-primary:hover {
    background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

/* Pagination */
.pagination {
    margin-top: 30px;
}

.page-link {
    color: var(--primary-color);
    border-radius: 8px;
    margin: 0 4px;
    border: 1px solid #dee2e6;
}

.page-link:hover {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.page-item.active .page-link {
    background: var(--primary-color);
    border-color: var(--primary-color);
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

.project-card {
    animation: fadeInUp 0.9s ease-out backwards;
}

.project-card:nth-child(1) { animation-delay: 0.15s; }
.project-card:nth-child(2) { animation-delay: 0.30s; }
.project-card:nth-child(3) { animation-delay: 0.45s; }
.project-card:nth-child(4) { animation-delay: 0.60s; }
.project-card:nth-child(5) { animation-delay: 0.75s; }
.project-card:nth-child(6) { animation-delay: 0.90s; }

/* Responsive */
@media (max-width: 992px) {
    .menu-toggle { display: inline-flex; align-items: center; justify-content: center; }
    .main-content { margin-left: 0; padding: 15px; min-height: auto; }
    .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
    body.sidebar-open .sidebar { transform: translateX(0); }
    .sidebar-backdrop { display: block; opacity: 0; transition: opacity 0.25s ease; }
    body.sidebar-open .sidebar-backdrop { opacity: 1; pointer-events: auto; }
    body.sidebar-open { overflow: hidden; }
    footer.mt-5 { margin-top: 0.6rem !important; }

    .page-header-row {
        flex-direction: column;
        align-items: stretch !important;
        gap: 12px;
    }

    .header-left {
        width: 100%;
        justify-content: space-between;
    }

    .header-left .header-title {
        display: none;
    }

    .header-right-icon {
        display: inline-flex;
    }

    .page-header .btn-primary {
        width: 100%;
        justify-content: center;
    }

    .mobile-filter-toggle {
        display: inline-flex;
        align-items: center;
        width: 100%;
        margin-bottom: 12px;
    }

    .filter-card.mobile-collapsible {
        display: none;
        margin-top: 0;
    }

    .filter-card.mobile-collapsible.show {
        display: block;
    }

    .filter-action-col {
        display: block;
    }

    .filter-action-col .btn {
        height: auto;
    }
}

@media (max-width: 480px) {
    .header-left {
        width: 100%;
        justify-content: space-between;
    }
}

.project-card { transition: all 0.3s ease; }
.project-card:hover { transform: translateY(-5px); }
</style>
</head>
<body>
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
         <!-- <div class="top-bar">
            <h3><i class="fas fa-project-diagram me-2"></i> Project Management</h3>
            <div>
                <span class="me-3">Welcome, <?php echo htmlspecialchars($current_user['full_name']); ?></span>
                <a href="logout.php" class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div> -->
        
        <!-- Page Content -->
        <div class="container-fluid">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center page-header-row">
                        <div class="d-flex align-items-center gap-3 header-left">
                            <button class="menu-toggle" type="button" aria-label="Toggle sidebar" aria-expanded="false">
                                <i class="fas fa-bars"></i>
                            </button>
                            <div class="header-title">
                                <h1><i class="fas fa-project-diagram me-2"></i>Project Management</h1>
                                <p class="mb-0 text-muted">Manage and track all member projects</p>
                            </div>
                            <span class="header-right-icon" aria-hidden="true"><i class="fas fa-project-diagram"></i></span>
                        </div>
                        <a href="add_project.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Add New Project
                        </a>
                    </div>
                </div>
                
                <!-- Flash Message -->
                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                        <?php echo $flash['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <button type="button" id="mobileFilterToggle" class="btn btn-outline-primary mobile-filter-toggle" aria-expanded="false" aria-controls="mobileFilterCard">
                    <i class="fas fa-filter me-2"></i>
                    <span>Filter Projects</span>
                    <i class="fas fa-chevron-down ms-auto" id="mobileFilterChevron"></i>
                </button>
                
                <!-- Filters and Search -->
                <div class="filter-card mobile-collapsible" id="mobileFilterCard">
                        <form action="projects.php" method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Search Projects</label>
                                <input type="text" name="search" class="form-control" placeholder="Search by title or description..." value="<?php echo htmlspecialchars($search_term); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <div class="dropdown filter-dropdown">
                                    <input type="hidden" name="status" id="statusInput" value="<?php echo htmlspecialchars($filter_status); ?>">
                                    <button class="btn btn-outline-secondary dropdown-toggle text-start form-select" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="statusBtn">
                                        <span><?php echo ($filter_status === 'all' || !$filter_status) ? 'All Statuses' : ucfirst(str_replace('-', ' ', $filter_status)); ?></span>
                                    </button>
                                    <ul class="dropdown-menu" aria-labelledby="statusBtn">
                                        <li><a class="dropdown-item" data-target-input="statusInput" data-label="statusBtn" data-value="all" href="#">All Statuses</a></li>
                                        <li><a class="dropdown-item" data-target-input="statusInput" data-label="statusBtn" data-value="planned" href="#">Planned</a></li>
                                        <li><a class="dropdown-item" data-target-input="statusInput" data-label="statusBtn" data-value="in-progress" href="#">In Progress</a></li>
                                        <li><a class="dropdown-item" data-target-input="statusInput" data-label="statusBtn" data-value="completed" href="#">Completed</a></li>
                                        <li><a class="dropdown-item" data-target-input="statusInput" data-label="statusBtn" data-value="on-hold" href="#">On Hold</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Sort By</label>
                                <div class="dropdown filter-dropdown">
                                    <input type="hidden" name="sort" id="sortInput" value="<?php echo htmlspecialchars($sort_by); ?>">
                                    <button class="btn btn-outline-secondary dropdown-toggle text-start form-select" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="sortBtn">
                                        <span><?php echo ($sort_by === 'start_date') ? 'Start Date' : (($sort_by === 'title') ? 'Title' : 'Status'); ?></span>
                                    </button>
                                    <ul class="dropdown-menu" aria-labelledby="sortBtn">
                                        <li><a class="dropdown-item" data-target-input="sortInput" data-label="sortBtn" data-value="start_date" href="#">Start Date</a></li>
                                        <li><a class="dropdown-item" data-target-input="sortInput" data-label="sortBtn" data-value="title" href="#">Title</a></li>
                                        <li><a class="dropdown-item" data-target-input="sortInput" data-label="sortBtn" data-value="project_status" href="#">Status</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Order</label>
                                <div class="dropdown filter-dropdown">
                                    <input type="hidden" name="order" id="orderInput" value="<?php echo htmlspecialchars($sort_order); ?>">
                                    <button class="btn btn-outline-secondary dropdown-toggle text-start form-select" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="orderBtn">
                                        <span><?php echo ($sort_order === 'ASC') ? 'Ascending' : 'Descending'; ?></span>
                                    </button>
                                    <ul class="dropdown-menu" aria-labelledby="orderBtn">
                                        <li><a class="dropdown-item" data-target-input="orderInput" data-label="orderBtn" data-value="DESC" href="#">Descending</a></li>
                                        <li><a class="dropdown-item" data-target-input="orderInput" data-label="orderBtn" data-value="ASC" href="#">Ascending</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-2 filter-action-col">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter me-2"></i>Apply Filters
                                </button>
                            </div>
                        </form>
                </div>
                
                <!-- Projects Grid -->
                <?php if (empty($projects)): ?>
                    <div class="empty-state">
                        <i class="fas fa-project-diagram"></i>
                        <h3 class="mt-3">No Projects Found</h3>
                        <p class="text-muted">No projects match your search criteria or none have been created yet.</p>
                        <a href="add_project.php" class="btn btn-primary mt-3">
                            <i class="fas fa-plus me-2"></i>Create Your First Project
                        </a>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($projects as $project): ?>
                            <div class="col-lg-4 col-md-6">
                                <div class="project-card">
                                    <div class="project-card-header">
                                        <div class="project-card-title"><?php echo htmlspecialchars($project['title']); ?></div>
                                        <div class="project-card-status">
                                            <span class="status-badge status-<?php echo $project['project_status']; ?>">
                                                <?php echo ucfirst(str_replace('-', ' ', $project['project_status'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="project-card-body">
                                        <div class="project-description">
                                            <?php echo substr(htmlspecialchars($project['description']), 0, 150) . '...'; ?>
                                        </div>
                                        <div class="project-meta">
                                            <div class="meta-item">
                                                <i class="fas fa-calendar-alt"></i>
                                                <span>
                                                    <?php echo date('M d, Y', strtotime($project['start_date'])); ?>
                                                    <?php if (!empty($project['completion_date'])): ?>
                                                        - <?php echo date('M d, Y', strtotime($project['completion_date'])); ?>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <div class="meta-item">
                                                <i class="fas fa-users"></i>
                                                <span><?php echo $project['member_count']; ?> Team Member<?php echo $project['member_count'] != 1 ? 's' : ''; ?></span>
                                            </div>
                                        </div>
                                        <div class="project-actions">
                                            <a href="view_project.php?id=<?php echo $project['project_id']; ?>" class="action-btn btn-view">
                                                <i class="fas fa-eye"></i>View
                                            </a>
                                            <a href="edit_project.php?id=<?php echo $project['project_id']; ?>" class="action-btn btn-edit">
                                                <i class="fas fa-edit"></i>Edit
                                            </a>
                                            <button type="button" class="action-btn btn-delete" onclick="deleteProject(<?php echo (int)$project['project_id']; ?>)">
                                                <i class="fas fa-trash"></i>Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Projects pagination">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_term); ?>&status=<?php echo $filter_status; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $sort_order; ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo ($page === $i) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_term); ?>&status=<?php echo $filter_status; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $sort_order; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_term); ?>&status=<?php echo $filter_status; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $sort_order; ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-light text-center py-3 mt-5">
        <div class="container">
            <small class="text-muted">&copy; <?php echo date('Y'); ?> NACOSAU. All Rights Reserved.</small><br>
            <small class="text-muted">Developed by <a href="https://johnicity.com.ng/portfolio" target="_blank" class="text-decoration-none">Johnicity</a></small>
        </div>
    </footer>

    <form id="deleteProjectForm" method="POST" action="" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?php echo $delete_csrf_token; ?>">
    </form>
    
    <?php include __DIR__ . '/includes/footer.php'; ?>
    <script>
        function deleteProject(projectId) {
            const form = document.getElementById('deleteProjectForm');
            if (!form || !projectId) return;

            const submitDelete = function() {
                form.action = 'delete_project.php?id=' + encodeURIComponent(projectId);
                form.submit();
            };

            if (typeof window.confirmModal !== 'function') {
                if (confirm('Are you sure you want to delete this project? This action cannot be undone.')) {
                    submitDelete();
                }
                return;
            }

            window.confirmModal('Are you sure you want to delete this project? This action cannot be undone.', {
                title: 'Delete Project',
                okLabel: 'Delete'
            }).then(function(confirmed) {
                if (confirmed) submitDelete();
            });
        }

        (() => {
            const toggleBtn = document.querySelector('.menu-toggle');
            const backdrop = document.querySelector('.sidebar-backdrop');
            const body = document.body;
            if (!toggleBtn) return;

            const closeMenu = () => {
                body.classList.remove('sidebar-open');
                toggleBtn.setAttribute('aria-expanded', 'false');
            };

            const toggleMenu = () => {
                const isOpen = body.classList.toggle('sidebar-open');
                toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            };

            toggleBtn.addEventListener('click', toggleMenu);
            if (backdrop) {
                backdrop.addEventListener('click', closeMenu);
            }
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeMenu();
            });
        })();

        (() => {
            const filterToggle = document.getElementById('mobileFilterToggle');
            const filterCard = document.getElementById('mobileFilterCard');
            const chevron = document.getElementById('mobileFilterChevron');
            if (!filterToggle || !filterCard) return;

            filterToggle.addEventListener('click', () => {
                const isOpen = filterCard.classList.toggle('show');
                filterToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                if (chevron) {
                    chevron.classList.toggle('fa-chevron-down', !isOpen);
                    chevron.classList.toggle('fa-chevron-up', isOpen);
                }
            });
        })();
    </script>
    <script>
    // Filter dropdown handler to sync hidden input + label (projects)
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
    
</body>
</html>

