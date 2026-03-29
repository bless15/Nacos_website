<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/announcements_helper.php';

$db = getDB();
$is_logged_in = isMemberLoggedIn();
$member = $is_logged_in ? getCurrentMember() : null;

[$audienceClause, $audienceParams] = announcementAudienceClause($member ?: null);

$query = "SELECT a.*,
    (SELECT COUNT(*) FROM announcement_comments c WHERE c.announcement_id = a.announcement_id AND c.is_hidden = 0) AS comments_count,
    (SELECT COUNT(*) FROM announcement_reactions r WHERE r.announcement_id = a.announcement_id) AS reactions_count
FROM announcements a
WHERE a.status = 'published'
  AND (a.publish_at IS NULL OR a.publish_at <= NOW())
  AND (a.expires_at IS NULL OR a.expires_at >= NOW())
  AND {$audienceClause}
ORDER BY a.is_pinned DESC, COALESCE(a.publish_at, a.created_at) DESC";

$announcements = $db->fetchAll($query, $audienceParams);
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - NACOS</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background:#f4f7ff; font-family:'Poppins',sans-serif; }
        .page-wrap { padding-top: 95px; }
        .page-header { background:#fff; border-radius:14px; padding:20px; box-shadow:0 2px 12px rgba(0,0,0,.05); margin-bottom:18px; }
        .announcement-card { background:#fff; border-radius:14px; box-shadow:0 2px 12px rgba(0,0,0,.05); overflow:hidden; border:1px solid rgba(15,107,62,.10); }
        .announcement-image { width:100%; max-height:280px; object-fit:cover; }
        .pin-badge { background:#F4B400; color:#0F6B3E; }
        .meta { color:#6c757d; font-size:.92rem; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/public_navbar.php'; ?>

<div class="container page-wrap pb-4">
    <?php if ($flash): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
    <?php endif; ?>

    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h3 class="mb-0 text-success"><i class="fas fa-bullhorn me-2"></i>Announcements</h3>
        <span class="text-muted"><?php echo count($announcements); ?> available</span>
    </div>

    <div class="row g-3">
        <?php if (!$announcements): ?>
            <div class="col-12"><div class="alert alert-info mb-0">No announcements available right now.</div></div>
        <?php endif; ?>

        <?php foreach ($announcements as $a): ?>
            <div class="col-12">
                <div class="announcement-card">
                    <?php if (!empty($a['image_path'])): ?>
                        <img class="announcement-image" src="../<?php echo htmlspecialchars($a['image_path']); ?>" alt="Announcement image">
                    <?php endif; ?>
                    <div class="p-3 p-md-4">
                        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                            <h4 class="mb-1 text-success"><?php echo htmlspecialchars($a['title']); ?></h4>
                            <?php if ((int)$a['is_pinned'] === 1): ?><span class="badge pin-badge">Pinned</span><?php endif; ?>
                        </div>
                        <?php if (!empty($a['body'])): ?>
                            <p class="mb-2 text-muted"><?php echo nl2br(htmlspecialchars(mb_strimwidth($a['body'], 0, 280, '...'))); ?></p>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="meta">
                                <i class="far fa-clock me-1"></i><?php echo date('M d, Y h:i A', strtotime($a['publish_at'] ?? $a['created_at'])); ?>
                                <span class="mx-2">•</span>
                                <i class="far fa-comments me-1"></i><?php echo (int)$a['comments_count']; ?>
                                <span class="mx-2">•</span>
                                <i class="far fa-heart me-1"></i><?php echo (int)$a['reactions_count']; ?>
                            </div>
                            <a href="announcement_details.php?id=<?php echo (int)$a['announcement_id']; ?>" class="btn btn-success btn-sm">Open</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
