<?php
/**
 * One-time migration helper: ensure PARTNERS.is_featured exists
 * Usage (PowerShell):
 *   & 'C:\xampp\php\php.exe' -f C:\xampp\htdocs\nacos\scripts\migrate_add_is_featured.php
 * or run from browser (development only): http://localhost/nacos/scripts/migrate_add_is_featured.php
 */
// Minimal bootstrapping to reuse database config
define('NACOS_ACCESS', true);
require_once __DIR__ . '/../config/database.php';

$db = getDB();

try {
    // Check if column exists using information_schema
    $row = $db->fetchOne("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'PARTNERS' AND COLUMN_NAME = 'is_featured'", [':db' => DB_NAME]);
    $exists = $row && intval($row['cnt']) > 0;

    if ($exists) {
        echo "OK: column 'is_featured' already exists on PARTNERS.\n";
        exit(0);
    }

    // Add column
    echo "Adding column is_featured to PARTNERS...\n";
    $db->query("ALTER TABLE PARTNERS ADD COLUMN is_featured TINYINT(1) DEFAULT 0 COMMENT 'Flag to feature partner on public pages'");
    echo "Success: is_featured column added.\n";
    exit(0);

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
