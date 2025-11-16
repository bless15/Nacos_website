<?php
/**
 * NACOS PUBLIC HOMEPAGE — rebuilt clean version
 */

// Security & includes
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cache.php';

// Force cache clear for partners to debug logo issue
cache_delete('homepage_partners');

$db = getDB();

// Stats
$total_members = (int)($db->fetchOne("SELECT COUNT(*) as count FROM MEMBERS WHERE membership_status = 'active'")["count"] ?? 0);
$total_projects = (int)($db->fetchOne("SELECT COUNT(*) as count FROM PROJECTS")["count"] ?? 0);
$total_events = (int)($db->fetchOne("SELECT COUNT(*) as count FROM EVENTS")["count"] ?? 0);

// Small dataset queries for homepage
$upcoming_events = $db->fetchAll("SELECT * FROM EVENTS WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 3");
$recent_projects = $db->fetchAll("SELECT * FROM PROJECTS WHERE project_status='completed' ORDER BY project_id DESC LIMIT 3");

$rows = $db->fetchAll("SELECT member_id, full_name FROM MEMBERS WHERE membership_status='active' LIMIT 100");
shuffle($rows);
$showcase_members = array_slice($rows, 0, min(8, count($rows)));

$partners = cache_get('homepage_partners');
if ($partners === null) {
    $partners = $db->fetchAll("SELECT partner_id, company_name, company_logo, website_url, is_featured FROM PARTNERS WHERE status='active' AND visibility='public' ORDER BY is_featured DESC, partnership_start_date DESC LIMIT 8");
    cache_set('homepage_partners', $partners, 60);
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NACOS — Innovate, Collaborate, and Excel</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <?php require_once __DIR__ . '/../includes/asset.php'; ?>
    <link rel="stylesheet" href="../<?php echo htmlspecialchars(asset('css/public.css')); ?>">
    <script src="../<?php echo htmlspecialchars(asset('js/public.js')); ?>" defer></script>
    <style>
        .hero-illustration{ max-width:520px; width:100%; height:auto; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/public_navbar.php'; ?>

    <section class="hero-section py-5">
        <div class="container">
            <div class="hero-card p-5 rounded-4 shadow-lg" style="background:linear-gradient(180deg, rgba(255,255,255,0.03), rgba(255,255,255,0));">
                <div class="row align-items-center">
                    <div class="col-lg-6">
                        <h1 class="hero-title display-4">Innovate, Collaborate, <span class="hero-highlight">and Excel</span> with NACOS</h1>
                        <p class="hero-subtitle lead">The official hub for computer science students fostering knowledge and real-world projects.</p>
                        <div class="d-flex gap-3 mt-4">
                            <a href="register.php" class="btn btn-light btn-lg">Become a Member</a>
                            <a href="events.php" class="btn btn-outline-light btn-lg">Explore Events</a>
                        </div>
                    </div>
                    <div class="col-lg-6 text-center">
                        <div class="hero-visual">
                            <svg class="hero-illustration" viewBox="0 0 840 520" xmlns="http://www.w3.org/2000/svg" role="img" aria-hidden="true">
                                <rect x="40" y="40" width="760" height="440" rx="28" fill="#0a6b2b" opacity="0.08" />
                                <rect x="80" y="80" width="700" height="360" rx="18" fill="#fff" />
                                <rect x="110" y="110" width="580" height="28" rx="6" fill="#f5f7fb" />
                                <rect x="140" y="160" width="360" height="180" rx="10" fill="#f6fbf7" />
                                <rect x="520" y="170" width="190" height="120" rx="10" fill="#f4fbff" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Community & Impact Section -->
    <section class="community-impact-section py-5">
        <div class="container position-relative">
            <!-- Decorative Elements -->
            <div class="decorative-dot dot-1"></div>
            <div class="decorative-dot dot-2"></div>
            <div class="decorative-dot dot-3"></div>

            <div class="text-center mb-5">
                <h2 class="fw-bold display-5">Our Community & Impact</h2>
            </div>
            <div class="row text-center">
                <div class="col-md-3 col-6 mb-4">
                    <h3 class="display-3 fw-bold text-success"><?php echo $total_members; ?>+</h3>
                    <p class="text-muted">Strong Network</p>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <h3 class="display-3 fw-bold text-success"><?php echo $total_projects; ?>+</h3>
                    <p class="text-muted">Successful Projects</p>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <h3 class="display-3 fw-bold text-success">25+</h3>
                    <p class="text-muted">Years & Impact</p>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <h3 class="display-3 fw-bold text-success"><?php echo $total_events; ?>+</h3>
                    <p class="text-muted">Annual Events</p>
                </div>
            </div>
            <div class="mt-4 text-center">
                <p class="lead text-muted mb-4">Proudly partnered with leading organizations. We foster growth, collaboration, and learning.</p>
                <div class="d-flex gap-3 justify-content-center align-items-center flex-wrap">
                    <?php foreach (array_slice($partners, 0, 5) as $p): ?>
                        <div class="partner-logo-card" title="<?php echo htmlspecialchars($p['company_name']); ?>">
                            <?php
                                $logoFile = $p['company_logo'] ?? '';
                                $uploadPath = __DIR__ . '/../uploads/partners/' . $logoFile;
                                $logoSrc = ($logoFile && file_exists($uploadPath)) ? '../uploads/partners/' . $logoFile : '../assets/images/nacos_logo.jpg';
                            ?>
                            <img src="<?php echo $logoSrc; ?>" alt="<?php echo htmlspecialchars($p['company_name']); ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-5 d-flex justify-content-center gap-3">
                    <a href="events.php" class="btn btn-primary btn-lg">Explore Events</a>
                    <a href="register.php" class="btn btn-outline-secondary btn-lg">Join Now</a>
                </div>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
