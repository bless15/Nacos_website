<?php
/**
 * ============================================
 * NACOS DASHBOARD - PROJECT DETAILS PAGE
 * ============================================
 * Purpose: Display detailed information about a specific project
 * Access: Public
 * Created: November 3, 2025
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Include required files
require_once '../config/database.php';
require_once '../includes/auth.php';

// Initialize database
$db = getDB();

// Get project ID from URL
$project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($project_id <= 0) {
    header('Location: projects.php');
    exit();
}

// Fetch project details
$project = $db->fetchOne(
    "SELECT * FROM PROJECTS WHERE project_id = ?",
    [$project_id]
);

// If project not found, redirect
if (!$project) {
    header('Location: projects.php');
    exit();
}

// Get related projects (same status or random)
$related_projects = $db->fetchAll(
    "SELECT * FROM PROJECTS WHERE project_id != ? ORDER BY RAND() LIMIT 3",
    [$project_id]
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($project['title']); ?> - NACOS Projects</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/public.css">
    <style>
        body {
            padding-top: 80px;
        }
        .project-hero {
            background: var(--gradient);
            color: #fff;
            padding: 60px 0;
            margin-bottom: 40px;
        }
        .project-hero h1 {
            color: #fff;
            font-size: 2.5rem;
            margin-bottom: 20px;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 600;
            text-transform: capitalize;
            margin-top: 15px;
        }
        .status-completed {
            background: #28a745;
            color: #fff;
        }
        .status-ongoing {
            background: #ffc107;
            color: #000;
        }
        .status-planned {
            background: #6c757d;
            color: #fff;
        }
        .project-detail-section {
            padding: 30px 0;
        }
        .detail-card {
            background: #fff;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .detail-card h3 {
            color: var(--primary-color);
            font-size: 1.5rem;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--light-gray);
            padding-bottom: 10px;
        }
        .tech-stack {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }
        .tech-badge {
            background: var(--light-gray);
            color: var(--primary-color);
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .project-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .stat-box {
            text-align: center;
            padding: 20px;
            background: var(--light-gray);
            border-radius: 10px;
        }
        .stat-box i {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        .stat-box h4 {
            font-size: 1.8rem;
            color: var(--dark-gray);
            margin-bottom: 5px;
        }
        .stat-box p {
            color: var(--text-color);
            margin: 0;
            font-size: 0.9rem;
        }
        .related-project-card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            height: 100%;
        }
        .related-project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .related-project-card h5 {
            color: var(--primary-color);
            font-size: 1.1rem;
        }
        .cta-section {
            background: var(--gradient);
            color: #fff;
            padding: 60px 0;
            margin-top: 60px;
            text-align: center;
        }
        .cta-section h2 {
            color: #fff;
            margin-bottom: 20px;
        }
        .github-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #fff;
            color: var(--primary-color);
            padding: 12px 30px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .github-link:hover {
            background: var(--light-gray);
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <!-- Header (shared) -->
    <?php include __DIR__ . '/../includes/public_navbar.php'; ?>

    <!-- Project Hero Section -->
    <section class="project-hero">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php" style="color: rgba(255,255,255,0.8);">Home</a></li>
                    <li class="breadcrumb-item"><a href="projects.php" style="color: rgba(255,255,255,0.8);">Projects</a></li>
                    <li class="breadcrumb-item active" aria-current="page" style="color: #fff;"><?php echo htmlspecialchars($project['title']); ?></li>
                </ol>
            </nav>
            <h1><?php echo htmlspecialchars($project['title']); ?></h1>
            <p class="lead"><?php echo htmlspecialchars($project['description']); ?></p>
            <span class="status-badge status-<?php echo $project['project_status']; ?>">
                <i class="fas fa-circle me-2"></i><?php echo ucfirst($project['project_status']); ?>
            </span>
        </div>
    </section>

    <!-- Project Details Section -->
    <section class="project-detail-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    <!-- Overview -->
                    <div class="detail-card">
                        <h3><i class="fas fa-info-circle me-2"></i>Project Overview</h3>
                        <p><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
                        
                        <?php if (!empty($project['github_link'])): ?>
                            <div class="mt-4">
                                <a href="<?php echo htmlspecialchars($project['github_link']); ?>" target="_blank" class="github-link">
                                    <i class="fab fa-github"></i> View on GitHub
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Technologies Used -->
                    <div class="detail-card">
                        <h3><i class="fas fa-code me-2"></i>Technologies & Tools</h3>
                        <p>This project leverages cutting-edge technologies to deliver exceptional results:</p>
                        <div class="tech-stack">
                            <?php
                            $techs = [];
                            $features = [];
                            if (!empty($project['tech_stack'])) {
                                $decoded = json_decode($project['tech_stack'], true);
                                if (is_array($decoded) && isset($decoded['technologies'])) {
                                    $techs = $decoded['technologies'];
                                    if (isset($decoded['features']) && is_array($decoded['features'])) {
                                        $features = $decoded['features'];
                                    }
                                } else {
                                    // Fallback: comma separated string
                                    $techs = array_values(array_filter(array_map('trim', preg_split('/,/', $project['tech_stack']))));
                                }
                            }

                            if (empty($techs)) {
                                // Legacy fallback
                                $techs = ['PHP', 'MySQL', 'JavaScript', 'Bootstrap', 'HTML5', 'CSS3', 'Git', 'VS Code'];
                            }

                            foreach ($techs as $tech):
                            ?>
                                <span class="tech-badge"><i class="fas fa-check-circle me-1"></i><?php echo htmlspecialchars($tech); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Key Features -->
                    <div class="detail-card">
                        <h3><i class="fas fa-star me-2"></i>Key Features</h3>
                        <?php if (!empty($features)): ?>
                            <ul class="list-unstyled">
                                <?php foreach ($features as $f): ?>
                                    <li class="mb-3"><i class="fas fa-check text-success me-2"></i><?php echo htmlspecialchars($f); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <ul class="list-unstyled">
                                <li class="mb-3"><i class="fas fa-check text-success me-2"></i>Responsive and mobile-friendly design</li>
                                <li class="mb-3"><i class="fas fa-check text-success me-2"></i>Secure authentication and authorization</li>
                                <li class="mb-3"><i class="fas fa-check text-success me-2"></i>Real-time data processing and updates</li>
                                <li class="mb-3"><i class="fas fa-check text-success me-2"></i>Intuitive user interface and experience</li>
                                <li class="mb-3"><i class="fas fa-check text-success me-2"></i>Scalable architecture for future growth</li>
                                <li class="mb-3"><i class="fas fa-check text-success me-2"></i>Comprehensive documentation and testing</li>
                            </ul>
                        <?php endif; ?>
                    </div>

                    
                </div>

                <div class="col-lg-4">
                    <!-- Project Stats -->
                    <div class="detail-card">
                        <h3><i class="fas fa-chart-bar me-2"></i>Project Stats</h3>
                        <div class="project-stats">
                            <div class="stat-box">
                                <i class="fas fa-calendar-alt"></i>
                                <h4><?php echo date('M Y', strtotime($project['created_at'])); ?></h4>
                                <p>Started</p>
                            </div>
                            <div class="stat-box">
                                <i class="fas fa-users"></i>
                                <h4>5+</h4>
                                <p>Contributors</p>
                            </div>
                            <div class="stat-box">
                                <i class="fas fa-code-branch"></i>
                                <h4>12+</h4>
                                <p>Features</p>
                            </div>
                            <div class="stat-box">
                                <i class="fas fa-star"></i>
                                <h4>4.8/5</h4>
                                <p>Rating</p>
                            </div>
                        </div>
                    </div>

                    <!-- Project Info -->
                    <div class="detail-card">
                        <h3><i class="fas fa-clipboard-list me-2"></i>Project Info</h3>
                        <div class="mb-3">
                            <strong><i class="fas fa-flag me-2 text-primary"></i>Status:</strong><br>
                            <span class="badge bg-<?php echo $project['project_status'] === 'completed' ? 'success' : ($project['project_status'] === 'ongoing' ? 'warning' : 'secondary'); ?> mt-2">
                                <?php echo ucfirst($project['project_status']); ?>
                            </span>
                        </div>
                        <div class="mb-3">
                            <strong><i class="fas fa-calendar me-2 text-primary"></i>Created:</strong><br>
                            <?php echo date('F j, Y', strtotime($project['created_at'])); ?>
                        </div>
                        <div class="mb-3">
                            <strong><i class="fas fa-layer-group me-2 text-primary"></i>Category:</strong><br>
                            Web Development
                        </div>
                    </div>

                    <!-- Share Project -->
                    <div class="detail-card">
                        <h3><i class="fas fa-share-alt me-2"></i>Share Project</h3>
                        <div class="d-flex gap-2">
                            <a href="#" class="btn btn-outline-primary flex-fill"><i class="fab fa-twitter"></i></a>
                            <a href="#" class="btn btn-outline-primary flex-fill"><i class="fab fa-facebook"></i></a>
                            <a href="#" class="btn btn-outline-primary flex-fill"><i class="fab fa-linkedin"></i></a>
                            <a href="#" class="btn btn-outline-primary flex-fill"><i class="fas fa-link"></i></a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Related Projects -->
            <?php if (!empty($related_projects)): ?>
            <div class="row mt-5">
                <div class="col-12">
                    <h2 class="mb-4">Related Projects</h2>
                </div>
                <?php foreach ($related_projects as $related): ?>
                <div class="col-lg-4 mb-4">
                    <div class="related-project-card">
                        <div class="mb-3">
                            <span class="badge bg-<?php echo $related['project_status'] === 'completed' ? 'success' : ($related['project_status'] === 'ongoing' ? 'warning' : 'secondary'); ?>">
                                <?php echo ucfirst($related['project_status']); ?>
                            </span>
                        </div>
                        <h5><?php echo htmlspecialchars($related['title']); ?></h5>
                        <p class="text-muted"><?php echo substr(htmlspecialchars($related['description']), 0, 100) . '...'; ?></p>
                        <a href="project_details.php?id=<?php echo $related['project_id']; ?>" class="btn btn-outline-primary btn-sm">View Details</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <h2>Want to Contribute to Our Projects?</h2>
            <p class="lead mb-4">Join NACOS and be part of building innovative solutions!</p>
            <a href="register.php" class="btn btn-light btn-lg">Become a Member</a>
        </div>
    </section>

    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
