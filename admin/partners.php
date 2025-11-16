<?php
require_once __DIR__ . '/../includes/security.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
requireAdminRole();

$db = getDB();
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$items_per_page = 12;
$offset = ($page - 1) * $items_per_page;

$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(company_name LIKE ? OR description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($type_filter)) {
    $where_conditions[] = "partnership_type = ?";
    $params[] = $type_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
$total_partners = $db->fetchOne("SELECT COUNT(*) as total FROM PARTNERS $where_clause", $params)['total'];
$total_pages = ceil($total_partners / $items_per_page);

$partners = $db->fetchAll("
    SELECT p.*
    FROM PARTNERS p
    $where_clause
    ORDER BY p.partnership_start_date DESC, p.created_at DESC
    LIMIT $items_per_page OFFSET $offset
", $params);

$stats = [
    'total' => $db->fetchOne("SELECT COUNT(*) as count FROM PARTNERS")['count'],
    'sponsors' => $db->fetchOne("SELECT COUNT(*) as count FROM PARTNERS WHERE partnership_type = 'sponsor'")['count'],
    'mentors' => $db->fetchOne("SELECT COUNT(*) as count FROM PARTNERS WHERE partnership_type = 'mentor'")['count'],
    'active' => $db->fetchOne("SELECT COUNT(*) as count FROM PARTNERS WHERE status = 'active'")['count'],
];

// Count new partner requests for admin quick access badge
$request_count_row = $db->fetchOne("SELECT COUNT(*) as cnt FROM PARTNER_REQUESTS WHERE status = 'new'");
$request_count = $request_count_row ? intval($request_count_row['cnt']) : 0;

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Partners Management - NACOS Admin</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <?php include 'includes/navbar.php'; ?>
            <div class="container-fluid px-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-0">Partners Management</h1>
                        <p class="text-muted">Manage sponsors, collaborators, and partners</p>
                    </div>
                    <div class="btn-group" role="group" aria-label="partner-actions">
                        <a href="partner_requests.php" class="btn btn-outline-primary">
                            <i class="fas fa-inbox"></i> Requests
                            <?php if ($request_count > 0): ?>
                                <span class="badge bg-danger ms-1"><?php echo $request_count; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="add_partner.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Partner</a>
                    </div>
                </div>
                
                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                        <?php echo $flash['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-handshake fa-2x text-primary me-3"></i>
                                    <div>
                                        <h3 class="mb-0"><?php echo number_format($stats['total']); ?></h3>
                                        <p class="text-muted mb-0 small">Total Partners</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-dollar-sign fa-2x text-success me-3"></i>
                                    <div>
                                        <h3 class="mb-0"><?php echo number_format($stats['sponsors']); ?></h3>
                                        <p class="text-muted mb-0 small">Sponsors</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-user-tie fa-2x text-info me-3"></i>
                                    <div>
                                        <h3 class="mb-0"><?php echo number_format($stats['mentors']); ?></h3>
                                        <p class="text-muted mb-0 small">Mentors</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-check-circle fa-2x text-success me-3"></i>
                                    <div>
                                        <h3 class="mb-0"><?php echo number_format($stats['active']); ?></h3>
                                        <p class="text-muted mb-0 small">Active Partners</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <input type="text" class="form-control" name="search" placeholder="Search partners..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" name="type">
                                    <option value="">All Types</option>
                                    <option value="sponsor" <?php echo $type_filter === 'sponsor' ? 'selected' : ''; ?>>Sponsor</option>
                                    <option value="mentor" <?php echo $type_filter === 'mentor' ? 'selected' : ''; ?>>Mentor</option>
                                    <option value="industry_partner" <?php echo $type_filter === 'industry_partner' ? 'selected' : ''; ?>>Industry Partner</option>
                                    <option value="academic_partner" <?php echo $type_filter === 'academic_partner' ? 'selected' : ''; ?>>Academic Partner</option>
                                    <option value="other" <?php echo $type_filter === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Search</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php if (empty($partners)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-briefcase fa-4x text-muted mb-3"></i>
                        <p class="text-muted">No partners found.</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($partners as $partner): ?>
                            <div class="col-md-3 mb-4">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <?php if (!empty($partner['company_logo'])): ?>
                                            <img src="<?php echo htmlspecialchars($partner['company_logo']); ?>" 
                                                 alt="<?php echo htmlspecialchars($partner['company_name']); ?>" 
                                                 class="img-fluid mb-3" style="max-height: 100px;">
                                        <?php else: ?>
                                            <i class="fas fa-building fa-4x text-muted mb-3"></i>
                                        <?php endif; ?>
                                        <h5 class="card-title"><?php echo htmlspecialchars($partner['company_name']); ?></h5>
                                        <span class="badge bg-primary mb-2">
                                            <?php echo ucfirst(str_replace('_', ' ', $partner['partnership_type'])); ?>
                                        </span>
                                        <span class="badge bg-<?php echo $partner['status'] === 'active' ? 'success' : 'secondary'; ?> mb-2">
                                            <?php echo ucfirst($partner['status']); ?>
                                        </span>
                                        <?php if ($partner['visibility'] === 'public'): ?>
                                            <span class="badge bg-info text-dark mb-2"><i class="fas fa-eye"></i> Public</span>
                                        <?php endif; ?>
                                        <p class="card-text small text-muted">
                                            <?php echo substr(htmlspecialchars($partner['description'] ?? ''), 0, 100); ?>...
                                        </p>
                                        <div class="d-flex gap-2 justify-content-center">
                                            <?php if (!empty($partner['website_url'])): ?>
                                                <a href="<?php echo htmlspecialchars($partner['website_url']); ?>" 
                                                   target="_blank" 
                                                   rel="noopener noreferrer"
                                                   class="btn btn-sm btn-info" 
                                                   title="Visit Website">
                                                    <i class="fas fa-external-link-alt"></i>
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary" 
                                                        title="No website available" 
                                                        disabled>
                                                    <i class="fas fa-external-link-alt"></i>
                                                </button>
                                            <?php endif; ?>
                                            <a href="edit_partner.php?id=<?php echo $partner['partner_id']; ?>" 
                                               class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if (!empty($partner['is_featured'])): ?>
                                                <a href="toggle_featured.php?id=<?php echo $partner['partner_id']; ?>&action=off" class="btn btn-sm btn-outline-success" title="Unfeature">
                                                    <i class="fas fa-star"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="toggle_featured.php?id=<?php echo $partner['partner_id']; ?>&action=on" class="btn btn-sm btn-outline-secondary" title="Feature">
                                                    <i class="far fa-star"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="delete_partner.php?id=<?php echo $partner['partner_id']; ?>" 
                                               class="btn btn-sm btn-danger" 
                                               title="Delete"
                                               class="confirm-action-link" data-message="Are you sure you want to delete this partner?">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                        <nav>
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
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
    
    <?php // Include admin footer which loads Bootstrap and confirmation modal ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
