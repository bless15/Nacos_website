<?php
/**
 * Security gate - Define access constant
 * Include this at the top of all protected files
 */
if (!defined('NACOS_ACCESS')) {
	define('NACOS_ACCESS', true);
}

// Load session helpers so files including security.php get a working session
$sessionFile = __DIR__ . '/session.php';
if (file_exists($sessionFile)) {
	require_once $sessionFile;
	// Initialize session if possible
	if (function_exists('initSession')) {
		try {
			initSession();
		} catch (Throwable $e) {
			error_log('initSession() failed in security include: ' . $e->getMessage());
		}
	}
}
