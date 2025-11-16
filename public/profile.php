<?php
/**
 * ============================================
 * NACOS DASHBOARD - EDIT PROFILE
 * ============================================
 * Purpose: Allow members to edit their profile information
 * Access: Requires member authentication
 * Created: November 3, 2025
 * ============================================
 */

require_once __DIR__ . '/../includes/security.php';
require_once '../config/database.php';
require_once '../includes/auth.php';

// Require member to be logged in
if (!isMemberLoggedIn()) {
    header('Location: login.php');
    exit();
}

$db = getDB();
$current_member = getCurrentMember();
$member_id = $current_member['member_id'];

// Check profile edit count for the current month
$start_of_month = date('Y-m-01 00:00:00');
$end_of_month = date('Y-m-t 23:59:59');

$edit_count_result = $db->fetchOne(
    "SELECT COUNT(*) as edit_count FROM PROFILE_EDIT_LOGS WHERE member_id = ? AND edit_timestamp BETWEEN ? AND ?",
    [$member_id, $start_of_month, $end_of_month]
);
$edit_count = $edit_count_result['edit_count'] ?? 0;
$edits_left = max(0, 2 - $edit_count);


$errors = [];
$success_profile = '';

// Handle profile information update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if ($edits_left <= 0) {
        $errors[] = "You have reached your monthly limit of 2 profile edits.";
    } else {
        $full_name = sanitizeInput($_POST['full_name']);
        $email = sanitizeInput($_POST['email']);
        $phone = sanitizeInput($_POST['phone']);
        $department = sanitizeInput($_POST['department']);
        $level = sanitizeInput($_POST['level']);

        // Basic validation
        if (empty($full_name) || empty($email) || empty($phone) || empty($department) || empty($level)) {
            $errors[] = "All profile fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        } else {
            // Check if email is already taken by another member
            $existing_member = $db->fetchOne("SELECT member_id FROM MEMBERS WHERE email = ? AND member_id != ?", [$email, $member_id]);
            if ($existing_member) {
                $errors[] = "This email address is already in use by another member.";
            } else {
                // Update profile
                $update_stmt = $db->query(
                    "UPDATE MEMBERS SET full_name = ?, email = ?, phone = ?, department = ?, level = ? WHERE member_id = ?",
                    [$full_name, $email, $phone, $department, $level, $member_id]
                );
                if ($update_stmt) {
                    // Log the edit
                    $db->query("INSERT INTO PROFILE_EDIT_LOGS (member_id) VALUES (?)", [$member_id]);

                    $success_profile = "Your profile has been updated successfully.";
                    // Refresh member data and edit count
                    $current_member = getCurrentMember();
                    $edit_count++;
                    $edits_left = max(0, 2 - $edit_count);
                } else {
                    $errors[] = "Failed to update profile. Please try again.";
                }
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - NACOS</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/public.css">
</head>
<body>

    <!-- Header -->
    <header class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="index.php"><img src="../assets/images/nacos_logo.jpg" alt="NACOS Logo" style="height: 40px; margin-right: 10px;"> NACOS</a>
            <div class="ms-auto d-flex align-items-center">
                <a href="dashboard.php" class="btn btn-outline-secondary me-2">Dashboard</a>
                <a href="logout.php" class="btn btn-outline-primary">Logout</a>
            </div>
        </div>
    </header>

    <main class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-12">
                <h1 class="mb-4">Edit Your Profile</h1>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <p class="mb-0"><?php echo $error; ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Profile Details Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Profile Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            You have <strong><?php echo $edits_left; ?></strong> profile edit(s) remaining for this month.
                        </div>

                        <?php if ($success_profile): ?>
                            <div class="alert alert-success"><?php echo $success_profile; ?></div>
                        <?php endif; ?>
                        <form action="profile.php" method="POST">
                            <div class="mb-3">
                                                        <label for="full_name" class="form-label">Full Name</label>
                                                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($current_member['full_name'] ?? ''); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="email" class="form-label">Email Address</label>
                                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($current_member['email'] ?? ''); ?>" required>
                                                    </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($current_member['phone'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="matric_no" class="form-label">Matriculation Number</label>
                                    <input type="text" class="form-control" id="matric_no" name="matric_no" value="<?php echo htmlspecialchars($current_member['matric_no'] ?? ''); ?>" disabled>
                                    <small class="form-text text-muted">Matric number cannot be changed.</small>
                                </div>
                            </div>
                             <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="department" class="form-label">Department</label>
                                    <input type="text" class="form-control" id="department" name="department" value="<?php echo htmlspecialchars($current_member['department'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="level" class="form-label">Level</label>
                                    <select class="form-select" id="level" name="level" required>
                                        <option value="100" <?php echo ($current_member['level'] ?? '') == '100' ? 'selected' : ''; ?>>100 Level</option>
                                        <option value="200" <?php echo ($current_member['level'] ?? '') == '200' ? 'selected' : ''; ?>>200 Level</option>
                                        <option value="300" <?php echo ($current_member['level'] ?? '') == '300' ? 'selected' : ''; ?>>300 Level</option>
                                        <option value="400" <?php echo ($current_member['level'] ?? '') == '400' ? 'selected' : ''; ?>>400 Level</option>
                                        <option value="500" <?php echo ($current_member['level'] ?? '') == '500' ? 'selected' : ''; ?>>500 Level</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" name="update_profile" class="btn btn-primary" <?php if ($edits_left <= 0) echo 'disabled'; ?>>Save Changes</button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
