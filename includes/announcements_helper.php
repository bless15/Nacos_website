<?php
/**
 * Announcement helper functions
 */

if (!defined('NACOS_ACCESS')) {
    die('Direct access not permitted');
}

function getAnnouncementActorContext(): array {
    initSession();

    if (isLoggedIn() && isset($_SESSION['admin_id'])) {
        return [
            'type' => 'admin_account',
            'id' => (int)$_SESSION['admin_id'],
            'name' => $_SESSION['full_name'] ?? ($_SESSION['username'] ?? 'Admin')
        ];
    }

    if (isMemberLoggedIn() && isset($_SESSION['member_id'])) {
        return [
            'type' => 'member',
            'id' => (int)$_SESSION['member_id'],
            'name' => $_SESSION['member_full_name'] ?? 'Member'
        ];
    }

    return ['type' => 'member', 'id' => 0, 'name' => 'Unknown'];
}

function queueAnnouncementNotifications(Database $db, int $announcementId, string $audienceLevel, string $audienceStatus): void {
    $where = ["membership_status != 'pending'"];
    $params = [];

    if ($audienceLevel !== 'all') {
        $where[] = "level = ?";
        $params[] = $audienceLevel;
    }

    if ($audienceStatus !== 'all') {
        $where[] = "membership_status = ?";
        $params[] = $audienceStatus;
    }

    $members = $db->fetchAll(
        "SELECT member_id FROM members WHERE " . implode(' AND ', $where),
        $params
    );

    foreach ($members as $member) {
        $db->query(
            "INSERT IGNORE INTO announcement_notifications (announcement_id, member_id, is_read) VALUES (?, ?, 0)",
            [$announcementId, (int)$member['member_id']]
        );
    }
}

function logAnnouncementAudit(Database $db, ?int $announcementId, string $action, string $details = ''): void {
    $actor = getAnnouncementActorContext();

    $db->query(
        "INSERT INTO announcement_audit_logs (announcement_id, actor_type, actor_id, action, details)
         VALUES (?, ?, ?, ?, ?)",
        [$announcementId, $actor['type'], $actor['id'], $action, $details]
    );
}

function announcementAudienceClause(array $member = null): array {
    if (!$member) {
        return [
            "(audience_status = 'all')",
            []
        ];
    }

    $clauses = [
        "(audience_level = 'all' OR audience_level = ?)",
        "(audience_status = 'all' OR audience_status = ?)"
    ];

    return [
        implode(' AND ', $clauses),
        [(string)$member['level'], (string)$member['membership_status']]
    ];
}
