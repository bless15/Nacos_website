<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/announcements_helper.php';

$db = getDB();
$is_logged_in = isMemberLoggedIn();
$member = $is_logged_in ? getCurrentMember() : null;
$member_id = $member['member_id'] ?? null;

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    redirectWithMessage('announcements.php', 'Invalid announcement ID.', 'error');
}

[$audienceClause, $audienceParams] = announcementAudienceClause($member ?: null);
$announcement = $db->fetchOne(
    "SELECT * FROM announcements
     WHERE announcement_id = ?
       AND status = 'published'
       AND (publish_at IS NULL OR publish_at <= NOW())
       AND (expires_at IS NULL OR expires_at >= NOW())
       AND {$audienceClause}",
    array_merge([$id], $audienceParams)
);

if (!$announcement) {
    redirectWithMessage('announcements.php', 'Announcement unavailable or restricted.', 'error');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$is_logged_in) {
        redirectWithMessage('announcement_details.php?id=' . $id, 'Please log in to interact with announcements.', 'error');
    }

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('announcement_details.php?id=' . $id, 'Invalid request token.', 'error');
    }

    $action = sanitizeInput($_POST['action'] ?? '');

    try {
        if ($action === 'react') {
            $reaction = sanitizeInput($_POST['reaction'] ?? '');
            if (!in_array($reaction, ['like', 'love'], true)) {
                redirectWithMessage('announcement_details.php?id=' . $id, 'Invalid reaction.', 'error');
            }
            $db->query(
                "INSERT INTO announcement_reactions (announcement_id, member_id, reaction)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE reaction = VALUES(reaction), created_at = CURRENT_TIMESTAMP",
                [$id, $member_id, $reaction]
            );
            logAnnouncementAudit($db, $id, 'reaction', 'Reaction: ' . $reaction);
            redirectWithMessage('announcement_details.php?id=' . $id, 'Reaction recorded.', 'success');
        }

        if ($action === 'comment') {
            if ((int)$announcement['allow_comments'] !== 1) {
                redirectWithMessage('announcement_details.php?id=' . $id, 'Comments are disabled for this announcement.', 'error');
            }

            $commentText = trim($_POST['comment_text'] ?? '');
            if ($commentText === '' || mb_strlen($commentText) < 2) {
                redirectWithMessage('announcement_details.php?id=' . $id, 'Comment is too short.', 'error');
            }
            if (mb_strlen($commentText) > 1000) {
                redirectWithMessage('announcement_details.php?id=' . $id, 'Comment must be under 1000 characters.', 'error');
            }

            $rateCheck = $db->fetchOne(
                "SELECT COUNT(*) AS total FROM announcement_comments
                 WHERE announcement_id = ? AND member_id = ? AND created_at >= (NOW() - INTERVAL 30 SECOND)",
                [$id, $member_id]
            );

            if ((int)($rateCheck['total'] ?? 0) > 0) {
                redirectWithMessage('announcement_details.php?id=' . $id, 'Please wait a bit before posting another comment.', 'warning');
            }

            $db->query(
                "INSERT INTO announcement_comments (announcement_id, member_id, comment_text) VALUES (?, ?, ?)",
                [$id, $member_id, $commentText]
            );
            logAnnouncementAudit($db, $id, 'comment', 'Comment added');
            redirectWithMessage('announcement_details.php?id=' . $id, 'Comment posted.', 'success');
        }

        if ($action === 'vote') {
            if ((int)$announcement['allow_poll'] !== 1) {
                redirectWithMessage('announcement_details.php?id=' . $id, 'Poll is not enabled for this announcement.', 'error');
            }

            $poll = $db->fetchOne("SELECT poll_id FROM announcement_polls WHERE announcement_id = ?", [$id]);
            if (!$poll) {
                redirectWithMessage('announcement_details.php?id=' . $id, 'Poll not found.', 'error');
            }

            $pollId = (int)$poll['poll_id'];
            $optionId = (int)($_POST['option_id'] ?? 0);

            $option = $db->fetchOne("SELECT option_id FROM announcement_poll_options WHERE option_id = ? AND poll_id = ?", [$optionId, $pollId]);
            if (!$option) {
                redirectWithMessage('announcement_details.php?id=' . $id, 'Invalid poll option selected.', 'error');
            }

            $alreadyVoted = $db->fetchOne("SELECT vote_id FROM announcement_poll_votes WHERE poll_id = ? AND member_id = ?", [$pollId, $member_id]);
            if ($alreadyVoted) {
                redirectWithMessage('announcement_details.php?id=' . $id, 'You have already voted on this poll.', 'warning');
            }

            $db->query(
                "INSERT INTO announcement_poll_votes (poll_id, option_id, member_id) VALUES (?, ?, ?)",
                [$pollId, $optionId, $member_id]
            );
            logAnnouncementAudit($db, $id, 'vote', 'Poll vote submitted');
            redirectWithMessage('announcement_details.php?id=' . $id, 'Vote submitted successfully.', 'success');
        }

        redirectWithMessage('announcement_details.php?id=' . $id, 'Unsupported action.', 'error');
    } catch (Exception $e) {
        redirectWithMessage('announcement_details.php?id=' . $id, 'Action failed: ' . $e->getMessage(), 'error');
    }
}

$flash = getFlashMessage();
$csrf = generateCSRFToken();

$reactions = $db->fetchAll(
    "SELECT reaction, COUNT(*) AS total FROM announcement_reactions WHERE announcement_id = ? GROUP BY reaction",
    [$id]
);
$reactionMap = ['like' => 0, 'love' => 0];
foreach ($reactions as $row) { $reactionMap[$row['reaction']] = (int)$row['total']; }

$userReaction = null;
if ($is_logged_in) {
    $myReaction = $db->fetchOne("SELECT reaction FROM announcement_reactions WHERE announcement_id = ? AND member_id = ?", [$id, $member_id]);
    $userReaction = $myReaction['reaction'] ?? null;
}

$comments = $db->fetchAll(
    "SELECT c.comment_text, c.created_at, m.full_name
     FROM announcement_comments c
     JOIN members m ON m.member_id = c.member_id
     WHERE c.announcement_id = ? AND c.is_hidden = 0
     ORDER BY c.created_at DESC",
    [$id]
);

$poll = $db->fetchOne("SELECT * FROM announcement_polls WHERE announcement_id = ?", [$id]);
$pollOptions = [];
$userHasVoted = false;
if ($poll) {
    $pollOptions = $db->fetchAll(
        "SELECT o.option_id, o.option_text, COUNT(v.vote_id) AS votes
         FROM announcement_poll_options o
         LEFT JOIN announcement_poll_votes v ON v.option_id = o.option_id
         WHERE o.poll_id = ?
         GROUP BY o.option_id, o.option_text
         ORDER BY o.sort_order ASC, o.option_id ASC",
        [(int)$poll['poll_id']]
    );

    if ($is_logged_in) {
        $voteCheck = $db->fetchOne("SELECT vote_id FROM announcement_poll_votes WHERE poll_id = ? AND member_id = ?", [(int)$poll['poll_id'], $member_id]);
        $userHasVoted = (bool)$voteCheck;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($announcement['title']); ?> - Announcement</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background:#f4f7ff; font-family:'Poppins',sans-serif; }
        .page-wrap { padding-top: 95px; }
        .card-box { background:#fff; border-radius:14px; border:1px solid rgba(15,107,62,.12); box-shadow:0 2px 12px rgba(0,0,0,.05); }
        .hero-img { width:100%; max-height:360px; object-fit:cover; border-radius: 12px 12px 0 0; }
        .comment { border-bottom:1px solid #eee; padding:12px 0; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/public_navbar.php'; ?>

<div class="container page-wrap pb-4">
    <?php if ($flash): ?><div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?>"><?php echo htmlspecialchars($flash['message']); ?></div><?php endif; ?>

    <div class="card-box overflow-hidden mb-3">
        <?php if (!empty($announcement['image_path'])): ?><img class="hero-img" src="../<?php echo htmlspecialchars($announcement['image_path']); ?>" alt="Announcement image"><?php endif; ?>
        <div class="p-3 p-md-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <h3 class="text-success mb-1"><?php echo htmlspecialchars($announcement['title']); ?></h3>
                <?php if ((int)$announcement['is_pinned'] === 1): ?><span class="badge bg-warning text-dark">Pinned</span><?php endif; ?>
            </div>
            <p class="text-muted mb-2"><i class="far fa-clock me-1"></i><?php echo date('M d, Y h:i A', strtotime($announcement['publish_at'] ?? $announcement['created_at'])); ?></p>
            <?php if (!empty($announcement['body'])): ?><div class="mb-3"><?php echo nl2br(htmlspecialchars($announcement['body'])); ?></div><?php endif; ?>

            <div class="d-flex flex-wrap gap-2 mb-2">
                <?php if (!empty($announcement['external_link'])): ?>
                    <a href="<?php echo htmlspecialchars($announcement['external_link']); ?>" target="_blank" rel="noopener" class="btn btn-success">
                        <i class="fas fa-arrow-up-right-from-square me-2"></i><?php echo htmlspecialchars($announcement['cta_label'] ?: 'Open Link'); ?>
                    </a>
                <?php endif; ?>
                <a class="btn btn-outline-secondary" href="announcements.php">Back to Announcements</a>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card-box p-3">
                <h5 class="text-success"><i class="far fa-heart me-2"></i>Reactions</h5>
                <div class="d-flex gap-2 mb-2">
                    <span class="badge bg-light text-dark border">👍 <?php echo (int)$reactionMap['like']; ?></span>
                    <span class="badge bg-light text-dark border">❤️ <?php echo (int)$reactionMap['love']; ?></span>
                </div>
                <?php if ($is_logged_in): ?>
                    <form method="POST" class="d-flex gap-2">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                        <input type="hidden" name="action" value="react">
                        <button class="btn btn-sm <?php echo $userReaction==='like' ? 'btn-success' : 'btn-outline-success'; ?>" name="reaction" value="like">👍 Like</button>
                        <button class="btn btn-sm <?php echo $userReaction==='love' ? 'btn-danger' : 'btn-outline-danger'; ?>" name="reaction" value="love">❤️ Love</button>
                    </form>
                <?php else: ?><small class="text-muted">Log in to react.</small><?php endif; ?>
            </div>

            <?php if ($poll): ?>
                <div class="card-box p-3 mt-3">
                    <h5 class="text-success"><i class="fas fa-chart-bar me-2"></i>Poll</h5>
                    <p class="mb-2"><?php echo htmlspecialchars($poll['question']); ?></p>
                    <?php $totalVotes = array_sum(array_map(fn($x) => (int)$x['votes'], $pollOptions)); ?>
                    <?php foreach ($pollOptions as $opt): ?>
                        <?php $pct = $totalVotes > 0 ? round(((int)$opt['votes'] / $totalVotes) * 100) : 0; ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between"><small><?php echo htmlspecialchars($opt['option_text']); ?></small><small><?php echo (int)$opt['votes']; ?> (<?php echo $pct; ?>%)</small></div>
                            <div class="progress" style="height:8px;"><div class="progress-bar bg-success" style="width:<?php echo $pct; ?>%"></div></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($is_logged_in && !$userHasVoted): ?>
                        <form method="POST" class="mt-2 d-flex gap-2">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                            <input type="hidden" name="action" value="vote">
                            <select class="form-select" name="option_id" required>
                                <option value="">Choose option...</option>
                                <?php foreach ($pollOptions as $opt): ?><option value="<?php echo (int)$opt['option_id']; ?>"><?php echo htmlspecialchars($opt['option_text']); ?></option><?php endforeach; ?>
                            </select>
                            <button class="btn btn-success" type="submit">Vote</button>
                        </form>
                    <?php elseif ($is_logged_in && $userHasVoted): ?><small class="text-success">You already voted on this poll.</small><?php else: ?><small class="text-muted">Log in to vote.</small><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-7">
            <div class="card-box p-3">
                <h5 class="text-success"><i class="far fa-comments me-2"></i>Comments (<?php echo count($comments); ?>)</h5>
                <?php if ((int)$announcement['allow_comments'] === 1): ?>
                    <?php if ($is_logged_in): ?>
                        <form method="POST" class="mb-3">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                            <input type="hidden" name="action" value="comment">
                            <textarea name="comment_text" class="form-control mb-2" rows="3" placeholder="Share your opinion..." maxlength="1000" required></textarea>
                            <button class="btn btn-success btn-sm" type="submit">Post Comment</button>
                        </form>
                    <?php else: ?><div class="alert alert-light border">Log in to comment.</div><?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-light border">Comments are disabled for this announcement.</div>
                <?php endif; ?>

                <?php if (!$comments): ?><p class="text-muted mb-0">No comments yet.</p><?php endif; ?>
                <?php foreach ($comments as $comment): ?>
                    <div class="comment">
                        <div class="d-flex justify-content-between"><strong><?php echo htmlspecialchars($comment['full_name']); ?></strong><small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($comment['created_at'])); ?></small></div>
                        <div class="text-muted"><?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
