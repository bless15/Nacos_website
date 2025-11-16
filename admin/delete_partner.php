<?php
require_once __DIR__ . '/../includes/security.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
requireAdminRole();

$db = getDB();
$partner_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$partner = $db->fetchOne("SELECT * FROM PARTNERS WHERE partner_id = :id", [':id' => $partner_id]);

if (!$partner) redirectWithMessage('partners.php', 'Partner not found.', 'error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        redirectWithMessage('partners.php', 'Invalid request.', 'error');
    }

    try {
        $db->query("DELETE FROM PARTNERS WHERE partner_id = :id", [':id' => $partner_id]);
        
        if (!empty($partner['logo_path']) && file_exists($partner['logo_path'])) {
            unlink($partner['logo_path']);
        }
        
        redirectWithMessage('partners.php', 'Partner deleted successfully!', 'success');
    } catch (Exception $e) {
        redirectWithMessage('partners.php', 'An error occurred.', 'error');
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delete Partner - NACOS Admin</title>
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
                    <h1 class="h3 mb-0">Delete Partner</h1>
                    <a href="partners.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
                </div>
                <div class="row justify-content-center">
                    <div class="col-lg-6">
                        <div class="card border-danger">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-exclamation-triangle fa-4x text-danger mb-3"></i>
                                <h3 class="mb-3">Confirm Deletion</h3>
                                <p class="text-muted mb-4">Are you sure you want to delete this partner?</p>
                                
                                <div class="alert alert-info text-start">
                                    <?php if ($partner['logo_path']): ?>
                                        <div class="text-center mb-2">
                                            <img src="<?php echo htmlspecialchars($partner['logo_path']); ?>" 
                                                 alt="Logo" class="img-thumbnail" style="max-height: 80px;">
                                        </div>
                                    <?php endif; ?>
                                    <strong>Name:</strong> <?php echo htmlspecialchars($partner['name']); ?><br>
                                    <strong>Type:</strong> <?php echo ucfirst($partner['partner_type']); ?>
                                </div>
                                
                                <form action="delete_partner.php?id=<?php echo $partner_id; ?>" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <button type="submit" class="btn btn-danger me-2">
                                        <i class="fas fa-trash"></i> Yes, Delete
                                    </button>
                                    <a href="partners.php" class="btn btn-secondary">Cancel</a>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php // Include admin footer which loads Bootstrap and confirmation modal ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
