<?php
// scripts/build_assets.php
// Simple PHP asset bundler + minifier for CSS and JS
// Usage: php scripts/build_assets.php

$root = __DIR__ . '/../';
$cssDir = $root . 'assets/css/';
$jsDir = $root . 'assets/js/';
$distDir = $root . 'assets/dist/';

if (!is_dir($distDir)) mkdir($distDir, 0755, true);

function minify_css($css) {
    // Remove comments
    $css = preg_replace('!/\*.*?\*/!s', '', $css);
    // Remove whitespace
    $css = preg_replace('/\s+/', ' ', $css);
    $css = preg_replace('/\s*([{}|:;,])\s*/', '$1', $css);
    $css = str_replace(';}', '}', $css);
    return trim($css);
}

function minify_js($js) {
    // Very simple JS minifier: remove comments and extra whitespace
    $js = preg_replace('#//.*#', '', $js);
    $js = preg_replace('#/\*.*?\*/#s', '', $js);
    $js = preg_replace('/\s+/', ' ', $js);
    $js = preg_replace('/\s*([{}|:;,\(\)])\s*/', '$1', $js);
    return trim($js);
}

$manifest = [];

// Build public.css bundle
$publicCssFiles = [ $cssDir . 'public.css' ];
$publicCssContent = '';
foreach ($publicCssFiles as $f) {
    if (file_exists($f)) $publicCssContent .= "\n" . file_get_contents($f);
}
$minCss = minify_css($publicCssContent);
$hash = substr(sha1($minCss), 0, 10);
$outName = "public.bundle.$hash.css";
file_put_contents($distDir . $outName, $minCss);
$manifest['css/public.css'] = 'assets/dist/' . $outName;

// Build admin.css bundle
$adminCssFiles = [ $cssDir . 'admin.css' ];
$adminCssContent = '';
foreach ($adminCssFiles as $f) {
    if (file_exists($f)) $adminCssContent .= "\n" . file_get_contents($f);
}
$minAdminCss = minify_css($adminCssContent);
$hash2 = substr(sha1($minAdminCss), 0, 10);
$outName2 = "admin.bundle.$hash2.css";
file_put_contents($distDir . $outName2, $minAdminCss);
$manifest['css/admin.css'] = 'assets/dist/' . $outName2;

// Build JS bundle if any JS files exist
$jsFiles = glob($jsDir . '*.js');
if (!empty($jsFiles)) {
    $jsContent = '';
    foreach ($jsFiles as $f) {
        if (strpos(basename($f), '.min.') !== false) continue; // skip already minified
        $jsContent .= "\n" . file_get_contents($f);
    }
    if ($jsContent !== '') {
        $minJs = minify_js($jsContent);
        $hash3 = substr(sha1($minJs), 0, 10);
        $outJs = "public.bundle.$hash3.js";
        file_put_contents($distDir . $outJs, $minJs);
        $manifest['js/public.js'] = 'assets/dist/' . $outJs;
    }
}

// Write manifest
file_put_contents($distDir . 'manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

echo "Build complete. Manifest written to assets/dist/manifest.json\n";
print_r($manifest);

?>