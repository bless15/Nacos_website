<?php
// scripts/check_opcache.php
// Run via CLI: php scripts/check_opcache.php
// Or open in the browser: http://localhost/nacos/scripts/check_opcache.php

header('Content-Type: text/plain');

echo "OPcache check for project nacos\n";

$phpIni = php_ini_loaded_file();
if ($phpIni) {
    echo "Loaded php.ini: $phpIni\n";
} else {
    echo "No php.ini file discovered by PHP.\n";
}

// Is opcache extension loaded?
if (!extension_loaded('Zend OPcache') && !extension_loaded('opcache')) {
    echo "OPcache extension is not loaded.\n";
    echo "Try enabling the extension in php.ini (extension=opcache) and restart Apache.\n";
    exit(0);
}

echo "OPcache extension is loaded.\n";

// show ini settings for opcache
$settings = ini_get_all('opcache', false);
if ($settings) {
    echo "OPcache INI settings:\n";
    foreach ($settings as $k => $v) {
        echo "  $k = " . (is_bool($v) ? ($v ? '1' : '0') : $v) . "\n";
    }
} else {
    echo "Could not read opcache ini settings via ini_get_all().\n";
}

// status via function (if available)
if (function_exists('opcache_get_status')) {
    $status = @opcache_get_status(false);
    if ($status === false) {
        echo "opcache_get_status() returned false — OPcache may be disabled for this SAPI.\n";
    } else {
        echo "OPcache Status:\n";
        echo "  Cache Full: " . (!empty($status['memory_usage']['free_memory']) ? 'no' : 'unknown') . "\n";
        echo "  Used Memory: " . ($status['memory_usage']['used_memory'] ?? 'n/a') . " bytes\n";
        echo "  Free Memory: " . ($status['memory_usage']['free_memory'] ?? 'n/a') . " bytes\n";
        echo "  Wasted Memory: " . ($status['memory_usage']['wasted_memory'] ?? 'n/a') . " bytes\n";
        echo "  Number of Cached Scripts: " . ($status['opcache_statistics']['num_cached_scripts'] ?? 'n/a') . "\n";
    }
} else {
    echo "opcache_get_status() not available in this environment.\n";
}

echo "\nQuick next steps:\n";
echo "  1) Open your php.ini (usually C:\\xampp\\php\\php.ini) and ensure the following lines are present and adjusted:\n";
echo "     opcache.enable=1\n";
echo "     opcache.memory_consumption=128\n";
echo "     opcache.interned_strings_buffer=8\n";
echo "     opcache.max_accelerated_files=10000\n";
echo "     opcache.revalidate_freq=2\n";
echo "     opcache.validate_timestamps=1   ; set to 0 in production when you deploy via an atomic process\n";

echo "  2) Restart Apache (XAMPP Control Panel or command line)\n";

echo "Example (PowerShell):\n";
echo "  C:\\xampp\\apache\\bin\\httpd.exe -k restart\n";

echo "\nNotes:\n";
echo " - On Windows+XAMPP, edit C:\\xampp\\php\\php.ini, not the system php.ini.\n";
echo " - If you prefer not to edit server config, you can enable APCu for small in-memory cache plus a file-cache fallback (we can add a helper).\n";

?>