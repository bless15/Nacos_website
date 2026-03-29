<?php
/**
 * ============================================
 * NACOS DASHBOARD - DELETE EVENT
 * ============================================
 */

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdminRole();

$db = getDB();
$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($event_id <= 0) {
    redirectWithMessage('events.php', 'Invalid event ID.', 'error');
}

$event = $db->fetchOne("SELECT event_id FROM events WHERE event_id = ?", [$event_id]);
if (!$event) {
    redirectWithMessage('events.php', 'Event not found.', 'error');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithMessage('events.php', 'Use the delete confirmation popup to remove an event.', 'info');
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    redirectWithMessage('events.php', 'Invalid request.', 'error');
}

try {
    $db->beginTransaction();
    $db->query("DELETE FROM member_events WHERE event_id = ?", [$event_id]);
    $db->query("DELETE FROM events WHERE event_id = ?", [$event_id]);
    $db->commit();

    redirectWithMessage('events.php', 'Event deleted permanently.', 'success');
} catch (Exception $e) {
    if (method_exists($db, 'rollBack')) {
        $db->rollBack();
    }
    @file_put_contents(
        __DIR__ . '/../logs/actions.log',
        date('[Y-m-d H:i:s]') . " Delete event ERROR: event_id={$event_id}, error=" . $e->getMessage() . "\n",
        FILE_APPEND | LOCK_EX
    );
    redirectWithMessage('events.php', 'An error occurred while deleting the event.', 'error');
}
