<?php
require_once __DIR__ . '/../includes/security.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
requireAdminRole();

$db = getDB();
$partner_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$partner = $db->fetchOne("SELECT * FROM PARTNERS WHERE partner_id = :id", [':id' => $partner_id]);

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
                UPDATE PARTNERS 
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
    <title>Edit Partner - NACOS Admin</title>
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
                    <h1 class="h3 mb-0">Edit Partner</h1>
                    <a href="partners.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
                </div>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form action="edit_partner.php?id=<?php echo $partner_id; ?>" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Company Name *</label>
                                <input type="text" class="form-control" name="company_name" value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Partnership Type *</label>
                                    <select class="form-select" name="partnership_type" required>
                                        <option value="sponsor" <?php echo ($_POST['partnership_type'] ?? '') === 'sponsor' ? 'selected' : ''; ?>>Sponsor</option>
                                        <option value="mentor" <?php echo ($_POST['partnership_type'] ?? '') === 'mentor' ? 'selected' : ''; ?>>Mentor</option>
                                        <option value="industry_partner" <?php echo ($_POST['partnership_type'] ?? '') === 'industry_partner' ? 'selected' : ''; ?>>Industry Partner</option>
                                        <option value="academic_partner" <?php echo ($_POST['partnership_type'] ?? '') === 'academic_partner' ? 'selected' : ''; ?>>Academic Partner</option>
                                        <option value="other" <?php echo ($_POST['partnership_type'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Status *</label>
                                    <select class="form-select" name="status" required>
                                        <option value="active" <?php echo ($_POST['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="pending" <?php echo ($_POST['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="inactive" <?php echo ($_POST['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="former" <?php echo ($_POST['status'] ?? '') === 'former' ? 'selected' : ''; ?>>Former</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Partnership Start Date</label>
                                    <input type="date" class="form-control" name="partnership_start_date" value="<?php echo htmlspecialchars($_POST['partnership_start_date'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Partnership End Date (Optional)</label>
                                    <input type="date" class="form-control" name="partnership_end_date" value="<?php echo htmlspecialchars($_POST['partnership_end_date'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <?php if (!empty($partner['company_logo'])): ?>
                                <div class="mb-3">
                                    <label class="form-label">Current Logo</label>
                                    <div><img src="<?php echo htmlspecialchars($partner['company_logo']); ?>" alt="Logo" class="img-thumbnail" style="max-height: 100px;"></div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label class="form-label">Replace Logo (Optional)</label>
                                <input type="file" class="form-control" name="partner_logo" accept="image/*">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Website URL</label>
                                <input type="url" class="form-control" name="website_url" value="<?php echo htmlspecialchars($_POST['website_url'] ?? ''); ?>">
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Contact Person</label>
                                    <input type="text" class="form-control" name="contact_person" value="<?php echo htmlspecialchars($_POST['contact_person'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Contact Email</label>
                                    <input type="email" class="form-control" name="contact_email" value="<?php echo htmlspecialchars($_POST['contact_email'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Contact Phone</label>
                                    <input type="tel" class="form-control" name="contact_phone" value="<?php echo htmlspecialchars($_POST['contact_phone'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Value Offered</label>
                                <textarea class="form-control" name="value_offered" rows="3" placeholder="Resources, funding, mentorship details..."><?php echo htmlspecialchars($_POST['value_offered'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Visibility *</label>
                                <select class="form-select" name="visibility" required>
                                    <option value="public" <?php echo ($_POST['visibility'] ?? 'public') === 'public' ? 'selected' : ''; ?>>Public (Show on Partner Portal)</option>
                                    <option value="private" <?php echo ($_POST['visibility'] ?? '') === 'private' ? 'selected' : ''; ?>>Private (Internal only)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_featured" id="is_featured" value="1" <?php echo !empty($partner['is_featured']) ? 'checked' : ''; ?> />
                                    <label class="form-check-label" for="is_featured">Feature this partner on homepage</label>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
                            <a href="partners.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
