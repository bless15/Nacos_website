<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/announcements_helper.php';

requireAdminRole();
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    redirectWithMessage('announcements.php', 'Invalid announcement ID.', 'error');
}

$announcement = $db->fetchOne("SELECT * FROM announcements WHERE announcement_id = ?", [$id]);
if (!$announcement) {
    redirectWithMessage('announcements.php', 'Announcement not found.', 'error');
}

$poll = $db->fetchOne("SELECT * FROM announcement_polls WHERE announcement_id = ?", [$id]);
$pollOptions = $poll ? $db->fetchAll("SELECT * FROM announcement_poll_options WHERE poll_id = ? ORDER BY sort_order ASC", [(int)$poll['poll_id']]) : [];

$errors = [];
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

    if ($title === '') $errors[] = 'Title is required.';
    if ($allow_poll && ($poll_question === '' || count($poll_options) < 2)) {
        $errors[] = 'Poll requires a question and at least 2 options.';
    }

    $image_path = $announcement['image_path'];
    if (!empty($_FILES['announcement_image']['name'])) {
        $file = $_FILES['announcement_image'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (isset($allowed[$mime]) && $file['size'] <= 5 * 1024 * 1024) {
                $uploadDir = __DIR__ . '/../uploads/announcements/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $newName = 'announcement_' . time() . '_' . uniqid() . '.' . $allowed[$mime];
                $target = $uploadDir . $newName;
                if (move_uploaded_file($file['tmp_name'], $target)) {
                    $image_path = 'uploads/announcements/' . $newName;
                }
            } else {
                $errors[] = 'Invalid image upload. Use JPG/PNG/WEBP up to 5MB.';
            }
        }
    }

    if (!$errors) {
        $publishAtValue = $publish_at !== '' ? $publish_at : null;
        $expiresAtValue = $expires_at !== '' ? $expires_at : null;
        $wasPublished = $announcement['status'] === 'published';

        try {
            $db->beginTransaction();
            $db->query(
                "UPDATE announcements SET
                title=?, body=?, image_path=?, external_link=?, cta_label=?, audience_level=?, audience_status=?,
                is_pinned=?, status=?, publish_at=?, expires_at=?, allow_comments=?, allow_poll=?
                WHERE announcement_id=?",
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
                    $id
                ]
            );

            if ($allow_poll) {
                if (!$poll) {
                    $db->query("INSERT INTO announcement_polls (announcement_id, question) VALUES (?, ?)", [$id, $poll_question]);
                    $pollId = (int)$db->lastInsertId();
                } else {
                    $pollId = (int)$poll['poll_id'];
                    $db->query("UPDATE announcement_polls SET question=? WHERE poll_id=?", [$poll_question, $pollId]);
                    $db->query("DELETE FROM announcement_poll_options WHERE poll_id=?", [$pollId]);
                    $db->query("DELETE FROM announcement_poll_votes WHERE poll_id=?", [$pollId]);
                }
                foreach ($poll_options as $idx => $optionText) {
                    $db->query("INSERT INTO announcement_poll_options (poll_id, option_text, sort_order) VALUES (?, ?, ?)", [$pollId, $optionText, $idx + 1]);
                }
            } else {
                if ($poll) {
                    $db->query("DELETE FROM announcement_polls WHERE poll_id=?", [(int)$poll['poll_id']]);
                }
            }

            if ($status === 'published' && (!$wasPublished || ($publishAtValue === null || strtotime($publishAtValue) <= time()))) {
                queueAnnouncementNotifications($db, $id, $audience_level, $audience_status);
            }

            logAnnouncementAudit($db, $id, 'updated', 'Announcement updated');
            $db->commit();
            redirectWithMessage('announcements.php', 'Announcement updated successfully.', 'success');
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = 'Failed to update announcement. ' . $e->getMessage();
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
    <title>Edit Announcement - NACOS Admin</title>
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
        @media(max-width:992px){
            .menu-toggle{display:inline-flex;}
            .main-content{padding:15px;}
            .page-header{padding:18px;}
            .page-header h1{font-size:22px;}
            .page-header .subtitle{font-size:16px;margin-top:10px;}
            .page-header-actions{width:100%;}
            .header-back-btn{width:100%;}
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
                        <h1><i class="fas fa-pen me-2"></i>Edit Announcement</h1>
                    </div>
                    <p class="subtitle">Update announcement details and publish settings</p>
                </div>
                <div class="page-header-actions">
                    <a href="announcements.php" class="btn btn-secondary header-back-btn"><i class="fas fa-arrow-left me-2"></i>Back to Announcements</a>
                </div>
            </div>
        </div>

        <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?php echo htmlspecialchars($err); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="panel">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
            <div class="row g-3">
                <div class="col-md-8"><label class="form-label">Title *</label><input class="form-control" name="title" required value="<?php echo htmlspecialchars($_POST['title'] ?? $announcement['title']); ?>"></div>
                <div class="col-md-4"><label class="form-label">Status</label><select class="form-select" name="status"><option value="draft" <?php echo (($_POST['status'] ?? $announcement['status']) === 'draft') ? 'selected' : ''; ?>>Draft</option><option value="published" <?php echo (($_POST['status'] ?? $announcement['status']) === 'published') ? 'selected' : ''; ?>>Published</option></select></div>
                <div class="col-12"><label class="form-label">Announcement Text</label><textarea class="form-control" name="body" rows="5"><?php echo htmlspecialchars($_POST['body'] ?? $announcement['body']); ?></textarea></div>
                <div class="col-md-4"><label class="form-label">Replace Image (optional)</label><input class="form-control" type="file" name="announcement_image" accept="image/png,image/jpeg,image/webp"></div>
                <div class="col-md-4"><label class="form-label">Action Link</label><input class="form-control" name="external_link" value="<?php echo htmlspecialchars($_POST['external_link'] ?? ($announcement['external_link'] ?? '')); ?>"></div>
                <div class="col-md-4"><label class="form-label">Action Button Text</label><input class="form-control" name="cta_label" value="<?php echo htmlspecialchars($_POST['cta_label'] ?? ($announcement['cta_label'] ?? '')); ?>"></div>

                <div class="col-md-3"><label class="form-label">Audience Level</label><select class="form-select" name="audience_level"><?php foreach(['all','100','200','300','400','500'] as $lv): ?><option value="<?php echo $lv; ?>" <?php echo (($_POST['audience_level'] ?? $announcement['audience_level'])===$lv)?'selected':''; ?>><?php echo strtoupper($lv); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label">Audience Status</label><select class="form-select" name="audience_status"><?php foreach(['all','active','inactive','alumni','pending'] as $st): ?><option value="<?php echo $st; ?>" <?php echo (($_POST['audience_status'] ?? $announcement['audience_status'])===$st)?'selected':''; ?>><?php echo ucfirst($st); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label">Publish At</label><input class="form-control" type="datetime-local" name="publish_at" value="<?php echo htmlspecialchars($_POST['publish_at'] ?? (!empty($announcement['publish_at']) ? date('Y-m-d\TH:i', strtotime($announcement['publish_at'])) : '')); ?>"></div>
                <div class="col-md-3"><label class="form-label">Expires At</label><input class="form-control" type="datetime-local" name="expires_at" value="<?php echo htmlspecialchars($_POST['expires_at'] ?? (!empty($announcement['expires_at']) ? date('Y-m-d\TH:i', strtotime($announcement['expires_at'])) : '')); ?>"></div>

                <div class="col-12">
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="is_pinned" id="isPinned" <?php echo ((int)($_POST['is_pinned'] ?? $announcement['is_pinned'])) ? 'checked' : ''; ?>><label class="form-check-label" for="isPinned">Pin announcement</label></div>
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="allow_comments" id="allowComments" <?php echo ((int)($_POST['allow_comments'] ?? $announcement['allow_comments'])) ? 'checked' : ''; ?>><label class="form-check-label" for="allowComments">Allow comments</label></div>
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="allow_poll" id="allowPoll" <?php echo ((int)($_POST['allow_poll'] ?? $announcement['allow_poll'])) ? 'checked' : ''; ?>><label class="form-check-label" for="allowPoll">Enable poll</label></div>
                </div>

                <div class="col-12" id="pollBox" style="display:none;">
                    <div class="border rounded p-3">
                        <label class="form-label">Poll Question</label>
                        <input class="form-control mb-2" name="poll_question" value="<?php echo htmlspecialchars($_POST['poll_question'] ?? ($poll['question'] ?? '')); ?>">
                        <label class="form-label">Poll Options (minimum 2)</label>
                        <?php
                        $existingOptions = $_POST['poll_options'] ?? array_map(fn($o) => $o['option_text'], $pollOptions);
                        $existingOptions = array_pad($existingOptions, 4, '');
                        for ($i = 0; $i < 4; $i++):
                        ?>
                            <input class="form-control mb-2" name="poll_options[]" value="<?php echo htmlspecialchars($existingOptions[$i]); ?>" placeholder="Option <?php echo $i + 1; ?>">
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="col-12 d-flex gap-2 justify-content-end"><a href="announcements.php" class="btn btn-outline-secondary">Cancel</a><button class="btn btn-primary" type="submit">Save Changes</button></div>
            </div>
        </form>
    </main>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.querySelector('.sidebar');
const backdrop = document.getElementById('sidebarBackdrop');
if (menuToggle && sidebar && backdrop) { menuToggle.addEventListener('click', () => { sidebar.classList.toggle('show'); backdrop.classList.toggle('show'); }); backdrop.addEventListener('click', () => { sidebar.classList.remove('show'); backdrop.classList.remove('show'); }); }
const allowPoll = document.getElementById('allowPoll');
const pollBox = document.getElementById('pollBox');
function syncPollBox(){ if (!allowPoll || !pollBox) return; pollBox.style.display = allowPoll.checked ? 'block' : 'none'; }
if (allowPoll) { allowPoll.addEventListener('change', syncPollBox); syncPollBox(); }
</script>
</body>
</html>
