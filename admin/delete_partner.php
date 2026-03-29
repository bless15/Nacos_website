<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/config.php';
require_once '../includes/auth.php';
requireAdminRole();

$db = getDB();
$partner_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$partner = $db->fetchOne("SELECT * FROM partners WHERE partner_id = :id", [':id' => $partner_id]);

if (!$partner) redirectWithMessage('partners.php', 'Partner not found.', 'error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        redirectWithMessage('partners.php', 'Invalid request.', 'error');
    }

    try {
        $db->query("DELETE FROM partners WHERE partner_id = :id", [':id' => $partner_id]);
        
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
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --sidebar-bg: #2c3e50;
            --sidebar-hover: #34495e;
            --danger-start: #f093fb;
            --danger-end: #f5576c;
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
        
        /* Danger Card */
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border: none;
            overflow: hidden;
            animation: fadeInUp 0.6s ease backwards;
            animation-delay: 0.2s;
        }
        
        .card.border-danger {
            border: 3px solid #dc3545 !important;
            box-shadow: 0 8px 30px rgba(220, 53, 69, 0.2);
        }
        
        .card-body {
            padding: 50px 40px;
        }
        
        /* Warning Icon */
        .warning-icon {
            width: 90px;
            height: 90px;
            margin: 0 auto 25px;
            background: linear-gradient(135deg, var(--danger-start), var(--danger-end));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 45px;
            box-shadow: 0 8px 25px rgba(240, 147, 251, 0.4);
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.9; }
        }
        
        /* Partner Details */
        .alert-info {
            background: linear-gradient(135deg, rgba(79, 172, 254, 0.1), rgba(0, 242, 254, 0.1));
            border-left: 5px solid #4facfe;
            border-radius: 12px;
            padding: 25px;
            margin: 25px 0;
            text-align: left;
        }
        
        .img-thumbnail {
            border-radius: 12px;
            border: 3px solid #e9ecef;
            padding: 10px;
            background: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        /* Typography */
        h3 {
            color: #2c3e50;
            font-weight: 700;
            font-size: 1.8rem;
        }
        
        .text-muted {
            color: #6c757d !important;
            font-size: 15px;
        }
        
        /* Buttons */
        .btn {
            border-radius: 10px;
            padding: 12px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
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
        
        .btn-danger {
            background: linear-gradient(135deg, var(--danger-start), var(--danger-end));
            color: white;
            box-shadow: 0 4px 12px rgba(240, 147, 251, 0.3);
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(240, 147, 251, 0.5);
            color: white;
            animation: shake 0.5s;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateY(-2px) translateX(0); }
            25% { transform: translateY(-2px) translateX(-5px); }
            75% { transform: translateY(-2px) translateX(5px); }
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
    </style>


    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <?php include 'includes/navbar.php'; ?>
            <div class="container-fluid px-4 py-4">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h1><i class="fas fa-trash-alt me-2" style="color: #dc3545;"></i>Delete Partner</h1>
                        <a href="partners.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i> Back to Partners
                        </a>
                    </div>
                </div>
                
                <div class="row justify-content-center">
                    <div class="col-lg-7">
                        <div class="card border-danger">
                            <div class="card-body text-center">
                                <div class="warning-icon">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                
                                <h3 class="mb-3">Confirm Deletion</h3>
                                <p class="text-muted mb-4">Are you sure you want to delete this partner? This action cannot be undone.</p>
                                
                                <div class="alert alert-info text-start">
                                    <?php if (!empty($partner['logo_path'])): ?>
                                        <div class="text-center mb-3">
                                            <img src="<?php echo htmlspecialchars($partner['logo_path']); ?>" 
                                                 alt="Partner Logo" 
                                                 class="img-thumbnail" 
                                                 style="max-height: 100px;">
                                        </div>
                                    <?php endif; ?>
                                    <div style="font-size: 15px;">
                                        <div class="mb-2">
                                            <strong style="color: #495057;">Name:</strong> 
                                            <span style="color: #2c3e50;"><?php echo htmlspecialchars($partner['name'] ?? 'N/A'); ?></span>
                                        </div>
                                        <div class="mb-2">
                                            <strong style="color: #495057;">Type:</strong> 
                                            <span style="color: #2c3e50;"><?php echo isset($partner['partner_type']) ? ucfirst($partner['partner_type']) : 'N/A'; ?></span>
                                        </div>
                                        <?php if (!empty($partner['website'])): ?>
                                            <div>
                                                <strong style="color: #495057;">Website:</strong> 
                                                <span style="color: #2c3e50;"><?php echo htmlspecialchars($partner['website']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <form action="delete_partner.php?id=<?php echo $partner_id; ?>" method="POST" class="mt-4">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <div class="d-flex gap-3 justify-content-center">
                                        <a href="partners.php" class="btn btn-secondary">
                                            <i class="fas fa-times me-2"></i> Cancel - Keep Partner
                                        </a>
                                        <button type="submit" class="btn btn-danger">
                                            <i class="fas fa-trash me-2"></i> Yes, Delete Permanently
                                        </button>
                                    </div>
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
