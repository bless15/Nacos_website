<?php
/**
 * ============================================
 * NACOS DASHBOARD - PUBLIC NAVIGATION BAR
 * ============================================
 * Reusable navigation component for public pages
 * ============================================
 */

// Ensure session is initialized and DB is available for the navbar
if (!function_exists('initSession')) {
    // auth.php defines initSession() and other helpers; include it if not already available
    if (file_exists(__DIR__ . '/auth.php')) {
        require_once __DIR__ . '/auth.php';
    }
}

// Initialize session if possible
if (function_exists('initSession')) {
    initSession();
}

$is_logged_in = isset($_SESSION['member_id']);
$current_page = basename($_SERVER['PHP_SELF']);

// Ensure $db is present (some pages include the navbar after initializing DB; fallback if not)
if (!isset($db) && function_exists('getDB')) {
    require_once __DIR__ . '/../config/database.php';
    $db = getDB();
}

// Get pending feedback count if logged in
$pending_feedback_count = 0;
if ($is_logged_in && isset($db)) {
    $member_id = $_SESSION['member_id'];
    $pending_feedback_count = $db->fetchOne(
        "SELECT COUNT(*) as count FROM MEMBER_EVENTS me
         WHERE me.member_id = ? 
         AND me.attendance_status = 'attended' 
         AND (me.feedback_rating IS NULL OR me.feedback_comment IS NULL OR me.feedback_comment = '')",
        [$member_id]
    )['count'] ?? 0;
}
?>
<header class="navbar navbar-expand-lg navbar-light fixed-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <img src="../assets/images/nacos_logo.jpg" alt="NACOS Logo" style="height:40px; margin-right:10px; vertical-align:middle;"> NACOS
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page === 'index.php') ? 'active' : ''; ?>" href="index.php">
                        Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page === 'about.php') ? 'active' : ''; ?>" href="about.php">
                        About
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page === 'events.php') ? 'active' : ''; ?>" href="events.php">
                        Events
                    </a>
                </li>
                
                <?php if ($is_logged_in): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page === 'my_events.php') ? 'active' : ''; ?>" href="my_events.php">
                        <i class="fas fa-calendar-check"></i> My Events
                        <?php if ($pending_feedback_count > 0): ?>
                            <span class="badge bg-danger rounded-pill ms-1"><?php echo $pending_feedback_count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; ?>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page === 'projects.php') ? 'active' : ''; ?>" href="projects.php">
                        Projects
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page === 'community.php') ? 'active' : ''; ?>" href="community.php">
                        Community
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page === 'contact.php') ? 'active' : ''; ?>" href="contact.php">
                        Contact
                    </a>
                </li>
            </ul>
            <div class="d-flex gap-2 ms-lg-3">
                <?php if ($is_logged_in): ?>
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                    </a>
                    <a href="logout.php" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt me-1"></i>Logout
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline-light">Member Login</a>
                    <a href="register.php" class="btn btn-success">Apply Now</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<style>
.navbar {
    background: #1a251a;
    backdrop-filter: blur(10px);
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 1rem 0;
    transition: all 0.3s ease;
}

.navbar.scrolled {
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
}

.navbar-brand {
    font-weight: 700;
    font-size: 1.5rem;
    color: #ffffff !important;
}

.nav-link {
    font-weight: 500;
    color: #e0e0e0 !important;
    padding: 0.5rem 1rem !important;
    transition: color 0.3s ease;
}

.nav-link:hover, .nav-link.active {
    color: #ffffff !important;
}

.badge {
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
    vertical-align: middle;
}
</style>

<script>
// Navbar scroll effect
window.addEventListener('scroll', function() {
    const navbar = document.querySelector('.navbar.fixed-top');
    if (navbar) {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    }
});
</script>
