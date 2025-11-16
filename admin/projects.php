<?php
/**
 * ============================================
 * NACOS DASHBOARD - ADMIN PROJECTS MANAGEMENT
 * ============================================
 * Purpose: Display and manage all projects
 * Access: Admin only
 * Created: November 3, 2025
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Include required files
require_once '../config/database.php';
require_once '../includes/auth.php';

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

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Base query with member count
$query = "
    SELECT p.*, COUNT(DISTINCT mp.member_id) as member_count
    FROM PROJECTS p
    LEFT JOIN MEMBER_PROJECTS mp ON p.project_id = mp.project_id
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
$count_query = "SELECT COUNT(DISTINCT p.project_id) as total FROM PROJECTS p";
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
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/admin.css">
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
            <a href="members.php">
                <i class="fas fa-users"></i> Members
            </a>
            <a href="projects.php" class="active">
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
            <h3><i class="fas fa-project-diagram me-2"></i> Project Management</h3>
            <div>
                <span class="me-3">Welcome, <?php echo htmlspecialchars($current_user['full_name']); ?></span>
                <a href="logout.php" class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        
        <!-- Page Content -->
        <div class="container-fluid px-4 py-4">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-0">Project Management</h1>
                        <p class="text-muted">Manage and track all member projects</p>
                    </div>
                    <a href="add_project.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Project
                    </a>
                </div>
                
                <!-- Flash Message -->
                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                        <?php echo $flash['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Filters and Search -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form action="projects.php" method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Search Projects</label>
                                <input type="text" name="search" class="form-control" placeholder="Search by title or description..." value="<?php echo htmlspecialchars($search_term); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="all" <?php echo ($filter_status === 'all') ? 'selected' : ''; ?>>All Statuses</option>
                                    <option value="planned" <?php echo ($filter_status === 'planned') ? 'selected' : ''; ?>>Planned</option>
                                    <option value="in-progress" <?php echo ($filter_status === 'in-progress') ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="completed" <?php echo ($filter_status === 'completed') ? 'selected' : ''; ?>>Completed</option>
                                    <option value="on-hold" <?php echo ($filter_status === 'on-hold') ? 'selected' : ''; ?>>On Hold</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Sort By</label>
                                <select name="sort" class="form-select">
                                    <option value="start_date" <?php echo ($sort_by === 'start_date') ? 'selected' : ''; ?>>Start Date</option>
                                    <option value="title" <?php echo ($sort_by === 'title') ? 'selected' : ''; ?>>Title</option>
                                    <option value="project_status" <?php echo ($sort_by === 'project_status') ? 'selected' : ''; ?>>Status</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Order</label>
                                <select name="order" class="form-select">
                                    <option value="DESC" <?php echo ($sort_order === 'DESC') ? 'selected' : ''; ?>>Descending</option>
                                    <option value="ASC" <?php echo ($sort_order === 'ASC') ? 'selected' : ''; ?>>Ascending</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter"></i> Apply Filters
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Projects Grid -->
                <?php if (empty($projects)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-project-diagram fa-4x text-muted mb-3"></i>
                            <h3>No Projects Found</h3>
                            <p class="text-muted">No projects match your search criteria or none have been created yet.</p>
                            <a href="add_project.php" class="btn btn-primary mt-3">
                                <i class="fas fa-plus"></i> Create Your First Project
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($projects as $project): ?>
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="card h-100 project-card">
                                    <div class="card-body d-flex flex-column">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($project['title']); ?></h5>
                                            <span class="badge bg-<?php 
                                                echo match($project['project_status']) {
                                                    'completed' => 'success',
                                                    'in-progress' => 'primary',
                                                    'planned' => 'warning',
                                                    'on-hold' => 'secondary',
                                                    default => 'info'
                                                };
                                            ?>">
                                                <?php echo ucfirst(str_replace('-', ' ', $project['project_status'])); ?>
                                            </span>
                                        </div>
                                        <p class="card-text text-muted flex-grow-1">
                                            <?php echo substr(htmlspecialchars($project['description']), 0, 120) . '...'; ?>
                                        </p>
                                        <div class="mb-3">
                                            <small class="text-muted">
                                                <i class="fas fa-calendar"></i> 
                                                <?php echo date('M d, Y', strtotime($project['start_date'])); ?>
                                                <?php if (!empty($project['completion_date'])): ?>
                                                    - <?php echo date('M d, Y', strtotime($project['completion_date'])); ?>
                                                <?php endif; ?>
                                            </small>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-users"></i> <?php echo $project['member_count']; ?> Member(s)
                                            </small>
                                        </div>
                                        <div class="btn-group w-100">
                                            <a href="view_project.php?id=<?php echo $project['project_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <a href="edit_project.php?id=<?php echo $project['project_id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="delete_project.php?id=<?php echo $project['project_id']; ?>" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
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
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <style>
        .project-card {
            transition: all 0.3s ease;
        }
        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
    </style>
</body>
</html>
