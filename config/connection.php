<?php
/**
 * Connection helper - convenience wrapper
 * Provides `$db` (Database instance) and `$pdo` (PDO) variables for legacy scripts.
 */

// Allow safe inclusion when NACOS_ACCESS is not yet defined
if (!defined('NACOS_ACCESS')) {
    define('NACOS_ACCESS', true);
}

require_once __DIR__ . '/database.php';

// Expose commonly used variables
$db = getDB();
$pdo = getConnection();

// Backwards-compatible globals
$GLOBALS['db'] = $db;
$GLOBALS['pdo'] = $pdo;

// End of file
