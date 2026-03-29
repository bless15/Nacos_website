<?php
/**
 * Session helpers for NACOS
 * Provides secure session initialization and common helpers
 */

if (!defined('NACOS_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Initialize and harden PHP session.
 */
function initSession(): void {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $domain = $_SERVER['HTTP_HOST'] ?? '';

    session_name('nacos_session');

    // Use PHP 7.3+ style options when available
    $cookieParams = [
        'lifetime' => 0,
        'path' => '/',
        'domain' => $domain,
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ];

    // Fallback for older PHP versions
    if (PHP_VERSION_ID < 70300) {
        session_set_cookie_params(
            $cookieParams['lifetime'],
            $cookieParams['path'] . '; samesite=' . $cookieParams['samesite'],
            $cookieParams['domain'],
            $cookieParams['secure'],
            $cookieParams['httponly']
        );
    } else {
        session_set_cookie_params($cookieParams);
    }

    session_start();

    // Regenerate id on new sessions to mitigate fixation
    if (empty($_SESSION['created'])) {
        $_SESSION['created'] = time();
        session_regenerate_id(true);
    } elseif (time() - $_SESSION['created'] > 1800) { // 30 minutes
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

/**
 * Destroy current session completely.
 */
function destroySession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Clear session array
    $_SESSION = [];

    // Delete session cookie
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'] ?? '/',
        $params['domain'] ?? '',
        $params['secure'] ?? false,
        $params['httponly'] ?? true
    );

    // Destroy session
    session_destroy();
}

/**
 * Regenerate the session id (use after privilege changes / login)
 */
function regenerateSession(bool $deleteOld = true): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_regenerate_id($deleteOld);
    $_SESSION['created'] = time();
}

// Note: authentication checks (isLoggedIn, isMemberLoggedIn, etc.)
// are implemented in `includes/auth.php`. This file provides
// only session lifecycle helpers to avoid function name collisions.

// End of file
