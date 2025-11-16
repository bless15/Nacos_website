<?php
/**
 * ============================================
 * NACOS DASHBOARD - DOCUMENTS MANAGEMENT
 * ============================================
 * Purpose: Manage official NACOS documents
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

// Initialize database
$db = getDB();

// --- Filtering, Searching, and Sorting Logic ---
$search_term = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$filter_type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : 'all';
$filter_visibility = isset($_GET['visibility']) ? sanitizeInput($_GET['visibility']) : 'all';
$filter_archived = isset($_GET['archived']) ? sanitizeInput($_GET['archived']) : 'active';
$sort_by = isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'upload_date';
$sort_order = isset($_GET['order']) ? sanitizeInput($_GET['order']) : 'DESC';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Base query
$query = "
    SELECT d.*, a.full_name as uploaded_by_name
    FROM DOCUMENTS d
    LEFT JOIN ADMINISTRATORS a ON d.uploaded_by = a.admin_id
";
$conditions = [];
$params = [];

// Add search condition
if (!empty($search_term)) {
    $conditions[] = "(d.title LIKE :search OR d.file_name LIKE :search OR d.description LIKE :search OR d.tags LIKE :search)";
    $params[':search'] = '%' . $search_term . '%';
}

// Add type filter
if ($filter_type !== 'all') {
    $conditions[] = "d.doc_type = :type";
    $params[':type'] = $filter_type;
}

// Add visibility filter
if ($filter_visibility !== 'all') {
    $conditions[] = "d.visibility = :visibility";
    $params[':visibility'] = $filter_visibility;
}

// Add archived filter
if ($filter_archived === 'active') {
    $conditions[] = "d.is_archived = FALSE";
} elseif ($filter_archived === 'archived') {
    $conditions[] = "d.is_archived = TRUE";
}

// Append conditions to query
if (!empty($conditions)) {
    $query .= " WHERE " . implode(' AND ', $conditions);
}

// Add sorting
$allowed_sorts = ['title', 'upload_date', 'doc_type', 'file_size', 'download_count'];
$sort_by = in_array($sort_by, $allowed_sorts) ? $sort_by : 'upload_date';
$sort_order = ($sort_order === 'ASC') ? 'ASC' : 'DESC';
$query .= " ORDER BY d.$sort_by $sort_order";

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM DOCUMENTS d";
if (!empty($conditions)) {
    $count_query .= " WHERE " . implode(' AND ', $conditions);
}
$total_documents = $db->fetchOne($count_query, $params)['total'];
$total_pages = ceil($total_documents / $per_page);

// Add pagination to query
$query .= " LIMIT $per_page OFFSET $offset";

// Fetch documents
$documents = $db->fetchAll($query, $params);

// Get flash message
$flash = getFlashMessage();

// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documents Management - NACOS Admin</title>
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
                        <h1 class="h3 mb-0">Documents Management</h1>
                        <p class="text-muted">Manage official NACOS documents and files</p>
                    </div>
                    <a href="add_document.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Upload New Document
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
                        <form action="documents.php" method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Search Documents</label>
                                <input type="text" name="search" class="form-control" placeholder="Search..." value="<?php echo htmlspecialchars($search_term); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Document Type</label>
                                <select name="type" class="form-select">
                                    <option value="all">All Types</option>
                                    <option value="meeting_minutes" <?php echo ($filter_type === 'meeting_minutes') ? 'selected' : ''; ?>>Meeting Minutes</option>
                                    <option value="financial_report" <?php echo ($filter_type === 'financial_report') ? 'selected' : ''; ?>>Financial Report</option>
                                    <option value="constitution" <?php echo ($filter_type === 'constitution') ? 'selected' : ''; ?>>Constitution</option>
                                    <option value="policy" <?php echo ($filter_type === 'policy') ? 'selected' : ''; ?>>Policy</option>
                                    <option value="annual_report" <?php echo ($filter_type === 'annual_report') ? 'selected' : ''; ?>>Annual Report</option>
                                    <option value="event_report" <?php echo ($filter_type === 'event_report') ? 'selected' : ''; ?>>Event Report</option>
                                    <option value="proposal" <?php echo ($filter_type === 'proposal') ? 'selected' : ''; ?>>Proposal</option>
                                    <option value="correspondence" <?php echo ($filter_type === 'correspondence') ? 'selected' : ''; ?>>Correspondence</option>
                                    <option value="handover" <?php echo ($filter_type === 'handover') ? 'selected' : ''; ?>>Handover</option>
                                    <option value="other" <?php echo ($filter_type === 'other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Visibility</label>
                                <select name="visibility" class="form-select">
                                    <option value="all">All</option>
                                    <option value="admin" <?php echo ($filter_visibility === 'admin') ? 'selected' : ''; ?>>Admin Only</option>
                                    <option value="members" <?php echo ($filter_visibility === 'members') ? 'selected' : ''; ?>>Members</option>
                                    <option value="public" <?php echo ($filter_visibility === 'public') ? 'selected' : ''; ?>>Public</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select name="archived" class="form-select">
                                    <option value="active" <?php echo ($filter_archived === 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="archived" <?php echo ($filter_archived === 'archived') ? 'selected' : ''; ?>>Archived</option>
                                    <option value="all" <?php echo ($filter_archived === 'all') ? 'selected' : ''; ?>>All</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">Sort</label>
                                <select name="sort" class="form-select">
                                    <option value="upload_date" <?php echo ($sort_by === 'upload_date') ? 'selected' : ''; ?>>Date</option>
                                    <option value="title" <?php echo ($sort_by === 'title') ? 'selected' : ''; ?>>Title</option>
                                    <option value="file_size" <?php echo ($sort_by === 'file_size') ? 'selected' : ''; ?>>Size</option>
                                    <option value="download_count" <?php echo ($sort_by === 'download_count') ? 'selected' : ''; ?>>Downloads</option>
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
                
                <!-- Documents Table -->
                <?php if (empty($documents)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                            <h3>No Documents Found</h3>
                            <p class="text-muted">No documents match your criteria or none have been uploaded yet.</p>
                            <a href="add_document.php" class="btn btn-primary mt-3">
                                <i class="fas fa-plus"></i> Upload Your First Document
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Document Title</th>
                                        <th>Type</th>
                                        <th>Visibility</th>
                                        <th>Size</th>
                                        <th>Uploaded</th>
                                        <th>Downloads</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($documents as $doc): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-file-pdf text-danger me-2"></i>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($doc['title']); ?></strong>
                                                        <?php if ($doc['is_archived']): ?>
                                                            <span class="badge bg-secondary ms-2">Archived</span>
                                                        <?php endif; ?>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($doc['file_name']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="badge bg-info"><?php echo ucfirst(str_replace('_', ' ', $doc['doc_type'])); ?></span></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo match($doc['visibility']) {
                                                        'admin' => 'danger',
                                                        'members' => 'warning',
                                                        'public' => 'success',
                                                        default => 'secondary'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst($doc['visibility']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $doc['file_size'] ? formatFileSize($doc['file_size']) : 'N/A'; ?></td>
                                            <td>
                                                <small><?php echo date('M d, Y', strtotime($doc['upload_date'])); ?></small>
                                                <br>
                                                <small class="text-muted">by <?php echo htmlspecialchars($doc['uploaded_by_name']); ?></small>
                                            </td>
                                            <td><span class="badge bg-light text-dark"><?php echo $doc['download_count']; ?></span></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="view_document.php?id=<?php echo $doc['doc_id']; ?>" class="btn btn-outline-primary" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="edit_document.php?id=<?php echo $doc['doc_id']; ?>" class="btn btn-outline-secondary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="delete_document.php?id=<?php echo $doc['doc_id']; ?>" class="btn btn-outline-danger" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Documents pagination" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_term); ?>&type=<?php echo $filter_type; ?>&visibility=<?php echo $filter_visibility; ?>&archived=<?php echo $filter_archived; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $sort_order; ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo ($page === $i) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_term); ?>&type=<?php echo $filter_type; ?>&visibility=<?php echo $filter_visibility; ?>&archived=<?php echo $filter_archived; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $sort_order; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_term); ?>&type=<?php echo $filter_type; ?>&visibility=<?php echo $filter_visibility; ?>&archived=<?php echo $filter_archived; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $sort_order; ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
