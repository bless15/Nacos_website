<?php
/**
 * Flash message helpers
 * Wraps auth.php flash functions for backwards compatibility
 */

if (!defined('NACOS_ACCESS')) {
    die('Direct access not permitted');
}

// Flash message functions are defined in auth.php
// This file exists for backwards compatibility with files that require it separately.
// The actual implementations are in includes/auth.php:
//   - redirectWithMessage($url, $message, $type)
//   - getFlashMessage()

// Ensure auth.php is loaded so flash functions are available
require_once __DIR__ . '/auth.php';

// End of file
