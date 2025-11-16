<?php
// includes/asset.php
// Simple helper to read assets/dist/manifest.json and return fingerprinted asset paths

function asset_manifest() {
    static $m;
    if ($m !== null) return $m;
    $manifestPath = __DIR__ . '/../assets/dist/manifest.json';
    if (!file_exists($manifestPath)) return [];
    $m = json_decode(file_get_contents($manifestPath), true) ?: [];
    return $m;
}

function asset($logical) {
    // logical: 'css/public.css' or 'js/public.js' or 'images/logo.png'
    $m = asset_manifest();
    if (isset($m[$logical])) return $m[$logical];
    // Fallback: return as-is (relative to project root)
    return 'assets/' . ltrim($logical, '/');
}

?>