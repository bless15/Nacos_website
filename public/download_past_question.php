<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/config.php';

$db = getDB();
$question_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($question_id <= 0) {
    redirectWithMessage('past_questions.php', 'Invalid file request.', 'error');
}

$question = $db->fetchOne(
    "SELECT question_id, file_path, file_name FROM past_questions WHERE question_id = ?",
    [$question_id]
);

if (!$question || empty($question['file_path'])) {
    redirectWithMessage('past_questions.php', 'File not found.', 'error');
}

$file_relative = ltrim($question['file_path'], '/\\');
$file_path = realpath(__DIR__ . '/../' . $file_relative);
$allowed_base = realpath(__DIR__ . '/../uploads/past_questions');

if (!$file_path || !$allowed_base || strpos($file_path, $allowed_base) !== 0 || !is_file($file_path)) {
    redirectWithMessage('past_questions.php', 'File is not available.', 'error');
}

$db->query("UPDATE past_questions SET download_count = download_count + 1 WHERE question_id = ?", [$question_id]);

$download_name = basename($question['file_name']);
$mime = mime_content_type($file_path) ?: 'application/octet-stream';

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $download_name) . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
readfile($file_path);
exit;
