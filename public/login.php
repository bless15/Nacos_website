<?php
/**
 * ============================================
 * NACOS DASHBOARD - MEMBER LOGIN
 * ============================================
 * Purpose: Handle member login authentication
 * Access: Public
 * Created: November 3, 2025
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Include required files
require_once '../config/database.php';
require_once '../includes/auth.php';

// Initialize database
$db = getDB();

// Initialize session
initSession();

// Redirect if already logged in
// Temporarily disabled to fix redirect loop
// if (isMemberLoggedIn()) {
//     header("Location: dashboard.php");
//     exit();
// }

$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = "Invalid request. Please try again.";
    } else {
        $matric_no = sanitizeInput($_POST['matric_no']);
        $password = $_POST['password'];

        if (empty($matric_no) || empty($password)) {
            $error_message = "Matriculation number and password are required.";
        } else {
            // First, try to fetch from MEMBERS table
            $member = $db->fetchOne("SELECT * FROM MEMBERS WHERE matric_no = :matric_no", [':matric_no' => $matric_no]);

            // If not found in MEMBERS, try ADMINISTRATORS table using matric_no as username
            if (!$member) {
                $admin = $db->fetchOne("SELECT * FROM ADMINISTRATORS WHERE username = :username", [':username' => $matric_no]);
                
                if ($admin && $admin['status'] === 'active' && password_verify($password, $admin['password_hash'])) {
                    // Admin login successful
                    session_regenerate_id(true);
                    
                    // Set admin session variables (compatible with admin panel)
                    $_SESSION['admin_id'] = $admin['admin_id'];
                    $_SESSION['username'] = $admin['username'];
                    $_SESSION['role'] = $admin['role'];
                    $_SESSION['full_name'] = $admin['full_name'];
                    $_SESSION['email'] = $admin['email'];
                    $_SESSION['logged_in'] = true;
                    $_SESSION['login_time'] = time();
                    $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                    $_SESSION['last_activity'] = time();
                    
                    // Update last login
                    $db->query("UPDATE ADMINISTRATORS SET last_login = NOW() WHERE admin_id = ?", [$admin['admin_id']]);
                    
                    logSecurityEvent("Admin login via member page: " . $admin['username'], 'info');
                    header("Location: ../admin/index.php");
                    exit();
                }
            }

            // Validate member exists and has password
            if (!$member) {
                $error_message = "Invalid matriculation number or password.";
            } elseif (empty($member['password_hash'])) {
                $error_message = "Account not properly configured. Please contact an administrator.";
            } elseif (password_verify($password, $member['password_hash'])) {
                // Check if member is approved
                if ($member['membership_status'] === 'pending') {
                    $error_message = "Your account is pending admin approval. Please wait a few hours while we verify your details. You'll be able to log in once approved.";
                } elseif ($member['membership_status'] === 'inactive' || $member['membership_status'] === 'suspended') {
                    $error_message = "Your account has been deactivated. Please contact an administrator for assistance.";
                } elseif ($member['membership_status'] === 'active') {
                    // Regenerate session ID
                    session_regenerate_id(true);

                    // Set session variables for member
                    $_SESSION['member_id'] = $member['member_id'];
                    $_SESSION['member_matric_no'] = $member['matric_no'];
                    $_SESSION['member_full_name'] = $member['full_name'];
                    $_SESSION['member_role'] = $member['role']; // Store member role
                    $_SESSION['member_email'] = $member['email']; // Store email for future use
                    $_SESSION['member_logged_in'] = true;
                    
                    // Redirect based on role
                    if ($member['role'] === 'admin' || $member['role'] === 'executive') {
                        header("Location: ../admin/index.php"); // Admin dashboard
                    } else {
                        header("Location: dashboard.php"); // Member dashboard
                    }
                    exit();
                } else {
                    $error_message = "Your membership is not active. Please contact an administrator.";
                }
            } else {
                $error_message = "Invalid matriculation number or password.";
            }
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Login - NACOS</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/public.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: var(--gradient);
            padding-top: 0;
        }
        .login-container {
            max-width: 450px;
            width: 100%;
        }
        .login-card {
            background: #fff;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h2 {
            font-weight: 700;
            color: var(--dark-gray);
        }
        .login-header p {
            color: #777;
        }
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(0, 128, 0, 0.25);
        }
        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }
        .btn-primary:hover {
            background: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        a {
            color: var(--primary-color);
        }
        a:hover {
            color: var(--secondary-color);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header text-center">
                <div style="display: flex; align-items: center; justify-content: center; gap: 20px; margin-bottom: 20px;">
                    <img src="../assets/images/nacos_logo.jpg" alt="NACOS Logo" style="height: 80px;">
                    <img src="../assets/images/nacos_logo.jpg" alt="NACOS Logo" style="height: 80px;">
                </div>
                <h2>Member Login</h2>
                <p>Access your NACOS dashboard.</p>
                <small class="text-muted">Faculty of Science Students' Association - Adeleke University</small>
            </div>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <form action="login.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <div class="mb-3">
                    <label for="matric_no" class="form-label">Matriculation Number</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user-graduate"></i></span>
                        <input type="text" class="form-control" id="matric_no" name="matric_no" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                </div>
                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">Login</button>
                </div>
            </form>
            <div class="text-center mt-4">
                <p>Don't have an account? <a href="register.php">Register here</a>.</p>
                <p><a href="index.php">Back to Homepage</a></p>
            </div>
        </div>
    </div>
    
    <?php
    // Show minimal footer on auth pages
    $minimal_footer = true;
    include __DIR__ . '/../includes/public_footer.php';
    ?>
</body>
</html>
