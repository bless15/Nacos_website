<?php
/**
 * NACOS - Central configuration and bootstrap
 * Created: December 16, 2025
 */

// Ensure this file is idempotent when included multiple times
if (!defined('NACOS_ACCESS')) {
    define('NACOS_ACCESS', true);
}

// Basic paths
if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/..'));
}

define('CONFIG_PATH', __DIR__);
define('PUBLIC_PATH', BASE_PATH . '/public');
define('UPLOADS_PATH', BASE_PATH . '/uploads');
define('STORAGE_PATH', BASE_PATH . '/storage');

// Timezone (override by defining TIMEZONE before including)
if (!defined('TIMEZONE')) {
    define('TIMEZONE', 'Africa/Lagos');
}
date_default_timezone_set(TIMEZONE);

// Composer autoload (optional)
$vendorAutoload = BASE_PATH . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

// Require lower-level config files which expect NACOS_ACCESS to be defined
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/email.php';
// Expose legacy-friendly connection variables ($db, $pdo)
require_once __DIR__ . '/connection.php';

// App debug flag (driven by `ENVIRONMENT` if available)
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', defined('ENVIRONMENT') && ENVIRONMENT === 'development');
}

// Session helpers (initSession defined in includes/session.php)
require_once __DIR__ . '/../includes/session.php';
// Initialize/harden session
initSession();

// Helper: derive a base URL for links (best-effort)
function nacos_base_url() {
    static $base = null;
    if ($base !== null) return $base;

    if (php_sapi_name() === 'cli' || empty($_SERVER['HTTP_HOST'])) {
        $base = 'http://localhost/nacos';
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $script = dirname($_SERVER['SCRIPT_NAME']);
        $base = rtrim($scheme . '://' . $host . $script, '/');
    }

    return $base;
}

if (!defined('BASE_URL')) {
    define('BASE_URL', nacos_base_url());
}

// Basic security headers
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    if (!APP_DEBUG) {
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'");
    }
}

// End of config.php
