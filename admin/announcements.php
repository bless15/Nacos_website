<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/announcements_helper.php';

requireAdminRole();

$db = getDB();
$flash = getFlashMessage();

$search = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? 'all');

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(title LIKE ? OR body LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if (in_array($status, ['draft', 'published'], true)) {
    $where[] = 'status = ?';
    $params[] = $status;
}

$query = "SELECT a.*, 
    (SELECT COUNT(*) FROM announcement_comments c WHERE c.announcement_id = a.announcement_id AND c.is_hidden = 0) AS comments_count,
    (SELECT COUNT(*) FROM announcement_reactions r WHERE r.announcement_id = a.announcement_id) AS reactions_count
    FROM announcements a";

if ($where) {
    $query .= ' WHERE ' . implode(' AND ', $where);
}

$query .= ' ORDER BY a.is_pinned DESC, COALESCE(a.publish_at, a.created_at) DESC';

$announcements = $db->fetchAll($query, $params);

$stats = $db->fetchOne("SELECT 
    COUNT(*) AS total,
    SUM(CASE WHEN status='published' THEN 1 ELSE 0 END) AS published,
    SUM(CASE WHEN status='draft' THEN 1 ELSE 0 END) AS drafts,
    SUM(CASE WHEN status='published' AND publish_at IS NOT NULL AND publish_at > NOW() THEN 1 ELSE 0 END) AS scheduled
FROM announcements");

$csrf = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - NACOS Admin</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --sidebar-bg: #2c3e50;
            --sidebar-hover: #34495e;
        }

        .sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            box-shadow: none;
            overflow-y: auto;
            scrollbar-color: #a6a6a6 #f1f1f1;
        }

        .sidebar::-webkit-scrollbar {
            width: 8px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: #a6a6a6;
            border-radius: 4px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: #8a8a8a;
        }

        .main-content {
            margin-left: 260px;
            padding: 20px;
            width: auto;
        }

        .top-bar { 
            background: #fff; 
            border-radius: 12px; 
            padding: 18px; 
            margin-bottom: 18px; 
            box-shadow: 0 2px 10px rgba(0,0,0,.05); 
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            animation: popCascadeIn 1s cubic-bezier(0.22, 1, 0.36, 1) 0.10s backwards;
        }
        .stat-card { 
            background: #fff; 
            border-radius: 12px; 
            padding: 16px; 
            border-left: 5px solid #0F6B3E; 
            box-shadow: 0 2px 12px rgba(0,0,0,.08);
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: #FFC107;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #0F6B3E;
            flex-shrink: 0;
        }
        .stat-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: center;
            min-width: 0;
        }
        .stat-number { 
            font-size: 20px; 
            font-weight: 700; 
            color: #0F6B3E; 
            margin: 0; 
        }
        .stat-label { 
            font-size: 12px; 
            color: #687077; 
            margin: 4px 0 0 0;
            font-weight: 500;
            line-height: 1.25;
            white-space: normal;
        }
        .stat-card:nth-child(1) { border-left-color: #0F6B3E; }
        .stat-card:nth-child(2) { border-left-color: #0F6B3E; }
        .stat-card:nth-child(3) { border-left-color: #E91E63; }
        .stat-card:nth-child(4) { border-left-color: #FFC107; }
        .table-wrap { background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,.05); overflow: hidden; }
        .table-wrap .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .table-wrap .table-responsive::-webkit-scrollbar {
            height: 8px;
        }
        .table-wrap .table-responsive::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .table-wrap .table-responsive::-webkit-scrollbar-thumb {
            background: #9a9a9a;
            border-radius: 6px;
        }
        .row.g-3.mb-4 { animation: popCascadeIn 1s cubic-bezier(0.22, 1, 0.36, 1) 0.34s backwards; }
        form.row.g-2.mb-3 { animation: popCascadeIn 1s cubic-bezier(0.22, 1, 0.36, 1) 0.56s backwards; }
        .table-wrap { animation: popCascadeIn 1s cubic-bezier(0.22, 1, 0.36, 1) 0.78s backwards; }

        @keyframes popCascadeIn {
            from {
                opacity: 0;
                transform: translateY(16px) scale(0.96);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        .menu-toggle { 
            display: none; 
            align-items: center; 
            justify-content: center; 
            width: 42px; 
            height: 42px; 
            border: 2px solid #dee2e6; 
            border-radius: 10px; 
            background: #fff; 
            flex-shrink: 0;
            cursor: pointer;
        }
        .announcement-title { font-weight: 600; color: #0F6B3E; }
        .page-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            gap: 12px;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }
        .header-title h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            color: #0F6B3E;
            line-height: 1.2;
        }
        .header-title p {
            margin: 2px 0 0;
            font-size: 13px;
            color: #687077;
        }
        .top-bar .btn {
            white-space: nowrap;
        }
        .right-icon {
            display: none;
            width: 42px;
            height: 42px;
            border-radius: 10px;
            font-size: 20px;
            color: #0F6B3E;
            align-items: center;
            justify-content: center;
            background: rgba(255, 193, 7, 0.22);
            flex-shrink: 0;
        }

        /* Tablet and up */
        @media (max-width: 992px) { 
            .menu-toggle { display: inline-flex; } 
            .main-content { margin-left: 0; width: 100%; padding: 15px; }
            .top-bar { padding: 14px 12px; gap: 10px; }
            .header-title h1 { font-size: 24px; }
            .header-title p { font-size: 12px; }
        }
        
        /* Mobile */
        @media (max-width: 576px) {
            .top-bar { padding: 12px; }
            .page-header-row { flex-wrap: wrap; }
            .header-left { width: 100%; justify-content: space-between; }
            .menu-toggle { order: 1; }
            .header-title { display: none; }
            .right-icon { display: flex; order: 2; margin-left: auto; }
            .top-bar .btn { width: 100%; padding: 12px 16px; font-size: 13px; border-radius: 10px; font-weight: 500; }
            .top-bar .btn i { margin-right: 6px; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="top-bar">
            <div class="page-header-row">
                <div class="header-left">
                    <button class="menu-toggle" id="menuToggle" type="button"><i class="fas fa-bars"></i></button>
                    <div class="header-title">
                        <h1><i class="fas fa-bullhorn me-2"></i>Announcements</h1>
                        <p>Manage and publish member announcements</p>
                    </div>
                    <span class="right-icon" aria-hidden="true"><i class="fas fa-bullhorn"></i></span>
                </div>
                <a href="add_announcement.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>New Announcement</a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-list"></i></div>
                    <div class="stat-content">
                        <h5 class="stat-number"><?php echo (int)($stats['total'] ?? 0); ?></h5>
                        <p class="stat-label">Total Announcements</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-content">
                        <h5 class="stat-number"><?php echo (int)($stats['published'] ?? 0); ?></h5>
                        <p class="stat-label">Published</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                    <div class="stat-content">
                        <h5 class="stat-number"><?php echo (int)($stats['drafts'] ?? 0); ?></h5>
                        <p class="stat-label">Drafts</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-content">
                        <h5 class="stat-number"><?php echo (int)($stats['scheduled'] ?? 0); ?></h5>
                        <p class="stat-label">Scheduled</p>
                    </div>
                </div>
            </div>
        </div>

        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-7"><input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search announcements..."></div>
            <div class="col-md-3">
                <select class="form-select" name="status">
                    <option value="all" <?php echo $status==='all' ? 'selected' : ''; ?>>All statuses</option>
                    <option value="published" <?php echo $status==='published' ? 'selected' : ''; ?>>Published</option>
                    <option value="draft" <?php echo $status==='draft' ? 'selected' : ''; ?>>Draft</option>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-outline-primary w-100" type="submit">Filter</button></div>
        </form>

        <div class="table-wrap">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Announcement</th><th>Status</th><th>Audience</th><th>Engagement</th><th>Publish</th><th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$announcements): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No announcements found.</td></tr>
                    <?php else: foreach ($announcements as $a): ?>
                        <?php $scheduled = !empty($a['publish_at']) && strtotime($a['publish_at']) > time(); ?>
                        <tr>
                            <td>
                                <div class="announcement-title"><?php echo htmlspecialchars($a['title']); ?><?php if ((int)$a['is_pinned']===1): ?> <span class="badge bg-warning text-dark ms-1">Pinned</span><?php endif; ?></div>
                                <small class="text-muted">Created <?php echo date('M d, Y H:i', strtotime($a['created_at'])); ?></small>
                            </td>
                            <td>
                                <?php if ($a['status']==='published'): ?>
                                    <span class="badge <?php echo $scheduled ? 'bg-info' : 'bg-success'; ?>"><?php echo $scheduled ? 'Scheduled' : 'Published'; ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td><small><?php echo strtoupper($a['audience_level']); ?> / <?php echo ucfirst($a['audience_status']); ?></small></td>
                            <td><small><?php echo (int)$a['comments_count']; ?> comments • <?php echo (int)$a['reactions_count']; ?> reactions</small></td>
                            <td><small><?php echo $a['publish_at'] ? date('M d, Y H:i', strtotime($a['publish_at'])) : 'Immediate'; ?></small></td>
                            <td class="text-end">
                                <a href="edit_announcement.php?id=<?php echo (int)$a['announcement_id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="POST" action="delete_announcement.php?id=<?php echo (int)$a['announcement_id']; ?>" class="d-inline confirm-action-form" data-title="Delete Announcement" data-message="Are you sure you want to delete this announcement?" data-ok-label="Delete">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<footer class="bg-light text-center py-3 mt-5">
    <div class="container">
        <small class="text-muted">&copy; <?php echo date('Y'); ?> NACOSAU. All Rights Reserved.</small><br>
        <small class="text-muted">Developed by <a href="https://johnicity.com.ng/portfolio" target="_blank" class="text-decoration-none">Johnicity</a></small>
    </div>
</footer>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.querySelector('.sidebar');
const backdrop = document.getElementById('sidebarBackdrop');
if (menuToggle && sidebar && backdrop) {
    menuToggle.addEventListener('click', () => { sidebar.classList.toggle('show'); backdrop.classList.toggle('show'); });
    backdrop.addEventListener('click', () => { sidebar.classList.remove('show'); backdrop.classList.remove('show'); });
}
</script>
</body>
</html>
