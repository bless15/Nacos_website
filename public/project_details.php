<?php
/**
 * ============================================
 * NACOS DASHBOARD - PROJECT DETAILS PAGE
 * ============================================
 * Purpose: Display detailed information about a specific project
 * Access: Public
 * Created: November 3, 2025
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Include required files
require_once '../includes/auth.php';
require_once __DIR__ . '/../config/config.php';

// Initialize database
$db = getDB();

// Get project ID from URL
$project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($project_id <= 0) {
    header('Location: projects.php');
    exit();
}

// Fetch project details
$project = $db->fetchOne(
    "SELECT * FROM projects WHERE project_id = ?",
    [$project_id]
);

// If project not found, redirect
if (!$project) {
    header('Location: projects.php');
    exit();
}

// Get related projects (same status or random)
$related_projects = $db->fetchAll(
    "SELECT * FROM projects WHERE project_id != ? ORDER BY RAND() LIMIT 3",
    [$project_id]
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($project['title']); ?> - NACOS Projects</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/public.css">
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
            background-color:var(--nacos-bg-light);
            font-family:'Poppins',sans-serif;
            margin:0;
            padding:28px;
        }
        .shell{
            max-width:1200px;
            margin:0 auto;
            display:flex;
            min-height:100vh;
        }
        .dash-sidebar{
            width:240px;
            background:#fff;
            border-radius:18px;
            box-shadow:0 2px 16px rgba(0,0,0,0.06);
            margin-right:24px;
            padding:24px 0 12px 0;
            display:flex;
            flex-direction:column;
            justify-content:space-between;
            position:relative;
            z-index:10;
        }
        .dash-main{
            flex:1;
            display:flex;
            flex-direction:column;
            gap:24px;
        }
        .main-content-mobile{
            width:100%;
        }
        @media (max-width:1000px){ .dash-sidebar{display:none} .shell{flex-direction:column} }
        @media (max-width:700px){
            .dash-main{flex-direction:column;padding:14px}
            .main-content-mobile{padding:0}
        }
        /* Additional custom styles for project details */
        .project-hero {
            background: var(--nacos-green-dark);
            color: #fff;
            padding: 40px 0 24px 0;
            margin-bottom: 24px;
            border-radius: 18px;
        }
        .project-hero h1 {
            color: #fff;
            font-size: 2.2rem;
            margin-bottom: 12px;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 600;
            text-transform: capitalize;
            margin-top: 10px;
        }
        .status-completed { background: #28a745; color: #fff; }
        .status-ongoing { background: #ffc107; color: #000; }
        .status-planned { background: #6c757d; color: #fff; }
        .project-detail-section { padding: 18px 0; }
        .detail-card {
            background: #fff;
            border-radius: 10px;
            padding: 24px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.1);
            margin-bottom: 18px;
        }
        .detail-card h3 {
            color: var(--nacos-green-dark);
            font-size: 1.2rem;
            margin-bottom: 14px;
            border-bottom: 2px solid #f4f7ff;
            padding-bottom: 8px;
        }
        .tech-stack { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
        .tech-badge { background: #f4f7ff; color: var(--nacos-green-dark); padding: 8px 15px; border-radius: 20px; font-weight: 500; font-size: 0.9rem; }
        .project-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin: 18px 0; }
        .stat-box { text-align: center; padding: 16px; background: #f4f7ff; border-radius: 10px; }
        .stat-box i { font-size: 1.5rem; color: var(--nacos-green-dark); margin-bottom: 8px; }
        .stat-box h4 { font-size: 1.2rem; color: #333; margin-bottom: 4px; }
        .stat-box p { color: #666; margin: 0; font-size: 0.9rem; }
        .related-project-card { background: #fff; border-radius: 10px; padding: 16px; box-shadow: 0 3px 10px rgba(0,0,0,0.1); transition: all 0.3s ease; height: 100%; }
        .related-project-card:hover { transform: translateY(-5px); box-shadow: 0 5px 20px rgba(0,0,0,0.15); }
        .related-project-card h5 { color: var(--nacos-green-dark); font-size: 1.1rem; }
        .cta-section { background: var(--nacos-green-dark); color: #fff; padding: 40px 0; margin-top: 32px; text-align: center; border-radius: 18px; }
        .cta-section h2 { color: #fff; margin-bottom: 14px; }
        .github-link { display: inline-flex; align-items: center; gap: 10px; background: #fff; color: var(--nacos-green-dark); padding: 12px 30px; border-radius: 25px; text-decoration: none; font-weight: 600; transition: all 0.3s ease; }
        .github-link:hover { background: #f4f7ff; transform: scale(1.05); }
    </style>
</head>

<body>
    <button class="hamburger" id="hamburgerBtn" aria-label="Open navigation" type="button"><span></span></button>
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
                    <?php
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
                    <img class="member-avatar" src="<?php echo htmlspecialchars($avatarUrl); ?>" style="width:44px;height:44px;border-radius:8px;object-fit:cover">
                    <div>
                        <div style="font-weight:700"><?php echo htmlspecialchars($member_details['full_name']); ?></div>
                        <div style="font-size:12px;color:#94a3b8"><?php echo htmlspecialchars($member_details['matric_no']); ?></div>
                    </div>
                </div>
            </div>
        </aside>
        <main class="dash-main">
            <div class="content">
                <!-- Project Hero Section -->
                <section class="project-hero">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php" style="color: rgba(255,255,255,0.8);">Home</a></li>
                            <li class="breadcrumb-item"><a href="projects.php" style="color: rgba(255,255,255,0.8);">Projects</a></li>
                            <li class="breadcrumb-item active" aria-current="page" style="color: #fff;\"><?php echo htmlspecialchars($project['title']); ?></li>
                        </ol>
                    </nav>
                    <h1><?php echo htmlspecialchars($project['title']); ?></h1>
                    <p class="lead"><?php echo htmlspecialchars($project['description']); ?></p>
                    <span class="status-badge status-<?php echo $project['project_status']; ?>">
                        <i class="fas fa-circle me-2"></i><?php echo ucfirst($project['project_status']); ?>
                    </span>
                </section>
                <!-- Project Details Section -->
                <section class="project-detail-section">
                    <div class="row">
                        <div class="col-lg-8">
                            <!-- Overview -->
                            <div class="detail-card">
                                <h3><i class="fas fa-info-circle me-2"></i>Project Overview</h3>
                                <p><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
                                <?php if (!empty($project['github_link'])): ?>
                                    <div class="mt-4">
                                        <a href="<?php echo htmlspecialchars($project['github_link']); ?>" target="_blank" class="github-link">
                                            <i class="fab fa-github"></i> View on GitHub
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <!-- Technologies Used -->
                            <div class="detail-card">
                                <h3><i class="fas fa-code me-2"></i>Technologies & Tools</h3>
                                <p>This project leverages cutting-edge technologies to deliver exceptional results:</p>
                                <div class="tech-stack">
                                    <?php
                                    $techs = [];
                                    $features = [];
                                    if (!empty($project['tech_stack'])) {
                                        $decoded = json_decode($project['tech_stack'], true);
                                        if (is_array($decoded) && isset($decoded['technologies'])) {
                                            $techs = $decoded['technologies'];
                                            if (isset($decoded['features']) && is_array($decoded['features'])) {
                                                $features = $decoded['features'];
                                            }
                                        } else {
                                            // Fallback: comma separated string
                                            $techs = array_values(array_filter(array_map('trim', preg_split('/,/', $project['tech_stack']))));
                                        }
                                    }
                                    if (empty($techs)) {
                                        // Legacy fallback
                                        $techs = ['PHP', 'MySQL', 'JavaScript', 'Bootstrap', 'HTML5', 'CSS3', 'Git', 'VS Code'];
                                    }
                                    foreach ($techs as $tech):
                                    ?>
                                        <span class="tech-badge"><i class="fas fa-check-circle me-1"></i><?php echo htmlspecialchars($tech); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <!-- Key Features -->
                            <div class="detail-card">
                                <h3><i class="fas fa-star me-2"></i>Key Features</h3>
                                <?php if (!empty($features)): ?>
                                    <ul class="list-unstyled">
                                        <?php foreach ($features as $f): ?>
                                            <li class="mb-3"><i class="fas fa-check text-success me-2"></i><?php echo htmlspecialchars($f); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <ul class="list-unstyled">
                                        <li class="mb-3"><i class="fas fa-check text-success me-2"></i>Responsive and mobile-friendly design</li>
                                        <li class="mb-3"><i class="fas fa-check text-success me-2"></i>Secure authentication and authorization</li>
                                        <li class="mb-3"><i class="fas fa-check text-success me-2"></i>Real-time data processing and updates</li>
                                        <li class="mb-3"><i class="fas fa-check text-success me-2"></i>Intuitive user interface and experience</li>
                                        <li class="mb-3"><i class="fas fa-check text-success me-2"></i>Scalable architecture for future growth</li>
                                        <li class="mb-3"><i class="fas fa-check text-success me-2"></i>Comprehensive documentation and testing</li>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <!-- Project Stats -->
                            <div class="detail-card">
                                <h3><i class="fas fa-chart-bar me-2"></i>Project Stats</h3>
                                <div class="project-stats">
                                    <div class="stat-box">
                                        <i class="fas fa-calendar-alt"></i>
                                        <h4><?php echo date('M Y', strtotime($project['created_at'])); ?></h4>
                                        <p>Started</p>
                                    </div>
                                    <div class="stat-box">
                                        <i class="fas fa-users"></i>
                                        <h4>5+</h4>
                                        <p>Contributors</p>
                                    </div>
                                    <div class="stat-box">
                                        <i class="fas fa-code-branch"></i>
                                        <h4>12+</h4>
                                        <p>Features</p>
                                    </div>
                                    <div class="stat-box">
                                        <i class="fas fa-star"></i>
                                        <h4>4.8/5</h4>
                                        <p>Rating</p>
                                    </div>
                                </div>
                            </div>
                            <!-- Project Info -->
                            <div class="detail-card">
                                <h3><i class="fas fa-clipboard-list me-2"></i>Project Info</h3>
                                <div class="mb-3">
                                    <strong><i class="fas fa-flag me-2 text-primary"></i>Status:</strong><br>
                                    <span class="badge bg-<?php echo $project['project_status'] === 'completed' ? 'success' : ($project['project_status'] === 'ongoing' ? 'warning' : 'secondary'); ?> mt-2">
                                        <?php echo ucfirst($project['project_status']); ?>
                                    </span>
                                </div>
                                <div class="mb-3">
                                    <strong><i class="fas fa-calendar me-2 text-primary"></i>Created:</strong><br>
                                    <?php echo date('F j, Y', strtotime($project['created_at'])); ?>
                                </div>
                                <div class="mb-3">
                                    <strong><i class="fas fa-layer-group me-2 text-primary"></i>Category:</strong><br>
                                    Web Development
                                </div>
                            </div>
                            <!-- Share Project -->
                            <div class="detail-card">
                                <h3><i class="fas fa-share-alt me-2"></i>Share Project</h3>
                                <div class="d-flex gap-2">
                                    <a href="#" class="btn btn-outline-primary flex-fill"><i class="fab fa-twitter"></i></a>
                                    <a href="#" class="btn btn-outline-primary flex-fill"><i class="fab fa-facebook"></i></a>
                                    <a href="#" class="btn btn-outline-primary flex-fill"><i class="fab fa-linkedin"></i></a>
                                    <a href="#" class="btn btn-outline-primary flex-fill"><i class="fas fa-link"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Related Projects -->
                    <?php if (!empty($related_projects)): ?>
                    <div class="row mt-5">
                        <div class="col-12">
                            <h2 class="mb-4">Related Projects</h2>
                        </div>
                        <?php foreach ($related_projects as $related): ?>
                        <div class="col-lg-4 mb-4">
                            <div class="related-project-card">
                                <div class="mb-3">
                                    <span class="badge bg-<?php echo $related['project_status'] === 'completed' ? 'success' : ($related['project_status'] === 'ongoing' ? 'warning' : 'secondary'); ?>">
                                        <?php echo ucfirst($related['project_status']); ?>
                                    </span>
                                </div>
                                <h5><?php echo htmlspecialchars($related['title']); ?></h5>
                                <p class="text-muted"><?php echo substr(htmlspecialchars($related['description']), 0, 100) . '...'; ?></p>
                                <a href="project_details.php?id=<?php echo $related['project_id']; ?>" class="btn btn-outline-primary btn-sm">View Details</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </section>
                <!-- CTA Section -->
                <section class="cta-section">
                    <h2>Want to Contribute to Our Projects?</h2>
                    <p class="lead mb-4">Join NACOS and be part of building innovative solutions!</p>
                    <a href="register.php" class="btn btn-light btn-lg">Become a Member</a>
                </section>
            </div>
        </main>
    </div>
    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
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
</body>
</html>
