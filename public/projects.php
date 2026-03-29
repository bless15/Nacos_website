<?php
/**
 * ============================================
 * NACOS DASHBOARD - PUBLIC projects PORTFOLIO
 * ============================================
 * Purpose: Showcase all member projects
 * Access: Public
 * Created: November 3, 2025
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Bootstrap and includes
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Initialize database
$db = getDB();

// --- Filtering and Searching Logic ---
$search_term = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'all';

// Base query
$query = "
    SELECT p.*
    FROM projects p
";
$conditions = [];
$params = [];

// Add search condition
if (!empty($search_term)) {
    $conditions[] = "(p.title LIKE :search OR p.description LIKE :search)";
    $params[':search'] = '%' . $search_term . '%';
}

// Add status condition
if ($filter_status !== 'all') {
    $conditions[] = "p.project_status = :status";
    $params[':status'] = $filter_status;
}

// Append conditions to query
if (!empty($conditions)) {
    $query .= " WHERE " . implode(' AND ', $conditions);
}

// Add ordering
$query .= " ORDER BY p.start_date DESC";

// Fetch projects
$projects = $db->fetchAll($query, $params);

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>NACOS — Projects</title>
    <link rel="icon" href="../assets/images/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{
            --nacos-green: #008000;
            --nacos-white: #ffffff;
            --nacos-bg-light: #f4f7ff;
            --nacos-green-dark: #006600;
            --nacos-green-fade: rgba(0,128,0,0.08);
            --nacos-green-border: #008000;
            --nacos-shadow: 0 8px 32px rgba(0,128,0,0.08);
        }
        body{
            background-color: var(--nacos-bg-light);
            background: linear-gradient(120deg, rgba(0,128,0,0.04), rgba(0,128,0,0.02));
            font-family: 'Poppins',sans-serif;
            margin:0;
            padding:28px;
        }
        .shell{
            max-width:1200px;
            margin:0 auto;
            background:#fff;
            border-radius:28px;
            overflow:hidden;
            box-shadow:0 20px 60px rgba(13,22,39,0.12);
            display:flex;
            transition:padding-left .26s cubic-bezier(.4,0,.2,1);
            position:relative;
        }
        .dash-sidebar{
            width:220px;
            background:#0f1724;
            color:#fff;
            padding:26px 18px;
            position:relative;
            transition:width .26s cubic-bezier(.4,0,.2,1), padding .26s cubic-bezier(.4,0,.2,1);
            overflow:visible;
            z-index: 1002;
        }
        /* Hamburger styles */
        .hamburger {
            display: none;
            position: absolute;
            top: 24px;
            right: 18px;
            width: 38px;
            height: 38px;
            background: none;
            border: none;
            z-index: 1100;
            cursor: pointer;
        }
        .hamburger span, .hamburger span:before, .hamburger span:after {
            display: block;
            position: absolute;
            width: 28px;
            height: 4px;
            background: #008000;
            border-radius: 2px;
            transition: all 0.3s cubic-bezier(.4,0,.2,1);
        }
        .hamburger span {
            top: 17px;
            left: 5px;
        }
        .hamburger span:before {
            content: '';
            top: -10px;
        }
        .hamburger span:after {
            content: '';
            top: 10px;
        }
        /* Mobile sidebar hidden by default */
        @media (max-width: 900px) {
            .dash-sidebar {
                position: fixed;
                left: -260px;
                top: 0;
                height: 100vh;
                width: 220px;
                box-shadow: 8px 0 32px rgba(0,0,0,0.08);
                transition: left 0.32s cubic-bezier(.4,0,.2,1);
            }
            .dash-sidebar.mobile-open {
                left: 0;
                transition: left 0.32s cubic-bezier(.4,0,.2,1);
            }
            .shell {
                flex-direction: column;
            }
            .hamburger {
                display: block;
                margin-bottom: 0;
            }
            #mainShell {
                margin-top: 60px;
            }
            .main-content-mobile {
                /* margin-top removed to align content to top */
                padding-right: 16px;
                padding-left: 16px;
            }
            @media (max-width: 900px) {
                .main-content-mobile > .content > div[style*="display:flex"] {
                    margin-top: 0 !important;
                }
            }
            .sidebar-backdrop {
                display: none;
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0,0,0,0.18);
                z-index: 1001;
            }
            .sidebar-backdrop.active {
                display: block;
            }
        }
        .dash-sidebar.collapsed {
            width:64px !important;
            padding:18px 10px !important;
        }
        .dash-brand{display:flex;align-items:center;gap:12px;margin-bottom:18px}
        .dash-brand img{height:34px;border-radius:6px}
        .dash-brand strong{display:inline-block;transition:opacity .2s}
        .dash-sidebar.collapsed .dash-brand strong{display:none}
        .dash-menu{margin-top:18px}
        .dash-menu a{display:flex;align-items:center;gap:12px;padding:10px;border-radius:10px;color:rgba(255,255,255,0.9);text-decoration:none;margin-bottom:8px;transition:background .2s,color .2s}
        .dash-menu a.active{background:rgba(0,128,0,0.12);color:var(--nacos-green);font-weight:600}
        .dash-menu a .link-text{display:inline-block;transition:opacity .2s}
        .dash-sidebar.collapsed .dash-menu a .link-text{display:none}
        .sidebar-bottom{position:absolute;bottom:20px;left:18px;right:18px}
        .dash-sidebar.collapsed .sidebar-bottom{left:10px;right:10px}
        .dash-main{
            flex:1;
            display:flex;
            padding:28px;
            gap:18px;
            transition:padding .26s cubic-bezier(.4,0,.2,1);
        }
        .shell.collapsed .dash-main{
            padding-left:12px;
        }
        .content{flex:1}
        .right{width:320px}
        .panel{
            background:var(--nacos-white);
            padding:18px 16px;
            border-radius:16px;
            box-shadow:var(--nacos-shadow);
            margin-top:18px;
            border:2px solid var(--nacos-green-border);
            position:relative;
            transition:box-shadow .18s, border-color .18s;
        }
        .panel h6 {
            color:var(--nacos-green);
            font-weight:700;
            margin-bottom:12px;
        }
        .panel form .form-label {
            color:var(--nacos-green-dark);
            font-weight:500;
        }
        .project-card-full {
            background: var(--nacos-white);
            border-radius:14px;
            overflow: hidden;
            box-shadow: 0 6px 18px rgba(0,128,0,0.07);
            border:2px solid var(--nacos-green-border);
            transition:box-shadow .18s, border-color .18s, transform .18s;
            height: 100%;
            display: flex;
            flex-direction: column;
            position:relative;
        }
        .project-card-full:hover {
            box-shadow:0 12px 32px rgba(0,128,0,0.13);
            border-color:var(--nacos-green-dark);
            transform:translateY(-2px) scale(1.012);
            z-index:2;
        }
        .project-card-full .card-body {
            padding: 25px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        .project-card-full .card-title {
            font-size: 1.3rem;
            font-weight:700;
            color:var(--nacos-green-dark);
            margin-bottom:6px;
        }
        .project-card-full .card-text {
            flex-grow: 1;
            color:#374151;
            font-size:14px;
            margin-bottom:0;
        }
        .project-card-full .card-footer {
            background: #f4f7ff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            padding: 15px 25px;
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        .status-badge.completed { background-color: #d1e7dd; color: #0f5132; }
        .status-badge.in_progress { background-color: #cff4fc; color: #055160; }
        .status-badge.ideation { background-color: #fff3cd; color: #664d03; }
        .btn,
        .btn-primary,
        .btn-outline-primary {
            background: var(--nacos-green) !important;
            border-color: var(--nacos-green) !important;
            color: var(--nacos-white) !important;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(0,128,0,0.08);
            transition: background .18s, border-color .18s, color .18s;
        }
        .btn:hover,
        .btn-primary:hover,
        .btn-outline-primary:hover {
            background: var(--nacos-green-dark) !important;
            border-color: var(--nacos-green-dark) !important;
            color: var(--nacos-white) !important;
        }
        /* Responsive tweaks */
        @media (max-width:1000px){ .right{display:none} .shell{flex-direction:column} }
        @media (max-width:700px){
            .dash-main{flex-direction:column;padding:14px}
            .content{padding:0}
            .panel{padding:12px 6px}
            .project-card-full{padding:12px 6px}
        }
        @media (max-width:1000px){ .right{display:none} .shell{flex-direction:column} }
    </style>
</head>
<body>
<?php
require_once __DIR__ . '/../includes/auth.php';
$member_details = ['full_name'=>'','matric_no'=>''];
$avatarUrl = '../assets/images/default_avatar.png';
if (function_exists('getCurrentMember')) {
    $member = getCurrentMember();
    if (!empty($member['profile_picture'])) {
        $avatarUrl = '../' . $member['profile_picture'];
    }
    if (!empty($member['full_name'])) {
        $member_details['full_name'] = $member['full_name'];
    }
    if (!empty($member['matric_no'])) {
        $member_details['matric_no'] = $member['matric_no'];
    }
}
?>
<button class="hamburger" id="hamburgerBtn" aria-label="Open navigation" type="button">
    <span></span>
</button>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
<div class="shell" id="mainShell">
    <aside class="dash-sidebar" id="sidebarNav">
        <div class="dash-brand"><img src="../assets/images/nacos_logo.jpg" alt="logo"><strong> NACOS, AU Chapter.</strong></div>
        <div class="dash-menu">
            <a href="dashboard.php"><i class="fas fa-home"></i><span class="link-text"> Dashboard</span></a>
            <a href="events.php"><i class="fas fa-calendar"></i><span class="link-text"> Events</span></a>
            <a href="projects.php" class="active"><i class="fas fa-briefcase"></i><span class="link-text"> Projects</span></a>
            <a href="resources.php"><i class="fas fa-book"></i><span class="link-text"> Resources</span></a>
            <a href="profile.php"><i class="fas fa-user"></i><span class="link-text"> Profile</span></a>
        </div>
        <div class="sidebar-bottom">
            <div style="display:flex;align-items:center;gap:12px">
                <img class="member-avatar" src="<?php echo htmlspecialchars($avatarUrl); ?>" style="width:44px;height:44px;border-radius:8px;object-fit:cover">
                <div>
                    <div style="font-weight:700"><?php echo htmlspecialchars($member_details['full_name']); ?></div>
                    <div style="font-size:12px;color:#94a3b8"><?php echo htmlspecialchars($member_details['matric_no']); ?></div>
                </div>
            </div>
        </div>
    </aside>
    <main class="dash-main main-content-mobile">
        <div class="content">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px">
                <div>
                    <h2 style="margin:0">Projects</h2>
                    <p style="margin:6px 0 0 0;color:#6b7280">A showcase of innovation and collaboration by NACOS members.</p>
                </div>
                <div>
                    <form action="projects.php" method="GET" class="d-flex" style="gap:8px">
                        <input type="text" name="search" class="form-control" placeholder="Search by title or keyword..." value="<?php echo htmlspecialchars($search_term); ?>">
                        <button class="btn btn-primary">Search</button>
                    </form>
                </div>
            </div>
            <div class="panel">
                <form action="projects.php" method="GET" class="mb-3">
                    <div class="row g-3 align-items-center">
                        <div class="col-lg-6">
                            <input type="text" name="search" class="form-control" placeholder="Search by title or keyword..." value="<?php echo htmlspecialchars($search_term); ?>">
                        </div>
                        <div class="col-lg-3">
                            <select name="status" class="form-select">
                                <option value="all" <?php echo ($filter_status === 'all') ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="completed" <?php echo ($filter_status === 'completed') ? 'selected' : ''; ?>>Completed</option>
                                <option value="in_progress" <?php echo ($filter_status === 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                                <option value="ideation" <?php echo ($filter_status === 'ideation') ? 'selected' : ''; ?>>Planned</option>
                            </select>
                        </div>
                        <div class="col-lg-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filter Projects
                            </button>
                        </div>
                    </div>
                </form>
                <?php if (empty($projects)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-lightbulb-slash" style="font-size:48px;color:#ccc"></i>
                        <h4 class="mt-3">No Projects Found</h4>
                        <p class="text-muted">Your search or filter criteria did not match any projects. Try a different search.</p>
                        <a href="projects.php" class="btn btn-primary mt-3">Clear Filters</a>
                    </div>
                <?php else: ?>
                    <div class="row" id="projects-list">
                        <?php $projectCount = 0; foreach ($projects as $project): $projectCount++; ?>
                            <div class="col-lg-4 col-md-6 mb-4 project-box<?php if ($projectCount > 6) echo ' d-none'; ?>">
                                <div class="card project-card-full">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($project['title']); ?></h5>
                                        <p class="card-text text-muted">
                                            <?php echo substr(htmlspecialchars($project['description']), 0, 150) . '...'; ?>
                                        </p>
                                        <div class="mt-auto">
                                            <a href="project_details.php?id=<?php echo $project['project_id']; ?>" class="btn btn-outline-primary w-100">View Details</a>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <span class="status-badge <?php echo htmlspecialchars($project['project_status']); ?>">
                                            <?php echo ucfirst(str_replace(['_', '-'], ' ', $project['project_status'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($projects) > 6): ?>
                        <div class="text-center mt-3">
                            <button id="viewMoreBtn" class="btn btn-primary">View More</button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
<script>
// Desktop sidebar collapse logic (unchanged)
(function(){
    const sidebar = document.querySelector('.dash-sidebar');
    const shell = document.querySelector('.shell');
    if (!sidebar || !shell) return;
    const INACTIVITY_TIMEOUT = 10000;
    const ACTIVITY_THROTTLE_MS = 200;
    let inactivityTimer = null;
    let lastActivity = 0;
    function expandSidebar(){
        sidebar.classList.remove('collapsed');
        shell.classList.remove('collapsed');
    }
    function collapseSidebar(){
        sidebar.classList.add('collapsed');
        shell.classList.add('collapsed');
    }
    function clearInactivity(){
        if (inactivityTimer) { clearTimeout(inactivityTimer); inactivityTimer = null; }
    }
    function scheduleInactivityCollapse(){
        clearInactivity();
        inactivityTimer = setTimeout(() => { collapseSidebar(); }, INACTIVITY_TIMEOUT);
    }
    function handleUserActivity(){
        const now = Date.now();
        if (now - lastActivity < ACTIVITY_THROTTLE_MS) return;
        lastActivity = now;
        expandSidebar();
        scheduleInactivityCollapse();
    }
    collapseSidebar();
    scheduleInactivityCollapse();
    const activityEvents = ['mousemove','mousedown','keydown','touchstart','scroll'];
    activityEvents.forEach(evt => {
        window.addEventListener(evt, handleUserActivity, { passive: true });
    });
    sidebar.addEventListener('mouseenter', function(){ expandSidebar(); clearInactivity(); });
    sidebar.addEventListener('mouseleave', function(){ scheduleInactivityCollapse(); });
    sidebar.addEventListener('focusin', function(){ expandSidebar(); clearInactivity(); });
    sidebar.addEventListener('focusout', function(){ scheduleInactivityCollapse(); });
    window.addEventListener('unload', function(){ clearInactivity(); activityEvents.forEach(evt => window.removeEventListener(evt, handleUserActivity)); });
})();
// Mobile hamburger menu logic
document.addEventListener('DOMContentLoaded', function() {
    const hamburger = document.getElementById('hamburgerBtn');
    const sidebar = document.getElementById('sidebarNav');
    const backdrop = document.getElementById('sidebarBackdrop');
    function openSidebar() {
        sidebar.classList.add('mobile-open');
        backdrop.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        sidebar.classList.remove('mobile-open');
        backdrop.classList.remove('active');
        document.body.style.overflow = '';
    }
    hamburger.addEventListener('click', function(e) {
        e.stopPropagation();
        openSidebar();
    });
    backdrop.addEventListener('click', function() {
        closeSidebar();
    });
    // Optional: close on ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeSidebar();
    });
    // Prevent sidebar click from closing
    sidebar.addEventListener('click', function(e) {
        e.stopPropagation();
    });
    // Hide sidebar on resize to desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth > 900) closeSidebar();
    });
});
</script>
<script>
// ...existing code...
document.addEventListener('DOMContentLoaded', function() {
    var viewMoreBtn = document.getElementById('viewMoreBtn');
    if (viewMoreBtn) {
        viewMoreBtn.addEventListener('click', function() {
            var hiddenBoxes = document.querySelectorAll('.project-box.d-none');
            hiddenBoxes.forEach(function(box) { box.classList.remove('d-none'); });
            viewMoreBtn.style.display = 'none';
        });
    }
});
</script>
</body>
</html>
