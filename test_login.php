<?php
/**
 * ============================================
 * DIAGNOSTIC TOOL - Database & Auth Check
 * ============================================
 * Purpose: Verify database connection and admin accounts
 * Usage: Visit http://localhost/nacos/test_login.php
 * ============================================
 */

require_once __DIR__ . '/includes/security.php';
require_once 'config/database.php';

echo "<html><head><title>NACOS Login Diagnostic</title>";
echo "<style>
    body { font-family: Arial; padding: 20px; background: #f5f5f5; }
    .success { background: #d4edda; padding: 15px; margin: 10px 0; border-left: 4px solid #28a745; }
    .error { background: #f8d7da; padding: 15px; margin: 10px 0; border-left: 4px solid #dc3545; }
    .info { background: #d1ecf1; padding: 15px; margin: 10px 0; border-left: 4px solid #17a2b8; }
    .warning { background: #fff3cd; padding: 15px; margin: 10px 0; border-left: 4px solid #ffc107; }
    h2 { color: #333; }
    table { width: 100%; border-collapse: collapse; background: white; margin: 10px 0; }
    th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
    th { background: #667eea; color: white; }
    code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
</style></head><body>";

echo "<h1>🔍 NACOS Login Diagnostic Tool</h1>";

// Test 1: Database Connection
echo "<h2>1. Database Connection Test</h2>";
try {
    $db = getDB();
    echo "<div class='success'>✅ <strong>SUCCESS:</strong> Database connected successfully!</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ <strong>ERROR:</strong> Database connection failed: " . $e->getMessage() . "</div>";
    echo "<div class='info'>📝 <strong>Fix:</strong> Check your database credentials in <code>config/database.php</code></div>";
    exit;
}

// Test 2: Check if ADMINISTRATORS table exists
echo "<h2>2. ADMINISTRATORS Table Check</h2>";
try {
    $tableCheck = $db->fetchOne("SHOW TABLES LIKE 'ADMINISTRATORS'");
    if ($tableCheck) {
        echo "<div class='success'>✅ <strong>SUCCESS:</strong> ADMINISTRATORS table exists</div>";
    } else {
        echo "<div class='error'>❌ <strong>ERROR:</strong> ADMINISTRATORS table not found</div>";
        echo "<div class='info'>📝 <strong>Fix:</strong> Import <code>database/schema.sql</code> in phpMyAdmin</div>";
        exit;
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ <strong>ERROR:</strong> " . $e->getMessage() . "</div>";
    exit;
}

// Test 3: Count admin accounts
echo "<h2>3. Admin Accounts Check</h2>";
try {
    $adminCount = $db->fetchOne("SELECT COUNT(*) as count FROM ADMINISTRATORS")['count'];
    echo "<div class='success'>✅ <strong>Found:</strong> $adminCount admin account(s) in database</div>";
    
    if ($adminCount == 0) {
        echo "<div class='error'>❌ <strong>ERROR:</strong> No admin accounts found!</div>";
        echo "<div class='info'>📝 <strong>Fix:</strong> Import <code>database/seed_data.sql</code> in phpMyAdmin</div>";
        exit;
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ <strong>ERROR:</strong> " . $e->getMessage() . "</div>";
    exit;
}

// Test 4: List all admin accounts
echo "<h2>4. Available Admin Accounts</h2>";
try {
    $admins = $db->fetchAll("SELECT admin_id, username, role, full_name, email, status FROM ADMINISTRATORS ORDER BY admin_id");
    
    echo "<table>";
    echo "<tr><th>ID</th><th>Username</th><th>Role</th><th>Full Name</th><th>Status</th></tr>";
    foreach ($admins as $admin) {
        $statusColor = $admin['status'] === 'active' ? '#28a745' : '#dc3545';
        echo "<tr>";
        echo "<td>{$admin['admin_id']}</td>";
        echo "<td><strong>{$admin['username']}</strong></td>";
        echo "<td>{$admin['role']}</td>";
        echo "<td>{$admin['full_name']}</td>";
        echo "<td style='color: $statusColor; font-weight: bold;'>{$admin['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<div class='error'>❌ <strong>ERROR:</strong> " . $e->getMessage() . "</div>";
}

// Test 5: Test password verification
echo "<h2>5. Password Hash Test</h2>";
$testPassword = 'Admin@2025';
echo "<div class='info'>📝 Testing password: <code>$testPassword</code></div>";

try {
    $testUser = $db->fetchOne("SELECT username, password_hash, status FROM ADMINISTRATORS WHERE username = 'super_admin'");
    
    if (!$testUser) {
        echo "<div class='error'>❌ <strong>ERROR:</strong> User 'super_admin' not found</div>";
        echo "<div class='warning'>⚠️ Try one of the usernames listed above</div>";
    } else {
        echo "<div class='success'>✅ Found user: <code>{$testUser['username']}</code></div>";
        echo "<div class='info'>📝 Account status: <code>{$testUser['status']}</code></div>";
        
        if ($testUser['status'] !== 'active') {
            echo "<div class='error'>❌ <strong>ERROR:</strong> Account is not active!</div>";
        }
        
        // Test password verification
        if (password_verify($testPassword, $testUser['password_hash'])) {
            echo "<div class='success'>✅ <strong>SUCCESS:</strong> Password verification works! Password is correct.</div>";
        } else {
            echo "<div class='error'>❌ <strong>ERROR:</strong> Password verification failed!</div>";
            echo "<div class='warning'>⚠️ The password hash in the database doesn't match <code>$testPassword</code></div>";
            
            // Generate new hash
            $newHash = password_hash($testPassword, PASSWORD_BCRYPT);
            echo "<div class='info'>";
            echo "📝 <strong>To fix this, run this SQL in phpMyAdmin:</strong><br><br>";
            echo "<code>UPDATE ADMINISTRATORS SET password_hash = '$newHash' WHERE username = 'super_admin';</code>";
            echo "</div>";
        }
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ <strong>ERROR:</strong> " . $e->getMessage() . "</div>";
}

// Test 6: Generate correct password hashes
echo "<h2>6. Generate New Password Hashes</h2>";
echo "<div class='info'>If passwords aren't working, run these SQL commands in phpMyAdmin:</div>";

$correctHash = password_hash('Admin@2025', PASSWORD_BCRYPT);
echo "<pre style='background: #f4f4f4; padding: 15px; overflow-x: auto;'>";
echo "-- Update all admin passwords to: Admin@2025\n";
echo "UPDATE ADMINISTRATORS SET password_hash = '$correctHash' WHERE username = 'super_admin';\n";
echo "UPDATE ADMINISTRATORS SET password_hash = '$correctHash' WHERE username = 'admin_tech';\n";
echo "UPDATE ADMINISTRATORS SET password_hash = '$correctHash' WHERE username = 'admin_events';\n";
echo "UPDATE ADMINISTRATORS SET password_hash = '$correctHash' WHERE username = 'moderator_1';\n";
echo "</pre>";

// Summary
echo "<h2>7. Summary & Next Steps</h2>";
echo "<div class='success'>";
echo "<strong>✅ If all tests above passed, you should be able to login with:</strong><br>";
echo "Username: Any username from the table above<br>";
echo "Password: <code>Admin@2025</code>";
echo "</div>";

echo "<div class='info'>";
echo "<strong>🔗 Quick Links:</strong><br>";
echo "<a href='admin/login.php'>→ Go to Login Page</a><br>";
echo "<a href='http://localhost/phpmyadmin'>→ Open phpMyAdmin</a>";
echo "</div>";

echo "<hr style='margin: 30px 0;'>";
echo "<p style='color: #666;'><strong>⚠️ Security Note:</strong> Delete this file (<code>test_login.php</code>) after debugging!</p>";

echo "</body></html>";
?>
