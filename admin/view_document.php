<?php
/**
 * ============================================
 * NACOS DASHBOARD - VIEW DOCUMENT
 * ============================================
 * Purpose: View document details and download
 * Access: Admin only
 * Created: November 3, 2025
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Include required files
require_once '../config/database.php';
require_once '../includes/auth.php';

// Require admin login
requireAdminRole();

// Initialize database
$db = getDB();

// Get document ID
$doc_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch document details
$document = $db->fetchOne("SELECT * FROM DOCUMENTS WHERE doc_id = :id", [':id' => $doc_id]);

if (!$document) {
    redirectWithMessage('documents.php', 'Document not found.', 'error');
}

// Fetch uploader details
$uploader = $db->fetchOne("SELECT full_name FROM MEMBERS WHERE member_id = :id", [':id' => $document['uploaded_by']]);

// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Helper function to get document type badge
function getDocTypeBadge($type) {
    $badges = [
        'meeting_minutes' => '<span class="badge bg-primary">Meeting Minutes</span>',
        'financial_report' => '<span class="badge bg-success">Financial Report</span>',
        'constitution' => '<span class="badge bg-danger">Constitution</span>',
        'policy' => '<span class="badge bg-warning">Policy</span>',
        'annual_report' => '<span class="badge bg-info">Annual Report</span>',
        'event_report' => '<span class="badge bg-secondary">Event Report</span>',
        'proposal' => '<span class="badge bg-purple">Proposal</span>',
        'correspondence' => '<span class="badge bg-dark">Correspondence</span>',
        'handover' => '<span class="badge bg-teal">Handover</span>',
        'other' => '<span class="badge bg-secondary">Other</span>'
    ];
    return $badges[$type] ?? '<span class="badge bg-secondary">Unknown</span>';
}

// Helper function to get visibility badge
function getVisibilityBadge($visibility) {
    $badges = [
        'admin' => '<span class="badge bg-danger"><i class="fas fa-lock"></i> Admin Only</span>',
        'members' => '<span class="badge bg-warning"><i class="fas fa-users"></i> Members</span>',
        'public' => '<span class="badge bg-success"><i class="fas fa-globe"></i> Public</span>'
    ];
    return $badges[$visibility] ?? '<span class="badge bg-secondary">Unknown</span>';
}

// Get file extension
$file_ext = strtolower(pathinfo($document['file_name'], PATHINFO_EXTENSION));

// Handle download request
if (isset($_GET['download']) && $_GET['download'] === '1') {
    // Increment download count
    $db->query("UPDATE DOCUMENTS SET download_count = download_count + 1 WHERE doc_id = :id", [':id' => $doc_id]);
    
    // Serve the file
    if (file_exists($document['file_path'])) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($document['file_name']) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($document['file_path']));
        readfile($document['file_path']);
        exit;
    } else {
        redirectWithMessage('view_document.php?id=' . $doc_id, 'File not found on server.', 'error');
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Document - NACOS Admin</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    
    <style>
        .document-icon {
            font-size: 5rem;
            margin-bottom: 1rem;
        }
        .pdf-icon { color: #dc3545; }
        .doc-icon { color: #0d6efd; }
        .xls-icon { color: #198754; }
        .ppt-icon { color: #fd7e14; }
        .txt-icon { color: #6c757d; }
        
        .stat-card {
            border-left: 4px solid #8b5cf6;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .info-row {
            padding: 0.75rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        
        .download-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            font-size: 1.1rem;
            padding: 12px 30px;
        }
        .download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navigation -->
            <?php include 'includes/navbar.php'; ?>
            
            <!-- Page Content -->
            <div class="container-fluid px-4 py-4">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-0">Document Details</h1>
                        <p class="text-muted">View and download official document</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="edit_document.php?id=<?php echo $doc_id; ?>" class="btn btn-warning">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="delete_document.php?id=<?php echo $doc_id; ?>" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                        <a href="documents.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                </div>
                
                <?php if (isset($_GET['message'])): ?>
                    <div class="alert alert-<?php echo $_GET['type'] ?? 'info'; ?> alert-dismissible fade show">
                        <?php echo htmlspecialchars($_GET['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <!-- Document Preview -->
                    <div class="col-lg-4">
                        <div class="card text-center">
                            <div class="card-body py-5">
                                <?php if ($file_ext === 'pdf'): ?>
                                    <i class="fas fa-file-pdf document-icon pdf-icon"></i>
                                <?php elseif (in_array($file_ext, ['doc', 'docx'])): ?>
                                    <i class="fas fa-file-word document-icon doc-icon"></i>
                                <?php elseif (in_array($file_ext, ['xls', 'xlsx'])): ?>
                                    <i class="fas fa-file-excel document-icon xls-icon"></i>
                                <?php elseif (in_array($file_ext, ['ppt', 'pptx'])): ?>
                                    <i class="fas fa-file-powerpoint document-icon ppt-icon"></i>
                                <?php elseif ($file_ext === 'txt'): ?>
                                    <i class="fas fa-file-alt document-icon txt-icon"></i>
                                <?php else: ?>
                                    <i class="fas fa-file document-icon text-secondary"></i>
                                <?php endif; ?>
                                
                                <h5 class="mb-3"><?php echo htmlspecialchars($document['file_name']); ?></h5>
                                <p class="text-muted mb-4"><?php echo formatFileSize($document['file_size']); ?></p>
                                
                                <a href="view_document.php?id=<?php echo $doc_id; ?>&download=1" class="btn btn-primary download-btn w-100">
                                    <i class="fas fa-download"></i> Download Document
                                </a>
                            </div>
                        </div>
                        
                        <!-- Statistics -->
                        <div class="row mt-3">
                            <div class="col-6">
                                <div class="card stat-card">
                                    <div class="card-body text-center">
                                        <h3 class="mb-0"><?php echo $document['download_count']; ?></h3>
                                        <small class="text-muted">Downloads</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="card stat-card">
                                    <div class="card-body text-center">
                                        <h3 class="mb-0"><?php echo strtoupper($file_ext); ?></h3>
                                        <small class="text-muted">Format</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Document Information -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Document Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="info-row">
                                    <div class="row">
                                        <div class="col-md-3"><strong>Title:</strong></div>
                                        <div class="col-md-9"><?php echo htmlspecialchars($document['title']); ?></div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($document['description'])): ?>
                                <div class="info-row">
                                    <div class="row">
                                        <div class="col-md-3"><strong>Description:</strong></div>
                                        <div class="col-md-9"><?php echo nl2br(htmlspecialchars($document['description'])); ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="info-row">
                                    <div class="row">
                                        <div class="col-md-3"><strong>Document Type:</strong></div>
                                        <div class="col-md-9"><?php echo getDocTypeBadge($document['doc_type']); ?></div>
                                    </div>
                                </div>
                                
                                <div class="info-row">
                                    <div class="row">
                                        <div class="col-md-3"><strong>Visibility:</strong></div>
                                        <div class="col-md-9"><?php echo getVisibilityBadge($document['visibility']); ?></div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($document['document_date'])): ?>
                                <div class="info-row">
                                    <div class="row">
                                        <div class="col-md-3"><strong>Document Date:</strong></div>
                                        <div class="col-md-9"><?php echo date('F j, Y', strtotime($document['document_date'])); ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($document['academic_session'])): ?>
                                <div class="info-row">
                                    <div class="row">
                                        <div class="col-md-3"><strong>Academic Session:</strong></div>
                                        <div class="col-md-9"><?php echo htmlspecialchars($document['academic_session']); ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($document['tags'])): ?>
                                <div class="info-row">
                                    <div class="row">
                                        <div class="col-md-3"><strong>Tags:</strong></div>
                                        <div class="col-md-9">
                                            <?php 
                                            $tags = explode(',', $document['tags']);
                                            foreach ($tags as $tag) {
                                                $tag = trim($tag);
                                                if (!empty($tag)) {
                                                    echo '<span class="badge bg-light text-dark me-1">' . htmlspecialchars($tag) . '</span>';
                                                }
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="info-row">
                                    <div class="row">
                                        <div class="col-md-3"><strong>Upload Date:</strong></div>
                                        <div class="col-md-9"><?php echo date('F j, Y \a\t g:i A', strtotime($document['upload_date'])); ?></div>
                                    </div>
                                </div>
                                
                                <div class="info-row">
                                    <div class="row">
                                        <div class="col-md-3"><strong>Uploaded By:</strong></div>
                                        <div class="col-md-9">
                                            <?php 
                                            if ($uploader) {
                                                echo htmlspecialchars($uploader['full_name']);
                                            } else {
                                                echo '<em>Unknown</em>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="info-row">
                                    <div class="row">
                                        <div class="col-md-3"><strong>Status:</strong></div>
                                        <div class="col-md-9">
                                            <?php if ($document['is_archived']): ?>
                                                <span class="badge bg-secondary"><i class="fas fa-archive"></i> Archived</span>
                                            <?php else: ?>
                                                <span class="badge bg-success"><i class="fas fa-check-circle"></i> Active</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="info-row">
                                    <div class="row">
                                        <div class="col-md-3"><strong>Document ID:</strong></div>
                                        <div class="col-md-9"><code>#<?php echo $doc_id; ?></code></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Download History -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0">Download Statistics</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h2 class="mb-0"><?php echo $document['download_count']; ?></h2>
                                        <p class="text-muted mb-0">Total Downloads</p>
                                    </div>
                                    <div>
                                        <i class="fas fa-chart-line fa-3x text-primary opacity-25"></i>
                                    </div>
                                </div>
                                <hr>
                                <p class="small text-muted mb-0">
                                    <i class="fas fa-info-circle"></i> This document has been downloaded <?php echo $document['download_count']; ?> time<?php echo $document['download_count'] !== 1 ? 's' : ''; ?> since upload.
                                </p>
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
