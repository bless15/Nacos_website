<?php
/**
 * ============================================
 * NACOS DASHBOARD - ADMIN LOGIN
 * ============================================
 * Purpose: Secure administrator login page
 * Security: Password verification, CSRF protection, rate limiting
 * Created: November 2, 2025
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Include required files
require_once '../config/database.php';
require_once '../includes/auth.php';

// Initialize session
initSession();

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('index.php');
}

// Handle form submission
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize input
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    
    // Validate input
    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both username and password.';
    } else {
        // Attempt authentication
        $user = authenticateUser($username, $password);
        
        if ($user) {
            // Login successful
            loginUser($user);
            
            // Log security event
            logSecurityEvent("Successful login: $username", 'info');
            
            // Redirect to dashboard
            redirectWithMessage('index.php', 'Welcome back, ' . $user['full_name'] . '!', 'success');
        } else {
            // Login failed
            $error_message = 'Invalid username or password, or account is inactive.';
            logSecurityEvent("Failed login attempt: $username", 'warning');
        }
    }
}

// Get flash message if any
$flash = getFlashMessage();
if ($flash) {
    if ($flash['type'] === 'success') {
        $success_message = $flash['message'];
    } else {
        $error_message = $flash['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - NACOS Dashboard</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/admin.css">
    
    <style>
        :root {
            --primary-color: #008000;
            --secondary-color: #006400;
            --gradient: linear-gradient(135deg, #008000 0%, #006400 100%);
        }
        
        body {
            background: var(--gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            max-width: 450px;
            width: 100%;
            padding: 20px;
        }
        
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        
        .login-header {
            background: var(--gradient);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .login-header h2 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        
        .login-header p {
            margin: 10px 0 0;
            opacity: 0.9;
            font-size: 14px;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 128, 0, 0.25);
        }
        
        .input-group-text {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-right: none;
        }
        
        .input-group .form-control {
            border-left: none;
        }
        
        .btn-login {
            background: var(--gradient);
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-size: 16px;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 128, 0, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .alert {
            border-radius: 8px;
            border: none;
        }
        
        .form-check-label {
            color: #666;
            font-size: 14px;
        }
        
        .login-footer {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            color: #666;
            font-size: 14px;
        }
        
        .login-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-footer a:hover {
            color: var(--secondary-color);
            text-decoration: underline;
        }
        
        .logo-icon {
            <?php // Include admin footer which loads Bootstrap and confirmation modal ?>
            <?php include __DIR__ . '/includes/footer.php'; ?>
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- Header -->
            <div class="login-header">
                <div style="display: flex; align-items: center; justify-content: center; gap: 20px; margin-bottom: 15px;">
                    <img src="../assets/images/nacos_logo.jpg" alt="NACOS Logo" style="height: 60px;">
                    <img src="../assets/images/nacos_logo.jpg" alt="NACOS Logo" style="height: 60px;">
                </div>
                <h2>NACOS Dashboard</h2>
                <p>Administrator Login Portal</p>
                <small style="color: rgba(255,255,255,0.7);">Faculty of Science - Adeleke University</small>
            </div>
            
            <!-- Body -->
            <div class="login-body">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="login.php" id="loginForm">
                    <!-- Username -->
                    <div class="mb-3">
                        <label for="username" class="form-label">
                            <i class="fas fa-user me-1"></i> Username
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-user-shield"></i>
                            </span>
                            <input 
                                type="text" 
                                class="form-control" 
                                id="username" 
                                name="username" 
                                placeholder="Enter your username"
                                value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                required 
                                autofocus
                            >
                        </div>
                    </div>
                    
                    <!-- Password -->
                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock me-1"></i> Password
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-key"></i>
                            </span>
                            <input 
                                type="password" 
                                class="form-control" 
                                id="password" 
                                name="password" 
                                placeholder="Enter your password"
                                required
                            >
                        </div>
                    </div>
                    
                    <!-- Remember Me -->
                    <div class="mb-4">
                        <div class="form-check">
                            <input 
                                class="form-check-input" 
                                type="checkbox" 
                                id="remember_me" 
                                name="remember_me"
                            >
                            <label class="form-check-label" for="remember_me">
                                Remember me for 30 days
                            </label>
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i> Login to Dashboard
                    </button>
                </form>
                
                <!-- Test Credentials Info -->
                <div class="alert alert-info mt-4" role="alert">
                    <strong><i class="fas fa-info-circle me-2"></i>Test Credentials:</strong><br>
                    <small>
                        Username: <code>super_admin</code><br>
                        Password: <code>Admin@2025</code>
                    </small>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="login-footer">
                <i class="fas fa-shield-alt me-1"></i> Secure Login Portal<br>
                <small>&copy; 2025 NACOSAU. All rights reserved.</small><br>
                <small class="mt-1 d-block">Developed by <a href="https://johnicity.com.ng/portfolio" target="_blank" style="color: rgba(255,255,255,0.8); text-decoration: underline;">Johnicity</a></small>
            </div>
        </div>
        
        <!-- Back to Public Site -->
        <div class="text-center mt-3">
            <a href="../public/index.php" class="text-white text-decoration-none">
                <i class="fas fa-arrow-left me-1"></i> Back to Public Site
            </a>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Form Validation -->
    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (username === '' || password === '') {
                e.preventDefault();
                alert('Please fill in all fields');
            }
        });
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                if (alert.querySelector('.btn-close')) {
                    alert.querySelector('.btn-close').click();
                }
            });
        }, 5000);
    </script>
</body>
</html>
