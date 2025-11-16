<?php
// Simple diagnostic page to check presence and accessibility of key assets
$root = realpath(__DIR__ . '/..');
$logoPath = $root . '/assets/images/nacos_logo.jpg';
$heroPath = $root . '/assets/images/hero-image.svg';

function fileInfo($path) {
    if (file_exists($path)) {
        return [
            'exists' => true,
            'size' => filesize($path),
            'readable' => is_readable($path),
            'realpath' => realpath($path)
        ];
    }
    return ['exists' => false];
}

$logo = fileInfo($logoPath);
$hero = fileInfo($heroPath);
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>NACOS Asset Check</title>
    <style>body{font-family:Arial,Helvetica,sans-serif;padding:20px}</style>
</head>
<body>
    <h1>Asset Check</h1>
    <h2>nacos_logo.jpg</h2>
    <pre><?php echo htmlspecialchars(json_encode($logo, JSON_PRETTY_PRINT)); ?></pre>
    <h3>Rendered image (relative path)</h3>
    <img src="../assets/images/nacos_logo.jpg" alt="nacos logo" style="height:80px;border:1px solid #ccc;padding:4px">

    <hr>
    <h2>hero-image.svg</h2>
    <pre><?php echo htmlspecialchars(json_encode($hero, JSON_PRETTY_PRINT)); ?></pre>
    <h3>Rendered image (relative path)</h3>
    <img src="../assets/images/hero-image.svg" alt="hero image" style="height:120px;border:1px solid #ccc;padding:4px">

    <p>Instructions: Open this page in your browser at <strong>/public/asset_check.php</strong>. If images don't render but files exist, check Apache error logs and file permissions.</p>
</body>
</html>
