<?php
/**
 * ============================================
 * NACOS DASHBOARD - resources MANAGEMENT
 * ============================================
 * Purpose: Manage learning resources, tutorials, code samples
 * Access: Admin only
 * Created: November 3, 2025
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Bootstrap and includes
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Require login
requireAdminRole();

// Initialize database
$db = getDB();

// Pagination
$items_per_page = 15;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $items_per_page;

// Filters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';
$level_filter = isset($_GET['level']) ? sanitizeInput($_GET['level']) : '';
$visibility_filter = isset($_GET['visibility']) ? sanitizeInput($_GET['visibility']) : (isset($_GET['featured']) ? sanitizeInput($_GET['featured']) : '');

// Schema compatibility checks (older/newer resources table versions)
$resource_columns = [];
try {
    $column_rows = $db->fetchAll("SHOW COLUMNS FROM resources");
    foreach ($column_rows as $row) {
        if (isset($row['Field'])) {
            $resource_columns[] = $row['Field'];
        }
    }
} catch (Exception $e) {
    $resource_columns = [];
}

$has_is_featured_column = in_array('is_featured', $resource_columns, true);
$has_featured_column = in_array('featured', $resource_columns, true);
$has_is_active_column = in_array('is_active', $resource_columns, true);
$has_visibility_column = in_array('visibility', $resource_columns, true);

// Build WHERE clause
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(title LIKE :search OR description LIKE :search)";
    $params['search'] = "%$search%";
}

if (!empty($type_filter)) {
    $where_conditions[] = "resource_type = :type";
    $params['type'] = $type_filter;
}

if (!empty($level_filter)) {
    $where_conditions[] = "level = :level";
    $params['level'] = $level_filter;
}

if ($visibility_filter === 'featured') {
    if ($has_is_featured_column) {
        $where_conditions[] = "is_featured = :is_featured";
        $params['is_featured'] = 1;
    } elseif ($has_featured_column) {
        $where_conditions[] = "featured = :featured";
        $params['featured'] = 1;
    }
} elseif ($visibility_filter === 'active') {
    if ($has_is_active_column) {
        $where_conditions[] = "is_active = :is_active";
        $params['is_active'] = 1;
    } elseif ($has_visibility_column) {
        $where_conditions[] = "visibility = :visibility";
        $params['visibility'] = 'public';
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_query = "SELECT COUNT(*) as total FROM resources {$where_clause}";
$total_resources = $db->fetchOne($count_query, $params)['total'];
$total_pages = ceil($total_resources / $items_per_page);

// Sorting defaults and validation
$sort_by = isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'upload_date';
$sort_order = (isset($_GET['order']) && strtolower($_GET['order']) === 'asc') ? 'ASC' : 'DESC';
$sort_order_ui = strtolower($sort_order);
$allowed_sorts = ['upload_date', 'downloads'];
if (!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'upload_date';
}

$upload_sort_column = in_array('upload_date', $resource_columns, true)
    ? 'upload_date'
    : (in_array('created_at', $resource_columns, true) ? 'created_at' : 'resource_id');

$downloads_sort_column = null;
if (in_array('downloads', $resource_columns, true)) {
    $downloads_sort_column = 'downloads';
} elseif (in_array('download_count', $resource_columns, true)) {
    $downloads_sort_column = 'download_count';
} elseif (in_array('downloads_count', $resource_columns, true)) {
    $downloads_sort_column = 'downloads_count';
}

$sort_column_map = [
    'upload_date' => $upload_sort_column,
    'downloads' => $downloads_sort_column ?: $upload_sort_column,
];

$sort_column = $sort_column_map[$sort_by] ?? $upload_sort_column;

// Get resources with sorting
$query = "SELECT * FROM resources {$where_clause} ORDER BY {$sort_column} {$sort_order} LIMIT {$items_per_page} OFFSET {$offset}";
$resources = $db->fetchAll($query, $params);

// Initialize stats array to prevent undefined variable errors
$stats = [
    'total' => 0,
    'slides' => 0,
    'videos' => 0,
    'documents' => 0
];

// Fetch stats from the database
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN resource_type = 'slides' THEN 1 ELSE 0 END) as slides,
    SUM(CASE WHEN resource_type = 'videos' THEN 1 ELSE 0 END) as videos,
    SUM(CASE WHEN resource_type = 'pdf' THEN 1 ELSE 0 END) as documents
FROM resources";

$stats_result = $db->fetchOne($stats_query);
if ($stats_result) {
    $stats = array_merge($stats, $stats_result);
}

// Flash message
$flash = getFlashMessage();

// CSRF token for actions
$csrf_token = generateCSRFToken();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resources Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* General Styling */
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }

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
            padding: 20px;
            min-height: 100vh;
        }


        .top-bar {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .page-header-row {
            animation: riseBlurIn 1s cubic-bezier(0.22, 1, 0.36, 1) 0.10s backwards;
        }

        .top-bar h3 {
            font-size: 24px;
            margin: 0;
        }

        .top-bar p {
            margin: 0;
            color: #666;
        }

        .header-right-icon {
            display: none;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            align-items: center;
            justify-content: center;
            background: rgba(102,126,234,0.08);
            color: #667eea;
            font-size: 18px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            font-size: 14px;
            transition: background 0.3s;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #764ba2, #667eea);
        }

        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
            animation: riseBlurIn 1s cubic-bezier(0.22, 1, 0.36, 1) 0.34s backwards;
        }

        /* Force two columns on small viewports for better use of space */
        @media (max-width: 600px) {
            .stats-overview { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .stat-box { padding: 14px; }
            .stat-icon { width:44px; height:44px; font-size:20px; }
            .stat-info h3 { font-size:20px; }
            .stat-info p { font-size:12px; }
        }

        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .stat-info h3 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }

        .stat-info p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }

        .table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .table th, .table td {
            padding: 15px;
            text-align: left;
        }

        .table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
        }

        .badge-success {
            background-color: #38ef7d;
            color: white;
        }

        .badge-danger {
            background-color: #f5576c;
            color: white;
        }

        .badge-warning {
            background-color: #ffcc00;
            color: white;
        }

        .badge-info {
            background-color: #4facfe;
            color: white;
        }

        .action-buttons .btn {
            font-size: 14px;
            padding: 8px 12px;
            border-radius: 5px;
            transition: background 0.3s;
        }

        .action-buttons .btn:hover {
            opacity: 0.9;
        }

        .action-buttons .btn-edit {
            background-color: #ffc107;
            color: white;
        }

        .action-buttons .btn-delete {
            background-color: #dc3545;
            color: white;
        }

        /* Custom Styles for Resources Page */
        .filters-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            animation: riseBlurIn 1s cubic-bezier(0.22, 1, 0.36, 1) 0.56s backwards;
        }
        .mobile-filter-toggle { display: none; }
        .mobile-collapsible { display: block; }
        .filters-card .filter-group { min-width: 0; }
        .filter-dropdown .btn { width: 100%; justify-content: space-between; align-items: center; }
        .filter-dropdown .dropdown-menu { width: 100%; max-height: 260px; overflow-y: auto; }

        .filters-card .form-label {
            font-weight: 500;
            margin-bottom: 10px;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #f2f2f2;
        }

        .table-striped tbody tr:hover {
            background-color: #e9ecef;
        }

        .table-responsive {
            margin-bottom: 20px;
        }

        /* Card Styles */
        .card {
            border: none;
            border-radius: 10px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .card-text {
            margin-bottom: 10px;
            color: #555;
        }

        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: white;
        }

        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }

        /* Masonry Grid Styles */
        .masonry-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        .masonry-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .masonry-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }

        .masonry-card .card-body {
            padding: 20px;
            background: linear-gradient(135deg, #f3f4f6, #e2e8f0);
        }

        .masonry-card .card-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .masonry-card .card-text {
            margin-bottom: 12px;
            color: #333;
        }

        .masonry-card .btn {
            font-size: 14px;
            padding: 10px 15px;
            border-radius: 5px;
            transition: background 0.3s;
        }

        .masonry-card .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            color: white;
        }

        .masonry-card .btn-primary:hover {
            background: linear-gradient(135deg, #764ba2, #667eea);
        }

        .masonry-card .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }

        /* Master-Detail Layout Styles */
        .master-detail-container {
            display: flex;
            gap: 20px;
            height: calc(100vh - 400px);
            min-height: 500px;
            animation: riseBlurIn 1s cubic-bezier(0.22, 1, 0.36, 1) 0.78s backwards;
        }
        
        @keyframes riseBlurIn {
            from {
                opacity: 0;
                filter: blur(6px);
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                filter: blur(0);
                transform: translateY(0);
            }
        }

        /* Master List (Left Panel) */
        .master-list {
            flex: 0 0 380px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow-y: auto;
            padding: 15px;
        }

        .resource-list-item {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid #e9ecef;
            background: #fff;
        }

        .resource-list-item:hover {
            background: #f8f9fa;
            border-color: #667eea;
            box-shadow: 0 2px 8px rgba(102,126,234,0.15);
        }

        .resource-list-item.active {
            background: linear-gradient(135deg, #667eea15, #764ba215);
            border-color: #667eea;
            box-shadow: 0 2px 8px rgba(102,126,234,0.25);
        }

        .resource-list-item .resource-icon {
            font-size: 1.5rem;
            margin-right: 12px;
            color: #667eea;
        }

        .resource-list-item .resource-title {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .resource-list-item .resource-meta {
            font-size: 12px;
            color: #6c757d;
        }

        .resource-list-item .badge {
            font-size: 10px;
            padding: 3px 8px;
            margin-right: 5px;
        }

        /* Detail Panel (Right Panel) */
        .detail-panel {
            flex: 1;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 30px;
            overflow-y: auto;
        }

        .detail-panel.empty-state {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: #adb5bd;
        }

        .detail-panel.empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
        }

        .detail-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }

        .detail-header h2 {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }

        .detail-actions {
            display: flex;
            gap: 10px;
        }

        .detail-body {
            margin-top: 20px;
        }

        .detail-section {
            margin-bottom: 25px;
        }

        .detail-section h5 {
            font-size: 14px;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
        }

        .detail-section p {
            font-size: 15px;
            color: #495057;
            line-height: 1.6;
        }

        .detail-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 15px;
        }

        .detail-badges .badge {
            font-size: 13px;
            padding: 8px 15px;
            border-radius: 6px;
        }

        @media (max-width: 992px) {
            .menu-toggle { display: inline-flex; align-items: center; justify-content: center; }
            .main-content { margin-left: 0; padding: 15px; min-height: auto; }
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
            body.sidebar-open .sidebar { transform: translateX(0); }
            .sidebar-backdrop { display: block; opacity: 0; transition: opacity 0.25s ease; }
            body.sidebar-open .sidebar-backdrop { opacity: 1; pointer-events: auto; }
            body.sidebar-open { overflow: hidden; }
            footer.mt-5 { margin-top: 0.6rem !important; }
            .top-bar { flex-direction: column; align-items: flex-start; gap: 12px; }
            .master-detail-container { flex-direction: column; height: auto; }
            .master-list { flex: none; max-height: 400px; }
            .detail-panel { min-height: 400px; }

            .page-header-row { flex-direction: column; align-items: stretch !important; gap: 10px; }
            .page-header-row .header-left { width: 100%; display: flex; align-items: center; justify-content: space-between; }
            .header-left .header-title { display: none; }
            .header-right-icon { display: inline-flex; }
            .page-header-row > div:last-child { width: 100%; }
            .add-resource-btn { width: 100%; align-self: stretch; justify-content: center; }

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
        /* Responsive header adjustments for small screens */
        @media (max-width: 480px) {
            .menu-toggle { padding: 10px; font-size: 20px; border-radius: 8px; }
            .menu-toggle i { font-size: 22px; }
            .add-resource-btn { padding: 8px 12px; font-size: 14px; border-radius: 10px; }
            @media (max-width: 360px) {
                .add-resource-btn { width: 100%; align-self: stretch; }
            }
        }
        /* Keep action button full-width in responsive header */
        @media (max-width: 768px) {
            .page-header-row { align-items: stretch; }
            .page-header-row > div:last-child { width: 100%; margin-left: 0; }
        }
    </style>
</head>
<body>
     <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4 page-header-row"
        style="background-color: #ffffff; padding: 20px;">
            <div class="d-flex align-items-center gap-3 header-left">
                <button class="menu-toggle" type="button" aria-label="Toggle sidebar" aria-expanded="false">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="header-title">
                    <h1 class="h3 mb-0 page-title">Resources Management</h1>
                    <p class="mb-0 text-muted">Manage learning resources, tutorials, and study materials</p>
                </div>
                <span class="header-right-icon" aria-hidden="true"><i class="fas fa-book"></i></span>
            </div>
            <div>
                <a href="add_resource.php" class="btn btn-primary add-resource-btn">
                    <i class="fas fa-plus me-2"></i> Add Resource
                </a>
            </div>
        </div>
        <div class="stats-overview">
            <div class="stat-box">
                <div class="stat-icon bg-primary">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['total']); ?></h3>
                    <p>Total Resources</p>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon bg-success">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['slides']); ?></h3>
                    <p>Slides</p>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon bg-danger">
                    <i class="fas fa-video"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['videos']); ?></h3>
                    <p>Videos</p>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon bg-warning">
                    <i class="fas fa-file-pdf"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['documents']); ?></h3>
                    <p>Documents</p>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <button type="button" class="btn btn-outline-primary mobile-filter-toggle" id="mobileFilterToggle" aria-expanded="false" aria-controls="mobileFilterCard">
            <span><i class="fas fa-filter me-2"></i>Filter Resources</span>
            <i class="fas fa-chevron-down" id="mobileFilterChevron"></i>
        </button>

        <div class="filters-card mb-4 mobile-collapsible" id="mobileFilterCard">
            <form method="GET" action="resources.php" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search Resources</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search by title, description, or tags..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Resource Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="tutorial" <?php echo $type_filter === 'tutorial' ? 'selected' : ''; ?>>Tutorial</option>
                        <option value="code_sample" <?php echo $type_filter === 'code_sample' ? 'selected' : ''; ?>>Code Sample</option>
                        <option value="video" <?php echo $type_filter === 'video' ? 'selected' : ''; ?>>Video</option>
                        <option value="pdf" <?php echo $type_filter === 'pdf' ? 'selected' : ''; ?>>PDF</option>
                        <option value="link" <?php echo $type_filter === 'link' ? 'selected' : ''; ?>>Link</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Visibility</label>
                    <select name="visibility" class="form-select">
                        <option value="">All</option>
                        <option value="featured" <?php echo $visibility_filter === 'featured' ? 'selected' : ''; ?>>Featured</option>
                        <option value="active" <?php echo $visibility_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Sort By</label>
                    <select name="sort" class="form-select">
                        <option value="upload_date" <?php echo $sort_by === 'upload_date' ? 'selected' : ''; ?>>Upload Date</option>
                        <option value="downloads" <?php echo $sort_by === 'downloads' ? 'selected' : ''; ?>>Downloads</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Order</label>
                    <select name="order" class="form-select">
                        <option value="desc" <?php echo $sort_order_ui === 'desc' ? 'selected' : ''; ?>>Desc</option>
                        <option value="asc" <?php echo $sort_order_ui === 'asc' ? 'selected' : ''; ?>>Asc</option>
                    </select>
                </div>
                
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-2"></i> Apply Filters
                    </button>
                    <a href="resources.php" class="btn btn-outline-secondary">
                        <i class="fas fa-redo me-2"></i> Reset
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Master-Detail Layout -->
        <div class="master-detail-container">
            <!-- Master List (Left Panel) -->
            <div class="master-list">
                <?php if (empty($resources)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No resources found</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($resources as $index => $resource): ?>
                        <div class="resource-list-item <?php echo $index === 0 ? 'active' : ''; ?>" 
                             data-resource-id="<?php echo $resource['resource_id']; ?>"
                             onclick="showResourceDetail(<?php echo htmlspecialchars(json_encode($resource)); ?>)">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-file-alt resource-icon"></i>
                                <div class="flex-grow-1">
                                    <div class="resource-title">
                                        <?php echo htmlspecialchars($resource['title'] ?? 'Unknown Title'); ?>
                                    </div>
                                    <div class="resource-meta mb-2">
                                        <?php echo isset($resource['upload_date']) ? date('M d, Y', strtotime($resource['upload_date'])) : 'Unknown Date'; ?>
                                    </div>
                                    <div>
                                        <span class="badge bg-info"><?php echo ucfirst($resource['resource_type'] ?? 'Unknown'); ?></span>
                                        <span class="badge bg-secondary"><?php echo $resource['download_count'] ?? 0; ?> downloads</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Detail Panel (Right Panel) -->
            <div class="detail-panel <?php echo empty($resources) ? 'empty-state' : ''; ?>" id="detailPanel">
                <?php if (empty($resources)): ?>
                    <i class="fas fa-file-alt"></i>
                    <h4>No Resource Selected</h4>
                    <p>Select a resource from the list to view details</p>
                <?php else: 
                    $firstResource = $resources[0];
                ?>
                    <div class="detail-header">
                        <div>
                            <h2><?php echo htmlspecialchars($firstResource['title'] ?? 'Unknown Title'); ?></h2>
                            <div class="detail-badges">
                                <span class="badge bg-info"><?php echo ucfirst($firstResource['resource_type'] ?? 'Unknown Type'); ?></span>
                                <?php if (isset($firstResource['related_event'])): ?>
                                    <span class="badge bg-secondary">Event #<?php echo $firstResource['related_event']; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="detail-actions">
                            <a href="edit_resource.php?id=<?php echo $firstResource['resource_id'] ?? 0; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <button type="button" class="btn btn-outline-danger" onclick="deleteResource(<?php echo (int)($firstResource['resource_id'] ?? 0); ?>)">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>

                    <div class="detail-body">
                        <div class="detail-section">
                            <h5>Description</h5>
                            <p><?php echo nl2br(htmlspecialchars($firstResource['description'] ?? 'No description available')); ?></p>
                        </div>

                        <div class="detail-section">
                            <h5>Resource Details</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <strong>Resource Type:</strong><br>
                                    <span class="badge bg-info mt-1"><?php echo ucfirst($firstResource['resource_type'] ?? 'Unknown'); ?></span>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <strong>Download Count:</strong><br>
                                    <span class="text-muted"><?php echo number_format($firstResource['download_count'] ?? 0); ?> downloads</span>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <strong>Upload Date:</strong><br>
                                    <span class="text-muted"><?php echo isset($firstResource['upload_date']) ? date('F d, Y', strtotime($firstResource['upload_date'])) : 'Unknown Date'; ?></span>
                                </div>
                                <?php if (isset($firstResource['related_event'])): ?>
                                <div class="col-md-6 mb-3">
                                    <strong>Related Event:</strong><br>
                                    <span class="text-muted">Event #<?php echo $firstResource['related_event']; ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="detail-section">
                            <h5>Tags</h5>
                            <p><?php echo htmlspecialchars($firstResource['tags'] ?? 'No tags'); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
        function showResourceDetail(resource) {
            // Remove active class from all items
            document.querySelectorAll('.resource-list-item').forEach(item => {
                item.classList.remove('active');
            });

            // Add active class to clicked item
            event.currentTarget.classList.add('active');

            // Update detail panel
            const detailPanel = document.getElementById('detailPanel');
            detailPanel.className = 'detail-panel';
            
            detailPanel.innerHTML = `
                <div class="detail-header">
                    <div>
                        <h2>${resource.title || 'Unknown Title'}</h2>
                        <div class="detail-badges">
                            <span class="badge bg-info">${resource.resource_type ? resource.resource_type.charAt(0).toUpperCase() + resource.resource_type.slice(1) : 'Unknown Type'}</span>
                            ${resource.related_event ? `<span class="badge bg-secondary">Event #${resource.related_event}</span>` : ''}
                        </div>
                    </div>
                    <div class="detail-actions">
                        <a href="edit_resource.php?id=${resource.resource_id || 0}" class="btn btn-outline-primary">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <button type="button" class="btn btn-outline-danger" onclick="deleteResource(${resource.resource_id || 0})">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>

                <div class="detail-body">
                    <div class="detail-section">
                        <h5>Description</h5>
                        <p>${resource.description ? resource.description.replace(/\n/g, '<br>') : 'No description available'}</p>
                    </div>

                    <div class="detail-section">
                        <h5>Resource Details</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <strong>Resource Type:</strong><br>
                                <span class="badge bg-info mt-1">${resource.resource_type ? resource.resource_type.charAt(0).toUpperCase() + resource.resource_type.slice(1) : 'Unknown'}</span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Download Count:</strong><br>
                                <span class="text-muted">${(resource.download_count || 0).toLocaleString()} downloads</span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Upload Date:</strong><br>
                                <span class="text-muted">${resource.upload_date ? new Date(resource.upload_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'Unknown Date'}</span>
                            </div>
                            ${resource.related_event ? `
                            <div class="col-md-6 mb-3">
                                <strong>Related Event:</strong><br>
                                <span class="text-muted">Event #${resource.related_event}</span>
                            </div>
                            ` : ''}
                        </div>
                    </div>

                    <div class="detail-section">
                        <h5>Tags</h5>
                        <p>${resource.tags || 'No tags'}</p>
                    </div>
                </div>
            `;
        }

        function deleteResource(resourceId) {
            if (!resourceId) return;

            const message = 'Delete this resource? This action cannot be undone.';
            const options = { title: 'Delete Resource', okLabel: 'Delete' };

            const runDelete = () => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `delete_resource.php?id=${resourceId}`;

                const csrf = document.createElement('input');
                csrf.type = 'hidden';
                csrf.name = 'csrf_token';
                csrf.value = <?php echo json_encode($csrf_token); ?>;

                form.appendChild(csrf);
                document.body.appendChild(form);
                form.submit();
            };

            if (typeof window.confirmModal === 'function') {
                window.confirmModal(message, options).then(function(ok) {
                    if (ok) runDelete();
                });
            } else {
                if (window.confirm(message)) runDelete();
            }
        }
        </script>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div>
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $items_per_page, $total_resources); ?> of <?php echo $total_resources; ?> resources
                </div>
                <nav>
                    <ul class="pagination mb-0">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Footer -->
    <footer class="bg-light text-center py-3 mt-5">
        <div class="container">
            <small class="text-muted">&copy; <?php echo date('Y'); ?> NACOSAU. All Rights Reserved.</small><br>
            <small class="text-muted">Developed by <a href="https://johnicity.com.ng/portfolio" target="_blank" class="text-decoration-none">Johnicity</a></small>
        </div>
    </footer>
    
    <?php // Include admin footer which loads Bootstrap and confirmation modal ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script>
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

            if (!filterToggle || !filterCard || !chevron) return;

            filterToggle.addEventListener('click', () => {
                const isVisible = filterCard.classList.toggle('show');
                filterToggle.setAttribute('aria-expanded', isVisible ? 'true' : 'false');
                chevron.classList.toggle('fa-chevron-up', isVisible);
                chevron.classList.toggle('fa-chevron-down', !isVisible);
            });
        })();
    </script>
</body>
</html>
