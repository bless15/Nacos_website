<?php
/**
 * Admin: View partner interest requests
 */
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdminRole();

$db = getDB();

$requests = $db->fetchAll("SELECT * FROM partner_requests ORDER BY created_at DESC");

$flash = getFlashMessage();

// Support simple search and CSV export
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if (!empty($search)) {
    $requests = array_filter($requests, function($r) use ($search) {
        $needle = mb_strtolower($search);
        return (mb_stripos($r['company_name'] ?? '', $needle) !== false) || (mb_stripos($r['contact_person'] ?? '', $needle) !== false) || (mb_stripos($r['contact_email'] ?? '', $needle) !== false);
    });
}

if (isset($_GET['export']) && $_GET['export'] == '1') {
    // export visible (filtered) requests to CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="partner_requests.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Company','Contact','Email','Website','Message','Submitted']);
    foreach ($requests as $r) {
        fputcsv($out, [
            $r['request_id'] ?? '',
            $r['company_name'] ?? '',
            $r['contact_person'] ?? '',
            $r['contact_email'] ?? '',
            $r['website_url'] ?? '',
            $r['message'] ?? '',
            $r['created_at'] ?? $r['submitted_at'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}

// Handle delete request (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('partner_requests.php', 'Invalid request.', 'error');
    }
    $rid = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($rid > 0) {
        try {
            $db->query("DELETE FROM partner_requests WHERE request_id = :id", [':id' => $rid]);
            redirectWithMessage('partner_requests.php', 'Request deleted.', 'success');
        } catch (Exception $e) {
            redirectWithMessage('partner_requests.php', 'Could not delete request.', 'error');
        }
    }
}

// Handle update request (edit request data)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('partner_requests.php', 'Invalid request.', 'error');
    }
    $rid = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($rid > 0) {
        $company = sanitizeInput($_POST['company'] ?? '');
        $contact = sanitizeInput($_POST['contact'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $website = sanitizeInput($_POST['website'] ?? '');
        $message = sanitizeInput($_POST['message'] ?? '');
        $preferred = sanitizeInput($_POST['preferred_type'] ?? '');
        try {
            $db->query("UPDATE partner_requests SET company_name = :company, contact_person = :contact, contact_email = :email, website_url = :website, message = :message, preferred_type = :preferred WHERE request_id = :id", [
                ':company' => $company, ':contact' => $contact, ':email' => $email, ':website' => $website, ':message' => $message, ':preferred' => $preferred, ':id' => $rid
            ]);
            redirectWithMessage('partner_requests.php', 'Request updated.', 'success');
        } catch (Exception $e) {
            redirectWithMessage('partner_requests.php', 'Could not update request.', 'error');
        }
    }
}

$csrf_token = generateCSRFToken();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Partner Requests - Admin</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>

:root {
            --primary-color: #0F6B3E;
            --secondary-color: #1B8A56;
            --sidebar-bg: #2c3e50;
            --sidebar-hover: #34495e;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
        }
        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 260px;
            background: var(--sidebar-bg);
            color: white;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 1000;
        }
        .sidebar-header {
            padding: 20px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            text-align: center;
        }
        .sidebar-header h4 {
            margin: 10px 0 5px;
            font-size: 20px;
            font-weight: 600;
        }
        .sidebar-header small {
            opacity: 0.9;
        }
        .sidebar-menu {
            padding: 20px 0;
        }
        .sidebar-menu a {
            display: block;
            padding: 12px 20px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s;
        }
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: var(--sidebar-hover);
            color: white;
            padding-left: 30px;
        }
        .sidebar-menu a i {
            width: 25px;
            margin-right: 10px;
        }

        .menu-toggle {
            display: none;
            border: none;
            background: transparent;
            color: var(--primary-color);
            font-size: 22px;
            padding: 6px 10px;
            border-radius: 8px;
        }

        .menu-toggle:focus {
            outline: 2px solid rgba(15, 107, 62, 0.35);
            outline-offset: 2px;
        }

        .sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.25s ease;
            z-index: 999;
        }

        .sidebar-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .top-bar-title {
            display: flex;
            align-items: center;
            margin: 0;
            gap: 8px;
        }
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 20px;
            min-height: 100vh;
        }



        /* Small screens: return actions to flow beneath header content */
        @media (max-width:700px) {
            .admin-actions { position: static; transform: none; margin-left: 0; justify-content: flex-end; width: 100%; padding-top:6px; }
            .admin-header-inner { flex-direction: column; align-items: stretch; gap:8px; }
            .admin-nav { margin-right: 0; overflow-x: auto; }
        }
        /* Modern accordion card style */
        .accordion {
            --bs-accordion-bg: transparent;
        }
        .accordion-item {
            border: none;
            margin-bottom: 18px;
            border-radius: 16px;
            box-shadow: 0 6px 24px rgba(44,62,80,0.07);
            background: #fff;
            overflow: hidden;
            transition: box-shadow 0.18s;
        }
        .accordion-item:last-child { margin-bottom: 0; }
        .accordion-item.active, .accordion-item:focus-within, .accordion-item:hover {
            box-shadow: 0 12px 32px rgba(44,62,80,0.13);
        }
        .accordion-button {
            background: linear-gradient(90deg, #0F6B3E 0%, #1B8A56 100%);
            color: #fff;
            font-weight: 600;
            font-size: 1.1rem;
            border: none;
            border-radius: 0;
            box-shadow: none;
            padding: 1.1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.2rem;
        }
        .accordion-button.collapsed {
            background: #fff;
            color: #232946;
            box-shadow: none;
        }
        .accordion-header .meta {
            color: #a0a7c7;
        }
        .accordion-button.collapsed .meta {
            color: #7a7e9e;
        }
        .accordion-button:focus {
            box-shadow: 0 0 0 2px #0F6B3E;
        }
        .accordion-body {
            background: #fff;
            padding: 1.5rem 2rem 1.5rem 2rem;
        }
        .accordion-header .meta {
            font-size: 0.97rem;
            color: #e0e7ff;
            margin-left: 1.5rem;
        }
        .accordion-header .btn,
        .accordion-header a.btn {
            font-size: 0.95rem;
            padding: 0.25rem 0.7rem;
        }
        .accordion-header .btn-outline-secondary {
            border-color: #fff3;
            color: #fff;
        }
        .accordion-header .btn-outline-secondary:hover {
            background: #fff2;
            color: #fff;
        }
        .accordion-header .btn-outline-primary {
            border-color: #fff3;
            color: #fff;
        }
        .accordion-header .btn-outline-primary:hover {
            background: #fff2;
            color: #fff;
        }
        .accordion-header .btn-outline-danger {
            border-color: #fff3;
            color: #fff;
        }
        .accordion-header .btn-outline-danger:hover {
            background: #fff2;
            color: #fff;
        }
        @media (max-width: 767px) {
            .accordion-body { padding: 1rem 0.7rem; }
        }

        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .menu-toggle {
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            .main-content {
                margin-left: 0;
                padding: 15px;
                width: 100%;
                max-width: 100%;
            }

            .top-bar .d-flex {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 10px;
            }

            .top-bar .btn {
                width: 100%;
            }

            .page-hero .d-flex {
                flex-direction: column;
                gap: 12px;
            }

            .page-hero .d-flex .d-flex {
                width: 100%;
                flex-direction: column;
                align-items: stretch !important;
            }

            .page-hero form.d-flex {
                width: 100%;
            }

            .page-hero form.d-flex input,
            .page-hero form.d-flex button,
            .page-hero .btn-outline-primary {
                width: 100%;
            }

            .partner-search-input {
                min-width: 0 !important;
            }
        }
        .page-hero { padding: 28px 0; }
        .page-hero h2 { font-size: 32px; margin:0 0 6px; }
        .page-hero .lead { margin:0; color:#6c757d; }

        /* Hero wrapper with subtle gradient and padding */
        .hero-wrapper { background: linear-gradient(180deg, rgba(15,107,62,0.08) 0%, rgba(27,138,86,0.04) 60%); border-radius: 12px; padding: 28px; margin-bottom: 20px; }
        .page-hero h2 { color: #0F6B3E; font-weight: 700; }

        /* Card tweaks and float animation */
        .card-custom { padding: 20px; border-radius: 14px; }
        .card-custom { animation: floatIn 420ms ease both; }
        @keyframes floatIn { from { opacity:0; transform: translateY(8px); } to { opacity:1; transform: translateY(0); } }

        /* Accordion polish */
        .accordion-item { position: relative; transition: transform 0.18s, box-shadow 0.18s; }
        .accordion-item:hover { transform: translateY(-4px); }
        .accordion-item::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 6px; background: transparent; border-top-left-radius: 12px; border-bottom-left-radius: 12px; transition: background 0.18s; }
        .accordion-item.is-open::before { background: linear-gradient(180deg,var(--primary-color),var(--secondary-color)); }
        .accordion-button { border-radius: 10px; padding: 20px 22px; }
        .accordion-button::after { transition: transform 0.22s ease; }
        .accordion-button:not(.collapsed) { box-shadow: 0 10px 28px rgba(15,107,62,0.12); }
        .accordion-button:not(.collapsed)::after { transform: rotate(180deg); }
        .accordion-header .meta { color: #6b6f92; }

        /* Buttons */
        .btn-primary-custom {
            background: linear-gradient(90deg,#0F6B3E,#1B8A56);
            border: none;
            color: #fff;
            box-shadow: 0 6px 18px rgba(15,107,62,0.18);
        }
        .btn-primary-custom:hover { filter: brightness(0.95); }

        /* Card / table container */
        .card-custom { border-radius: 12px; box-shadow: 0 8px 30px rgba(35,41,70,0.06); overflow: hidden; }
        .table-custom thead th { background: #f8f9fb; border-bottom: 1px solid #eef1f8; font-weight:700; }
        .table-custom tbody tr { transition: background 0.18s, transform 0.12s; }
        .table-custom tbody tr:hover { background: #fbfbff; transform: translateY(-2px); }
        .table-custom td, .table-custom th { vertical-align: middle; }

        /* Small screens: switch to card list */
        @media (max-width: 767px) {
            .table-responsive { display: none; }
            .requests-list { display:block; }
            .request-card { margin-bottom: 14px; border-radius: 10px; box-shadow: 0 6px 18px rgba(35,41,70,0.04); padding: 12px; background: #fff; }
        }
        @media (min-width: 768px) {
            .requests-list { display:none; }
        }
        /* Action buttons layout for request rows */
        .request-actions { display: flex; gap: 0.5rem; justify-content: flex-end; flex-wrap: wrap; }
        .request-actions .btn { margin: 0; }
        @media (max-width: 700px) {
            .request-actions { flex-direction: column; align-items: stretch; justify-content: flex-start; }
            .request-actions .btn { width: 100%; text-align: center; }
            .request-actions .btn + .btn { margin-top: 6px; }
        }
        /* Ensure Bootstrap modals appear above custom UI and receive pointer events.
           Use very large z-index and force fixed positioning to avoid stacking-context issues. */
        .modal {
            position: fixed !important;
            z-index: 999999 !important;
            pointer-events: auto !important;
        }
        .modal.show { opacity: 1 !important; }
        .modal .modal-dialog { z-index: 1000000 !important; pointer-events: auto !important; }
        .modal-backdrop {
            position: fixed !important;
            z-index: 999998 !important;
            pointer-events: auto !important;
        }
    </style>
</head>
<body>
    
    <!-- Clean Admin Header -->
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
            <div class="main-content">
            <div class="top-bar">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="top-bar-title">
                    <button type="button" class="menu-toggle" id="menuToggle" aria-label="Toggle navigation menu">
                        <i class="fas fa-bars"></i>
                    </button>
                    <span><i class="fas fa-calendar-plus me-2"></i> Add New Partners</span>
                </h3>
                <a href="partners.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back
                </a>
            </div>
        </div>
    <div class="container-fluid px-4 py-4">
                <div class="page-hero mb-4">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h2 class="text-gradient">Partner Requests</h2>
                            <p class="lead">Submissions from the public partner interest form</p>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <form class="d-flex" method="get" action="partner_requests.php">
                                <input name="search" value="<?php echo htmlspecialchars($search); ?>" class="form-control form-control-custom me-2 partner-search-input" placeholder="Search company, contact, email" style="min-width:260px;">
                                <button class="btn btn-primary-custom me-2" type="submit"><i class="fas fa-search me-1"></i> Search</button>
                            </form>
                            <a href="partner_requests.php?export=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-outline-primary"> <i class="fas fa-file-csv me-1"></i> Export CSV</a>
                        </div>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                        <?php echo $flash['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (empty($requests)): ?>
                    <div class="alert alert-info">No partner requests have been submitted yet.</div>
                <?php else: ?>
                    <div class="card card-custom">
                        <div class="card-body">
                            <!-- Accordion view for requests -->
                            <div class="accordion" id="requestsAccordion">
                                <?php foreach ($requests as $r): 
                                    $id = 'req' . htmlspecialchars($r['request_id']);
                                ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading-<?php echo $id; ?>">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $id; ?>" aria-expanded="false" aria-controls="collapse-<?php echo $id; ?>">
                                            <span><strong><?php echo htmlspecialchars($r['company_name'] ?? '-'); ?></strong></span>
                                            <span class="meta d-none d-md-inline ms-auto">
                                                <?php echo htmlspecialchars($r['contact_person'] ?? '-'); ?> ·
                                                <a href="mailto:<?php echo htmlspecialchars($r['contact_email'] ?? ''); ?>" class="text-light text-decoration-underline"><?php echo htmlspecialchars($r['contact_email'] ?? '-'); ?></a>
                                            </span>
                                            <?php if (!empty($r['website_url'])): ?>
                                                <a href="<?php echo htmlspecialchars($r['website_url']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary ms-3">Visit</a>
                                            <?php endif; ?>
                                            <span class="meta ms-3 d-none d-md-inline"><i class="far fa-clock me-1"></i><?php echo htmlspecialchars($r['created_at'] ?? $r['submitted_at'] ?? ''); ?></span>
                                        </button>
                                    </h2>
                                    <div id="collapse-<?php echo $id; ?>" class="accordion-collapse collapse" aria-labelledby="heading-<?php echo $id; ?>" data-bs-parent="#requestsAccordion">
                                        <div class="accordion-body">
                                            <div class="row">
                                                <div class="col-md-9">
                                                    <h6 class="fw-bold mb-2">Message</h6>
                                                    <p><?php echo nl2br(htmlspecialchars($r['message'] ?? '-')); ?></p>
                                                    <dl class="row mt-3">
                                                        <dt class="col-sm-3">Company</dt>
                                                        <dd class="col-sm-9"><?php echo htmlspecialchars($r['company_name'] ?? '-'); ?></dd>
                                                        <dt class="col-sm-3">Contact</dt>
                                                        <dd class="col-sm-9"><?php echo htmlspecialchars($r['contact_person'] ?? '-'); ?></dd>
                                                        <dt class="col-sm-3">Email</dt>
                                                        <dd class="col-sm-9"><a href="mailto:<?php echo htmlspecialchars($r['contact_email'] ?? ''); ?>"><?php echo htmlspecialchars($r['contact_email'] ?? '-'); ?></a></dd>
                                                        <dt class="col-sm-3">Website</dt>
                                                        <dd class="col-sm-9"><?php echo $r['website_url'] ? '<a href="' . htmlspecialchars($r['website_url']) . '" target="_blank" rel="noopener">' . htmlspecialchars($r['website_url']) . '</a>' : '-'; ?></dd>
                                                    </dl>
                                                </div>
                                                <div class="col-md-3 d-flex flex-column justify-content-between align-items-end">
                                                    <div class="w-100 request-actions">
                                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewRequestModal" data-company="<?php echo htmlspecialchars($r['company_name']); ?>" data-contact="<?php echo htmlspecialchars($r['contact_person']); ?>" data-email="<?php echo htmlspecialchars($r['contact_email']); ?>" data-website="<?php echo htmlspecialchars($r['website_url']); ?>" data-message="<?php echo htmlspecialchars($r['message']); ?>" data-submitted="<?php echo htmlspecialchars($r['created_at'] ?? $r['submitted_at'] ?? ''); ?>"><i class="fas fa-eye"></i> View</button>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editRequestModal" data-id="<?php echo $r['request_id']; ?>" data-company="<?php echo htmlspecialchars($r['company_name']); ?>" data-contact="<?php echo htmlspecialchars($r['contact_person']); ?>" data-email="<?php echo htmlspecialchars($r['contact_email']); ?>" data-website="<?php echo htmlspecialchars($r['website_url']); ?>" data-message="<?php echo htmlspecialchars($r['message']); ?>" data-preferred="<?php echo htmlspecialchars($r['preferred_type'] ?? ''); ?>"><i class="fas fa-edit"></i> Edit</button>
                                                        <a href="add_partner.php?request_id=<?php echo $r['request_id']; ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-check"></i> Approve</a>
                                                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete" data-id="<?php echo $r['request_id']; ?>" data-company="<?php echo htmlspecialchars($r['company_name']); ?>"><i class="fas fa-trash"></i> Delete</button>
                                                    </div>
                                                    <div class="w-100 text-end mt-2 small text-muted">Submitted: <?php echo htmlspecialchars($r['created_at'] ?? $r['submitted_at'] ?? ''); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

            </div>
            <!-- end main-content -->

<!-- View Request Modal -->
<div class="modal fade" id="viewRequestModal" tabindex="-1" aria-labelledby="viewRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewRequestModalLabel">Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="row">
                    <dt class="col-sm-3">Company</dt>
                    <dd class="col-sm-9" id="modal-company"></dd>

                    <dt class="col-sm-3">Contact</dt>
                    <dd class="col-sm-9" id="modal-contact"></dd>

                    <dt class="col-sm-3">Email</dt>
                    <dd class="col-sm-9" id="modal-email"></dd>

                    <dt class="col-sm-3">Website</dt>
                    <dd class="col-sm-9" id="modal-website"></dd>

                    <dt class="col-sm-3">Submitted</dt>
                    <dd class="col-sm-9" id="modal-submitted"></dd>

                    <dt class="col-sm-3">Message</dt>
                    <dd class="col-sm-9" id="modal-message"></dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Request Modal -->
<div class="modal fade" id="editRequestModal" tabindex="-1" aria-labelledby="editRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="partner_requests.php">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit-request-id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="editRequestModalLabel">Edit Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Company</label>
                        <input type="text" name="company" id="edit-company" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact</label>
                        <input type="text" name="contact" id="edit-contact" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="edit-email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Website</label>
                        <input type="url" name="website" id="edit-website" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Preferred Type</label>
                        <select name="preferred_type" id="edit-preferred" class="form-select">
                            <option value="">(no preference)</option>
                            <option value="sponsor">Sponsor</option>
                            <option value="collaborator">Collaborator</option>
                            <option value="affiliate">Affiliate</option>
                            <option value="industry">Industry</option>
                            <option value="academic">Academic</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea name="message" id="edit-message" class="form-control" rows="4"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
        var menuToggle = document.getElementById('menuToggle');
        var sidebar = document.querySelector('.sidebar');
        var sidebarOverlay = document.getElementById('sidebarBackdrop') || document.getElementById('sidebarOverlay');

        function closeSidebar() {
            if (!sidebar || !sidebarOverlay) return;
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        }

        if (menuToggle && sidebar && sidebarOverlay) {
            menuToggle.addEventListener('click', function () {
                sidebar.classList.toggle('show');
                sidebarOverlay.classList.toggle('show');
            });

            sidebarOverlay.addEventListener('click', closeSidebar);

            window.addEventListener('resize', function () {
                if (window.innerWidth > 991.98) {
                    closeSidebar();
                }
            });
        }

        var viewModal = document.getElementById('viewRequestModal');
        if(viewModal){
                viewModal.addEventListener('show.bs.modal', function (event) {
                        var button = event.relatedTarget;
                        var company = button.getAttribute('data-company') || '';
                        var contact = button.getAttribute('data-contact') || '';
                        var email = button.getAttribute('data-email') || '';
                        var website = button.getAttribute('data-website') || '';
                        var message = button.getAttribute('data-message') || '';
                        var submitted = button.getAttribute('data-submitted') || '';

                        viewModal.querySelector('#modal-company').textContent = company;
                        viewModal.querySelector('#modal-contact').textContent = contact;
                        viewModal.querySelector('#modal-email').innerHTML = email ? '<a href="mailto:'+email+'">'+email+'</a>' : '';
                        viewModal.querySelector('#modal-website').innerHTML = website ? '<a href="'+website+'" target="_blank" rel="noopener">Visit</a>' : '-';
                        viewModal.querySelector('#modal-submitted').textContent = submitted;
                        viewModal.querySelector('#modal-message').textContent = message;
                });
        }
                // Enable Bootstrap tooltips for message previews
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
                tooltipTriggerList.forEach(function (el) {
                    try { new bootstrap.Tooltip(el); } catch(e) { /* bootstrap not loaded yet */ }
                });
                        // Toggle open marker on accordion items for visual accent
                        try {
                            document.querySelectorAll('#requestsAccordion .accordion-collapse').forEach(function(c){
                                c.addEventListener('show.bs.collapse', function(){
                                    var item = c.closest('.accordion-item'); if(item) item.classList.add('is-open');
                                });
                                c.addEventListener('hide.bs.collapse', function(){
                                    var item = c.closest('.accordion-item'); if(item) item.classList.remove('is-open');
                                });
                            });
                        } catch(e){ /* ignore if bootstrap not ready */ }
                // Populate edit modal
                var editModal = document.getElementById('editRequestModal');
                if (editModal) {
                    editModal.addEventListener('show.bs.modal', function (event) {
                        var button = event.relatedTarget;
                        var id = button.getAttribute('data-id') || '';
                        var company = button.getAttribute('data-company') || '';
                        var contact = button.getAttribute('data-contact') || '';
                        var email = button.getAttribute('data-email') || '';
                        var website = button.getAttribute('data-website') || '';
                        var message = button.getAttribute('data-message') || '';
                        var preferred = button.getAttribute('data-preferred') || '';

                        document.getElementById('edit-request-id').value = id;
                        document.getElementById('edit-company').value = company;
                        document.getElementById('edit-contact').value = contact;
                        document.getElementById('edit-email').value = email;
                        document.getElementById('edit-website').value = website;
                        document.getElementById('edit-message').value = message;
                        document.getElementById('edit-preferred').value = preferred;
                    });
                }
});
</script>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
