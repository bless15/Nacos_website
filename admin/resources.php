<?php
/**
 * ============================================
 * NACOS DASHBOARD - RESOURCES MANAGEMENT
 * ============================================
 * Purpose: Manage learning resources, tutorials, code samples
 * Access: Admin only
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
$featured_filter = isset($_GET['featured']) ? sanitizeInput($_GET['featured']) : '';

// Build WHERE clause
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(title LIKE ? OR description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($type_filter)) {
    $where_conditions[] = "resource_type = ?";
    $params[] = $type_filter;
}

if (!empty($level_filter)) {
    // Level filter removed - column doesn't exist in schema
    // $where_conditions[] = "level = ?";
    // $params[] = $level_filter;
}

if ($featured_filter === 'yes') {
    // Featured filter removed - column doesn't exist in schema
    // $where_conditions[] = "is_featured = 1";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_query = "SELECT COUNT(*) as total FROM RESOURCES $where_clause";
$total_resources = $db->fetchOne($count_query, $params)['total'];
$total_pages = ceil($total_resources / $items_per_page);

// Get resources
$resources_query = "
    SELECT r.*
    FROM RESOURCES r
    $where_clause
    ORDER BY r.upload_date DESC
    LIMIT $items_per_page OFFSET $offset
";
$resources = $db->fetchAll($resources_query, $params);

// Get statistics
$stats = [
    'total' => $db->fetchOne("SELECT COUNT(*) as count FROM RESOURCES")['count'],
    'slides' => $db->fetchOne("SELECT COUNT(*) as count FROM RESOURCES WHERE resource_type = 'slides'")['count'],
    'videos' => $db->fetchOne("SELECT COUNT(*) as count FROM RESOURCES WHERE resource_type = 'video'")['count'],
    'documents' => $db->fetchOne("SELECT COUNT(*) as count FROM RESOURCES WHERE resource_type = 'document'")['count'],
];

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resources Management - NACOS Admin</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navigation -->
            <?php include 'includes/navbar.php'; ?>
            
            <!-- Page Content -->
            <div class="container-fluid px-4 py-4">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-0">Resources Management</h1>
                        <p class="text-muted">Manage learning resources, tutorials, and study materials</p>
                    </div>
                    <a href="add_resource.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Resource
                    </a>
                </div>
                
                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                        <?php echo $flash['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-book-open fa-2x text-primary"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h3 class="mb-0"><?php echo number_format($stats['total']); ?></h3>
                                        <p class="text-muted mb-0 small">Total Resources</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-presentation fa-2x text-primary"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h3 class="mb-0"><?php echo number_format($stats['slides']); ?></h3>
                                        <p class="text-muted mb-0 small">Slides</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-video fa-2x text-danger"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h3 class="mb-0"><?php echo number_format($stats['videos']); ?></h3>
                                        <p class="text-muted mb-0 small">Videos</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-file-pdf fa-2x text-success"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h3 class="mb-0"><?php echo number_format($stats['documents']); ?></h3>
                                        <p class="text-muted mb-0 small">Documents</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label"><i class="fas fa-search"></i> Search</label>
                                <input type="text" class="form-control" name="search" placeholder="Title, description, course..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><i class="fas fa-filter"></i> Type</label>
                                <select class="form-select" name="type">
                                    <option value="">All Types</option>
                                    <option value="slides" <?php echo $type_filter === 'slides' ? 'selected' : ''; ?>>Slides</option>
                                    <option value="video" <?php echo $type_filter === 'video' ? 'selected' : ''; ?>>Video</option>
                                    <option value="document" <?php echo $type_filter === 'document' ? 'selected' : ''; ?>>Document</option>
                                    <option value="code" <?php echo $type_filter === 'code' ? 'selected' : ''; ?>>Code</option>
                                    <option value="link" <?php echo $type_filter === 'link' ? 'selected' : ''; ?>>External Link</option>
                                    <option value="book" <?php echo $type_filter === 'book' ? 'selected' : ''; ?>>Book</option>
                                    <option value="other" <?php echo $type_filter === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><i class="fas fa-eye"></i> Visibility</label>
                                <select class="form-select" name="visibility">
                                    <option value="">All</option>
                                    <option value="public" <?php echo $featured_filter === 'public' ? 'selected' : ''; ?>>Public</option>
                                    <option value="members_only" <?php echo $featured_filter === 'members_only' ? 'selected' : ''; ?>>Members Only</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter"></i> Apply
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Resources List -->
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($resources)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                                <p class="text-muted">No resources found. Try adjusting your filters.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Type</th>
                                            <th>Related Event</th>
                                            <th>Tags</th>
                                            <th>Downloads</th>
                                            <th>Uploaded</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($resources as $resource): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($resource['title']); ?></strong>
                                                    <?php if ($resource['visibility'] === 'members_only'): ?>
                                                        <span class="badge bg-warning text-dark ms-1"><i class="fas fa-lock"></i> Members Only</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $type_badges = [
                                                        'slides' => 'primary',
                                                        'video' => 'danger',
                                                        'document' => 'success',
                                                        'code' => 'info',
                                                        'link' => 'dark',
                                                        'book' => 'secondary',
                                                        'other' => 'secondary'
                                                    ];
                                                    $badge_color = $type_badges[$resource['resource_type']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $badge_color; ?>">
                                                        <?php echo str_replace('_', ' ', ucfirst($resource['resource_type'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($resource['event_id'])): ?>
                                                        <span class="badge bg-info">Event #<?php echo $resource['event_id']; ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($resource['tags'] ?? '-'); ?></td>
                                                <td><?php echo number_format($resource['downloads_count'] ?? 0); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($resource['upload_date'])); ?></td>
                                                <td>
                                                    <a href="edit_resource.php?id=<?php echo $resource['resource_id']; ?>" 
                                                       class="btn btn-sm btn-warning" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="delete_resource.php?id=<?php echo $resource['resource_id']; ?>" 
                                                       class="btn btn-sm btn-danger" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
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
                        <?php endif; ?>
                    </div>
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
    
    <?php // Include admin footer which loads Bootstrap and confirmation modal ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
