<?php
/**
 * ============================================
 * NACOS DASHBOARD - MEMBER DASHBOARD
 * ============================================
 * Purpose: Main dashboard for logged-in members
 * Access: Members only
 * Created: November 3, 2025
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Include required files
require_once __DIR__ . '/../config/config.php';
require_once '../includes/auth.php';

// Require member to be logged in
requireMemberLogin();

// Initialize database
$db = getDB();

// Get current member data
$member = getCurrentMember();
$member_id = $member['member_id'];

// Handle avatar upload
$avatar_upload_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_avatar') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $avatar_upload_error = 'Invalid request. Please try again.';
    } elseif (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        $avatar_upload_error = 'Please select an image to upload.';
    } else {
        $file = $_FILES['avatar'];
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $maxBytes = 2 * 1024 * 1024; // 2MB
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!isset($allowed[$mime])) {
            $avatar_upload_error = 'Only JPG, PNG or WEBP images are allowed.';
        } elseif ($file['size'] > $maxBytes) {
            $avatar_upload_error = 'Image must be 2MB or smaller.';
        } else {
            $ext = $allowed[$mime];
            $filename = 'member_' . $member_id . '_' . time() . '.' . $ext;
            $targetDir = __DIR__ . '/../uploads/members/';
            if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
            $targetPath = $targetDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                // store relative path in DB
                $relativePath = 'uploads/members/' . $filename;
                try {
                    $db->query("UPDATE members SET profile_picture = :profile_picture WHERE member_id = :id", [':profile_picture' => $relativePath, ':id' => $member_id]);
                    // Refresh member details
                    $member_details = $db->fetchOne("SELECT * FROM members WHERE member_id = :id", [':id' => $member_id]);
                } catch (Exception $e) {
                    $avatar_upload_error = 'Failed to update profile picture. Please try again later.';
                    logSecurityEvent('Avatar update failed: ' . $e->getMessage(), 'error');
                }
            } else {
                $avatar_upload_error = 'Failed to move uploaded file.';
            }
        }
    }
}

// Fetch detailed member information
$member_details = $db->fetchOne("SELECT * FROM members WHERE member_id = :id", [':id' => $member_id]);

// Fetch member's registered events (upcoming)
$registered_events = $db->fetchAll(
    "SELECT e.* FROM events e
     JOIN event_registrations er ON e.event_id = er.event_id
     WHERE er.member_id = :member_id AND e.event_date >= CURDATE()
     ORDER BY e.event_date ASC",
    [':member_id' => $member_id]
);

// Fetch member's projects
$member_projects = $db->fetchAll(
    "SELECT p.* FROM projects p
     JOIN member_projects mp ON p.project_id = mp.project_id
     WHERE mp.member_id = :member_id
     ORDER BY p.start_date DESC",
    [':member_id' => $member_id]
);

?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>NACOS — Dashboard</title>
        <link rel="icon" href="../assets/images/favicon.png">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <style>
            :root{
                --nacos-green: #008000; /* NACOS primary green */
                --nacos-white: #ffffff;
                --nacos-bg-light: #f4f7ff;
                --nacos-green-dark: #006600;
            }

            body{
                background-color: var(--nacos-bg-light);
                background: linear-gradient(120deg, rgba(0,128,0,0.04), rgba(0,128,0,0.02));
                font-family: 'Poppins',sans-serif;
                margin:0;
                padding:28px;
                overflow-x: hidden;
            }

            /* Mobile Hamburger Menu Styles */
            .mobile-hamburger {
                display: none;
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 100;
                background: none;
                border: none;
                padding: 0;
                cursor: pointer;
                width: 50px;
                height: 50px;
                align-items: center;
                justify-content: center;
                border-radius: 8px;
                transition: all 0.3s ease;
                opacity: 1;
            }

            /* Hide hamburger while scrolling */
            .mobile-hamburger.scrolling {
                opacity: 0;
                pointer-events: none;
            }

            .mobile-hamburger:hover {
                background-color: rgba(0, 128, 0, 0.1);
            }

            .hamburger-icon {
                width: 28px;
                height: 24px;
                position: relative;
                display: flex;
                flex-direction: column;
                justify-content: space-around;
            }

            .hamburger-line {
                width: 100%;
                height: 3px;
                background-color: var(--nacos-green);
                border-radius: 2px;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                transform-origin: center;
            }

            /* Hamburger to X animation */
            .mobile-hamburger.active .hamburger-line:nth-child(1) {
                transform: translateY(10.5px) rotate(45deg);
            }

            .mobile-hamburger.active .hamburger-line:nth-child(2) {
                opacity: 0;
                transform: translateX(-10px);
            }

            .mobile-hamburger.active .hamburger-line:nth-child(3) {
                transform: translateY(-10.5px) rotate(-45deg);
            }

            /* Mobile Sidebar Styles */
            .mobile-sidebar {
                position: fixed;
                left: -100%;
                top: 0;
                width: 280px;
                height: 100vh;
                background: #0f1724;
                color: #fff;
                z-index: 99;
                padding: 0;
                overflow-y: auto;
                transition: left 0.35s cubic-bezier(0.4, 0, 0.2, 1);
                box-shadow: 2px 0 15px rgba(0, 0, 0, 0.3);
            }

            /* Sidebar open state - slides in from left */
            .mobile-sidebar.open {
                left: 0;
            }

            .mobile-sidebar-close {
                position: absolute;
                top: 20px;
                right: 20px;
                background: none;
                border: none;
                color: #fff;
                font-size: 24px;
                cursor: pointer;
                padding: 8px;
                border-radius: 4px;
                transition: all 0.3s ease;
                width: 44px;
                height: 44px;
                display: none;
                align-items: center;
                justify-content: center;
            }

            .mobile-sidebar-close:hover {
                background-color: rgba(255, 255, 255, 0.1);
                transform: rotate(90deg);
            }

            .mobile-sidebar-content {
                padding: 30px 0;
            }

            .mobile-sidebar-brand {
                display: flex;
                align-items: center;
                gap: 14px;
                padding: 24px 20px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                margin-bottom: 0;
            }

            .mobile-sidebar-brand img {
                height: 40px;
                width: 40px;
                border-radius: 8px;
                object-fit: cover;
            }

            .mobile-sidebar-brand strong {
                font-weight: 700;
                color: #fff;
                font-size: 14px;
                letter-spacing: 0.3px;
            }

            .mobile-sidebar-menu {
                margin-top: 0;
            }

            .mobile-sidebar-menu a {
                display: flex;
                align-items: center;
                gap: 16px;
                padding: 14px 20px;
                border-radius: 0;
                color: rgba(255, 255, 255, 0.85);
                text-decoration: none;
                margin-bottom: 0;
                transition: all 0.25s ease;
                font-size: 15px;
                font-weight: 500;
                position: relative;
            }

            .mobile-sidebar-menu a:hover {
                background: rgba(0, 128, 0, 0.12);
                color: var(--nacos-green);
                transform: none;
                padding-left: 24px;
            }

            .mobile-sidebar-menu a.active {
                background: rgba(0, 128, 0, 0.18);
                color: var(--nacos-green);
                font-weight: 600;
                border-left: 3px solid var(--nacos-green);
                padding-left: 17px;
            }

            .mobile-sidebar-menu i {
                width: 20px;
                text-align: center;
                font-size: 16px;
            }

            /* Mobile Overlay */
            .mobile-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 98;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.3s ease;
            }

            .mobile-overlay.visible {
                opacity: 1;
                pointer-events: auto;
            }

            /* Responsive Design */
            @media (max-width: 768px) {
                .mobile-hamburger {
                    display: flex;
                }

                body {
                    padding: 16px;
                }

                .shell {
                    border-radius: 16px;
                }

                .dash-sidebar {
                    display: none;
                }
            }

            /* Hide desktop sidebar on very small screens */
            @media (max-width: 600px) {
                .dash-sidebar {
                    display: none !important;
                }
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

            /* Sidebar (dark) */
            .dash-sidebar{
                width:220px;
                background:#0f1724;
                color:#fff;
                padding:26px 18px;
                position:relative;
                transition:width .26s cubic-bezier(.4,0,.2,1), padding .26s cubic-bezier(.4,0,.2,1);
                overflow:visible;
            }
            .shell.collapsed .dash-sidebar {
                width:64px !important;
                padding:18px 10px !important;
            }
            .dash-brand{display:flex;align-items:center;gap:12px;margin-bottom:18px}
            .dash-brand img{height:34px;border-radius:6px}
            .dash-brand strong{display:inline-block;transition:opacity .2s}
            .shell.collapsed .dash-brand strong{display:none}
            .dash-menu{margin-top:18px}
            .dash-menu a{display:flex;align-items:center;gap:12px;padding:10px;border-radius:10px;color:rgba(255,255,255,0.9);text-decoration:none;margin-bottom:8px;transition:background .2s,color .2s}
            .dash-menu a.active{background:rgba(0,128,0,0.12);color:var(--nacos-green);font-weight:600}
            .dash-menu a .link-text{display:inline-block;transition:opacity .2s}
            .shell.collapsed .dash-menu a .link-text{display:none}
            .sidebar-bottom{position:absolute;bottom:20px;left:18px;right:18px}
            .shell.collapsed .sidebar-bottom{left:10px;right:10px}
            .shell.collapsed .dash-main{padding-left:12px;}

            /* Main area */
            .dash-main{
                flex:1;
                display:flex;
                padding:28px;
                gap:18px;
                transition:padding .26s cubic-bezier(.4,0,.2,1), width .26s cubic-bezier(.4,0,.2,1);
            }
            .shell.collapsed .dash-main{
                padding-left:12px;
            }

            .content{flex:1}
            .right{width:320px}

            /* Welcome (NACOS themed) */
            .welcome{
                background: linear-gradient(90deg, rgba(0,128,0,0.08), rgba(0,128,0,0.06));
                border-radius:16px;padding:24px;color:var(--nacos-green);display:flex;align-items:center;gap:18px
            }
            .welcome .left{flex:1}
            .welcome h2{margin:0;font-size:22px;color:rgba(4,20,20,0.9)}
            .welcome p{margin:6px 0 0 0;color:#2b2b2b}
            .welcome .welcome-status{margin:6px 0 0 0;color:#2b2b2b}

            @media (max-width: 576px){
                .welcome .welcome-status{display:none;}
            }
            .welcome .cta{margin-top:12px}

            @media (max-width: 576px){
                .welcome .cta{display:flex;gap:8px}
                .welcome .cta .btn{font-size:12px;padding:6px 10px}
                .welcome .cta .btn + .btn{margin-left:0 !important}
            }

            /* Stat tiles */
            .tiles{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-top:18px}
            .tile{background:#fff;padding:14px;border-radius:12px;box-shadow:0 8px 20px rgba(17,24,39,0.04);display:flex;flex-direction:column;align-items:flex-start}
            .tile .num{font-weight:700;font-size:18px}
            .tile .label{font-size:13px;color:#6b7280}

            /* Activity and panels */
            .panel{background:#fff;padding:14px;border-radius:12px;box-shadow:0 8px 20px rgba(17,24,39,0.04);margin-top:18px;margin-bottom:0}
            .panel h6{margin:0 0 8px 0}
            .list-item{padding:10px 0;border-bottom:1px dashed #f0f0f0}

            /* Right column styling */
            .right .panel{margin-bottom:12px}

            /* Executives Auto-Scroll Section */
            .executives-scroll {
                max-height: 320px;
                overflow-y: auto;
                overflow-x: hidden;
                position: relative;
                margin-top: 12px;
                margin-bottom: 0;
                scroll-behavior: smooth;
            }

            .executives-scroll::-webkit-scrollbar {
                width: 6px;
            }

            .executives-scroll::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 10px;
            }

            .executives-scroll::-webkit-scrollbar-thumb {
                background: var(--nacos-green);
                border-radius: 10px;
            }

            .executives-scroll::-webkit-scrollbar-thumb:hover {
                background: var(--nacos-green-dark);
            }

            .executives-wrapper {
                padding: 0;
                margin: 0;
            }

            .executive-item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px 8px;
                border-bottom: 1px solid #f0f0f0;
                transition: background 0.2s ease;
                margin: 0;
            }

            .executive-item:hover {
                background: rgba(0, 128, 0, 0.03);
            }

            .executive-item:last-child {
                border-bottom: 1px solid #f0f0f0;
                margin-bottom: 0;
                padding-bottom: 12px;
            }

            .exec-photo {
                width: 48px;
                height: 48px;
                border-radius: 50%;
                object-fit: cover;
                border: 2px solid rgba(0, 128, 0, 0.2);
                flex-shrink: 0;
            }

            .exec-icon {
                width: 48px;
                height: 48px;
                background: linear-gradient(135deg, rgba(0,128,0,0.15), rgba(0,128,0,0.08));
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 22px;
                flex-shrink: 0;
                border: 2px solid rgba(0, 128, 0, 0.1);
            }

            .exec-name {
                font-weight: 600;
                color: #1f2937;
                font-size: 14px;
            }

            .exec-role {
                font-size: 12px;
                color: #6b7280;
                margin-top: 2px;
            }

            @keyframes scrollUp {
                0% {
                    transform: translateY(0);
                }
                100% {
                    transform: translateY(calc(-100% + 320px));
                }
            }

            /* Pause animation on hover or when user scrolls */
            .executives-scroll:hover .executives-wrapper,
            .executives-scroll.user-scrolling .executives-wrapper {
                animation-play-state: paused;
            }

            /* Buttons themed to NACOS */
            .btn-primary{background:var(--nacos-green);border-color:var(--nacos-green);color:var(--nacos-white)}
            .btn-primary:hover{background:var(--nacos-green-dark);border-color:var(--nacos-green-dark)}

            @media (max-width:1000px){ .tiles{grid-template-columns:repeat(2,1fr)} .right{display:none} .shell{flex-direction:column} }
        </style>
    </head>
    <body>
        <?php
            // small helper lists
            $shoutouts = $db->fetchAll("SELECT full_name FROM members WHERE member_id != :id LIMIT 3", [':id' => $member_id]);
            $talks = array_slice($registered_events,0,2);
            $meetings = array_slice($registered_events,2,2);
            $count_events = count($registered_events);
            $count_projects = count($member_projects);
            $count_members = $db->fetchOne("SELECT COUNT(*) as c FROM members")['c'] ?? 0;
            $count_docs = $db->fetchOne("SELECT COUNT(*) as c FROM documents")['c'] ?? 0;
            $avatarUrl = !empty($member_details['profile_picture']) ? '../' . $member_details['profile_picture'] : '../assets/images/default_avatar.png';
        ?>

        <!-- Mobile Hamburger Menu Button -->
        <button class="mobile-hamburger" id="mobileHamburger" aria-label="Toggle menu">
            <div class="hamburger-icon">
                <div class="hamburger-line"></div>
                <div class="hamburger-line"></div>
                <div class="hamburger-line"></div>
            </div>
        </button>

        <!-- Mobile Overlay -->
        <div class="mobile-overlay" id="mobileOverlay"></div>

        <!-- Mobile Sidebar -->
        <nav class="mobile-sidebar" id="mobileSidebar">
            <div class="mobile-sidebar-brand">
                <img src="../assets/images/nacos_logo.jpg" alt="logo">
                <strong>NACOS, AU Chapter.</strong>
            </div>
            <div class="mobile-sidebar-content">
                <div class="mobile-sidebar-menu">
                    <a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="events.php"><i class="fas fa-calendar"></i> Events</a>
                    <a href="projects.php"><i class="fas fa-briefcase"></i> Projects</a>
                    <a href="resources.php"><i class="fas fa-book"></i> Resources</a>
                    <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                </div>
            </div>
        </nav>

        <div class="shell">
            <aside class="dash-sidebar">
                <div class="dash-brand"><img src="../assets/images/nacos_logo.jpg" alt="logo"><strong> NACOS, AU Chapter.</strong></div>
                <div class="dash-menu">
                    <a href="dashboard.php" class="active"><i class="fas fa-home"></i><span class="link-text">  Dashboard</span></a>
                    <a href="events.php"><i class="fas fa-calendar"></i><span class="link-text"> Events</span></a>
                    <a href="projects.php"><i class="fas fa-briefcase"></i><span class="link-text"> Projects</span></a>
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

            <main class="dash-main">
                <div class="content">
                    <div class="welcome">
                        <div class="left">
                            <h2>Welcome back, <?php echo htmlspecialchars($member_details['full_name']); ?>!</h2>
                            <p class="welcome-status">your profile is <?php echo htmlspecialchars($member_details['membership_status']); ?> — take a look at recent updates and events.</p>
                           <div class="cta">
                                <a href="https://forms.gle/W86q7hNy6DJ67tiN8" class="btn btn-white" style="background:#fff;color:#8b5e34;border-radius:8px;padding:8px 14px" target="_blank" rel="noopener">Make a suggestion</a>
                                <a href="profile.php" class="btn btn-outline-dark" style="margin-left:8px">Edit Profile</a>
                            </div>
                        </div>
                        <div style="width:84px;height:84px;border-radius:50%;overflow:hidden;cursor:pointer;position:relative" id="avatarUploadArea">
                            <img class="member-avatar" id="dashboardAvatar" src="<?php echo htmlspecialchars($avatarUrl); ?>" style="width:100%;height:100%;object-fit:cover">
                            <form id="avatarForm" method="POST" action="dashboard.php" enctype="multipart/form-data" style="display:none;">
                                <input type="hidden" name="action" value="upload_avatar">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input id="avatarInput" type="file" name="avatar" accept="image/*" style="display:none">
                            </form>
                            <div id="avatarOverlay" style="display:none;position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.35);color:#fff;align-items:center;justify-content:center;font-size:14px;font-weight:600;z-index:2;border-radius:50%;text-align:center;pointer-events:none;">Change<br>Photo</div>
                        </div>
                    <script>
                    // Avatar upload logic for dashboard
                    document.addEventListener('DOMContentLoaded', function() {
                        const avatarArea = document.getElementById('avatarUploadArea');
                        const avatarInput = document.getElementById('avatarInput');
                        const avatarForm = document.getElementById('avatarForm');
                        const dashboardAvatar = document.getElementById('dashboardAvatar');
                        const avatarOverlay = document.getElementById('avatarOverlay');
                        if (avatarArea && avatarInput && avatarForm && dashboardAvatar) {
                            avatarArea.addEventListener('mouseenter', function() {
                                avatarOverlay.style.display = 'flex';
                            });
                            avatarArea.addEventListener('mouseleave', function() {
                                avatarOverlay.style.display = 'none';
                            });
                            avatarArea.addEventListener('click', function() {
                                avatarInput.click();
                            });
                            avatarInput.addEventListener('change', function(e) {
                                if (avatarInput.files && avatarInput.files[0]) {
                                    const reader = new FileReader();
                                    reader.onload = function(ev) {
                                        dashboardAvatar.src = ev.target.result;
                                    };
                                    reader.readAsDataURL(avatarInput.files[0]);
                                    setTimeout(function() { avatarForm.submit(); }, 400); // slight delay for preview
                                }
                            });
                        }
                    });
                    </script>
                    </div>

                    <div class="tiles">
                        <div class="tile"><div class="label">Tasks</div><div class="num">4</div></div>
                        <div class="tile"><div class="label">Users</div><div class="num"><?php echo $count_members; ?></div></div>
                        <div class="tile"><div class="label">Events</div><div class="num"><?php echo $count_events; ?></div></div>
                        <div class="tile"><div class="label">Resource</div><div class="num"><?php echo $count_docs; ?></div></div>
                    </div>

                    <div style="display:flex;gap:18px;margin-top:18px">
                        <!--<div style="flex:1" class="panel">
                            <h6>Team executive</h6>
                            <div style="width:100%;max-width:220px;margin:0 auto;">
                                <canvas id="teamChart" style="width:100%;height:auto;max-height:160px;aspect-ratio:1/1;"></canvas>
                            </div>-->
                        <?php if (!empty($avatar_upload_error)): ?>
                            <div class="alert alert-danger mt-2"><?php echo htmlspecialchars($avatar_upload_error); ?></div>
                        <?php endif; ?>
                        </div>
                        <div style="width:320px">
                            <div class="panel">
                                <h6>My activity</h6>
                                <div style="margin-top:8px">
                                    <strong>Upcoming talks</strong>
                                    <?php if (empty($talks)): ?><div class="text-muted">No talks</div><?php else: foreach($talks as $t): ?>
                                        <div class="list-item"><div style="font-weight:600"><?php echo htmlspecialchars($t['event_name']); ?></div><div style="font-size:12px;color:#6b7280"><?php echo date('M d, Y', strtotime($t['event_date'])); ?></div></div>
                                    <?php endforeach; endif; ?>

                                    <strong style="margin-top:12px;display:block">Upcoming meetings</strong>
                                    <?php if (empty($meetings)): ?><div class="text-muted">No meetings</div><?php else: foreach($meetings as $m): ?>
                                        <div class="list-item"><div style="font-weight:600"><?php echo htmlspecialchars($m['event_name']); ?></div><div style="font-size:12px;color:#6b7280"><?php echo date('M d, Y', strtotime($m['event_date'])); ?></div></div>
                                    <?php endforeach; endif; ?>
                                </div>
                            </div>

                            <div class="panel">
                                <h6>NACOS Executives</h6>
                                <div class="executives-scroll" id="execScroll">
                                    <div class="executives-wrapper">
                                        <div class="executive-item">
                                            <img src="../assets/images/president.png" alt="Joseph Joy" class="exec-photo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="exec-icon" style="display:none;">👤</div>
                                            <div>
                                                <div class="exec-name">Joseph Joy</div>
                                                <div class="exec-role">President</div>
                                            </div>
                                        </div>
                                        <div class="executive-item">
                                            <img src="../assets/images/fatima.jpg" alt="Aderibigbe Fatima" class="exec-photo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="exec-icon" style="display:none;">👤</div>
                                            <div>
                                                <div class="exec-name">Aderibigbe Fatima</div>
                                                <div class="exec-role">Vice President</div>
                                            </div>
                                        </div>
                                        <div class="executive-item">
                                            <img src="../assets/images/damola.jpg" alt="Alabi Akindamola" class="exec-photo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="exec-icon" style="display:none;">👤</div>
                                            <div>
                                                <div class="exec-name">Alabi Akindamola O.</div>
                                                <div class="exec-role">General Secretary</div>
                                            </div>
                                        </div>
                                        <div class="executive-item">
                                            <img src="../assets/images/oyedele.jpg" alt="Oyedele Hezekiah" class="exec-photo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="exec-icon" style="display:none;">👤</div>
                                            <div>
                                                <div class="exec-name">Oyedele Hezekiah A.</div>
                                                <div class="exec-role">Social Director</div>
                                            </div>
                                        </div>
                                        <div class="executive-item">
                                            <img src="../assets/images/praise.jpg" alt="Adewoyin Praise" class="exec-photo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="exec-icon" style="display:none;">👤</div>
                                            <div>
                                                <div class="exec-name">Adewoyin Praise</div>
                                                <div class="exec-role">Public Relations Officer</div>
                                            </div>
                                        </div>
                                        <div class="executive-item">
                                            <img src="../assets/images/iyanu.jpg" alt="Adefiranye Iyanuoluwa" class="exec-photo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="exec-icon" style="display:none;">👤</div>
                                            <div>
                                                <div class="exec-name">Adefiranye Iyanuoluwa</div>
                                                <div class="exec-role">Welfare Director</div>
                                            </div>
                                        </div>
                                        <div class="executive-item">
                                            <img src="../assets/images/glory.jpg" alt="Kolawole Glory" class="exec-photo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="exec-icon" style="display:none;">👤</div>
                                            <div>
                                                <div class="exec-name">Kolawole Glory</div>
                                                <div class="exec-role">Creative Director</div>
                                            </div>
                                        </div>
                                        <div class="executive-item">
                                            <img src="../assets/images/khalid.jpg" alt="Akintola Khalid" class="exec-photo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="exec-icon" style="display:none;">👤</div>
                                            <div>
                                                <div class="exec-name">Akintola Khalid A.</div>
                                                <div class="exec-role">Sport Director</div>
                                            </div>
                                        </div>
                                        <div class="executive-item">
                                            <img src="../assets/images/sofiyat.jpg" alt="Atoyebi Sofiyat" class="exec-photo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="exec-icon" style="display:none;">👤</div>
                                            <div>
                                                <div class="exec-name">Atoyebi Sofiyat</div>
                                                <div class="exec-role">Financial Secretary</div>
                                            </div>
                                        </div>
                                        <div class="executive-item">
                                            <img src="../assets/images/amoo.jpg" alt="Amoo Adeola" class="exec-photo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="exec-icon" style="display:none;">👤</div>
                                            <div>
                                                <div class="exec-name">Amoo Adeola</div>
                                                <div class="exec-role">Treasurer</div>
                                            </div>
                                        </div>
                                        <div class="executive-item">
                                            <img src="../assets/images/oyedokun.jpg" alt="Oluokun David" class="exec-photo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="exec-icon" style="display:none;">👤</div>
                                            <div>
                                                <div class="exec-name">Oluokun Oluwadimimu David</div>
                                                <div class="exec-role">Academic Director</div>
                                            </div>
                                        </div>
                                        
                                        <!-- Duplicate for seamless scroll -->
                                        
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

               <!-- <aside class="right">
                    <div class="panel">
                        <h6>Quick actions</h6>
                        <a href="events.php">Register for event</a><br>
                        <a href="projects.php">Create project</a>
                    </div> 
                    <div class="panel">
                        <h6>Your profile</h6>
                        <div style="display:flex;align-items:center;gap:12px">
                            <img class="member-avatar" src="<?php echo htmlspecialchars($avatarUrl); ?>" style="width:56px;height:56px;border-radius:8px;object-fit:cover">
                            <div>
                                <div style="font-weight:700"><?php echo htmlspecialchars($member_details['full_name']); ?></div>
                                <div style="font-size:13px;color:#6b7280"><?php echo htmlspecialchars($member_details['email']); ?></div>
                            </div>
                        </div>
                        <form id="avatarForm" method="POST" action="dashboard.php" enctype="multipart/form-data" style="margin-top:12px">
                            <input type="hidden" name="action" value="upload_avatar">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input id="avatarInput" type="file" name="avatar" accept="image/*" style="width:100%">
                            <div style="display:flex;gap:8px;margin-top:8px">
                                <button id="avatarUploadBtn" class="btn btn-sm btn-primary" type="submit">Upload</button>
                                <button type="button" id="avatarCancelBtn" class="btn btn-sm btn-outline-secondary">Cancel</button>
                            </div>
                        </form>
                        <?php if (!empty($avatar_upload_error)): ?><div class="text-danger mt-2"><?php echo htmlspecialchars($avatar_upload_error); ?></div><?php endif; ?>
                    </div>-->
                </aside>
            </main>
        </div>

        <script>
        // Mobile Hamburger Menu & Sidebar Controller
        document.addEventListener('DOMContentLoaded', function() {
            const hamburger = document.getElementById('mobileHamburger');
            const sidebar = document.getElementById('mobileSidebar');
            const overlay = document.getElementById('mobileOverlay');
            const sidebarLinks = document.querySelectorAll('.mobile-sidebar-menu a');
            
            // Check if elements exist
            if (!hamburger || !sidebar || !overlay) {
                console.error('Mobile menu elements not found');
                return;
            }

            // Executives scroll pause on user interaction
            const execScroll = document.getElementById('execScroll');
            if (execScroll) {
                let scrollTimer;
                execScroll.addEventListener('scroll', function() {
                    execScroll.classList.add('user-scrolling');
                    clearTimeout(scrollTimer);
                    scrollTimer = setTimeout(function() {
                        execScroll.classList.remove('user-scrolling');
                    }, 3000); // Resume auto-scroll after 3 seconds of no manual scrolling
                }, { passive: true });
            }

            // Scroll handling - hide hamburger while scrolling, show when stopped
            let scrollTimeout;
            let isScrolling = false;

            window.addEventListener('scroll', function() {
                hamburger.classList.add('scrolling');
                isScrolling = true;

                // Clear previous timeout
                clearTimeout(scrollTimeout);

                // Set timeout to remove scrolling class after 1.5 seconds of no scrolling
                scrollTimeout = setTimeout(function() {
                    hamburger.classList.remove('scrolling');
                    isScrolling = false;
                }, 1500);
            }, { passive: true });

            // Toggle sidebar open/close
            function toggleSidebar() {
                hamburger.classList.toggle('active');
                sidebar.classList.toggle('open');
                overlay.classList.toggle('visible');
                // Remove scrolling class when opening sidebar
                hamburger.classList.remove('scrolling');
            }

            // Close sidebar
            function closeSidebar() {
                hamburger.classList.remove('active');
                sidebar.classList.remove('open');
                overlay.classList.remove('visible');
            }

            // Event listeners
            hamburger.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });
            
            overlay.addEventListener('click', closeSidebar);

            // Close sidebar when a link is clicked
            sidebarLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    closeSidebar();
                });
            });

            // Close sidebar on Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });

            // Coming Soon - Resources and Projects links
            const comingSoonLinks = document.querySelectorAll('a[href="resources.php"], a[href="projects.php"]');
            const comingSoonOverlay = document.getElementById('comingSoonOverlay');
            const comingSoonCloseBtn = document.getElementById('comingSoonCloseBtn');

            function showComingSoon(e) {
                e.preventDefault();
                comingSoonOverlay.style.display = 'flex';
            }

            function hideComingSoon() {
                comingSoonOverlay.style.display = 'none';
            }

            comingSoonLinks.forEach(link => {
                link.addEventListener('click', showComingSoon);
            });

            comingSoonCloseBtn.addEventListener('click', hideComingSoon);
            comingSoonOverlay.addEventListener('click', function(e) {
                if (e.target === comingSoonOverlay) hideComingSoon();
            });
        });
        </script>

        <!-- Coming Soon Popup -->
        <div id="comingSoonOverlay" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:10000;align-items:center;justify-content:center;">
            <div style="background:#fff;border-radius:12px;padding:40px;max-width:400px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                <i class="fas fa-rocket" style="font-size:48px;color:var(--nacos-green);margin-bottom:16px;display:block;"></i>
                <h3 style="color:var(--nacos-green);margin:16px 0;font-weight:700;">Coming Soon</h3>
                <p style="color:#666;margin:0 0 24px 0;font-size:15px;">This feature is under development and will be available soon!</p>
                <button id="comingSoonCloseBtn" style="background:var(--nacos-green);color:#fff;border:none;padding:10px 24px;border-radius:6px;cursor:pointer;font-weight:600;">Got it</button>
            </div>
        </div>

       <!-- <script>
            // Team chart demo (donut)
            const tctx = document.getElementById('teamChart').getContext('2d');
            new Chart(tctx, {
                type: 'doughnut',
                data: {
                    labels: ['Green dove','Blue owl','Yellow peacock','Red eagle'],
                    datasets: [{data:[10,20,7,8],backgroundColor:['#34d399','#60a5fa','#f6c85f','#fb7185']}]
                },
                options:{plugins:{legend:{position:'bottom'}}}
            });
        </script>-->
        <style>
            /* Theme overrides - NACOS green */
            :root { --nacos-green: #008000; --nacos-white: #ffffff; }

            /* (removed duplicate/old sidebar collapse CSS) */

            /* Edit profile modal - smaller and centered */
            .modal-backdrop-custom{position:fixed;inset:0;background:rgba(8,12,20,0.45);display:flex;align-items:center;justify-content:center;z-index:9999}
            .modal-panel{background:var(--nacos-white);border-radius:10px;max-width:560px;width:100%;padding:18px;box-shadow:0 20px 60px rgba(2,6,23,0.12)}

            /* Buttons and accents */
            .btn-primary{background:var(--nacos-green);border-color:var(--nacos-green);color:var(--nacos-white)}
            .btn-primary:hover{background:#006600;border-color:#006600}

            /* Responsive tweaks */
            @media (max-width:1000px){ .dash-sidebar{position:relative;z-index:20} .dash-sidebar.expanded{width:200px} }
        </style>

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
