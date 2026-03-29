<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
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
$total_partners = $db->fetchOne("SELECT COUNT(*) as total FROM partners $where_clause", $params)['total'];
$total_pages = ceil($total_partners / $items_per_page);

$partners = $db->fetchAll("
    SELECT p.*
    FROM partners p
    $where_clause
    ORDER BY p.partnership_start_date DESC, p.created_at DESC
    LIMIT $items_per_page OFFSET $offset
", $params);

$stats = [
    'total' => $db->fetchOne("SELECT COUNT(*) as count FROM partners")['count'],
    'sponsors' => $db->fetchOne("SELECT COUNT(*) as count FROM partners WHERE partnership_type = 'sponsor'")['count'],
    'mentors' => $db->fetchOne("SELECT COUNT(*) as count FROM partners WHERE partnership_type = 'mentor'")['count'],
    'active' => $db->fetchOne("SELECT COUNT(*) as count FROM partners WHERE status = 'active'")['count'],
];

$request_count_row = $db->fetchOne("SELECT COUNT(*) as cnt FROM partner_requests WHERE status = 'new'");
$request_count = $request_count_row ? intval($request_count_row['cnt']) : 0;

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partners Management - NACOS Admin</title>
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

        .partner-actions-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sidebar-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.35);
            z-index: 900;
            pointer-events: none;
        }
        /* Main Content Fix */
        .main-content {
            margin-left: 260px;
            padding: 20px;
            min-height: 100vh;
        }

        /* Polished flip-card styles */
        .flip-card { perspective: 1200px; position: relative; min-height: 260px; background: transparent; }
        .flip-inner { position: relative; width: 100%; min-height: 260px; transform-style: preserve-3d; transition: transform 0.6s cubic-bezier(.2,.9,.3,1); }
        .flip-card:hover .flip-inner, .flip-inner.flipped { transform: rotateY(180deg); }

        .flip-front, .flip-back {
            position: absolute;
            inset: 0;
            border-radius: 14px;
            -webkit-backface-visibility: hidden;
            backface-visibility: hidden;
            box-shadow: 0 12px 28px rgba(12,35,64,0.06);
            overflow: hidden;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 22px;
            box-sizing: border-box;
        }

        .flip-front { z-index: 2; }
        .flip-back { transform: rotateY(180deg); z-index: 1; justify-content: flex-start; }

        .partner-logo { width: 96px; height: 96px; border-radius: 14px; object-fit: cover; display:block; margin: 4px auto 12px; box-shadow: 0 10px 30px rgba(12,35,64,0.08); }
        .partner-logo-placeholder { width:96px; height:96px; border-radius:14px; background:#f1f5f9; color:#6c757d; display:flex; align-items:center; justify-content:center; font-size:28px; box-shadow: 0 8px 20px rgba(12,35,64,0.04); margin:4px auto 12px; }

        .partner-title { font-size: 1.15rem; font-weight: 800; margin: 6px 0; text-align:center; color:#0b1b2b; text-shadow: 0 1px 0 rgba(255,255,255,0.7); }
        .partner-type { font-size: 0.9rem; color: #6c757d; text-align:center; margin-bottom: 8px; text-transform: capitalize; }

        .partner-desc { color:#495057; font-size:14px; margin-top:8px; text-align:left; width:100%; padding: 0 12px 56px 12px; box-sizing: border-box; }

        .partner-actions { display:flex; gap:10px; justify-content:center; position: absolute; left: 12px; right: 12px; bottom: 14px; }
        .partner-actions a { white-space: nowrap; }

        @media (hover: none) {
            .flip-card:hover .flip-inner { transform: none; }
        }

        /* Responsive */
        @media (max-width: 992px) {
            .menu-toggle { display: inline-flex; align-items: center; justify-content: center; }
            .main-content { margin-left: 0; padding: 15px; min-height: auto; }
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
            body.sidebar-open .sidebar { transform: translateX(0); }
            .sidebar-backdrop { display: block; opacity: 0; transition: opacity 0.25s ease; }
            body.sidebar-open .sidebar-backdrop { opacity: 1; pointer-events: auto; }
            body.sidebar-open { overflow: hidden; }

            .partners-list-title { display: none; }
            .partners-list-toolbar { display: none !important; }
            .partners-main-shell { padding-top: 0.35rem !important; padding-bottom: 0 !important; }
            .partners-cards-shell { padding-top: 0 !important; padding-bottom: 0 !important; }
            footer.mt-5 { margin-top: 0.6rem !important; }

            .page-header-row { flex-direction: column; align-items: stretch !important; gap: 10px; }
            .page-header-row .header-left { width: 100%; display: flex; align-items: center; justify-content: space-between; }
            .header-left .header-title { display: none; }
            .header-right-icon { display: inline-flex; }

            .partner-actions-group {
                width: 100%;
                display: flex;
                flex-direction: column;
                gap: 8px;
            }

            .partner-actions-group .btn {
                width: 100%;
                justify-content: center;
            }

            .partners-cards-shell .row {
                --bs-gutter-x: 0.75rem;
                --bs-gutter-y: 0.5rem;
            }

            .flip-card,
            .flip-inner {
                min-height: 182px;
            }

            .flip-front,
            .flip-back {
                padding: 12px;
                border-radius: 10px;
            }

            .partner-logo,
            .partner-logo-placeholder {
                width: 56px;
                height: 56px;
                margin: 0 auto 8px;
                border-radius: 10px;
            }

            .partner-title {
                font-size: 0.95rem;
                margin: 2px 0;
            }

            .partner-type {
                font-size: 0.78rem;
                margin-bottom: 2px;
            }
        }

        /* Filter responsiveness */
        .filters-card .filter-group { min-width: 0; }
        .filter-dropdown .btn { width: 100%; justify-content: space-between; align-items: center; }
        .filter-dropdown .dropdown-menu { width: 100%; max-height: 260px; overflow-y: auto; }
        /* Responsive header adjustments for small screens */
        @media (max-width: 480px) {
            .menu-toggle { padding: 10px; font-size: 20px; border-radius: 8px; }
            .menu-toggle i { font-size: 22px; }
            .add-partner-btn { padding: 8px 12px; font-size: 14px; border-radius: 10px; }
            .requests-btn { padding: 8px 10px; }

            .partners-cards-shell .row {
                --bs-gutter-y: 0.45rem;
            }

            .flip-card,
            .flip-inner {
                min-height: 168px;
            }

            .flip-front,
            .flip-back {
                padding: 10px;
            }

            .partner-logo,
            .partner-logo-placeholder {
                width: 52px;
                height: 52px;
            }

            .partner-title {
                font-size: 0.9rem;
            }

            @media (max-width: 360px) {
                .add-partner-btn, .requests-btn { width: 100%; }
            }
        }

        .requests-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            white-space: nowrap;
        }

        .page-header-row {
            animation: tiltSlideIn 1s cubic-bezier(0.22, 1, 0.36, 1) 0.10s backwards;
        }

        .partners-main-shell {
            animation: tiltSlideIn 1s cubic-bezier(0.22, 1, 0.36, 1) 0.42s backwards;
        }

        .partners-cards-shell {
            animation: tiltSlideIn 1s cubic-bezier(0.22, 1, 0.36, 1) 0.78s backwards;
        }

        @keyframes tiltSlideIn {
            from {
                opacity: 0;
                transform: translateY(18px) rotateX(8deg);
            }
            to {
                opacity: 1;
                transform: translateY(0) rotateX(0);
            }
        }

        .requests-btn .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            min-width: 1.2rem;
            padding: 0.25em 0.45em;
        }
</style>


</head>
<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    <div class="wrapper">

        <div class="main-content">
         <!-- Top Navigation -->
             <!--?php include 'includes/navbar.php'; ?-->
            <div class="container-fluid px-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4 page-header-row" style="background-color: #ffffff; padding: 20px; border-radius: 12px;">
                    <div class="d-flex align-items-center gap-3 header-left">
                        <button class="menu-toggle" type="button" aria-label="Toggle sidebar" aria-expanded="false">
                            <i class="fas fa-bars"></i>
                        </button>
                        <div class="header-title">
                            <h1 class="h3 mb-0 page-title">Partners Management</h1>
                            <p class="text-muted">Manage sponsors, collaborators, and partners</p>
                        </div>
                        <span class="header-right-icon" aria-hidden="true"><i class="fas fa-handshake"></i></span>
                    </div>
                    <div class="partner-actions-group" aria-label="partner-actions">
                        <a href="partner_requests.php" class="btn btn-outline-primary requests-btn">
                            <i class="fas fa-inbox"></i> Requests
                            <?php if ($request_count > 0): ?>
                                <span class="badge bg-danger ms-1"><?php echo $request_count; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="add_partner.php" class="btn btn-primary add-partner-btn"><i class="fas fa-plus"></i> Add Partner</a>
                    </div>
                </div>
                
                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                        <?php echo $flash['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Partners Management Page -->
                <div class="container py-5 partners-main-shell">
    <div class="d-flex justify-content-between align-items-center mb-4 partners-list-toolbar">
        <h2 class="text-primary fw-bold partners-list-title">Partners Management</h2>
        <form class="d-flex" method="get" action="partners.php">
            <input name="search" type="text" value="<?php echo htmlspecialchars($search); ?>" class="form-control me-2" placeholder="Search partners..." style="max-width: 300px;">
            <select name="type" class="form-select me-2" style="max-width: 200px;">
                <option value="">All Types</option>
                <option value="academic" <?php echo $type_filter === 'academic' ? 'selected' : ''; ?>>Academic Partner</option>
                <option value="industry" <?php echo $type_filter === 'industry' ? 'selected' : ''; ?>>Industry Partner</option>
                <option value="sponsor" <?php echo $type_filter === 'sponsor' ? 'selected' : ''; ?>>Sponsor</option>
            </select>
            <button type="submit" class="btn btn-primary">Search</button>
        </form>
    </div>
    <!-- Final Flip Card Layout -->
    <div class="container py-5 partners-cards-shell">
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($partners as $partner): ?>
                <div class="col">
                    <div class="flip-card">
                        <div class="flip-inner">
                            <div class="flip-front text-center">
                                    <?php if (!empty($partner['company_logo'])): ?>
                                        <div class="partner-logo-placeholder mb-3" style="display:none;"><i class="fas fa-building"></i></div>
                                        <img src="<?php echo htmlspecialchars($partner['company_logo']); ?>" alt="<?php echo htmlspecialchars($partner['company_name']); ?>" class="partner-logo mb-3" onload="this.previousElementSibling.style.display='none';" onerror="this.style.display='none'; this.previousElementSibling.style.display='flex';">
                                    <?php else: ?>
                                        <div class="partner-logo-placeholder mb-3 d-flex align-items-center justify-content-center" style="font-size:28px;"><i class="fas fa-building"></i></div>
                                    <?php endif; ?>
                                <div class="partner-title"><?php echo htmlspecialchars($partner['company_name'] ?? $partner['name'] ?? 'Unknown Partner'); ?></div>
                                <div class="partner-type"><?php echo ucfirst(str_replace('_', ' ', $partner['partnership_type'] ?? $partner['type'] ?? 'Unknown')); ?></div>
                            </div>
                            <div class="flip-back">
                                <div class="partner-desc mt-2"><?php echo nl2br(htmlspecialchars($partner['description'] ?? 'No description available.')); ?></div>
                                    <div class="partner-actions">
                                    <a href="edit_partner.php?id=<?php echo $partner['partner_id'] ?? $partner['id'] ?? 0; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i> Edit</a>
                                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="event.preventDefault();" aria-disabled="true" title="Decorative star">
                                        <i class="fas fa-star"></i> Star
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete" data-id="<?php echo $partner['partner_id'] ?? $partner['id'] ?? 0; ?>" data-name="<?php echo htmlspecialchars($partner['company_name'] ?? $partner['name'] ?? 'Partner'); ?>">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
                
                <?php if (empty($partners)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-briefcase fa-4x text-muted mb-3"></i>
                        <p class="text-muted">No partners found.</p>
                    </div>
                <?php else: ?>
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
            <!-- Delete confirmation modal -->
            <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="confirmDeleteLabel">Confirm Delete</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p id="confirmDeleteText">Are you sure you want to delete this partner?</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
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

    <script>
    (() => {
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
    })();

    // Tap-to-flip for touch devices: toggle `.flipped` on the inner element
    document.addEventListener('DOMContentLoaded', function(){
        document.querySelectorAll('.flip-card').forEach(function(card){
            card.addEventListener('click', function(){
                if (window.matchMedia && window.matchMedia('(hover: none)').matches) {
                    var inner = card.querySelector('.flip-inner');
                    if (inner) inner.classList.toggle('flipped');
                }
            });
        });
    });

    // Delete modal handling: replace native confirm with Bootstrap modal
    (function(){
        var deleteModalEl = document.getElementById('confirmDeleteModal');
        if (!deleteModalEl) return;
        var deleteModal = new bootstrap.Modal(deleteModalEl);
        var confirmText = document.getElementById('confirmDeleteText');
        var confirmBtn = document.getElementById('confirmDeleteBtn');

        document.querySelectorAll('.btn-delete').forEach(function(btn){
            btn.addEventListener('click', function(e){
                var id = btn.getAttribute('data-id');
                var name = btn.getAttribute('data-name') || 'this partner';
                confirmText.textContent = 'Are you sure you want to delete "' + name + '"? This action cannot be undone.';
                // set href to delete handler
                confirmBtn.setAttribute('href', 'delete_partner.php?id=' + encodeURIComponent(id));
                deleteModal.show();
            });
        });
    })();
    </script>
</body>
</html>
