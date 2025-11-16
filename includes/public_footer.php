<?php
/**
 * ============================================
 * NACOS DASHBOARD - PUBLIC FOOTER
 * ============================================
 * Reusable footer component for public pages
 * ============================================
 */
?>
<?php if (!isset($minimal_footer) || !$minimal_footer) { ?>
<?php
// Fetch a few public partners to show in the footer (silent fail if DB not available)
if (!function_exists('getDB')) {
    // Try to include database config if not already present
    if (file_exists(__DIR__ . '/../config/database.php')) {
        require_once __DIR__ . '/../config/database.php';
    }
}
$partners_for_footer = [];
// Use cache helper if available
if (file_exists(__DIR__ . '/cache.php')) {
    require_once __DIR__ . '/cache.php';
}
try {
    if (function_exists('getDB')) {
        $db = getDB();
        $partners_for_footer = function_exists('cache_get') ? cache_get('footer_partners') : null;
        if ($partners_for_footer === null) {
            $partners_for_footer = $db->fetchAll("SELECT company_name, company_logo, website_url, is_featured FROM PARTNERS WHERE status = 'active' AND visibility = 'public' ORDER BY is_featured DESC, partnership_start_date DESC LIMIT 6");
            if (function_exists('cache_set')) cache_set('footer_partners', $partners_for_footer, 120);
        }
    }
} catch (Exception $e) {
    $partners_for_footer = [];
}
?>
<footer class="footer">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4">
                <h5 class="footer-brand">
                    <i class="fas fa-graduation-cap"></i> NACOS
                </h5>
                <p class="text-muted">Fostering a community of innovators and leaders in computer science.</p>
                <div class="social-links">
                    <a href="#" title="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="#" title="Facebook"><i class="fab fa-facebook"></i></a>
                    <a href="#" title="LinkedIn"><i class="fab fa-linkedin"></i></a>
                    <a href="#" title="GitHub"><i class="fab fa-github"></i></a>
                    <a href="#" title="Instagram"><i class="fab fa-instagram"></i></a>
                </div>

                <?php
                // Footer partners removed from thumbnail display per request.
                // Partner listings are now accessible via the Quick Links -> Partners page.
                ?>
            </div>
            <div class="col-lg-2 col-6 mb-4">
                <h5>Quick Links</h5>
                <ul class="list-unstyled">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="about.php">About Us</a></li>
                    <li><a href="events.php">Events</a></li>
                    <li><a href="projects.php">Projects</a></li>
                    <li><a href="contact.php">Contact</a></li>
                    <li><a href="partners.php">Partners</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-6 mb-4">
                <h5>For Members</h5>
                <ul class="list-unstyled">
                    <?php if (isset($_SESSION['member_id'])): ?>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="my_events.php">My Events</a></li>
                        <li><a href="profile.php">My Profile</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Member Login</a></li>
                        <li><a href="register.php">Register</a></li>
                    <?php endif; ?>
                    <!--<li><a href="../admin/login.php">Admin Login</a></li>-->
                </ul>
            </div>
            <div class="col-lg-3 mb-4">
                <h5>Contact Info</h5>
                <ul class="list-unstyled text-muted">
                    <li><i class="fas fa-map-marker-alt me-2"></i>Computer Science Department</li>
                    <li><i class="fas fa-envelope me-2"></i>nacos@university.edu</li>
                    <li><i class="fas fa-phone me-2"></i>+234 XXX XXX XXXX</li>
                </ul>
            </div>
        </div>
        <hr class="my-4">
        <div class="row">
            <div class="col-12 text-center">
                <p class="text-muted mb-2">
                    &copy; <?php echo date('Y'); ?> NACOSAU. All Rights Reserved.
                </p>
                <p class="text-muted mb-0">
                    Developed by <a href="https://johnicity.com.ng/portfolio" target="_blank" rel="noopener noreferrer" style="color: var(--primary-color); text-decoration: none;">Johnicity</a>
                </p>
            </div>
        </div>
    </div>
</footer>

<style>
.footer {
    background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
    color: #ffffff;
    padding: 60px 0 20px;
    margin-top: 80px;
}

.footer h5 {
    color: #ffffff;
    font-weight: 600;
    margin-bottom: 20px;
    font-size: 1.1rem;
}

.footer-brand {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary-color) !important;
}

.footer a {
    color: #b0b0b0;
    text-decoration: none;
    transition: color 0.3s ease;
    display: inline-block;
    margin-bottom: 8px;
}

.footer a:hover {
    color: var(--primary-color);
    transform: translateX(5px);
}

.footer .list-unstyled li {
    margin-bottom: 10px;
    transition: transform 0.3s ease;
}

.footer .list-unstyled li:hover {
    transform: translateX(3px);
}

.social-links {
    display: flex;
    gap: 15px;
    margin-top: 20px;
}

.social-links a {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    color: #ffffff;
    font-size: 1.2rem;
    transition: all 0.3s ease;
    margin-bottom: 0;
}

.social-links a:hover {
    background: var(--primary-color);
    color: #ffffff;
    transform: translateY(-3px);
}

.footer hr {
    border-color: rgba(255, 255, 255, 0.1);
}

.footer .text-muted {
    color: #888 !important;
}

/* Footer partners thumbnails */
.footer-partners .partner-thumb img { transition: transform .18s ease, box-shadow .18s ease; }
.footer-partners .partner-thumb:hover img { transform: translateY(-4px) scale(1.03); box-shadow: 0 8px 20px rgba(0,0,0,0.18); }
</style>

<!-- Load Bootstrap JS bundle and include confirmation modal for public pages -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>

<?php // Include shared confirmation modal so public pages can reuse the same modal API ?>
<?php
if (file_exists(__DIR__ . '/confirm_modal.php')) {
    include __DIR__ . '/confirm_modal.php';
}

} else {
    ?>
    <footer class="footer-minimal">
        <div class="container text-center py-3">
            <p class="text-muted mb-2">&copy; <?php echo date('Y'); ?> NACOSAU. All Rights Reserved.</p>
            <p class="text-muted mb-0">Developed by <a href="https://johnicity.com.ng/portfolio" target="_blank" rel="noopener noreferrer" class="text-decoration-none">Johnicity</a></p>
        </div>
    </footer>
    <style>
    /* Minimal footer fixed to bottom so it doesn't sit beside centered auth cards */
    .footer-minimal { 
        background: transparent; 
        padding: 12px 0; 
        margin-top: 0; 
        position: fixed; 
        bottom: 0; 
        left: 0; 
        width: 100%; 
        z-index: 1000;
    }
    .footer-minimal .text-muted { color: #fff !important; opacity: 0.95; font-size: 0.9rem; }
    .footer-minimal a { color: #fff !important; text-decoration: underline; }
    /* Add small bottom padding to body when minimal footer is present to avoid overlap */
    body { padding-bottom: 60px; }
    </style>
<?php } ?>
