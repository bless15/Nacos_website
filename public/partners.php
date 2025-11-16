<?php
/**
 * Public Partners Page
 * Lists active, public partners with logos and descriptions.
 */
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$db = getDB();

$partners = $db->fetchAll(
    "SELECT partner_id, company_name, company_logo, website_url, description, partnership_start_date
     FROM PARTNERS
     WHERE status = 'active' AND visibility = 'public'
     ORDER BY partnership_start_date DESC"
);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Our Partners - NACOS</title>
    <link rel="stylesheet" href="../assets/css/public.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/public_navbar.php'; ?>

    <main class="py-5">
        <div class="container">
            <div class="text-center mb-4">
                <h1>Our Partners</h1>
                <p class="lead">We value the organisations that support NACOS. Below are our active partners who work with our members to provide mentorship, resources, and opportunities.</p>
            </div>

            <?php if (empty($partners)): ?>
                <div class="alert alert-info">There are currently no partners to display.</div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($partners as $p):
                        $logo = trim($p['company_logo'] ?? '');
                        $logoSrc = '../assets/images/nacos_logo.jpg';

                        if ($logo !== '') {
                            // Absolute URL / protocol-relative / data URI -> use directly
                            if (preg_match('#^(https?:)?//#i', $logo) || strpos($logo, 'data:') === 0) {
                                $logoSrc = $logo;
                            } else {
                                // Normalize any leading ../ or ./ or / entries
                                $normalized = preg_replace('#^\.{1,2}/+#', '', $logo);

                                // If the stored value already contains uploads/partners, ensure correct prefix from public/
                                if (stripos($normalized, 'uploads/partners') === 0) {
                                    $logoSrc = '../' . $normalized;
                                } else {
                                    // Candidate: filename stored
                                    $candidate = __DIR__ . '/../uploads/partners/' . $normalized;
                                    if (file_exists($candidate)) {
                                        $logoSrc = '../uploads/partners/' . $normalized;
                                    } else {
                                        // Try relative to project root
                                        $candidate2 = __DIR__ . '/../' . ltrim($normalized, '/');
                                        if (file_exists($candidate2)) {
                                            $logoSrc = '../' . ltrim($normalized, '/');
                                        }
                                    }
                                }
                            }
                        }
                    ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card h-100">
                            <div class="card-body d-flex gap-3 align-items-start">
                                <div style="width:110px; flex:0 0 110px;">
                                    <img src="<?php echo $logoSrc; ?>" alt="<?php echo htmlspecialchars($p['company_name']); ?>" class="img-fluid" style="max-height:90px; object-fit:contain;" loading="lazy" decoding="async">
                                </div>
                                <div>
                                    <h5 class="card-title mb-1"><?php echo htmlspecialchars($p['company_name']); ?></h5>
                                    <p class="text-muted small mb-2"><?php echo htmlspecialchars(substr($p['description'] ?? '', 0, 180)); ?><?php echo (strlen($p['description'] ?? '') > 180) ? '...' : ''; ?></p>
                                    <p class="mb-0"><a href="<?php echo htmlspecialchars($p['website_url'] ?? '#'); ?>" target="_blank" rel="noopener noreferrer">Visit website</a></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="text-center mt-5">
                <a class="btn btn-primary" href="partner_request.php">Partner with Us</a>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
