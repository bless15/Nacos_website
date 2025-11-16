<?php
/**
 * ============================================
 * NACOS DASHBOARD - MEMBER REGISTRATION
 * ============================================
 * Purpose: Handle new member registration
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

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isMemberLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error_message = '';
$success_message = '';
$input_data = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = "Invalid request. Please try again.";
    } else {
        // Sanitize and retrieve input data
        $full_name = sanitizeInput($_POST['full_name']);
        $matric_no = sanitizeInput($_POST['matric_no']);
        $email = sanitizeInput($_POST['email']);
        $phone_number = sanitizeInput($_POST['phone_number']);
        $department = sanitizeInput($_POST['department']);
        $level = sanitizeInput($_POST['level']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        $input_data = $_POST;

        // Format matric number
        $formatted_matric = formatMatricNumber($matric_no);

        // Validation
        if (empty($full_name) || empty($matric_no) || empty($email) || empty($department) || empty($level) || empty($password)) {
            $error_message = "All fields with * are required.";
        } elseif (!$formatted_matric) {
            $error_message = "Invalid matriculation number format. Use format: XX/XXXX (e.g., 23/0223)";
        } elseif (!isValidEmail($email)) {
            $error_message = "Invalid email format.";
        } elseif ($password !== $confirm_password) {
            $error_message = "Passwords do not match.";
        } elseif (strlen($password) < 5) {
            $error_message = "Password must be at least 5 characters long.";
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $error_message = "Password must contain at least 1 letter and 1 number.";
        } else {
            // Use formatted matric number
            $matric_no = $formatted_matric;
            // Check if matric number or email already exists
            $existing_member = $db->fetchOne("SELECT * FROM MEMBERS WHERE matric_no = :matric_no OR email = :email", [
                ':matric_no' => $matric_no,
                ':email' => $email
            ]);

            if ($existing_member) {
                if ($existing_member['matric_no'] === $matric_no) {
                    $error_message = "A member with this matriculation number already exists.";
                } else {
                    $error_message = "A member with this email address already exists.";
                }
            } else {
                // Hash the password
                $password_hash = hashPassword($password);

                // Insert new member into database with pending status
                $query = "
                    INSERT INTO MEMBERS 
                    (full_name, matric_no, email, phone, department, `level`, password_hash, membership_status, registration_date) 
                    VALUES 
                    (:full_name, :matric_no, :email, :phone, :department, :level, :password_hash, 'pending', CURDATE())
                ";
                $params = [
                    ':full_name' => $full_name,
                    ':matric_no' => $matric_no,
                    ':email' => $email,
                    ':phone' => $phone_number,
                    ':department' => $department,
                    ':level' => $level,
                    ':password_hash' => $password_hash
                ];

                try {
                    $db->query($query, $params);
                    $success_message = "Registration successful! Your account is pending admin approval. Please wait a few hours while we verify your details. You'll be able to log in once approved.";
                    $input_data = []; // Clear form on success
                } catch (Exception $e) {
                    $error_message = "An error occurred during registration. Please try again later.";
                    logSecurityEvent("Member registration failed: " . $e->getMessage(), 'error');
                }
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
    <title>Member Registration - NACOS</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/public.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: var(--gradient);
            padding: 40px 0;
        }
        .register-container {
            max-width: 700px;
            width: 100%;
        }
        .register-card {
            background: #fff;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .form-control:focus, .form-select:focus {
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
    <div class="register-container">
        <div class="register-card">
            <div class="register-header text-center">
                <div style="display: flex; align-items: center; justify-content: center; gap: 20px; margin-bottom: 20px;">
                    <img src="../assets/images/nacos_logo.jpg" alt="NACOS Logo" style="height: 80px;">
                    <img src="../assets/images/nacos_logo.jpg" alt="NACOS Logo" style="height: 80px;">
                </div>
                <h2>Become a NACOS Member</h2>
                <p>Join our community of innovators.</p>
                <small class="text-muted">Faculty of Science Students' Association - Adeleke University</small>
            </div>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                    <div class="mt-2">
                        <a href="login.php" class="btn btn-primary">Proceed to Login</a>
                    </div>
                </div>
            <?php else: ?>
                <form action="register.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="full_name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($input_data['full_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="matric_no" class="form-label">Matriculation Number *</label>
                            <input type="text" class="form-control" id="matric_no" name="matric_no" value="<?php echo htmlspecialchars($input_data['matric_no'] ?? ''); ?>" pattern="\d{2}/\d{4}" title="Format: XX/XXXX (e.g., 23/0223)" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($input_data['email'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phone_number" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($input_data['phone_number'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="department" class="form-label">Department *</label>
                            <select class="form-select" id="department" name="department" required>
                                <option value="">-- Select Department --</option>
                                <option value="Computer Science" <?php echo (($input_data['department'] ?? '') == 'Computer Science') ? 'selected' : ''; ?>>Computer Science</option>
                                <option value="Computer Information System" <?php echo (($input_data['department'] ?? '') == 'Computer Information System') ? 'selected' : ''; ?>>Computer Information System</option>
                                <option value="Software Engineering" <?php echo (($input_data['department'] ?? '') == 'Software Engineering') ? 'selected' : ''; ?>>Software Engineering</option>
                                <option value="Information Technology" <?php echo (($input_data['department'] ?? '') == 'Information Technology') ? 'selected' : ''; ?>>Information Technology</option>
                                <option value="Cyber Security" <?php echo (($input_data['department'] ?? '') == 'Cyber Security') ? 'selected' : ''; ?>>Cyber Security</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="level" class="form-label">Level *</label>
                            <select class="form-select" id="level" name="level" required>
                                <option value="">-- Select Level --</option>
                                <option value="100" <?php echo (($input_data['level'] ?? '') == '100') ? 'selected' : ''; ?>>100 Level</option>
                                <option value="200" <?php echo (($input_data['level'] ?? '') == '200') ? 'selected' : ''; ?>>200 Level</option>
                                <option value="300" <?php echo (($input_data['level'] ?? '') == '300') ? 'selected' : ''; ?>>300 Level</option>
                                <option value="400" <?php echo (($input_data['level'] ?? '') == '400') ? 'selected' : ''; ?>>400 Level</option>
                                <option value="graduate" <?php echo (($input_data['level'] ?? '') == 'graduate') ? 'selected' : ''; ?>>Graduate</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password *</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">Register</button>
                    </div>
                </form>
            <?php endif; ?>
            
            <div class="text-center mt-4">
                <p>Already have an account? <a href="login.php">Login here</a>.</p>
                <p><a href="index.php">Back to Homepage</a></p>
            </div>
        </div>
    </div>
    
    <?php
    // Always use the minimal footer on the registration page to avoid showing the
    // full site footer here. This ensures only the compact, fixed-bottom footer
    // is rendered for this auth page.
    $minimal_footer = true;
    include __DIR__ . '/../includes/public_footer.php';
    ?>
</body>
</html>
