<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$db = getDB();
$flash = getFlashMessage();

$selected_level = sanitizeInput($_GET['level'] ?? 'all');
$search = trim(sanitizeInput($_GET['q'] ?? ''));

$params = [];
$where = [];

if (in_array($selected_level, ['100', '200', '300', '400'], true)) {
    $where[] = 'level = :level';
    $params[':level'] = $selected_level;
}

if ($search !== '') {
    $where[] = 'course_title LIKE :search';
    $params[':search'] = '%' . $search . '%';
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$questions = $db->fetchAll(
    "SELECT question_id, level, course_title, file_name, file_type, file_size, created_at
     FROM past_questions
     $where_sql
     ORDER BY CAST(level AS UNSIGNED) ASC, course_title ASC, created_at DESC",
    $params
);

$grouped = ['100' => [], '200' => [], '300' => [], '400' => []];
foreach ($questions as $item) {
    $grouped[$item['level']][] = $item;
}

function formatBytes($bytes) {
    $bytes = (int)$bytes;
    if ($bytes <= 0) return 'N/A';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>NACOS — Past Questions</title>
    <link rel="icon" href="../assets/images/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 90px;
        }
        .page-shell {
            max-width: 1100px;
            margin: 0 auto;
            padding: 20px 14px 40px;
        }
        .header-card,
        .content-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
            padding: 16px;
        }
        .level-title {
            color: #0F6B3E;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .question-item {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            background: #fff;
        }
        .accordion-button:not(.collapsed) {
            color: #0F6B3E;
            background-color: #f7fbf8;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/public_navbar.php'; ?>

<div class="page-shell">
    <div class="header-card mb-3">
        <h1 class="h4 mb-1" style="color:#0F6B3E;">Past Questions</h1>
        <p class="text-muted mb-0">Download past questions by level (100L to 400L).</p>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
    <?php endif; ?>

    <div class="content-card mb-3">
        <form method="GET" class="d-flex flex-wrap gap-2">
            <select name="level" class="form-select" style="max-width: 180px;">
                <option value="all" <?php echo $selected_level === 'all' ? 'selected' : ''; ?>>All Levels</option>
                <option value="100" <?php echo $selected_level === '100' ? 'selected' : ''; ?>>100 Level</option>
                <option value="200" <?php echo $selected_level === '200' ? 'selected' : ''; ?>>200 Level</option>
                <option value="300" <?php echo $selected_level === '300' ? 'selected' : ''; ?>>300 Level</option>
                <option value="400" <?php echo $selected_level === '400' ? 'selected' : ''; ?>>400 Level</option>
            </select>
            <input type="text" class="form-control" style="max-width: 280px;" name="q" placeholder="Search course title" value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-success"><i class="fas fa-filter me-1"></i>Filter</button>
        </form>
    </div>

    <div class="content-card">
        <div class="accordion" id="pastQuestionsAccordionPublic">
            <?php foreach (['100', '200', '300', '400'] as $index => $lvl): ?>
                <?php if ($selected_level !== 'all' && $selected_level !== $lvl) { continue; } ?>
                <?php $is_open = ($selected_level === 'all' && $index === 0) || ($selected_level === $lvl); ?>
                <div class="accordion-item mb-2 border rounded">
                    <h2 class="accordion-header" id="headingPublic<?php echo $lvl; ?>">
                        <button class="accordion-button <?php echo $is_open ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePublic<?php echo $lvl; ?>" aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>" aria-controls="collapsePublic<?php echo $lvl; ?>">
                            <?php echo $lvl; ?> Level (<?php echo count($grouped[$lvl]); ?>)
                        </button>
                    </h2>
                    <div id="collapsePublic<?php echo $lvl; ?>" class="accordion-collapse collapse <?php echo $is_open ? 'show' : ''; ?>" aria-labelledby="headingPublic<?php echo $lvl; ?>" data-bs-parent="#pastQuestionsAccordionPublic">
                        <div class="accordion-body">
                            <?php if (empty($grouped[$lvl])): ?>
                                <p class="text-muted mb-0">No past questions available for <?php echo $lvl; ?> level.</p>
                            <?php else: ?>
                                <?php foreach ($grouped[$lvl] as $item): ?>
                                    <div class="question-item">
                                        <div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($item['course_title']); ?></div>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($item['file_name']); ?>
                                                • <?php echo strtoupper(htmlspecialchars($item['file_type'])); ?>
                                                • <?php echo htmlspecialchars(formatBytes($item['file_size'])); ?>
                                            </small>
                                        </div>
                                        <a href="download_past_question.php?id=<?php echo (int)$item['question_id']; ?>" class="btn btn-outline-success btn-sm">
                                            <i class="fas fa-download me-1"></i>Download
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/public_footer.php'; ?>

</body>
</html>
