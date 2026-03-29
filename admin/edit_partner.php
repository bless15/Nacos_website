<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/config.php';
require_once '../includes/auth.php';
requireAdminRole();

$db = getDB();
$partner_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$partner = $db->fetchOne("SELECT * FROM partners WHERE partner_id = :id", [':id' => $partner_id]);

if (!$partner) redirectWithMessage('partners.php', 'Partner not found.', 'error');

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = "Invalid request.";
    } else {
        $company_name = sanitizeInput($_POST['company_name']);
        $description = sanitizeInput($_POST['description']);
        $partnership_type = sanitizeInput($_POST['partnership_type']);
        $status = sanitizeInput($_POST['status']);
        $website_url = !empty($_POST['website_url']) ? sanitizeInput($_POST['website_url']) : null;
        $contact_person = !empty($_POST['contact_person']) ? sanitizeInput($_POST['contact_person']) : null;
        $contact_email = !empty($_POST['contact_email']) ? sanitizeInput($_POST['contact_email']) : null;
        $contact_phone = !empty($_POST['contact_phone']) ? sanitizeInput($_POST['contact_phone']) : null;
        $partnership_start_date = !empty($_POST['partnership_start_date']) ? sanitizeInput($_POST['partnership_start_date']) : null;
        $partnership_end_date = !empty($_POST['partnership_end_date']) ? sanitizeInput($_POST['partnership_end_date']) : null;
        $value_offered = !empty($_POST['value_offered']) ? sanitizeInput($_POST['value_offered']) : null;
    $visibility = sanitizeInput($_POST['visibility']);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;

        try {
            $company_logo = $partner['company_logo'];

            if (!empty($_FILES['partner_logo']['name'])) {
                $file = $_FILES['partner_logo'];
                if ($file['error'] === UPLOAD_ERR_OK) {
                    $file_tmp = $file['tmp_name'];
                    $file_size = $file['size'];
                    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg'];

                    if (in_array($file_ext, $allowed) && $file_size <= 5242880) {
                        $upload_dir = '../uploads/partners/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        $unique_filename = time() . '_' . uniqid() . '.' . $file_ext;
                        $new_logo_path = $upload_dir . $unique_filename;

                        if (move_uploaded_file($file_tmp, $new_logo_path)) {
                            // Remove old files (original and thumbnail) if present
                            if (!empty($company_logo)) {
                                if (file_exists($company_logo)) @unlink($company_logo);
                                // Build old thumbnail path by directory + thumb_ + basename
                                $oldThumb = dirname($company_logo) . '/thumb_' . basename($company_logo);
                                if (file_exists($oldThumb)) @unlink($oldThumb);
                            }
                            $company_logo = $new_logo_path;
                            // Generate thumbnail
                            @require_once __DIR__ . '/../includes/image_helpers.php';
                            $thumb_path = $upload_dir . 'thumb_' . $unique_filename;
                            @create_image_thumbnail($new_logo_path, $thumb_path, 300, 300, 85);
                        }
                    }
                }
            }

            $query = "
                UPDATE partners 
                SET company_name = :company_name, description = :description, 
                    partnership_type = :partnership_type, status = :status,
                    company_logo = :company_logo, website_url = :website_url,
                    contact_person = :contact_person, contact_email = :contact_email, 
                    contact_phone = :contact_phone, partnership_start_date = :partnership_start_date,
                    partnership_end_date = :partnership_end_date, value_offered = :value_offered,
                    visibility = :visibility,
                    is_featured = :is_featured
                WHERE partner_id = :partner_id
            ";
            $params = [
                ':company_name' => $company_name, ':description' => $description, 
                ':partnership_type' => $partnership_type, ':status' => $status,
                ':company_logo' => $company_logo, ':website_url' => $website_url,
                ':contact_person' => $contact_person, ':contact_email' => $contact_email, 
                ':contact_phone' => $contact_phone, ':partnership_start_date' => $partnership_start_date,
                ':partnership_end_date' => $partnership_end_date, ':value_offered' => $value_offered,
                ':visibility' => $visibility, ':is_featured' => $is_featured, ':partner_id' => $partner_id
            ];

            $db->query($query, $params);
            // Invalidate partner caches
            if (file_exists(__DIR__ . '/../includes/cache.php')) {
                require_once __DIR__ . '/../includes/cache.php';
                if (function_exists('cache_delete')) {
                    cache_delete('homepage_partners');
                    cache_delete('footer_partners');
                }
            }
            redirectWithMessage('partners.php', 'Partner updated successfully!', 'success');
        } catch (Exception $e) {
            $error_message = "An error occurred.";
        }
    }
} else {
    $_POST = $partner;
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Partner - NACOS Admin</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --sidebar-bg: #2c3e50;
            --sidebar-hover: #34495e;
            --success-start: #11998e;
            --success-end: #38ef7d;
            --info-start: #4facfe;
            --info-end: #00f2fe;
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
        
        .sidebar-header small {
            opacity: 0.9;
        }
        
        .sidebar-menu {
            padding: 20px 0;
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

        .menu-toggle {
            display: none;
            border: none;
            background: transparent;
            color: var(--primary-color);
            font-size: 22px;
            padding: 6px 10px;
            border-radius: 8px;
        }

        .menu-toggle:focus {
            outline: 2px solid rgba(102, 126, 234, 0.35);
            outline-offset: 2px;
        }

        .sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.25s ease;
            z-index: 999;
        }

        .sidebar-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 30px;
            min-height: 100vh;
        }
        
        /* Page Header */
        .page-header {
            background: white;
            padding: 25px 30px;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
            animation: fadeInDown 0.6s ease;
        }
        
        .page-header h1 {
            color: #2c3e50;
            font-weight: 700;
            margin: 0;
            font-size: 2rem;
        }
        
        /* Card */
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border: none;
            overflow: hidden;
            animation: fadeInUp 0.6s ease backwards;
            animation-delay: 0.2s;
        }
        
        .card-body {
            padding: 40px;
        }
        
        /* Form Sections */
        .form-section {
            margin-bottom: 40px;
            position: relative;
        }
        
        .form-section:last-child {
            margin-bottom: 0;
        }
        
        .section-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .section-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }
        
        .section-header h5 {
            margin: 0;
            color: #2c3e50;
            font-weight: 700;
            font-size: 1.3rem;
        }
        
        /* Form Controls */
        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-control,
        .form-select {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            background: white;
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        
        /* Current Logo Display */
        .logo-preview {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            border: 2px dashed #e9ecef;
        }
        
        .logo-preview img {
            border-radius: 12px;
            border: 3px solid white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        /* File Input Styling */
        input[type="file"] {
            padding: 10px;
        }
        
        /* Checkbox Styling */
        .form-check {
            padding: 15px;
            background: linear-gradient(135deg, rgba(17, 153, 142, 0.05), rgba(56, 239, 125, 0.05));
            border-radius: 10px;
            border-left: 4px solid var(--success-start);
        }
        
        .form-check-input {
            width: 20px;
            height: 20px;
            margin-top: 3px;
            cursor: pointer;
        }
        
        .form-check-input:checked {
            background-color: var(--success-start);
            border-color: var(--success-start);
        }
        
        .form-check-label {
            font-weight: 600;
            color: #2c3e50;
            cursor: pointer;
            margin-left: 5px;
        }
        
        /* Buttons */
        .btn {
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            font-size: 15px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
            color: white;
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
            color: white;
        }
        
        /* Alert */
        .alert {
            border-radius: 12px;
            border: none;
            padding: 15px 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            animation: fadeInDown 0.5s ease;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, rgba(240, 147, 251, 0.1), rgba(245, 87, 108, 0.1));
            border-left: 5px solid #f093fb;
            color: #721c24;
        }
        
        /* Decorative Elements */
        .form-section::before {
            content: '';
            position: absolute;
            top: -10px;
            right: -10px;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
            z-index: -1;
        }
        
        .form-section:nth-child(even)::before {
            left: -10px;
            right: auto;
            background: linear-gradient(135deg, rgba(17, 153, 142, 0.05), rgba(56, 239, 125, 0.05));
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
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .menu-toggle {
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            .page-header {
                padding: 18px;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .page-header .header-content {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 12px;
            }

            .page-header .header-content .btn {
                width: 100%;
            }
            
            .card-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
        <div class="main-content">
            <?php include 'includes/navbar.php'; ?>
            <div class="container-fluid px-4 py-4">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center header-content">
                        <h1>
                            <button type="button" class="menu-toggle me-2" id="menuToggle" aria-label="Toggle navigation menu">
                                <i class="fas fa-bars"></i>
                            </button>
                            <i class="fas fa-edit me-2" style="color: #667eea;"></i>Edit Partner
                        </h1>
                        <a href="partners.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i> Back to Partners
                        </a>
                    </div>
                </div>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form action="edit_partner.php?id=<?php echo $partner_id; ?>" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <!-- Section 1: Basic Information -->
                            <div class="form-section">
                                <div class="section-header">
                                    <div class="section-icon" style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));">
                                        <i class="fas fa-building"></i>
                                    </div>
                                    <h5>Basic Information</h5>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-briefcase me-2"></i>Company Name *</label>
                                    <input type="text" class="form-control" name="company_name" value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>" required placeholder="Enter company name">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-align-left me-2"></i>Description</label>
                                    <textarea class="form-control" name="description" rows="4" placeholder="Brief description of the partner organization"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><i class="fas fa-tag me-2"></i>Partnership Type *</label>
                                        <select class="form-select" name="partnership_type" required>
                                            <option value="sponsor" <?php echo ($_POST['partnership_type'] ?? '') === 'sponsor' ? 'selected' : ''; ?>>Sponsor</option>
                                            <option value="mentor" <?php echo ($_POST['partnership_type'] ?? '') === 'mentor' ? 'selected' : ''; ?>>Mentor</option>
                                            <option value="industry_partner" <?php echo ($_POST['partnership_type'] ?? '') === 'industry_partner' ? 'selected' : ''; ?>>Industry Partner</option>
                                            <option value="academic_partner" <?php echo ($_POST['partnership_type'] ?? '') === 'academic_partner' ? 'selected' : ''; ?>>Academic Partner</option>
                                            <option value="other" <?php echo ($_POST['partnership_type'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><i class="fas fa-toggle-on me-2"></i>Status *</label>
                                        <select class="form-select" name="status" required>
                                            <option value="active" <?php echo ($_POST['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="pending" <?php echo ($_POST['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="inactive" <?php echo ($_POST['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                            <option value="former" <?php echo ($_POST['status'] ?? '') === 'former' ? 'selected' : ''; ?>>Former</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section 2: Partnership Details -->
                            <div class="form-section">
                                <div class="section-header">
                                    <div class="section-icon" style="background: linear-gradient(135deg, var(--info-start), var(--info-end));">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <h5>Partnership Timeline</h5>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><i class="fas fa-calendar-check me-2"></i>Partnership Start Date</label>
                                        <input type="date" class="form-control" name="partnership_start_date" value="<?php echo htmlspecialchars($_POST['partnership_start_date'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><i class="fas fa-calendar-times me-2"></i>Partnership End Date (Optional)</label>
                                        <input type="date" class="form-control" name="partnership_end_date" value="<?php echo htmlspecialchars($_POST['partnership_end_date'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-gift me-2"></i>Value Offered</label>
                                    <textarea class="form-control" name="value_offered" rows="4" placeholder="Resources, funding, mentorship details..."><?php echo htmlspecialchars($_POST['value_offered'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <!-- Section 3: Branding & Logo -->
                            <div class="form-section">
                                <div class="section-header">
                                    <div class="section-icon" style="background: linear-gradient(135deg, #fa709a, #fee140);">
                                        <i class="fas fa-image"></i>
                                    </div>
                                    <h5>Branding & Logo</h5>
                                </div>
                                
                                <?php if (!empty($partner['company_logo'])): ?>
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-eye me-2"></i>Current Logo</label>
                                        <div class="logo-preview">
                                            <img src="<?php echo htmlspecialchars($partner['company_logo']); ?>" alt="Partner Logo" class="img-thumbnail" style="max-height: 120px;">
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-upload me-2"></i>Replace Logo (Optional)</label>
                                    <input type="file" class="form-control" name="partner_logo" accept="image/*">
                                    <small class="text-muted mt-1 d-block">Accepted formats: JPG, PNG, GIF, SVG (Max: 5MB)</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-globe me-2"></i>Website URL</label>
                                    <input type="url" class="form-control" name="website_url" value="<?php echo htmlspecialchars($_POST['website_url'] ?? ''); ?>" placeholder="https://example.com">
                                </div>
                            </div>
                            
                            <!-- Section 4: Contact Information -->
                            <div class="form-section">
                                <div class="section-header">
                                    <div class="section-icon" style="background: linear-gradient(135deg, var(--success-start), var(--success-end));">
                                        <i class="fas fa-address-book"></i>
                                    </div>
                                    <h5>Contact Information</h5>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label"><i class="fas fa-user me-2"></i>Contact Person</label>
                                        <input type="text" class="form-control" name="contact_person" value="<?php echo htmlspecialchars($_POST['contact_person'] ?? ''); ?>" placeholder="Full name">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label"><i class="fas fa-envelope me-2"></i>Contact Email</label>
                                        <input type="email" class="form-control" name="contact_email" value="<?php echo htmlspecialchars($_POST['contact_email'] ?? ''); ?>" placeholder="email@example.com">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label"><i class="fas fa-phone me-2"></i>Contact Phone</label>
                                        <input type="tel" class="form-control" name="contact_phone" value="<?php echo htmlspecialchars($_POST['contact_phone'] ?? ''); ?>" placeholder="+234 XXX XXX XXXX">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section 5: Visibility & Display -->
                            <div class="form-section">
                                <div class="section-header">
                                    <div class="section-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                                        <i class="fas fa-eye"></i>
                                    </div>
                                    <h5>Visibility & Display Settings</h5>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-lock me-2"></i>Visibility *</label>
                                    <select class="form-select" name="visibility" required>
                                        <option value="public" <?php echo ($_POST['visibility'] ?? 'public') === 'public' ? 'selected' : ''; ?>>Public (Show on Partner Portal)</option>
                                        <option value="private" <?php echo ($_POST['visibility'] ?? '') === 'private' ? 'selected' : ''; ?>>Private (Internal only)</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_featured" id="is_featured" value="1" <?php echo !empty($partner['is_featured']) ? 'checked' : ''; ?> />
                                        <label class="form-check-label" for="is_featured">
                                            <i class="fas fa-star me-2"></i>Feature this partner on homepage
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Submit Buttons -->
                            <div class="d-flex gap-3 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i> Update Partner
                                </button>
                                <a href="partners.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/includes/footer.php'; ?>
    <script>
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.querySelector('.sidebar');
        const sidebarOverlay = document.getElementById('sidebarBackdrop') || document.getElementById('sidebarOverlay');

        function closeSidebar() {
            if (!sidebar || !sidebarOverlay) return;
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        }

        if (menuToggle && sidebar && sidebarOverlay) {
            menuToggle.addEventListener('click', function () {
                sidebar.classList.toggle('show');
                sidebarOverlay.classList.toggle('show');
            });

            sidebarOverlay.addEventListener('click', closeSidebar);

            window.addEventListener('resize', function () {
                if (window.innerWidth > 991.98) {
                    closeSidebar();
                }
            });
        }
    </script>
</body>
</html>
