<?php
// scripts/migrate_add_indexes.php
// Idempotent migration to add recommended indexes for performance.
// Run via: php scripts/migrate_add_indexes.php

// Allow config/database.php to be loaded from CLI
if (!defined('NACOS_ACCESS')) define('NACOS_ACCESS', true);
require_once __DIR__ . '/../config/database.php';
$db = getDB();

$checks = [
    [
        'table' => 'partners',
        'index' => 'idx_partners_featured_start',
        'columns' => 'is_featured, partnership_start_date',
        'sql' => "ALTER TABLE partners ADD INDEX idx_partners_featured_start (is_featured, partnership_start_date)"
    ],
    [
        'table' => 'events',
        'index' => 'idx_events_event_date',
        'columns' => 'event_date',
        'sql' => "ALTER TABLE events ADD INDEX idx_events_event_date (event_date)"
    ],
    [
        'table' => 'projects',
        'index' => 'idx_projects_status_updated',
        'columns' => 'project_status, updated_at',
        'sql' => "ALTER TABLE projects ADD INDEX idx_projects_status_updated (project_status, updated_at)"
    ],
    [
        'table' => 'partner_requests',
        'index' => 'idx_partner_requests_created',
        'columns' => 'created_at',
        'sql' => "ALTER TABLE partner_requests ADD INDEX idx_partner_requests_created (created_at)"
    ],
    [
        'table' => 'members',
        'index' => 'idx_members_status',
        'columns' => 'membership_status',
        'sql' => "ALTER TABLE members ADD INDEX idx_members_status (membership_status)"
    ],
    [
        'table' => 'member_projects',
        'index' => 'idx_member_projects_member_join',
        'columns' => 'member_id, join_date',
        'sql' => "ALTER TABLE member_projects ADD INDEX idx_member_projects_member_join (member_id, join_date)"
    ]
];

$created = 0;
foreach ($checks as $c) {
    $table = $c['table'];
    $index = $c['index'];
    $columns = $c['columns'];

    // Check if index already exists
    $exists = $db->fetchOne(
        "SELECT COUNT(1) as cnt FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index",
        [':table' => $table, ':index' => $index]
    );

    if ($exists && $exists['cnt'] > 0) {
        echo "Index {$index} on {$table} already exists.\n";
        continue;
    }

    try {
        echo "Adding index {$index} on {$table} ({$columns})... ";
        $db->query($c['sql']);
        echo "OK\n";
        $created++;
    } catch (Exception $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    }
}

echo "Migration complete. Indexes created: {$created}\n";

?>