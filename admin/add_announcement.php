<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/announcements_helper.php';

requireAdminRole();

$db = getDB();
$errors = [];
$data = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token.';
    }

    $title = sanitizeInput($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $external_link = sanitizeInput($_POST['external_link'] ?? '');
    $cta_label = sanitizeInput($_POST['cta_label'] ?? '');
    $audience_level = sanitizeInput($_POST['audience_level'] ?? 'all');
    $audience_status = sanitizeInput($_POST['audience_status'] ?? 'all');
    $status = sanitizeInput($_POST['status'] ?? 'draft');
    $publish_at = sanitizeInput($_POST['publish_at'] ?? '');
    $expires_at = sanitizeInput($_POST['expires_at'] ?? '');
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
    $allow_comments = isset($_POST['allow_comments']) ? 1 : 0;
    $allow_poll = isset($_POST['allow_poll']) ? 1 : 0;

    $poll_question = sanitizeInput($_POST['poll_question'] ?? '');
    $poll_options = array_values(array_filter(array_map('trim', $_POST['poll_options'] ?? []), fn($v) => $v !== ''));

    if ($title === '') {
        $errors[] = 'Title is required.';
    }

    if (!in_array($status, ['draft', 'published'], true)) {
        $errors[] = 'Invalid status.';
    }

    if ($allow_poll && ($poll_question === '' || count($poll_options) < 2)) {
        $errors[] = 'Poll requires a question and at least 2 options.';
    }

    $image_path = null;
    if (!empty($_FILES['announcement_image']['name'])) {
        $file = $_FILES['announcement_image'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Image upload failed.';
        } else {
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!isset($allowed[$mime])) {
                $errors[] = 'Only JPG, PNG, WEBP images are allowed.';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $errors[] = 'Image must be 5MB or smaller.';
            } else {
                $uploadDir = __DIR__ . '/../uploads/announcements/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $newName = 'announcement_' . time() . '_' . uniqid() . '.' . $allowed[$mime];
                $target = $uploadDir . $newName;
                if (!move_uploaded_file($file['tmp_name'], $target)) {
                    $errors[] = 'Failed to move uploaded image.';
                } else {
                    $image_path = 'uploads/announcements/' . $newName;
                }
            }
        }
    }

    if (!$errors) {
        $actor = getAnnouncementActorContext();
        $publishAtValue = $publish_at !== '' ? $publish_at : null;
        $expiresAtValue = $expires_at !== '' ? $expires_at : null;

        try {
            $db->beginTransaction();
            $db->query(
                "INSERT INTO announcements
                (title, body, image_path, external_link, cta_label, audience_level, audience_status, is_pinned, status, publish_at, expires_at, allow_comments, allow_poll, creator_type, created_by_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $title,
                    $body !== '' ? $body : null,
                    $image_path,
                    $external_link !== '' ? $external_link : null,
                    $cta_label !== '' ? $cta_label : null,
                    $audience_level,
                    $audience_status,
                    $is_pinned,
                    $status,
                    $publishAtValue,
                    $expiresAtValue,
                    $allow_comments,
                    $allow_poll,
                    $actor['type'],
                    $actor['id']
                ]
            );

            $announcementId = (int)$db->lastInsertId();

            if ($allow_poll) {
                $db->query(
                    "INSERT INTO announcement_polls (announcement_id, question) VALUES (?, ?)",
                    [$announcementId, $poll_question]
                );
                $pollId = (int)$db->lastInsertId();
                foreach ($poll_options as $idx => $optionText) {
                    $db->query(
                        "INSERT INTO announcement_poll_options (poll_id, option_text, sort_order) VALUES (?, ?, ?)",
                        [$pollId, $optionText, $idx + 1]
                    );
                }
            }

            if ($status === 'published' && ($publishAtValue === null || strtotime($publishAtValue) <= time())) {
                queueAnnouncementNotifications($db, $announcementId, $audience_level, $audience_status);
            }

            logAnnouncementAudit($db, $announcementId, 'created', 'Announcement created via admin panel');
            $db->commit();
            redirectWithMessage('announcements.php', 'Announcement created successfully.', 'success');
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = 'Failed to save announcement. ' . $e->getMessage();
        }
    }
}

$csrf = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Announcement - NACOS Admin</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .panel { background:#fff; border-radius:12px; padding:20px; box-shadow:0 2px 10px rgba(0,0,0,.05); }
        .page-header {
            background: #fff;
            border-radius: 15px;
            padding: 22px 24px;
            box-shadow: 0 4px 15px rgba(0,0,0,.08);
            margin-bottom: 18px;
            position: relative;
            overflow: hidden;
        }
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: #0F6B3E;
        }
        .page-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
            line-height: 1.2;
        }
        .page-header .subtitle {
            margin: 12px 0 0;
            color: #5f6c7b;
            font-size: 18px;
        }
        .menu-toggle {
            display:none;
            align-items:center;
            justify-content:center;
            width:42px;
            height:42px;
            border:2px solid #dee2e6;
            border-radius:10px;
            background:#FFC107;
            color:#0F6B3E;
            flex-shrink: 0;
        }
        .header-back-btn {
            min-width: 170px;
            justify-content: center;
        }
        @media (max-width: 992px) {
            .menu-toggle { display:inline-flex; }
            .main-content { padding:15px; }
            .page-header { padding: 18px; }
            .page-header h1 { font-size: 22px; }
            .page-header .subtitle { font-size: 16px; margin-top: 10px; }
            .page-header-actions { width: 100%; }
            .header-back-btn { width: 100%; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="page-header">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2 mb-md-0">
                        <button class="menu-toggle" id="menuToggle" type="button"><i class="fas fa-bars"></i></button>
                        <h1><i class="fas fa-plus me-2"></i>New Announcement</h1>
                    </div>
                    <p class="subtitle">Create and publish updates for members</p>
                </div>
                <div class="page-header-actions">
                    <a href="announcements.php" class="btn btn-secondary header-back-btn"><i class="fas fa-arrow-left me-2"></i>Back to Announcements</a>
                </div>
            </div>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?php echo htmlspecialchars($err); ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="panel">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Title *</label>
                    <input class="form-control" name="title" required value="<?php echo htmlspecialchars($data['title'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="draft" <?php echo (($data['status'] ?? 'draft') === 'draft') ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?php echo (($data['status'] ?? '') === 'published') ? 'selected' : ''; ?>>Published</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label">Announcement Text</label>
                    <textarea class="form-control" name="body" rows="5" placeholder="Write announcement details..."><?php echo htmlspecialchars($data['body'] ?? ''); ?></textarea>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Image (optional)</label>
                    <input class="form-control" type="file" name="announcement_image" accept="image/png,image/jpeg,image/webp">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Action Link</label>
                    <input class="form-control" name="external_link" placeholder="https://..." value="<?php echo htmlspecialchars($data['external_link'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Action Button Text</label>
                    <input class="form-control" name="cta_label" placeholder="Read More / Register" value="<?php echo htmlspecialchars($data['cta_label'] ?? ''); ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Audience Level</label>
                    <select class="form-select" name="audience_level">
                        <?php foreach (['all','100','200','300','400','500'] as $lv): ?>
                            <option value="<?php echo $lv; ?>" <?php echo (($data['audience_level'] ?? 'all') === $lv) ? 'selected' : ''; ?>><?php echo strtoupper($lv); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Audience Status</label>
                    <select class="form-select" name="audience_status">
                        <?php foreach (['all','active','inactive','alumni','pending'] as $st): ?>
                            <option value="<?php echo $st; ?>" <?php echo (($data['audience_status'] ?? 'all') === $st) ? 'selected' : ''; ?>><?php echo ucfirst($st); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Publish At (optional)</label>
                    <input class="form-control" type="datetime-local" name="publish_at" value="<?php echo htmlspecialchars($data['publish_at'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Expires At (optional)</label>
                    <input class="form-control" type="datetime-local" name="expires_at" value="<?php echo htmlspecialchars($data['expires_at'] ?? ''); ?>">
                </div>

                <div class="col-12">
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="is_pinned" id="isPinned" <?php echo isset($data['is_pinned']) ? 'checked' : ''; ?>><label class="form-check-label" for="isPinned">Pin announcement</label></div>
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="allow_comments" id="allowComments" <?php echo !isset($data['allow_comments']) || isset($data['allow_comments']) ? 'checked' : ''; ?>><label class="form-check-label" for="allowComments">Allow comments</label></div>
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="allow_poll" id="allowPoll" <?php echo isset($data['allow_poll']) ? 'checked' : ''; ?>><label class="form-check-label" for="allowPoll">Add poll</label></div>
                </div>

                <div class="col-12" id="pollBox" style="display:none;">
                    <div class="border rounded p-3">
                        <label class="form-label">Poll Question</label>
                        <input class="form-control mb-2" name="poll_question" value="<?php echo htmlspecialchars($data['poll_question'] ?? ''); ?>">
                        <label class="form-label">Poll Options (minimum 2)</label>
                        <input class="form-control mb-2" name="poll_options[]" placeholder="Option 1">
                        <input class="form-control mb-2" name="poll_options[]" placeholder="Option 2">
                        <input class="form-control mb-2" name="poll_options[]" placeholder="Option 3 (optional)">
                        <input class="form-control" name="poll_options[]" placeholder="Option 4 (optional)">
                    </div>
                </div>

                <div class="col-12 d-flex gap-2 justify-content-end">
                    <a href="announcements.php" class="btn btn-outline-secondary">Cancel</a>
                    <button class="btn btn-primary" type="submit"><i class="fas fa-save me-2"></i>Save Announcement</button>
                </div>
            </div>
        </form>
    </main>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.querySelector('.sidebar');
const backdrop = document.getElementById('sidebarBackdrop');
if (menuToggle && sidebar && backdrop) {
    menuToggle.addEventListener('click', () => { sidebar.classList.toggle('show'); backdrop.classList.toggle('show'); });
    backdrop.addEventListener('click', () => { sidebar.classList.remove('show'); backdrop.classList.remove('show'); });
}
const allowPoll = document.getElementById('allowPoll');
const pollBox = document.getElementById('pollBox');
function syncPollBox(){ if (!allowPoll || !pollBox) return; pollBox.style.display = allowPoll.checked ? 'block' : 'none'; }
if (allowPoll) { allowPoll.addEventListener('change', syncPollBox); syncPollBox(); }
</script>
</body>
</html>
