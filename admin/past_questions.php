<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdminRole();

$db = getDB();
$flash = getFlashMessage();
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid request. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'upload') {
            $level = sanitizeInput($_POST['level'] ?? '');
            $course_title = trim(sanitizeInput($_POST['course_title'] ?? ''));
            $allowed_levels = ['100', '200', '300', '400'];

            if (!in_array($level, $allowed_levels, true) || $course_title === '') {
                $error_message = 'Level and course title are required.';
            } elseif (empty($_FILES['question_file']['name'])) {
                $error_message = 'Please select a file to upload.';
            } else {
                $file = $_FILES['question_file'];

                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $error_message = 'File upload failed. Please try again.';
                } else {
                    $original_name = $file['name'];
                    $tmp_path = $file['tmp_name'];
                    $file_size = (int)$file['size'];
                    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

                    $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
                    if (!in_array($extension, $allowed_extensions, true)) {
                        $error_message = 'Only PDF or image files (JPG, JPEG, PNG, WEBP) are allowed.';
                    } elseif ($file_size > 15728640) {
                        $error_message = 'Maximum file size is 15MB.';
                    } else {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime_type = $finfo ? finfo_file($finfo, $tmp_path) : '';
                        if ($finfo) {
                            finfo_close($finfo);
                        }

                        $allowed_mimes = [
                            'application/pdf',
                            'image/jpeg',
                            'image/png',
                            'image/webp'
                        ];

                        if (!in_array($mime_type, $allowed_mimes, true)) {
                            $error_message = 'Invalid file content. Please upload a valid PDF or image.';
                        } else {
                            $upload_dir_abs = __DIR__ . '/../uploads/past_questions/';
                            $upload_dir_rel = 'uploads/past_questions/';

                            if (!is_dir($upload_dir_abs)) {
                                mkdir($upload_dir_abs, 0755, true);
                            }

                            $safe_base = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($original_name, PATHINFO_FILENAME));
                            $unique_name = time() . '_' . uniqid() . '_' . $safe_base . '.' . $extension;
                            $target_abs = $upload_dir_abs . $unique_name;
                            $stored_rel = $upload_dir_rel . $unique_name;

                            if (!move_uploaded_file($tmp_path, $target_abs)) {
                                $error_message = 'Failed to save uploaded file.';
                            } else {
                                $file_type = $extension === 'pdf' ? 'pdf' : 'image';
                                $uploader = getCurrentMember();
                                $uploaded_by = $uploader['member_id'] ?? null;

                                $db->query(
                                    "INSERT INTO past_questions (level, course_title, file_path, file_name, file_type, file_size, uploaded_by)
                                     VALUES (:level, :course_title, :file_path, :file_name, :file_type, :file_size, :uploaded_by)",
                                    [
                                        ':level' => $level,
                                        ':course_title' => $course_title,
                                        ':file_path' => $stored_rel,
                                        ':file_name' => $original_name,
                                        ':file_type' => $file_type,
                                        ':file_size' => $file_size,
                                        ':uploaded_by' => $uploaded_by
                                    ]
                                );

                                redirectWithMessage('past_questions.php', 'Past question uploaded successfully.', 'success');
                            }
                        }
                    }
                }
            }
        }

        if ($action === 'delete') {
            $question_id = isset($_POST['question_id']) ? (int)$_POST['question_id'] : 0;
            if ($question_id > 0) {
                $record = $db->fetchOne("SELECT file_path FROM past_questions WHERE question_id = ?", [$question_id]);
                if ($record) {
                    $db->query("DELETE FROM past_questions WHERE question_id = ?", [$question_id]);

                    $file_abs = __DIR__ . '/../' . $record['file_path'];
                    if (is_file($file_abs)) {
                        @unlink($file_abs);
                    }

                    redirectWithMessage('past_questions.php', 'Past question deleted successfully.', 'success');
                }
            }
        }
    }
}

$level_filter = sanitizeInput($_GET['level'] ?? 'all');
$search = trim(sanitizeInput($_GET['q'] ?? ''));

$params = [];
$where = [];

if (in_array($level_filter, ['100', '200', '300', '400'], true)) {
    $where[] = 'level = :level';
    $params[':level'] = $level_filter;
}

if ($search !== '') {
    $where[] = 'course_title LIKE :search';
    $params[':search'] = '%' . $search . '%';
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$questions = $db->fetchAll(
    "SELECT * FROM past_questions $where_sql ORDER BY CAST(level AS UNSIGNED) ASC, course_title ASC, created_at DESC",
    $params
);

$grouped = ['100' => [], '200' => [], '300' => [], '400' => []];
foreach ($questions as $item) {
    $grouped[$item['level']][] = $item;
}

$counts = $db->fetchAll("SELECT level, COUNT(*) AS total FROM past_questions GROUP BY level");
$stats = ['100' => 0, '200' => 0, '300' => 0, '400' => 0];
foreach ($counts as $c) {
    if (isset($stats[$c['level']])) {
        $stats[$c['level']] = (int)$c['total'];
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Past Questions - NACOS Admin</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1 !important;
        }
        ::-webkit-scrollbar-thumb {
            background: #9a9a9a !important;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #7f7f7f !important;
        }
        .sidebar {
            background: #117a43 !important;
            scrollbar-color: #9a9a9a #f1f1f1;
        }
        .sidebar-header {
            background: #0d6b3b !important;
        }
        .sidebar-menu a {
            color: rgba(255,255,255,0.9) !important;
        }
        .sidebar-menu a i {
            color: #ffc107;
        }
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: #3f8433 !important;
            color: #fff !important;
        }

        .menu-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            background: #fff;
            cursor: pointer;
            flex-shrink: 0;
        }
        .top-bar {
            background: #fff;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: sweepRevealIn 1s cubic-bezier(0.22, 1, 0.36, 1) 0.10s backwards;
        }
        .top-bar h3 {
            margin: 0;
            color: #0F6B3E;
            font-size: 24px;
            font-weight: 700;
        }
        .page-header-row {
            width: 100%;
        }
        .header-left {
            min-width: 0;
        }
        .header-title p {
            margin-bottom: 0;
            color: #6c757d;
            font-size: 14px;
        }
        .header-right-icon {
            display: none;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            align-items: center;
            justify-content: center;
            background: rgba(15, 107, 62, 0.08);
            color: #0F6B3E;
            font-size: 18px;
        }
        .upload-card,
        .table-card,
        .stats-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
            padding: 16px;
        }
        .row.g-3.mb-3 { animation: sweepRevealIn 1s cubic-bezier(0.22, 1, 0.36, 1) 0.34s backwards; }
        .table-card { animation: sweepRevealIn 1s cubic-bezier(0.22, 1, 0.36, 1) 0.78s backwards; }

        @keyframes sweepRevealIn {
            from {
                opacity: 0;
                transform: translateX(18px);
                clip-path: inset(0 100% 0 0);
            }
            to {
                opacity: 1;
                transform: translateX(0);
                clip-path: inset(0 0 0 0);
            }
        }
        .stat-pill {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 10px 12px;
            text-align: center;
            background: #fafafa;
        }
        .section-title {
            color: #0F6B3E;
            font-weight: 700;
            font-size: 20px;
            margin-bottom: 12px;
        }
        .table td, .table th {
            vertical-align: middle;
        }
        .accordion-button:not(.collapsed) {
            color: #0F6B3E;
            background-color: #f7fbf8;
        }
        @media (max-width: 992px) {
            .menu-toggle { display: inline-flex; }
            .main-content { padding: 15px; }
            .top-bar { flex-direction: column; align-items: flex-start; gap: 12px; }
            .page-header-row { flex-direction: column; align-items: stretch !important; gap: 10px; }
            .header-left { width: 100%; display: flex; align-items: center; justify-content: space-between; }
            .header-left .header-title { display: none; }
            .header-right-icon { display: inline-flex; }
            .page-header-row .btn-warning,
            .top-bar .btn-warning.add-question-btn {
                width: 100%;
                align-self: stretch;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="top-bar">
            <div class="d-flex justify-content-between align-items-center page-header-row">
                <div class="d-flex align-items-center gap-3 header-left">
                    <button class="menu-toggle" id="menuToggle" type="button" aria-label="Toggle menu">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="header-title">
                        <h3><i class="fas fa-file-pdf me-2"></i> Past Questions</h3>
                        <p>Upload and manage past questions for 100L, 200L, 300L, and 400L.</p>
                    </div>
                    <span class="header-right-icon" aria-hidden="true"><i class="fas fa-file-pdf"></i></span>
                </div>
                <a href="#uploadSection" class="btn btn-warning text-success fw-semibold add-question-btn">
                    <i class="fas fa-plus me-2"></i> Add New Past Question
                </a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($flash['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <div class="row g-3 mb-3">
            <div class="col-12 col-lg-7">
                <div class="upload-card h-100" id="uploadSection">
                    <h2 class="section-title">Upload New File</h2>
                    <form method="POST" enctype="multipart/form-data" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="action" value="upload">

                        <div class="col-md-4">
                            <label class="form-label">Level</label>
                            <select name="level" class="form-select" required>
                                <option value="">Select level</option>
                                <option value="100">100 Level</option>
                                <option value="200">200 Level</option>
                                <option value="300">300 Level</option>
                                <option value="400">400 Level</option>
                            </select>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label">Course Title</label>
                            <input type="text" name="course_title" class="form-control" placeholder="e.g. CSC 101 - Introduction to Computing" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Upload File (PDF or Image)</label>
                            <input type="file" name="question_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                            <small class="text-muted">Allowed: PDF, JPG, JPEG, PNG, WEBP (Max: 15MB)</small>
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-upload me-1"></i> Upload Past Question
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-12 col-lg-5">
                <div class="stats-card h-100">
                    <h2 class="section-title">Level Summary</h2>
                    <div class="row g-2">
                        <div class="col-6"><div class="stat-pill"><strong>100L</strong><br><?php echo $stats['100']; ?> file(s)</div></div>
                        <div class="col-6"><div class="stat-pill"><strong>200L</strong><br><?php echo $stats['200']; ?> file(s)</div></div>
                        <div class="col-6"><div class="stat-pill"><strong>300L</strong><br><?php echo $stats['300']; ?> file(s)</div></div>
                        <div class="col-6"><div class="stat-pill"><strong>400L</strong><br><?php echo $stats['400']; ?> file(s)</div></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h2 class="section-title mb-0">Uploaded Past Questions</h2>
                <form method="GET" class="d-flex flex-wrap gap-2">
                    <select name="level" class="form-select" style="width:160px;">
                        <option value="all" <?php echo $level_filter === 'all' ? 'selected' : ''; ?>>All Levels</option>
                        <option value="100" <?php echo $level_filter === '100' ? 'selected' : ''; ?>>100 Level</option>
                        <option value="200" <?php echo $level_filter === '200' ? 'selected' : ''; ?>>200 Level</option>
                        <option value="300" <?php echo $level_filter === '300' ? 'selected' : ''; ?>>300 Level</option>
                        <option value="400" <?php echo $level_filter === '400' ? 'selected' : ''; ?>>400 Level</option>
                    </select>
                    <input type="text" name="q" class="form-control" style="width:230px;" placeholder="Search course title" value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-filter me-1"></i>Filter</button>
                </form>
            </div>

            <div class="accordion" id="pastQuestionsAccordionAdmin">
                <?php foreach (['100', '200', '300', '400'] as $index => $lvl): ?>
                    <?php if ($level_filter !== 'all' && $level_filter !== $lvl) { continue; } ?>
                    <?php $is_open = ($level_filter === 'all' && $index === 0) || ($level_filter === $lvl); ?>
                    <div class="accordion-item mb-2 border rounded">
                        <h2 class="accordion-header" id="headingAdmin<?php echo $lvl; ?>">
                            <button class="accordion-button <?php echo $is_open ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdmin<?php echo $lvl; ?>" aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>" aria-controls="collapseAdmin<?php echo $lvl; ?>">
                                <?php echo $lvl; ?> Level (<?php echo count($grouped[$lvl]); ?>)
                            </button>
                        </h2>
                        <div id="collapseAdmin<?php echo $lvl; ?>" class="accordion-collapse collapse <?php echo $is_open ? 'show' : ''; ?>" aria-labelledby="headingAdmin<?php echo $lvl; ?>" data-bs-parent="#pastQuestionsAccordionAdmin">
                            <div class="accordion-body p-0">
                                <div class="table-responsive mb-0">
                                    <table class="table table-striped table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Course Title</th>
                                                <th>File</th>
                                                <th>Type</th>
                                                <th>Downloads</th>
                                                <th>Added</th>
                                                <th style="width:120px;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (empty($grouped[$lvl])): ?>
                                            <tr><td colspan="6" class="text-muted">No files uploaded for <?php echo $lvl; ?> level.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($grouped[$lvl] as $item): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($item['course_title']); ?></td>
                                                    <td><?php echo htmlspecialchars($item['file_name']); ?></td>
                                                    <td><span class="badge bg-secondary text-uppercase"><?php echo htmlspecialchars($item['file_type']); ?></span></td>
                                                    <td><?php echo (int)$item['download_count']; ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                                                    <td>
                                                        <div class="d-flex gap-2">
                                                            <a href="../<?php echo htmlspecialchars($item['file_path']); ?>" class="btn btn-sm btn-outline-primary" target="_blank" title="Open file">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <form method="POST" onsubmit="return confirm('Delete this past question?');">
                                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="question_id" value="<?php echo (int)$item['question_id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>

<footer class="bg-light text-center py-3 mt-5">
    <div class="container">
        <p class="mb-0 text-muted">© <?php echo date('Y'); ?> NACOS Admin Dashboard. All rights reserved.</p>
    </div>
</footer>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
(function () {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (!menuToggle || !sidebar || !backdrop) return;

    menuToggle.addEventListener('click', () => {
        sidebar.classList.toggle('show');
        backdrop.classList.toggle('show');
    });

    backdrop.addEventListener('click', () => {
        sidebar.classList.remove('show');
        backdrop.classList.remove('show');
    });
})();
</script>
</body>
</html>
