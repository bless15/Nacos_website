<?php
/**
 * ============================================
 * NACOS DASHBOARD - EDIT PROFILE
 * ============================================
 * Purpose: Allow members to edit their profile information
 * Access: Requires member authentication
 * Created: November 3, 2025
 * ============================================
 */

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/config.php';
require_once '../includes/auth.php';

// Require member to be logged in
if (!isMemberLoggedIn()) {
    header('Location: login.php');
    exit();
}

$db = getDB();
$current_member = getCurrentMember();
$member_id = $current_member['member_id'];

// Check profile edit count for the current month
$start_of_month = date('Y-m-01 00:00:00');
$end_of_month = date('Y-m-t 23:59:59');

$edit_count_result = $db->fetchOne(
    "SELECT COUNT(*) as edit_count FROM profile_edit_logs WHERE member_id = ? AND edit_timestamp BETWEEN ? AND ?",
    [$member_id, $start_of_month, $end_of_month]
);
$edit_count = $edit_count_result['edit_count'] ?? 0;
$edits_left = max(0, 2 - $edit_count);


$errors = [];
$success_profile = '';

// Handle profile information update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if ($edits_left <= 0) {
        $errors[] = "You have reached your monthly limit of 2 profile edits.";
    } else {
        $full_name = sanitizeInput($_POST['full_name']);
        $email = sanitizeInput($_POST['email']);
        $phone = sanitizeInput($_POST['phone']);
        $department = sanitizeInput($_POST['department']);
        $level = sanitizeInput($_POST['level']);

        // Basic validation
        if (empty($full_name) || empty($email) || empty($phone) || empty($department) || empty($level)) {
            $errors[] = "All profile fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        } else {
            // Check if email is already taken by another member
            $existing_member = $db->fetchOne("SELECT member_id FROM members WHERE email = ? AND member_id != ?", [$email, $member_id]);
            if ($existing_member) {
                $errors[] = "This email address is already in use by another member.";
            } else {
                // Update profile
                $update_stmt = $db->query(
                    "UPDATE members SET full_name = ?, email = ?, phone = ?, department = ?, level = ? WHERE member_id = ?",
                    [$full_name, $email, $phone, $department, $level, $member_id]
                );
                if ($update_stmt) {
                    // Log the edit
                    $db->query("INSERT INTO profile_edit_logs (member_id) VALUES (?)", [$member_id]);

                    $success_profile = "Your profile has been updated successfully.";
                    // Refresh member data and edit count
                    $current_member = getCurrentMember();
                    $edit_count++;
                    $edits_left = max(0, 2 - $edit_count);
                } else {
                    $errors[] = "Failed to update profile. Please try again.";
                }
            }
        }
    }
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>NACOS — Profile</title>
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
        @media (max-width:1000px){ .right{display:none} .shell{flex-direction:column} }
        @media (max-width:700px){
            .dash-main{flex-direction:column;padding:14px}
            .content{padding:0}
            .panel{padding:12px 6px}
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
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="events.php"><i class="fas fa-calendar"></i> Events</a>
            <a href="projects.php"><i class="fas fa-briefcase"></i> Projects</a>
            <a href="resources.php"><i class="fas fa-book"></i> Resources</a>
            <a href="profile.php" class="active"><i class="fas fa-user"></i> Profile</a>
        </div>
    </div>
</nav>

<div class="shell" id="mainShell">
    <aside class="dash-sidebar" id="sidebarNav">
        <div class="dash-brand"><img src="../assets/images/nacos_logo.jpg" alt="logo"><strong> NACOS, AU Chapter.</strong></div>
        <div class="dash-menu">
            <a href="dashboard.php"><i class="fas fa-home"></i><span class="link-text"> Dashboard</span></a>
            <a href="events.php"><i class="fas fa-calendar"></i><span class="link-text"> Events</span></a>
            <a href="projects.php"><i class="fas fa-briefcase"></i><span class="link-text"> Projects</span></a>
            <a href="resources.php"><i class="fas fa-book"></i><span class="link-text"> Resources</span></a>
            <a href="profile.php" class="active"><i class="fas fa-user"></i><span class="link-text"> Profile</span></a>
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
            <h2>Edit Your Profile</h2>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p class="mb-0"><?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="panel">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    You have <strong><?php echo $edits_left; ?></strong> profile edit(s) remaining for this month.
                </div>
                <?php if ($success_profile): ?>
                    <div class="alert alert-success"><?php echo $success_profile; ?></div>
                <?php endif; ?>
                <form action="profile.php" method="POST">
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($current_member['full_name'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($current_member['email'] ?? ''); ?>" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($current_member['phone'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="matric_no" class="form-label">Matriculation Number</label>
                            <input type="text" class="form-control" id="matric_no" name="matric_no" value="<?php echo htmlspecialchars($current_member['matric_no'] ?? ''); ?>" disabled>
                            <small class="form-text text-muted">Matric number cannot be changed.</small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="department" class="form-label">Department</label>
                            <input type="text" class="form-control" id="department" name="department" value="<?php echo htmlspecialchars($current_member['department'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="level" class="form-label">Level</label>
                            <select class="form-select" id="level" name="level" required>
                                <option value="100" <?php echo ($current_member['level'] ?? '') == '100' ? 'selected' : ''; ?>>100 Level</option>
                                <option value="200" <?php echo ($current_member['level'] ?? '') == '200' ? 'selected' : ''; ?>>200 Level</option>
                                <option value="300" <?php echo ($current_member['level'] ?? '') == '300' ? 'selected' : ''; ?>>300 Level</option>
                                <option value="400" <?php echo ($current_member['level'] ?? '') == '400' ? 'selected' : ''; ?>>400 Level</option>
                                <option value="500" <?php echo ($current_member['level'] ?? '') == '500' ? 'selected' : ''; ?>>500 Level</option>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" name="update_profile" class="btn btn-primary" <?php if ($edits_left <= 0) echo 'disabled'; ?>>Save Changes</button>
                        <a href="dashboard.php" class="btn btn-outline-primary">Return</a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<!-- Coming Soon Popup -->
<div id="comingSoonOverlay" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:10000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:40px;max-width:400px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
        <i class="fas fa-rocket" style="font-size:48px;color:var(--nacos-green);margin-bottom:16px;display:block;"></i>
        <h3 style="color:var(--nacos-green);margin:16px 0;font-weight:700;">Coming Soon</h3>
        <p style="color:#666;margin:0 0 24px 0;font-size:15px;">This feature is under development and will be available soon!</p>
        <button id="comingSoonCloseBtn" style="background:var(--nacos-green);color:#fff;border:none;padding:10px 24px;border-radius:6px;cursor:pointer;font-weight:600;">Got it</button>
    </div>
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
</body>
</html>
