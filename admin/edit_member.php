<?php
/**
 * ============================================
 * NACOS DASHBOARD - EDIT MEMBER
 * ============================================
 * Purpose: Edit existing member with validation
 * Access: Requires authentication
 * Created: November 3, 2025
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Include required files
require_once '../config/database.php';
require_once '../includes/auth.php';

// Require login
requireAdminRole();

// Get current user
$current_user = getCurrentMember();

// Initialize database
$db = getDB();

// Get member ID
$member_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($member_id <= 0) {
    redirectWithMessage('members.php', 'Invalid member ID', 'error');
}

// Get member data
$member = $db->fetchOne(
    "SELECT * FROM MEMBERS WHERE member_id = ?", 
    [$member_id]
);

if (!$member) {
    redirectWithMessage('members.php', 'Member not found', 'error');
}

// Initialize variables
$errors = [];

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
    
    // Check for duplicate matric number (excluding current member)
    if (empty($errors)) {
        $check_matric = $db->fetchOne(
            "SELECT member_id FROM MEMBERS WHERE matric_no = ? AND member_id != ?", 
            [$matric_no, $member_id]
        );
        
        if ($check_matric) {
            $errors[] = "Matric number already exists for another member";
        }
    }
    
    // Check for duplicate email (excluding current member)
    if (empty($errors)) {
        $check_email = $db->fetchOne(
            "SELECT member_id FROM MEMBERS WHERE email = ? AND member_id != ?", 
            [$email, $member_id]
        );
        
        if ($check_email) {
            $errors[] = "Email address already exists for another member";
        }
    }
    
    // If no errors, update member
    if (empty($errors)) {
        try {
            $query = "UPDATE MEMBERS SET 
                        matric_no = ?, 
                        full_name = ?, 
                        email = ?, 
                        phone = ?, 
                        department = ?, 
                        level = ?, 
                        gender = ?, 
                        registration_date = ?, 
                        membership_status = ?, 
                        bio = ?, 
                        github_username = ?, 
                        linkedin_url = ?, 
                        skills = ?
                      WHERE member_id = ?";
            
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
                $skills,
                $member_id
            ]);
            
            redirectWithMessage(
                'members.php', 
                "Member '$full_name' updated successfully!", 
                'success'
            );
            
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    } else {
        // If there are errors, update member array with POST data for display
        $member = array_merge($member, $_POST);
    }
}

// Get departments for dropdown
$departments = $db->fetchAll("SELECT DISTINCT department FROM MEMBERS ORDER BY department");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Member - NACOS Dashboard</title>
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
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 260px;
            background: var(--sidebar-bg);
            color: white;
            overflow-y: auto;
            z-index: 1000;
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
        
        .main-content {
            margin-left: 260px;
            padding: 20px;
            min-height: 100vh;
        }
        
        .top-bar {
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }
        
        .form-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            max-width: 900px;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .form-section h5 {
            color: var(--primary-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .required {
            color: #dc3545;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .form-text {
            font-size: 12px;
            color: #666;
        }
        
        .member-info-box {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
        }
        
        .member-info-box h4 {
            margin: 0;
            font-size: 20px;
        }
        
        .member-info-box p {
            margin: 5px 0 0;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="../assets/images/nacos_logo.jpg" alt="NACOS Logo" style="height: 60px; margin-bottom: 10px;">
            <h4>NACOS Dashboard</h4>
            <small>Admin Panel</small>
        </div>
        
        <div class="sidebar-menu">
            <a href="index.php">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="members.php" class="active">
                <i class="fas fa-users"></i> Members
            </a>
            <a href="projects.php">
                <i class="fas fa-project-diagram"></i> Projects
            </a>
            <a href="events.php">
                <i class="fas fa-calendar-alt"></i> Events
            </a>
            <a href="resources.php">
                <i class="fas fa-book"></i> Resources
            </a>
            <a href="partners.php">
                <i class="fas fa-handshake"></i> Partners
            </a>
            <a href="documents.php">
                <i class="fas fa-folder"></i> Documents
            </a>
            <hr style="border-color: rgba(255,255,255,0.1);">
            <a href="../public/index.php" target="_blank">
                <i class="fas fa-external-link-alt"></i> View Public Site
            </a>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="d-flex justify-content-between align-items-center">
                <h3><i class="fas fa-user-edit me-2"></i> Edit Member</h3>
                <div class="d-flex gap-2">
                    <a href="view_member.php?id=<?php echo $member_id; ?>" class="btn btn-outline-info">
                        <i class="fas fa-eye me-2"></i> View Profile
                    </a>
                    <a href="members.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Members
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Member Info -->
        <div class="member-info-box">
            <h4><i class="fas fa-user me-2"></i> Editing: <?php echo htmlspecialchars($member['full_name']); ?></h4>
            <p><i class="fas fa-id-card me-2"></i> <?php echo htmlspecialchars($member['matric_no']); ?></p>
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
        
        <!-- Edit Member Form -->
        <div class="form-card">
            <form method="POST" action="edit_member.php?id=<?php echo $member_id; ?>" id="editMemberForm">
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
                                   value="<?php echo htmlspecialchars($member['matric_no']); ?>"
                                   required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="full_name" class="form-label">
                                Full Name <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="full_name" 
                                   name="full_name" 
                                   value="<?php echo htmlspecialchars($member['full_name']); ?>"
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
                                   value="<?php echo htmlspecialchars($member['email']); ?>"
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
                                   value="<?php echo htmlspecialchars($member['phone'] ?? ''); ?>">
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
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept['department']); ?>"
                                        <?php echo $member['department'] === $dept['department'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['department']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="level" class="form-label">
                                Level <span class="required">*</span>
                            </label>
                            <select class="form-select" id="level" name="level" required>
                                <?php 
                                $levels = ['100', '200', '300', '400', '500'];
                                foreach ($levels as $level): 
                                ?>
                                    <option value="<?php echo $level; ?>" <?php echo $member['level'] === $level ? 'selected' : ''; ?>>
                                        <?php echo $level; ?> Level
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="gender" class="form-label">
                                Gender
                            </label>
                            <select class="form-select" id="gender" name="gender">
                                <option value="" <?php echo empty($member['gender']) ? 'selected' : ''; ?>>Prefer not to say</option>
                                <option value="Male" <?php echo $member['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo $member['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo $member['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
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
                                   value="<?php echo htmlspecialchars($member['registration_date']); ?>"
                                   required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="membership_status" class="form-label">
                                Membership Status <span class="required">*</span>
                            </label>
                            <select class="form-select" id="membership_status" name="membership_status" required>
                                <option value="active" <?php echo $member['membership_status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $member['membership_status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="alumni" <?php echo $member['membership_status'] === 'alumni' ? 'selected' : ''; ?>>Alumni</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Additional Information -->
                <div class="form-section">
                    <h5><i class="fas fa-link me-2"></i> Additional Information</h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="github_username" class="form-label">
                                <i class="fab fa-github me-1"></i> GitHub Username
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="github_username" 
                                   name="github_username" 
                                   value="<?php echo htmlspecialchars($member['github_username'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="linkedin_url" class="form-label">
                                <i class="fab fa-linkedin me-1"></i> LinkedIn Profile URL
                            </label>
                            <input type="url" 
                                   class="form-control" 
                                   id="linkedin_url" 
                                   name="linkedin_url" 
                                   value="<?php echo htmlspecialchars($member['linkedin_url'] ?? ''); ?>">
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
                               value="<?php echo htmlspecialchars($member['skills'] ?? ''); ?>">
                        <div class="form-text">Separate skills with commas</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="bio" class="form-label">
                            <i class="fas fa-user me-1"></i> Bio
                        </label>
                        <textarea class="form-control" 
                                  id="bio" 
                                  name="bio" 
                                  rows="3"><?php echo htmlspecialchars($member['bio'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <!-- Submit Buttons -->
                <div class="d-flex gap-2 justify-content-end">
                    <a href="members.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-2"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Update Member
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <?php // Include admin footer which loads Bootstrap and confirmation modal ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>
    
    <script>
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

