<?php
/**
 * Admin: View partner interest requests
 */
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdminRole();

$db = getDB();

$requests = $db->fetchAll("SELECT * FROM PARTNER_REQUESTS ORDER BY created_at DESC");

$flash = getFlashMessage();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Partner Requests - Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <?php include 'includes/navbar.php'; ?>
            <div class="container-fluid px-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-0">Partner Requests</h1>
                        <p class="text-muted">Submissions from the public partner interest form</p>
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
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Company</th>
                                    <th>Contact</th>
                                    <th>Email</th>
                                    <th>Website</th>
                                    <th>Message</th>
                                    <th>Submitted</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $r): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($r['request_id']); ?></td>
                                        <td><?php echo htmlspecialchars($r['company_name']); ?></td>
                                        <td><?php echo htmlspecialchars($r['contact_person'] ?? ''); ?></td>
                                        <td><a href="mailto:<?php echo htmlspecialchars($r['contact_email'] ?? ''); ?>"><?php echo htmlspecialchars($r['contact_email'] ?? ''); ?></a></td>
                                        <td><?php echo $r['website_url'] ? '<a href="' . htmlspecialchars($r['website_url']) . '" target="_blank" rel="noopener noreferrer">Visit</a>' : '-'; ?></td>
                                        <td style="max-width:320px; white-space:normal;"><?php echo nl2br(htmlspecialchars($r['message'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars($r['created_at'] ?? $r['submitted_at'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
