<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/config.php';
require_once '../includes/auth.php';
requireAdminRole();

$db = getDB();
$error_message = '';

// If a request_id is provided, prefill the form with data from partner_requests
$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;
$prefill = [];
if ($request_id > 0) {
    try {
        $prefill = $db->fetchOne("SELECT * FROM partner_requests WHERE request_id = ?", [$request_id]);
    } catch (Exception $e) {
        $prefill = [];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = "Invalid request.";
    } else {
        $name = sanitizeInput($_POST['name']);
        $description = sanitizeInput($_POST['description']);
        $partner_type = sanitizeInput($_POST['partner_type']);
        $website_url = !empty($_POST['website_url']) ? sanitizeInput($_POST['website_url']) : null;
        $contact_email = !empty($_POST['contact_email']) ? sanitizeInput($_POST['contact_email']) : null;
        $contact_phone = !empty($_POST['contact_phone']) ? sanitizeInput($_POST['contact_phone']) : null;
        $partnership_since = !empty($_POST['partnership_since']) ? sanitizeInput($_POST['partnership_since']) : null;
        $display_order = !empty($_POST['display_order']) ? (int)$_POST['display_order'] : 0;
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $added_by = getCurrentMember()['member_id'];
        $from_request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;

        if (empty($name) || empty($partner_type)) {
            $error_message = "Name and partner type are required.";
        } else {
            try {
                $logo_path = null;
                $logo_name = null;

                if (!empty($_FILES['partner_logo']['name'])) {
                    $file = $_FILES['partner_logo'];
                    if ($file['error'] === UPLOAD_ERR_OK) {
                        $logo_name = $file['name'];
                        $file_tmp = $file['tmp_name'];
                        $file_size = $file['size'];
                        $file_ext = strtolower(pathinfo($logo_name, PATHINFO_EXTENSION));

                        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg'];

                        if (!in_array($file_ext, $allowed)) {
                            $error_message = "Invalid logo type. Use JPG, PNG, GIF, or SVG.";
                        } elseif ($file_size > 5242880) {
                            $error_message = "Logo size exceeds 5MB.";
                        } else {
                            $upload_dir = '../uploads/partners/';
                            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                            $unique_filename = time() . '_' . uniqid() . '.' . $file_ext;
                            $logo_path = $upload_dir . $unique_filename;

                            if (!move_uploaded_file($file_tmp, $logo_path)) {
                                $error_message = "Failed to upload logo.";
                            } else {
                                // Generate thumbnail for faster display
                                @require_once __DIR__ . '/../includes/image_helpers.php';
                                $thumb_path = $upload_dir . 'thumb_' . $unique_filename;
                                @create_image_thumbnail($logo_path, $thumb_path, 300, 300, 85);
                            }
                        }
                    }
                }

                if (empty($error_message)) {
                    $query = "
                        INSERT INTO partners 
                        (name, description, partner_type, logo_path, logo_name, website_url, 
                         contact_email, contact_phone, partnership_since, display_order, 
                         is_featured, added_by) 
                        VALUES 
                        (:name, :description, :partner_type, :logo_path, :logo_name, :website_url,
                         :contact_email, :contact_phone, :partnership_since, :display_order,
                         :is_featured, :added_by)
                    ";
                    $params = [
                        ':name' => $name, ':description' => $description, ':partner_type' => $partner_type,
                        ':logo_path' => $logo_path, ':logo_name' => $logo_name, ':website_url' => $website_url,
                        ':contact_email' => $contact_email, ':contact_phone' => $contact_phone,
                        ':partnership_since' => $partnership_since, ':display_order' => $display_order,
                        ':is_featured' => $is_featured, ':added_by' => $added_by
                    ];

                    $db->query($query, $params);
                    // If this partner was created from a request, mark it approved (or remove)
                    if ($from_request_id > 0) {
                        try {
                            $db->query("UPDATE partner_requests SET status = 'approved' WHERE request_id = :id", [':id' => $from_request_id]);
                        } catch (Exception $e) {
                            // fallback: delete the request if update fails
                            try { $db->query("DELETE FROM partner_requests WHERE request_id = :id", [':id' => $from_request_id]); } catch (Exception $ee) { }
                        }
                    }
                    // Invalidate caches related to partners so homepage/footer update quickly
                    if (file_exists(__DIR__ . '/../includes/cache.php')) {
                        require_once __DIR__ . '/../includes/cache.php';
                        if (function_exists('cache_delete')) {
                            cache_delete('homepage_partners');
                            cache_delete('footer_partners');
                        }
                    }
                    redirectWithMessage('partners.php', 'Partner added successfully!', 'success');
                }
            } catch (Exception $e) {
                $error_message = "An error occurred.";
            }
        }
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Partner - NACOS Admin</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 30px;
}

.page-header h1 {
    font-size: 28px;
    font-weight: 700;
    color: #2c3e50;
    margin: 0;
}

/* Form Card */
.form-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    overflow: hidden;
}

.form-section {
    padding: 30px;
    border-bottom: 1px solid #e9ecef;
}

.form-section:last-child {
    border-bottom: none;
}

.form-section-header {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f1f3f5;
}

.form-section-header i {
    font-size: 24px;
    color: var(--primary-color);
    margin-right: 12px;
}

.form-section-header h5 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #2c3e50;
}

/* Form Controls */
.form-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
    font-size: 14px;
}

.form-control, .form-select {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 12px 15px;
    font-size: 14px;
    transition: all 0.3s;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
}

textarea.form-control {
    min-height: 120px;
}

/* File Upload */
.file-upload-wrapper {
    position: relative;
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    padding: 40px;
    text-align: center;
    transition: all 0.3s;
}

.file-upload-wrapper:hover {
    border-color: var(--primary-color);
    background: #f0f4ff;
}

.file-upload-wrapper input[type="file"] {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}

.file-upload-label {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    pointer-events: none;
}

.file-upload-label i {
    font-size: 48px;
    color: var(--primary-color);
}

.file-upload-label .upload-text {
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
}

.file-upload-label .upload-hint {
    font-size: 13px;
    color: #6c757d;
}

/* Action Buttons */
.form-actions {
    padding: 25px 30px;
    background: #f8f9fa;
    display: flex;
    gap: 15px;
    justify-content: flex-start;
}

.btn {
    padding: 12px 30px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s;
    border: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
}

.btn-primary:hover {
    background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

/* Info Card */
.info-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    overflow: hidden;
    position: sticky;
    top: 30px;
}

.info-card-header {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    padding: 20px;
}

.info-card-header h5 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
}

.info-card-body {
    padding: 25px;
}

.info-card-body ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.info-card-body ul li {
    padding: 12px 0;
    border-bottom: 1px solid #e9ecef;
    font-size: 14px;
}

.info-card-body ul li:last-child {
    border-bottom: none;
}

.info-card-body ul li strong {
    color: var(--primary-color);
    display: block;
    margin-bottom: 4px;
}

/* Alert Messages */
.alert {
    border-radius: 8px;
    padding: 15px 20px;
    margin-bottom: 25px;
    border: none;
}

.alert-danger {
    background: #fee;
    color: #c33;
    border-left: 4px solid #c33;
}

/* Form Check */
.form-check {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
}

.form-check-input {
    width: 20px;
    height: 20px;
    margin-top: 0;
    cursor: pointer;
}

.form-check-label {
    font-weight: 600;
    margin-left: 8px;
    cursor: pointer;
}

/* Responsive */
@media (max-width: 992px) {
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

    .main-content {
        margin-left: 0;
        padding: 15px;
    }

    .page-header {
        padding: 18px;
    }

    .page-header h1 {
        font-size: 22px;
    }

    .page-header .header-content {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 12px;
    }

    .page-header .header-content .btn {
        width: 100%;
    }
    
    .form-section {
        padding: 20px;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
    }
    
    .info-card {
        position: static;
        margin-top: 20px;
    }
}
</style>
</head>
<body>
     <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
        
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center header-content">
                <div>
                    <h1>
                        <button type="button" class="menu-toggle me-2" id="menuToggle" aria-label="Toggle navigation menu">
                            <i class="fas fa-bars"></i>
                        </button>
                        <i class="fas fa-handshake me-2"></i>Add New Partner
                    </h1>
                    <p class="mb-0 text-muted">Create a new partnership and manage details</p>
                </div>
                <a href="partners.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back
                </a>
            </div>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <!-- Form Card -->
                <form action="add_partner.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <?php if (!empty($request_id)): ?>
                        <input type="hidden" name="request_id" value="<?php echo $request_id; ?>">
                    <?php elseif (!empty($prefill['request_id'])): ?>
                        <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($prefill['request_id']); ?>">
                    <?php endif; ?>
                    
                    <div class="form-card">
                        <!-- Basic Information Section -->
                        <div class="form-section">
                            <div class="form-section-header">
                                <i class="fas fa-info-circle"></i>
                                <h5>Basic Information</h5>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Partner Name *</label>
                                <input type="text" class="form-control" name="name" 
                                       placeholder="Enter partner or company name" required 
                                       value="<?php echo htmlspecialchars($prefill['company_name'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" 
                                          placeholder="Provide a brief description of the partnership"><?php echo htmlspecialchars($prefill['message'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <!-- Classification Section -->
                        <div class="form-section">
                            <div class="form-section-header">
                                <i class="fas fa-tags"></i>
                                <h5>Partnership Details</h5>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Partner Type *</label>
                                    <select class="form-select" name="partner_type" required>
                                        <?php $pt = $prefill['preferred_type'] ?? '';?>
                                        <option value="sponsor" <?php echo $pt === 'sponsor' ? 'selected' : ''; ?>>Sponsor</option>
                                        <option value="collaborator" <?php echo $pt === 'collaborator' ? 'selected' : ''; ?>>Collaborator</option>
                                        <option value="affiliate" <?php echo $pt === 'affiliate' ? 'selected' : ''; ?>>Affiliate</option>
                                        <option value="industry" <?php echo $pt === 'industry' ? 'selected' : ''; ?>>Industry Partner</option>
                                        <option value="academic" <?php echo $pt === 'academic' ? 'selected' : ''; ?>>Academic Partner</option>
                                        <option value="other" <?php echo $pt === 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Partnership Since</label>
                                    <input type="date" class="form-control" name="partnership_since">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Website URL</label>
                                <input type="url" class="form-control" name="website_url" 
                                       placeholder="https://example.com" 
                                       value="<?php echo htmlspecialchars($prefill['website_url'] ?? ''); ?>">
                            </div>
                        </div>

                        <!-- Logo Upload Section -->
                        <div class="form-section">
                            <div class="form-section-header">
                                <i class="fas fa-image"></i>
                                <h5>Logo & Branding</h5>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Upload Logo</label>
                                <div class="file-upload-wrapper">
                                    <input type="file" name="partner_logo" id="partnerLogo" accept="image/*">
                                    <label class="file-upload-label" for="partnerLogo">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <span class="upload-text">Choose a logo or drag it here</span>
                                        <span class="upload-hint">Max 5MB. Formats: JPG, PNG, GIF, SVG</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Contact Information Section -->
                        <div class="form-section">
                            <div class="form-section-header">
                                <i class="fas fa-address-card"></i>
                                <h5>Contact Information</h5>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Contact Email</label>
                                    <input type="email" class="form-control" name="contact_email" 
                                           placeholder="contact@partner.com"
                                           value="<?php echo htmlspecialchars($prefill['contact_email'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Contact Phone</label>
                                    <input type="tel" class="form-control" name="contact_phone" 
                                           placeholder="+234 XXX XXX XXXX"
                                           value="<?php echo htmlspecialchars($prefill['contact_phone'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Display Settings Section -->
                        <div class="form-section">
                            <div class="form-section-header">
                                <i class="fas fa-cog"></i>
                                <h5>Display Settings</h5>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Display Order</label>
                                    <input type="number" class="form-control" name="display_order" value="0">
                                    <small class="text-muted">Lower numbers appear first</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label d-block mb-2">Visibility</label>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" name="is_featured" value="1" id="featuredCheck">
                                        <label class="form-check-label" for="featuredCheck">
                                            <i class="fas fa-star me-1"></i> Feature on Homepage
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Add Partner
                            </button>
                            <a href="partners.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="col-lg-4">
                <!-- Info Card -->
                <div class="info-card">
                    <div class="info-card-header">
                        <h5><i class="fas fa-info-circle me-2"></i>Partner Types</h5>
                    </div>
                    <div class="info-card-body">
                        <ul>
                            <li>
                                <strong>Sponsor</strong>
                                Financial supporters and sponsors
                            </li>
                            <li>
                                <strong>Collaborator</strong>
                                Joint project partners
                            </li>
                            <li>
                                <strong>Affiliate</strong>
                                Associated organizations
                            </li>
                            <li>
                                <strong>Industry</strong>
                                Tech companies and industry partners
                            </li>
                            <li>
                                <strong>Academic</strong>
                                Universities and schools
                            </li>
                        </ul>
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
