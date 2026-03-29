<?php
/**
 * ============================================
 * NACOS DASHBOARD - PUBLIC resources PAGE
 * ============================================
 * Purpose: Showcase downloadable and useful resources
 * Access: Public
 * Created: February 17, 2026
 * ============================================
 */

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$db = getDB();

// Fetch all resources
$resources = $db->fetchAll("SELECT * FROM resources ORDER BY created_at DESC");

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>NACOS — Resources</title>
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
        }
        .dash-sidebar{
            width:220px;
            background:#0f1724;
            color:#fff;
            padding:26px 18px;
            position:relative;
            transition:width .26s cubic-bezier(.4,0,.2,1), padding .26s cubic-bezier(.4,0,.2,1);
            overflow:visible;
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
        .panel h2 {
            color:var(--nacos-green);
            font-weight:700;
            margin-bottom:12px;
        }
        .resource-list{
            margin-top:18px;
        }
        .resource-card{
            background:var(--nacos-white);
            border-radius:14px;
            box-shadow:0 6px 18px rgba(0,128,0,0.07);
            border:2px solid var(--nacos-green-border);
            padding:18px 16px;
            margin-bottom:18px;
            display:flex;
            align-items:center;
            gap:18px;
            transition:box-shadow .18s, border-color .18s, transform .18s;
        }
        .resource-card:hover{
            box-shadow:0 12px 32px rgba(0,128,0,0.13);
            border-color:var(--nacos-green-dark);
            transform:translateY(-2px) scale(1.012);
            z-index:2;
        }
        .resource-icon{
            font-size:2.2rem;
            color:var(--nacos-green);
            flex-shrink:0;
        }
        .resource-info{
            flex:1;
        }
        .resource-title{
            font-size:1.1rem;
            font-weight:700;
            color:var(--nacos-green-dark);
            margin-bottom:4px;
        }
        .resource-meta{
            font-size:0.95rem;
            color:#64748b;
        }
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
        @media (max-width:1000px){ .shell{flex-direction:column} }
        @media (max-width:700px){
            .dash-main{flex-direction:column;padding:14px}
            .content{padding:0}
            .panel{padding:12px 6px}
            .resource-card{padding:12px 6px}
        }
    </style>
</head>
<body>
<?php
// Use dashboard-style avatar logic for sidebar
$avatarUrl = '../assets/images/default_avatar.png';
$sidebarName = 'Guest';
$sidebarSub = 'NACOS';
if (function_exists('getCurrentMember')) {
    $member = getCurrentMember();
    if (!empty($member['profile_picture'])) {
        $avatarUrl = '../' . $member['profile_picture'];
    }
    if (!empty($member['full_name'])) {
        $sidebarName = $member['full_name'];
    }
    if (!empty($member['matric_no'])) {
        $sidebarSub = $member['matric_no'];
    }
}
?>
<div class="shell collapsed">
    <aside class="dash-sidebar">
        <div class="dash-brand"><img src="../assets/images/nacos_logo.jpg" alt="logo"><strong> NACOS, AU Chapter.</strong></div>
        <div class="dash-menu">
            <a href="dashboard.php"><i class="fas fa-home"></i><span class="link-text"> Dashboard</span></a>
            <a href="events.php"><i class="fas fa-calendar"></i><span class="link-text"> Events</span></a>
            <a href="projects.php"><i class="fas fa-briefcase"></i><span class="link-text"> Projects</span></a>
            <a href="resources.php" class="active"><i class="fas fa-book"></i><span class="link-text"> Resources</span></a>
            <a href="profile.php"><i class="fas fa-user"></i><span class="link-text"> Profile</span></a>
        </div>
        <div class="sidebar-bottom">
            <div style="display:flex;align-items:center;gap:12px">
                <img class="member-avatar" src="<?php echo htmlspecialchars($avatarUrl); ?>" style="width:44px;height:44px;border-radius:8px;object-fit:cover">
                <div>
                    <div style="font-weight:700"><?php echo htmlspecialchars($sidebarName); ?></div>
                    <div style="font-size:12px;color:#94a3b8"><?php echo htmlspecialchars($sidebarSub); ?></div>
                </div>
            </div>
        </div>
    </aside>
    <main class="dash-main">
        <div class="content">
            <div class="panel">
                <h2>Resources</h2>
                <p class="mb-3" style="color:#6b7280">Download useful materials, guides, and tools provided by NACOS. <a href="../admin/resources.php" style="color:var(--nacos-green);font-weight:600;text-decoration:underline" target="_blank"><i class="fas fa-link"></i> Go to Admin Resources</a></p>
                <div class="resource-list">
                    <?php if (empty($resources)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-folder-open resource-icon" style="font-size:3rem;color:#ccc"></i>
                            <h4 class="mt-3">No Resources Available</h4>
                            <p class="text-muted">Check back later for new resources.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($resources as $resource): ?>
                            <div class="resource-card">
                                <div class="resource-icon">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div class="resource-info">
                                    <div class="resource-title"><?php echo htmlspecialchars($resource['title']); ?></div>
                                    <div class="resource-meta">
                                        Uploaded: 
                                        <?php if (!empty($resource['uploaded_at'])): ?>
                                            <?php echo date('M d, Y', strtotime($resource['uploaded_at'])); ?>
                                        <?php elseif (!empty($resource['created_at'])): ?>
                                            <?php echo date('M d, Y', strtotime($resource['created_at'])); ?>
                                        <?php else: ?>
                                            Unknown
                                        <?php endif; ?>
                                        <?php if (!empty($resource['category'])): ?>
                                            | Category: <?php echo htmlspecialchars($resource['category']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div>
                                    <a href="../uploads/<?php echo rawurlencode($resource['file_path']); ?>" class="btn btn-primary" download><i class="fas fa-download"></i> Download</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
<script>
(function(){
    const sidebar = document.querySelector('.dash-sidebar');
    const shell = document.querySelector('.shell');
    if (!sidebar || !shell) return;
    // Collapse by default
    shell.classList.add('collapsed');
    const INACTIVITY_TIMEOUT = 10000;
    let inactivityTimer = null;
    function scheduleAutoCollapse(){
        clearTimeout(inactivityTimer);
        inactivityTimer = setTimeout(() => {
            shell.classList.add('collapsed');
        }, INACTIVITY_TIMEOUT);
    }
    function expandSidebar(){
        shell.classList.remove('collapsed');
        scheduleAutoCollapse();
    }
    function collapseSidebar(){
        shell.classList.add('collapsed');
    }
    sidebar.addEventListener('mouseenter', function(){
        expandSidebar();
        clearTimeout(inactivityTimer);
    });
    sidebar.addEventListener('mouseleave', function(){
        scheduleAutoCollapse();
    });
    sidebar.addEventListener('focusin', function(){
        expandSidebar();
        clearTimeout(inactivityTimer);
    });
    sidebar.addEventListener('focusout', function(){
        scheduleAutoCollapse();
    });
    // User activity expands sidebar
    const activityEvents = ['mousemove','mousedown','keydown','touchstart','scroll'];
    function handleUserActivity(){
        expandSidebar();
    }
    activityEvents.forEach(evt => {
        window.addEventListener(evt, handleUserActivity, { passive: true });
    });
    // Start the timer on page load
    scheduleAutoCollapse();
    window.addEventListener('unload', function(){
        clearTimeout(inactivityTimer);
        activityEvents.forEach(evt => window.removeEventListener(evt, handleUserActivity));
    });
})();
</script>
</body>
</html>
