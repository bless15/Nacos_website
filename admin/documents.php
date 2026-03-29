
<?php
/**
 * ============================================
 * NACOS DASHBOARD - documents MANAGEMENT
 * ============================================
 * Purpose: Manage official NACOS documents
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
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Base query
$query = "
    SELECT d.*, a.full_name as uploaded_by_name
    FROM documents d
    LEFT JOIN administrators a ON d.uploaded_by = a.admin_id
";
$conditions = [];
$params = [];

// Add search condition
if (!empty($search_term)) {
    $conditions[] = "(d.title LIKE :search OR d.file_name LIKE :search OR d.description LIKE :search OR d.tags LIKE :search)";
    $params[':search'] = '%' . $search_term . '%';
}

if ($filter_type !== 'all') {
    $conditions[] = "d.doc_type = :type";
    $params[':type'] = $filter_type;
}
// Add remaining filters if needed (visibility, archived, etc.)
if ($filter_visibility !== 'all') {
    $conditions[] = "d.visibility = :visibility";
    $params[':visibility'] = $filter_visibility;
}
if ($filter_archived === 'active') {
    $conditions[] = "(d.is_archived = 0 OR d.is_archived IS NULL)";
} elseif ($filter_archived === 'archived') {
    $conditions[] = "d.is_archived = 1";
}

// Build final query
if (!empty($conditions)) {
    $query .= ' WHERE ' . implode(' AND ', $conditions);
}
$query .= " ORDER BY d.$sort_by $sort_order LIMIT $per_page OFFSET $offset";

// Fetch documents
$documents = $db->fetchAll($query, $params);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM documents d";
if (!empty($conditions)) {
    $count_query .= ' WHERE ' . implode(' AND ', $conditions);
}
$total_documents = $db->fetchOne($count_query, $params)['total'];
$total_pages = ($per_page > 0) ? ceil($total_documents / $per_page) : 1;

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

// Get flash message (ensure $flash is always defined)
$flash = function_exists('getFlashMessage') ? getFlashMessage() : null;
$delete_csrf_token = generateCSRFToken();

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

        .main-content {
            margin-left: 260px;
            padding: 20px;
            min-height: 100vh;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .menu-toggle { display: inline-flex; align-items: center; justify-content: center; }
            .main-content { margin-left: 0; padding: 15px 15px 6px; min-height: auto; }
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
            body.sidebar-open .sidebar { transform: translateX(0); }
            .sidebar-backdrop { display: block; opacity: 0; transition: opacity 0.25s ease; }
            body.sidebar-open .sidebar-backdrop { opacity: 1; pointer-events: auto; }
            body.sidebar-open { overflow: hidden; }
            footer.mt-5 { margin-top: 0.6rem !important; }

            .page-header-row { flex-direction: column; align-items: stretch !important; gap: 10px; }
            .page-header-row .header-left { width: 100%; display: flex; align-items: center; justify-content: space-between; }
            .header-left .header-title { display: none; }
            .header-right-icon { display: inline-flex; }
            .upload-btn { width: 100%; align-self: stretch; justify-content: center; }
        }

        /* Filter responsiveness helpers */
        .mobile-filter-toggle { display: none; }
        .mobile-collapsible { display: block; }
        .filters-card .filter-group { min-width: 0; }
        .filter-dropdown .btn { width: 100%; justify-content: space-between; align-items: center; }
        .filter-dropdown .dropdown-menu { width: 100%; max-height: 260px; overflow-y: auto; }
        /* Visible action container for headers */
        .actions-visible {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            width: 100%;
            background: #f2eded;
            padding: 12px 16px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.06);
            border: 1px solid rgba(0,0,0,0.04);
            box-sizing: border-box;
            animation: softFocusIn 1s cubic-bezier(0.22, 1, 0.36, 1) 0.10s backwards;
        }

        .filters-card {
            animation: softFocusIn 1s cubic-bezier(0.22, 1, 0.36, 1) 0.42s backwards;
        }

        .documents-grid,
        .container-fluid > .card:not(.filters-card) {
            animation: softFocusIn 1s cubic-bezier(0.22, 1, 0.36, 1) 0.78s backwards;
        }

        @keyframes softFocusIn {
            from {
                opacity: 0;
                filter: saturate(85%) blur(4px);
                transform: scale(0.985);
            }
            to {
                opacity: 1;
                filter: saturate(100%) blur(0);
                transform: scale(1);
            }
        }
    </style>
    <style>
        /* Responsive header adjustments for small screens */
        @media (max-width: 480px) {
            .menu-toggle { padding: 10px; font-size: 20px; border-radius: 8px; }
            .menu-toggle i { font-size: 22px; }
            .upload-btn { padding: 8px 12px; font-size: 14px; border-radius: 10px; }
            /* Make upload button full width on very narrow screens */
            @media (max-width: 360px) {
                .upload-btn { width: 100%; align-self: stretch; }
            }
        }
        /* Slightly increase touch target for toggle on all small viewports */
        @media (max-width: 992px) {
            .menu-toggle { padding: 8px 10px; }

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
    </style>
</head>
<body>
        	     <?php require_once __DIR__ . '/includes/sidebar.php'; ?>


       
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navigation -->
            <!--?php include 'includes/navbar.php'; ?-->
            
            <!-- Page Content -->
            <div class="container-fluid px-4 py-4">
                <!-- Page Header -->
                <div class="actions-visible d-flex justify-content-between align-items-center mb-4 page-header-row">
                    <div class="d-flex align-items-center gap-3 header-left">
                        <button class="menu-toggle" type="button" aria-label="Toggle sidebar" aria-expanded="false" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
                            <i class="fas fa-bars"></i>
                        </button>
                        <div class="header-title">
                            <h1 class="h3 mb-0 page-title">Documents Management</h1>
                            <p class="text-muted">Manage official NACOS documents and files</p>
                        </div>
                        <span class="header-right-icon" aria-hidden="true"><i class="fas fa-folder"></i></span>
                    </div>
                    <a href="add_document.php" class="btn btn-primary upload-btn">
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
                <button type="button" class="btn btn-outline-primary mobile-filter-toggle" id="mobileFilterToggle" aria-expanded="false" aria-controls="mobileFilterCard">
                    <span><i class="fas fa-filter me-2"></i>Filter Documents</span>
                    <i class="fas fa-chevron-down" id="mobileFilterChevron"></i>
                </button>

                <div class="card mb-4 filters-card mobile-collapsible" id="mobileFilterCard">
                    <div class="card-body">
                        <form action="documents.php" method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Search Documents</label>
                                <input type="text" name="search" class="form-control" placeholder="Search..." value="<?php echo htmlspecialchars($search_term); ?>">
                            </div>
                            <div class="col-md-2">
                                    <label class="form-label">Document Type</label>
                                    <div class="dropdown filter-dropdown">
                                        <input type="hidden" name="type" id="typeInput" value="<?php echo htmlspecialchars($filter_type); ?>">
                                        <button class="btn btn-outline-secondary dropdown-toggle text-start form-select" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="typeBtn">
                                            <span><?php echo ($filter_type === 'all' || !$filter_type) ? 'All Types' : ucfirst(str_replace('_', ' ', $filter_type)); ?></span>
                                        </button>
                                        <ul class="dropdown-menu" aria-labelledby="typeBtn">
                                            <li><a class="dropdown-item" data-target-input="typeInput" data-label="typeBtn" data-value="all" href="#">All Types</a></li>
                                            <li><a class="dropdown-item" data-target-input="typeInput" data-label="typeBtn" data-value="meeting_minutes" href="#">Meeting Minutes</a></li>
                                            <li><a class="dropdown-item" data-target-input="typeInput" data-label="typeBtn" data-value="financial_report" href="#">Financial Report</a></li>
                                            <li><a class="dropdown-item" data-target-input="typeInput" data-label="typeBtn" data-value="constitution" href="#">Constitution</a></li>
                                            <li><a class="dropdown-item" data-target-input="typeInput" data-label="typeBtn" data-value="policy" href="#">Policy</a></li>
                                            <li><a class="dropdown-item" data-target-input="typeInput" data-label="typeBtn" data-value="annual_report" href="#">Annual Report</a></li>
                                            <li><a class="dropdown-item" data-target-input="typeInput" data-label="typeBtn" data-value="event_report" href="#">Event Report</a></li>
                                            <li><a class="dropdown-item" data-target-input="typeInput" data-label="typeBtn" data-value="proposal" href="#">Proposal</a></li>
                                            <li><a class="dropdown-item" data-target-input="typeInput" data-label="typeBtn" data-value="correspondence" href="#">Correspondence</a></li>
                                            <li><a class="dropdown-item" data-target-input="typeInput" data-label="typeBtn" data-value="handover" href="#">Handover</a></li>
                                            <li><a class="dropdown-item" data-target-input="typeInput" data-label="typeBtn" data-value="other" href="#">Other</a></li>
                                        </ul>
                                    </div>
                                </div>
                            <div class="col-md-2">
                                <label class="form-label">Visibility</label>
                                <div class="dropdown filter-dropdown">
                                    <input type="hidden" name="visibility" id="visibilityInput" value="<?php echo htmlspecialchars($filter_visibility); ?>">
                                    <button class="btn btn-outline-secondary dropdown-toggle text-start form-select" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="visibilityBtn">
                                        <span><?php echo ($filter_visibility === 'all' || !$filter_visibility) ? 'All' : ucfirst($filter_visibility); ?></span>
                                    </button>
                                    <ul class="dropdown-menu" aria-labelledby="visibilityBtn">
                                        <li><a class="dropdown-item" data-target-input="visibilityInput" data-label="visibilityBtn" data-value="all" href="#">All</a></li>
                                        <li><a class="dropdown-item" data-target-input="visibilityInput" data-label="visibilityBtn" data-value="admin" href="#">Admin Only</a></li>
                                        <li><a class="dropdown-item" data-target-input="visibilityInput" data-label="visibilityBtn" data-value="members" href="#">Members</a></li>
                                        <li><a class="dropdown-item" data-target-input="visibilityInput" data-label="visibilityBtn" data-value="public" href="#">Public</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <div class="dropdown filter-dropdown">
                                    <input type="hidden" name="archived" id="archivedInput" value="<?php echo htmlspecialchars($filter_archived); ?>">
                                    <button class="btn btn-outline-secondary dropdown-toggle text-start form-select" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="archivedBtn">
                                        <span><?php echo ($filter_archived === 'active') ? 'Active' : (($filter_archived === 'archived') ? 'Archived' : 'All'); ?></span>
                                    </button>
                                    <ul class="dropdown-menu" aria-labelledby="archivedBtn">
                                        <li><a class="dropdown-item" data-target-input="archivedInput" data-label="archivedBtn" data-value="active" href="#">Active</a></li>
                                        <li><a class="dropdown-item" data-target-input="archivedInput" data-label="archivedBtn" data-value="archived" href="#">Archived</a></li>
                                        <li><a class="dropdown-item" data-target-input="archivedInput" data-label="archivedBtn" data-value="all" href="#">All</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">Sort</label>
                                <div class="dropdown filter-dropdown">
                                    <input type="hidden" name="sort" id="docSortInput" value="<?php echo htmlspecialchars($sort_by); ?>">
                                    <button class="btn btn-outline-secondary dropdown-toggle text-start form-select" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="docSortBtn">
                                        <span><?php echo ($sort_by === 'upload_date') ? 'Date' : (($sort_by === 'title') ? 'Title' : (($sort_by === 'file_size') ? 'Size' : 'Downloads')); ?></span>
                                    </button>
                                    <ul class="dropdown-menu" aria-labelledby="docSortBtn">
                                        <li><a class="dropdown-item" data-target-input="docSortInput" data-label="docSortBtn" data-value="upload_date" href="#">Date</a></li>
                                        <li><a class="dropdown-item" data-target-input="docSortInput" data-label="docSortBtn" data-value="title" href="#">Title</a></li>
                                        <li><a class="dropdown-item" data-target-input="docSortInput" data-label="docSortBtn" data-value="file_size" href="#">Size</a></li>
                                        <li><a class="dropdown-item" data-target-input="docSortInput" data-label="docSortBtn" data-value="download_count" href="#">Downloads</a></li>
                                    </ul>
                                </div>
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
                
                <!-- Modern Card List Style -->
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
                    <style>
                    /* Compact document cards, single-column layout */
                        .documents-grid { display: grid; grid-template-columns: 1fr; gap: 0.6rem; width: 100%; max-width: none; margin: 0; padding: 0; }
                    .document-card { position: relative; border-radius: 12px; box-shadow: 0 2px 8px rgba(44,62,80,0.06); transition: box-shadow 0.18s, transform 0.18s; border: 1px solid #eef2f7; background: #fff; margin-bottom: 0.75rem; }
                    .document-card:hover { transform: translateY(-4px); box-shadow: 0 8px 22px rgba(44,62,80,0.09); }
                    .document-card .card-body { padding: 0.7rem 0.9rem; padding-right: 2.6rem; display:flex; align-items:center; gap:0.6rem; }
                    .document-card .fa-file-pdf { font-size: 1.1rem; margin-right: 0.55rem; color: #e55353; }
                    .document-card .fw-bold { font-size: 0.98rem; display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width: calc(100% - 28px); }
                    .document-card .meta-row { font-size: 0.9rem; color: #6c757d; }
                    .document-card .badge { font-size: 0.72rem; margin-right: 0.35rem; padding: 0.22rem 0.45rem; }
                    .document-card .kebab-btn { width:34px; height:34px; padding:0; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; background:#fff; border:1px solid #eef2f7; box-shadow:0 1px 2px rgba(44,62,80,0.04); font-size:0.92rem; }
                    .document-card .dropdown, .document-card .dropup { position:absolute; top:50%; right:12px; transform:translateY(-50%); }
                    .document-card .dropdown .dropdown-menu { min-width:10rem; }
                    .document-card .collapse { transition: height 0.2s cubic-bezier(.4,0,.2,1); }
                    .document-card .card-body.bg-light-subtle { background:#f8fafc !important; border-top:1px solid #e9ecef; border-radius:0 0 12px 12px; margin-top:-0.5rem; }
                    @media (max-width:600px) { .document-card .card-body { flex-direction:column; align-items:stretch; gap:0.5rem; padding:0.8rem; padding-right:2.2rem; } .document-card .dropdown, .document-card .dropup { top:10px; right:10px; transform:none; } }
                    .document-card:focus-within { box-shadow: 0 6px 20px rgba(44,62,80,0.08); outline: 3px solid rgba(102,126,234,0.08); }
                    </style>
                    <div class="documents-grid">
                        <?php foreach ($documents as $index => $doc): ?>
                        <div>
                            <div class="document-card card shadow-sm border-0">
                                <div class="card-body">
                                    <i class="fas fa-file-pdf text-danger"></i>
                                    <div class="lh-sm flex-grow-1" style="min-width:0;">
                                        <div class="fw-bold mb-1" title="<?php echo htmlspecialchars($doc['title']); ?>"><?php echo htmlspecialchars($doc['title']); ?><?php if ($doc['is_archived']): ?> <span class="badge bg-secondary ms-2">Archived</span><?php endif; ?></div>
                                        <div class="meta-row text-truncate"><?php echo htmlspecialchars($doc['file_name']); ?></div>
                                        <div class="d-flex flex-wrap gap-2 mt-1">
                                            <span class="badge bg-info"><?php echo ucfirst(str_replace('_', ' ', $doc['doc_type'])); ?></span>
                                            <span class="badge bg-<?php echo match($doc['visibility']) { 'admin' => 'danger', 'members' => 'warning', 'public' => 'success', default => 'secondary' }; ?>"><?php echo ucfirst($doc['visibility']); ?></span>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <div class="dropup">
                                            <button class="btn btn-sm kebab-btn" type="button" id="docMenu<?php echo $doc['doc_id']; ?>" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Open actions for <?php echo htmlspecialchars($doc['title']); ?>">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="docMenu<?php echo $doc['doc_id']; ?>" role="menu">
                                                <li><a class="dropdown-item" role="menuitem" href="view_document.php?id=<?php echo $doc['doc_id']; ?>">View</a></li>
                                                <li><a class="dropdown-item" role="menuitem" href="edit_document.php?id=<?php echo $doc['doc_id']; ?>">Edit</a></li>
                                                <li>
                                                    <button class="dropdown-item text-danger" role="menuitem" type="button" onclick="deleteDocument(<?php echo (int)$doc['doc_id']; ?>)">Delete</button>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><button class="dropdown-item" role="menuitem" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $doc['doc_id']; ?>" aria-expanded="false">Details</button></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="collapse" id="collapse<?php echo $doc['doc_id']; ?>">
                                    <div class="card-body bg-light-subtle border-top rounded-bottom">
                                        <div class="row">
                                            <div class="col-md-6 mb-2">
                                                <strong>Description:</strong><br>
                                                <span><?php echo nl2br(htmlspecialchars($doc['description'] ?? '')); ?></span>
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <strong>Tags:</strong><br>
                                                <span><?php echo htmlspecialchars($doc['tags'] ?? ''); ?></span>
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <strong>Academic Session:</strong><br>
                                                <span><?php echo htmlspecialchars($doc['academic_session'] ?? ''); ?></span>
                                            </div>
                                        </div>
                                        <div class="row mt-3">
                                            <div class="col-12 d-flex flex-wrap gap-2">
                                                <span class="badge bg-light text-dark border">Size: <?php echo $doc['file_size'] ? formatFileSize($doc['file_size']) : 'N/A'; ?></span>
                                                <span class="badge bg-light text-dark border">Downloads: <?php echo $doc['download_count']; ?></span>
                                                <span class="badge bg-light text-dark border">Uploaded: <?php echo date('M d, Y', strtotime($doc['upload_date'])); ?></span>
                                                <span class="badge bg-light text-dark border">By: <?php echo htmlspecialchars($doc['uploaded_by_name']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
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

    <form id="deleteDocumentForm" method="POST" action="" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?php echo $delete_csrf_token; ?>">
    </form>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function(){
        window.deleteDocument = function(documentId) {
            const form = document.getElementById('deleteDocumentForm');
            if (!form || !documentId) return;

            const submitDelete = function() {
                form.action = 'delete_document.php?id=' + encodeURIComponent(documentId);
                form.submit();
            };

            if (typeof window.confirmModal !== 'function') {
                if (confirm('Are you sure you want to delete this document? This action cannot be undone.')) {
                    submitDelete();
                }
                return;
            }

            window.confirmModal('Are you sure you want to delete this document? This action cannot be undone.', {
                title: 'Delete Document',
                okLabel: 'Delete'
            }).then(function(confirmed) {
                if (confirmed) submitDelete();
            });
        };

        // Filter dropdown handler to sync hidden input + label (documents)
        (function(){
            const items = document.querySelectorAll('.filter-dropdown .dropdown-item');
            items.forEach(item => {
                item.addEventListener('click', function(e){
                    e.preventDefault();
                    const targetId = this.dataset.targetInput;
                    const labelId = this.dataset.label;
                    const value = this.dataset.value;
                    const target = document.getElementById(targetId);
                    const label = document.getElementById(labelId) ? document.getElementById(labelId).querySelector('span') : null;
                    if (target) target.value = value;
                    if (label) label.textContent = this.textContent.trim();
                });
            });
        })();

        // Sidebar toggle behavior (mobile)
        const toggleBtn = document.querySelector('.menu-toggle');
        const backdrop = document.querySelector('.sidebar-backdrop');
        const body = document.body;
        if (toggleBtn) {
            const closeMenu = () => {
                body.classList.remove('sidebar-open');
                toggleBtn.setAttribute('aria-expanded', 'false');
            };
            const toggleMenu = () => {
                const isOpen = body.classList.toggle('sidebar-open');
                toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            };
            toggleBtn.addEventListener('click', toggleMenu);
            if (backdrop) backdrop.addEventListener('click', closeMenu);
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeMenu(); });
        }

        // Mobile filters collapse toggle (documents)
        const filterToggle = document.getElementById('mobileFilterToggle');
        const filterCard = document.getElementById('mobileFilterCard');
        const filterChevron = document.getElementById('mobileFilterChevron');
        if (filterToggle && filterCard && filterChevron) {
            filterToggle.addEventListener('click', function() {
                const isVisible = filterCard.classList.toggle('show');
                filterToggle.setAttribute('aria-expanded', isVisible ? 'true' : 'false');
                filterChevron.classList.toggle('fa-chevron-up', isVisible);
                filterChevron.classList.toggle('fa-chevron-down', !isVisible);
            });
        }
    });
    </script>
</body>
</html>
