<?php
// includes/cache.php
// Simple file-based cache helper for small transient values.
// API: cache_get($key), cache_set($key, $value, $ttl_seconds), cache_delete($key)

if (!defined('CACHE_DIR')) {
    define('CACHE_DIR', __DIR__ . '/../storage/cache');
}

if (!file_exists(CACHE_DIR)) {
    @mkdir(CACHE_DIR, 0755, true);
}

function cache_key_to_path($key) {
    $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
    return CACHE_DIR . '/' . $safe . '.cache';
}

function cache_set($key, $value, $ttl = 60) {
    $path = cache_key_to_path($key);
    $payload = [
        'expires_at' => time() + (int)$ttl,
        'data' => $value
    ];
    file_put_contents($path, serialize($payload), LOCK_EX);
}

function cache_get($key) {
    $path = cache_key_to_path($key);
    if (!file_exists($path)) return null;
    $payload = @unserialize(@file_get_contents($path));
    if (!is_array($payload) || !isset($payload['expires_at']) || !array_key_exists('data', $payload)) {
        @unlink($path);
        return null;
    }
    if ($payload['expires_at'] < time()) {
        @unlink($path);
        return null;
    }
    return $payload['data'];
}

function cache_delete($key) {
    $path = cache_key_to_path($key);
    if (file_exists($path)) @unlink($path);
}

?>