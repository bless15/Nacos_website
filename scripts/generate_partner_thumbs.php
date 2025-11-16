<?php
// scripts/generate_partner_thumbs.php
// Usage (CLI): php scripts/generate_partner_thumbs.php [--apply-db=0|1] [--thumb-width=300] [--thumb-height=300]

$opts = array_reduce($argv, function($acc, $arg) {
    if (strpos($arg, '--') === 0) {
        $pair = explode('=', substr($arg,2), 2);
        $acc[$pair[0]] = isset($pair[1]) ? $pair[1] : true;
    }
    return $acc;
}, []);

$applyDb = isset($opts['apply-db']) ? (bool)$opts['apply-db'] : false;
$thumbWidth = isset($opts['thumb-width']) ? (int)$opts['thumb-width'] : 300;
$thumbHeight = isset($opts['thumb-height']) ? (int)$opts['thumb-height'] : 300;

echo "Generate partner thumbnails (dry-run by default).\n";
echo "Options: apply-db=" . ($applyDb ? '1' : '0') . ", thumb-size={$thumbWidth}x{$thumbHeight}\n";

$baseDir = __DIR__ . '/../uploads/partners/';
if (!is_dir($baseDir)) {
    echo "Uploads directory not found: $baseDir\n";
    exit(1);
}

require_once __DIR__ . '/../includes/image_helpers.php';

$files = array_values(array_filter(scandir($baseDir), function($f) use ($baseDir) {
    if ($f === '.' || $f === '..') return false;
    if (strpos($f, 'thumb_') === 0) return false;
    $full = $baseDir . $f;
    return is_file($full) && preg_match('/\.(jpe?g|png|gif|svg)$/i', $f);
}));

$total = count($files);
echo "Found $total candidate files.\n";

$created = 0;
$skipped = 0;

foreach ($files as $file) {
    $src = $baseDir . $file;
    $thumb = $baseDir . 'thumb_' . $file;
    if (file_exists($thumb)) {
        $skipped++;
        // echo "Skipped existing: $thumb\n";
        continue;
    }
    echo "Creating thumbnail for $file... ";
    $ok = create_image_thumbnail($src, $thumb, $thumbWidth, $thumbHeight, 85);
    if ($ok) {
        echo "OK\n";
        $created++;
    } else {
        echo "FAILED\n";
    }
}

echo "Done. Created: $created, Skipped (already exist): $skipped, Total candidates: $total\n";

if ($applyDb) {
    echo "\nUpdating DB to store filenames only (APPLY MODE)\n";
    require_once __DIR__ . '/../config/database.php';
    $db = getDB();
    $partners = $db->fetchAll("SELECT partner_id, company_logo FROM PARTNERS");
    $updated = 0;
    foreach ($partners as $p) {
        $logo = $p['company_logo'] ?? '';
        if (!$logo) continue;
        // normalize
        $normalized = preg_replace('#^\.{1,2}/+#', '', $logo);
        $filename = basename($normalized);
        if ($filename !== $logo) {
            $db->query("UPDATE PARTNERS SET company_logo = :logo WHERE partner_id = :id", [':logo' => $filename, ':id' => $p['partner_id']]);
            $updated++;
        }
    }
    echo "DB updated for $updated partner(s).\n";
} else {
    echo "\nDB not modified (dry-run). To update DB use --apply-db=1 flag.\n";
}

exit(0);
