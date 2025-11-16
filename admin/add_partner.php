<?php
require_once __DIR__ . '/../includes/security.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
requireAdminRole();

$db = getDB();
$error_message = '';

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
                        INSERT INTO PARTNERS 
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
    <title>Add Partner - NACOS Admin</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <?php include 'includes/navbar.php'; ?>
            <div class="container-fluid px-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">Add New Partner</h1>
                    <a href="partners.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
                </div>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                                <form action="add_partner.php" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Partner Name *</label>
                                        <input type="text" class="form-control" name="name" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" name="description" rows="3"></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Partner Type *</label>
                                            <select class="form-select" name="partner_type" required>
                                                <option value="sponsor">Sponsor</option>
                                                <option value="collaborator">Collaborator</option>
                                                <option value="affiliate">Affiliate</option>
                                                <option value="industry">Industry Partner</option>
                                                <option value="academic">Academic Partner</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Partnership Since</label>
                                            <input type="date" class="form-control" name="partnership_since">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Upload Logo</label>
                                        <input type="file" class="form-control" name="partner_logo" accept="image/*">
                                        <small class="text-muted">Max 5MB. Formats: JPG, PNG, GIF, SVG</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Website URL</label>
                                        <input type="url" class="form-control" name="website_url" placeholder="https://example.com">
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Contact Email</label>
                                            <input type="email" class="form-control" name="contact_email">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Contact Phone</label>
                                            <input type="tel" class="form-control" name="contact_phone">
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Display Order</label>
                                            <input type="number" class="form-control" name="display_order" value="0">
                                            <small class="text-muted">Lower numbers appear first</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="form-check mt-4">
                                                <input type="checkbox" class="form-check-input" name="is_featured" value="1">
                                                <label class="form-check-label">Feature on Homepage</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Partner</button>
                                    <a href="partners.php" class="btn btn-secondary">Cancel</a>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Partner Types</h5>
                            </div>
                            <div class="card-body">
                                <ul class="small mb-0">
                                    <li><strong>Sponsor:</strong> Financial supporters</li>
                                    <li><strong>Collaborator:</strong> Joint project partners</li>
                                    <li><strong>Affiliate:</strong> Associated organizations</li>
                                    <li><strong>Industry:</strong> Tech companies</li>
                                    <li><strong>Academic:</strong> Universities, schools</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
