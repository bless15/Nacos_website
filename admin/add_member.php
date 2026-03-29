<?php
/**
 * ============================================
 * NACOS DASHBOARD - ADD NEW MEMBER
 * ============================================
 * Purpose: Add new member with validation
 * Access: Requires authentication
 * Created: November 3, 2025
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Bootstrap and includes
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Require full admin privileges
requireFullAdminRole();

// Get current user
$current_user = getCurrentMember();

// Initialize database
$db = getDB();

// Initialize variables
$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize input
    $matric_no = sanitizeInput($_POST['matric_no'] ?? '');
    $full_name = sanitizeInput($_POST['full_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $department = sanitizeInput($_POST['department'] ?? '');
    $level = sanitizeInput($_POST['level'] ?? '');
    $gender = sanitizeInput($_POST['gender'] ?? '');
    $registration_date = sanitizeInput($_POST['registration_date'] ?? '');
    $membership_status = sanitizeInput($_POST['membership_status'] ?? 'active');
    $bio = sanitizeInput($_POST['bio'] ?? '');
    $github_username = sanitizeInput($_POST['github_username'] ?? '');
    $linkedin_url = sanitizeInput($_POST['linkedin_url'] ?? '');
    $skills = sanitizeInput($_POST['skills'] ?? '');
    
    // Validation
    if (empty($matric_no)) {
        $errors[] = "Matric number is required";
    }
    
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!isValidEmail($email)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($department)) {
        $errors[] = "Department is required";
    }
    
    if (empty($level)) {
        $errors[] = "Level is required";
    }
    
    if (empty($registration_date)) {
        $errors[] = "Registration date is required";
    }
    
    // Check for duplicate matric number
    if (empty($errors)) {
        $check_matric = $db->fetchOne(
            "SELECT member_id FROM members WHERE matric_no = ?", 
            [$matric_no]
        );
        
        if ($check_matric) {
            $errors[] = "Matric number already exists";
        }
    }
    
    // Check for duplicate email
    if (empty($errors)) {
        $check_email = $db->fetchOne(
            "SELECT member_id FROM members WHERE email = ?", 
            [$email]
        );
        
        if ($check_email) {
            $errors[] = "Email address already exists";
        }
    }
    
    // If no errors, insert member
    if (empty($errors)) {
        try {
            $query = "INSERT INTO members (
                        matric_no, full_name, email, phone, department, level, 
                        gender, registration_date, membership_status, bio, 
                        github_username, linkedin_url, skills
                      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $db->query($query, [
                $matric_no,
                $full_name,
                $email,
                $phone,
                $department,
                $level,
                $gender,
                $registration_date,
                $membership_status,
                $bio,
                $github_username,
                $linkedin_url,
                $skills
            ]);
            
            redirectWithMessage(
                'members.php', 
                "Member '$full_name' added successfully!", 
                'success'
            );
            
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Get departments for dropdown (from existing members)
$departments = $db->fetchAll("SELECT DISTINCT department FROM members ORDER BY department");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Member - NACOS Dashboard</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --sidebar-bg: #2c3e50;
            --sidebar-hover: #34495e;
            --success-start: #11998e;
            --success-end: #38ef7d;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 260px;
            background: var(--sidebar-bg);
            color: white;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 1000;
            box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-header {
            padding: 20px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            text-align: center;
        }
        
        .sidebar-header h4 {
            margin: 10px 0 5px;
            font-size: 20px;
            font-weight: 600;
        }
        
        .sidebar-menu a {
            display: block;
            padding: 12px 20px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: var(--sidebar-hover);
            color: white;
            padding-left: 30px;
        }
        
        .sidebar-menu a i {
            width: 25px;
            margin-right: 10px;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 30px;
            min-height: 100vh;
        }
        
        /* Top Bar */
        .top-bar {
            background: white;
            padding: 25px 30px;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
            animation: fadeInDown 0.6s ease;
        }
        
        .top-bar h3 {
            color: #2c3e50;
            font-weight: 700;
            margin: 0;
        }

        .menu-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 10px;
            border: 2px solid #dee2e6;
            background: #fff;
            color: #2c3e50;
            font-size: 18px;
        }

        .menu-toggle:hover {
            background: #f8f9fa;
        }

        .sidebar-overlay {
            display: none;
        }
        
        /* Form Card */
        .form-card {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            max-width: 1000px;
            animation: fadeInUp 0.6s ease backwards;
            animation-delay: 0.2s;
        }
        
        /* Form Sections */
        .form-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            border-left: 5px solid var(--primary-color);
            position: relative;
            overflow: hidden;
        }
        
        .form-section::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 150px;
            height: 150px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            opacity: 0.05;
            border-radius: 50%;
            transform: translate(30%, -30%);
        }
        
        .form-section h5 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.3rem;
            position: relative;
            z-index: 1;
        }
        
        .form-section h5 i {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        /* Form Controls */
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
            font-size: 14px;
            position: relative;
            z-index: 1;
        }
        
        .required {
            color: #dc3545;
        }
        
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 16px;
            transition: all 0.3s ease;
            font-size: 14px;
            position: relative;
            z-index: 1;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
            outline: none;
        }
        
        textarea.form-control {
            resize: vertical;
        }
        
        .form-text {
            font-size: 13px;
            color: #6c757d;
            margin-top: 6px;
            position: relative;
            z-index: 1;
        }
        
        /* Buttons */
        .btn {
            border-radius: 10px;
            padding: 12px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .btn-outline-secondary {
            border: 2px solid #6c757d;
            color: #6c757d;
            background: transparent;
        }
        
        .btn-outline-secondary:hover {
            background: #6c757d;
            color: white;
            transform: translateY(-2px);
        }
        
        /* Alerts */
        .alert {
            border-radius: 12px;
            padding: 20px 25px;
            border: none;
            margin-bottom: 25px;
            animation: fadeInDown 0.6s ease;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, rgba(240, 147, 251, 0.15), rgba(245, 87, 108, 0.15));
            border-left: 5px solid #f093fb;
        }
        
        /* Animations */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 991.98px) {
            .sidebar {
                position: fixed;
                width: 260px;
                height: 100vh;
                transform: translateX(-100%);
                box-shadow: 6px 0 20px rgba(0, 0, 0, 0.2);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .menu-toggle {
                display: inline-flex;
            }

            .sidebar-overlay {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.45);
                z-index: 999;
            }

            .sidebar-overlay.show {
                display: block;
            }

            .top-bar,
            .form-card {
                padding: 20px;
            }

            .form-section {
                padding: 20px;
                margin-bottom: 20px;
            }

            .form-section h5 {
                font-size: 1.1rem;
                gap: 10px;
            }

            .form-section h5 i {
                width: 38px;
                height: 38px;
                font-size: 16px;
            }
        }

        @media (max-width: 767.98px) {
            .sidebar-header {
                padding: 16px;
            }

            .sidebar-header h4 {
                font-size: 18px;
            }

            .sidebar-menu a {
                padding: 10px 16px;
            }

            .sidebar-menu a:hover,
            .sidebar-menu a.active {
                padding-left: 20px;
            }

            .main-content {
                padding: 15px;
            }

            .top-bar,
            .form-card {
                padding: 16px;
                border-radius: 12px;
            }

            .form-section {
                padding: 16px;
                border-radius: 12px;
            }

            .top-bar h3 {
                font-size: 1.25rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .action-buttons .btn {
                width: 100%;
            }

            .btn {
                width: 100%;
                padding: 11px 16px;
            }
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="menu-toggle" id="menuToggle" aria-label="Toggle navigation menu">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h3><i class="fas fa-user-plus me-2"></i> Add New Member</h3>
                </div>
                <a href="members.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Members
                </a>
            </div>
        </div>
        
        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong><i class="fas fa-exclamation-circle me-2"></i> Please fix the following errors:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Add Member Form -->
        <div class="form-card">
            <form method="POST" action="add_member.php" id="addMemberForm">
                <!-- Basic Information -->
                <div class="form-section">
                    <h5><i class="fas fa-info-circle me-2"></i> Basic Information</h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="matric_no" class="form-label">
                                Matric Number <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="matric_no" 
                                   name="matric_no" 
                                   placeholder="e.g., CSC/2024/001"
                                   value="<?php echo htmlspecialchars($_POST['matric_no'] ?? ''); ?>"
                                   required>
                            <div class="form-text">Must be unique identifier</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="full_name" class="form-label">
                                Full Name <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="full_name" 
                                   name="full_name" 
                                   placeholder="Enter full name"
                                   value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                                   required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">
                                Email Address <span class="required">*</span>
                            </label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   placeholder="student@example.com"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                   required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">
                                Phone Number
                            </label>
                            <input type="tel" 
                                   class="form-control" 
                                   id="phone" 
                                   name="phone" 
                                   placeholder="08012345678"
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Academic Information -->
                <div class="form-section">
                    <h5><i class="fas fa-graduation-cap me-2"></i> Academic Information</h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="department" class="form-label">
                                Department <span class="required">*</span>
                            </label>
                            <select class="form-select" id="department" name="department" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept['department']); ?>"
                                        <?php echo (isset($_POST['department']) && $_POST['department'] === $dept['department']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['department']); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="Computer Science">Computer Science</option>
                                <option value="Software Engineering">Software Engineering</option>
                                <option value="Information Technology">Information Technology</option>
                                <option value="Cyber Security">Cyber Security</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="level" class="form-label">
                                Level <span class="required">*</span>
                            </label>
                            <select class="form-select" id="level" name="level" required>
                                <option value="">Select Level</option>
                                <option value="100" <?php echo (isset($_POST['level']) && $_POST['level'] === '100') ? 'selected' : ''; ?>>100 Level</option>
                                <option value="200" <?php echo (isset($_POST['level']) && $_POST['level'] === '200') ? 'selected' : ''; ?>>200 Level</option>
                                <option value="300" <?php echo (isset($_POST['level']) && $_POST['level'] === '300') ? 'selected' : ''; ?>>300 Level</option>
                                <option value="400" <?php echo (isset($_POST['level']) && $_POST['level'] === '400') ? 'selected' : ''; ?>>400 Level</option>
                                <option value="500" <?php echo (isset($_POST['level']) && $_POST['level'] === '500') ? 'selected' : ''; ?>>500 Level</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="gender" class="form-label">
                                Gender
                            </label>
                            <select class="form-select" id="gender" name="gender">
                                <option value="">Prefer not to say</option>
                                <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="registration_date" class="form-label">
                                Registration Date <span class="required">*</span>
                            </label>
                            <input type="date" 
                                   class="form-control" 
                                   id="registration_date" 
                                   name="registration_date" 
                                   value="<?php echo htmlspecialchars($_POST['registration_date'] ?? date('Y-m-d')); ?>"
                                   required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="membership_status" class="form-label">
                                Membership Status <span class="required">*</span>
                            </label>
                            <select class="form-select" id="membership_status" name="membership_status" required>
                                <option value="active" selected>Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="alumni">Alumni</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Additional Information -->
                <div class="form-section">
                    <h5><i class="fas fa-link me-2"></i> Additional Information (Optional)</h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="github_username" class="form-label">
                                <i class="fab fa-github me-1"></i> GitHub Username
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="github_username" 
                                   name="github_username" 
                                   placeholder="username"
                                   value="<?php echo htmlspecialchars($_POST['github_username'] ?? ''); ?>">
                            <div class="form-text">Without @ symbol</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="linkedin_url" class="form-label">
                                <i class="fab fa-linkedin me-1"></i> LinkedIn Profile URL
                            </label>
                            <input type="url" 
                                   class="form-control" 
                                   id="linkedin_url" 
                                   name="linkedin_url" 
                                   placeholder="https://linkedin.com/in/username"
                                   value="<?php echo htmlspecialchars($_POST['linkedin_url'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="skills" class="form-label">
                            <i class="fas fa-code me-1"></i> Skills
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="skills" 
                               name="skills" 
                               placeholder="e.g., Python, JavaScript, React, Node.js"
                               value="<?php echo htmlspecialchars($_POST['skills'] ?? ''); ?>">
                        <div class="form-text">Separate skills with commas</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="bio" class="form-label">
                            <i class="fas fa-user me-1"></i> Bio
                        </label>
                        <textarea class="form-control" 
                                  id="bio" 
                                  name="bio" 
                                  rows="3" 
                                  placeholder="Tell us about yourself..."><?php echo htmlspecialchars($_POST['bio'] ?? ''); ?></textarea>
                        <div class="form-text">Brief description about the member</div>
                    </div>
                </div>
                
                <!-- Submit Buttons -->
                <div class="d-flex gap-2 justify-content-end action-buttons">
                    <a href="members.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-2"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Add Member
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <?php // Include admin footer which loads Bootstrap and confirmation modal ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>
    
    <script>
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.querySelector('.sidebar');
        const sidebarOverlay = document.getElementById('sidebarBackdrop') || document.getElementById('sidebarOverlay');

        function closeSidebar() {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        }

        if (menuToggle && sidebar && sidebarOverlay) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('show');
                sidebarOverlay.classList.toggle('show');
            });

            sidebarOverlay.addEventListener('click', closeSidebar);

            window.addEventListener('resize', function() {
                if (window.innerWidth > 991.98) {
                    closeSidebar();
                }
            });
        }

        // Form validation
        document.getElementById('addMemberForm').addEventListener('submit', function(e) {
            const matricNo = document.getElementById('matric_no').value.trim();
            const fullName = document.getElementById('full_name').value.trim();
            const email = document.getElementById('email').value.trim();
            const department = document.getElementById('department').value;
            const level = document.getElementById('level').value;
            
            if (!matricNo || !fullName || !email || !department || !level) {
                e.preventDefault();
                alert('Please fill in all required fields marked with *');
                return false;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address');
                return false;
            }
        });
        
        // Auto-dismiss alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                const closeBtn = alert.querySelector('.btn-close');
                if (closeBtn) closeBtn.click();
            });
        }, 8000);
    </script>
</body>
</html>

