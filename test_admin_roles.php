<?php
/**
 * ============================================
 * ADMIN ROLE MANAGEMENT - SYSTEM TEST
 * ============================================
 * Test all authentication functions and role management
 * Run this from command line: php test_admin_roles.php
 * ============================================
 */

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

echo "========================================\n";
echo "ADMIN ROLE MANAGEMENT SYSTEM TEST\n";
echo "========================================\n\n";

$tests_passed = 0;
$tests_failed = 0;

// Test 1: Database Connection
echo "Test 1: Database Connection... ";
try {
    $db = getDB();
    echo "✓ PASSED\n";
    $tests_passed++;
} catch (Exception $e) {
    echo "✗ FAILED: " . $e->getMessage() . "\n";
    $tests_failed++;
}

// Test 2: Check MEMBERS table has role column
echo "Test 2: MEMBERS table role column... ";
try {
    $result = $db->fetchOne("SHOW COLUMNS FROM MEMBERS LIKE 'role'");
    if ($result) {
        echo "✓ PASSED (Type: {$result['Type']})\n";
        $tests_passed++;
    } else {
        echo "✗ FAILED: Role column not found\n";
        $tests_failed++;
    }
} catch (Exception $e) {
    echo "✗ FAILED: " . $e->getMessage() . "\n";
    $tests_failed++;
}

// Test 3: Check for admin members
echo "Test 3: Admin members exist... ";
try {
    $admins = $db->fetchAll("SELECT member_id, full_name, matric_no, role FROM MEMBERS WHERE role = 'admin'");
    if (count($admins) > 0) {
        echo "✓ PASSED (" . count($admins) . " admin(s) found)\n";
        foreach ($admins as $admin) {
            echo "   - {$admin['full_name']} ({$admin['matric_no']})\n";
        }
        $tests_passed++;
    } else {
        echo "✗ FAILED: No admin members found\n";
        $tests_failed++;
    }
} catch (Exception $e) {
    echo "✗ FAILED: " . $e->getMessage() . "\n";
    $tests_failed++;
}

// Test 4: Check auth functions exist
echo "Test 4: Auth functions exist... ";
$functions = ['isMemberLoggedIn', 'isMemberAdmin', 'requireAdminRole', 'getCurrentMember'];
$all_exist = true;
foreach ($functions as $func) {
    if (!function_exists($func)) {
        echo "✗ FAILED: Function $func not found\n";
        $all_exist = false;
        break;
    }
}
if ($all_exist) {
    echo "✓ PASSED (All required functions exist)\n";
    $tests_passed++;
} else {
    $tests_failed++;
}

// Test 5: Check admin files use requireAdminRole()
echo "Test 5: Admin files protection... ";
$admin_files = glob(__DIR__ . '/admin/*.php');
$protected = 0;
$unprotected = [];
foreach ($admin_files as $file) {
    $basename = basename($file);
    if (in_array($basename, ['login.php', 'logout.php', 'update_auth_protection.php', 'fix_current_user.php'])) {
        continue; // Skip these files
    }
    $content = file_get_contents($file);
    if (strpos($content, 'requireAdminRole()') !== false) {
        $protected++;
    } else {
        $unprotected[] = $basename;
    }
}

if (count($unprotected) === 0) {
    echo "✓ PASSED ($protected files protected)\n";
    $tests_passed++;
} else {
    echo "✗ FAILED: " . count($unprotected) . " files not protected\n";
    foreach ($unprotected as $file) {
        echo "   - $file\n";
    }
    $tests_failed++;
}

// Test 6: Check login.php stores role
echo "Test 6: Login stores member role... ";
$login_content = file_get_contents(__DIR__ . '/public/login.php');
if (strpos($login_content, "\$_SESSION['member_role']") !== false) {
    echo "✓ PASSED\n";
    $tests_passed++;
} else {
    echo "✗ FAILED: Login doesn't store role in session\n";
    $tests_failed++;
}

// Test 7: Check toggle_admin_role.php exists
echo "Test 7: Role management file exists... ";
if (file_exists(__DIR__ . '/admin/toggle_admin_role.php')) {
    echo "✓ PASSED\n";
    $tests_passed++;
} else {
    echo "✗ FAILED: toggle_admin_role.php not found\n";
    $tests_failed++;
}

// Test 8: Check view_member.php has role management UI
echo "Test 8: Role management UI exists... ";
$view_member_content = file_get_contents(__DIR__ . '/admin/view_member.php');
if (strpos($view_member_content, 'roleModal') !== false && 
    strpos($view_member_content, 'Manage Role') !== false) {
    echo "✓ PASSED\n";
    $tests_passed++;
} else {
    echo "✗ FAILED: Role management UI not found in view_member.php\n";
    $tests_failed++;
}

// Test 9: Check for duplicate functions
echo "Test 9: No duplicate functions... ";
$auth_content = file_get_contents(__DIR__ . '/includes/auth.php');
preg_match_all('/function\s+(\w+)\s*\(/', $auth_content, $matches);
$functions_found = $matches[1];
$duplicates = array_diff_assoc($functions_found, array_unique($functions_found));
if (empty($duplicates)) {
    echo "✓ PASSED\n";
    $tests_passed++;
} else {
    echo "✗ FAILED: Duplicate functions found: " . implode(', ', array_unique($duplicates)) . "\n";
    $tests_failed++;
}

// Test 10: Check getCurrentMember includes role
echo "Test 10: getCurrentMember returns role... ";
if (strpos($auth_content, "'role' => \$_SESSION['member_role']") !== false) {
    echo "✓ PASSED\n";
    $tests_passed++;
} else {
    echo "✗ FAILED: getCurrentMember doesn't return role\n";
    $tests_failed++;
}

// Summary
echo "\n========================================\n";
echo "TEST SUMMARY\n";
echo "========================================\n";
echo "Tests Passed: $tests_passed\n";
echo "Tests Failed: $tests_failed\n";
echo "Total Tests: " . ($tests_passed + $tests_failed) . "\n";
echo "Success Rate: " . round(($tests_passed / ($tests_passed + $tests_failed)) * 100, 1) . "%\n";
echo "========================================\n";

if ($tests_failed === 0) {
    echo "\n✓ ALL TESTS PASSED! System is ready.\n";
    exit(0);
} else {
    echo "\n✗ Some tests failed. Please review above.\n";
    exit(1);
}
?>
