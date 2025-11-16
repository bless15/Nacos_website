<?php
/**
 * ============================================
 * NACOS DASHBOARD - PUBLIC PROJECTS PORTFOLIO
 * ============================================
 * Purpose: Showcase all member projects
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

// --- Filtering and Searching Logic ---
$search_term = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'all';

// Base query
$query = "
    SELECT p.*
    FROM PROJECTS p
";
$conditions = [];
$params = [];

// Add search condition
if (!empty($search_term)) {
    $conditions[] = "(p.title LIKE :search OR p.description LIKE :search)";
    $params[':search'] = '%' . $search_term . '%';
}

// Add status condition
if ($filter_status !== 'all') {
    $conditions[] = "p.project_status = :status";
    $params[':status'] = $filter_status;
}

// Append conditions to query
if (!empty($conditions)) {
    $query .= " WHERE " . implode(' AND ', $conditions);
}

// Add ordering
$query .= " ORDER BY p.start_date DESC";

// Fetch projects
$projects = $db->fetchAll($query, $params);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects - NACOS</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/public.css">
    <style>
        .page-header {
            background: var(--gradient);
            color: #fff;
            padding: 80px 0;
            text-align: center;
        }
        .page-header h1 {
            color: #fff;
            font-size: 3rem;
        }
        .filters-bar {
            background: var(--light-gray);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 40px;
        }
        .no-projects {
            text-align: center;
            padding: 80px 20px;
            background: var(--light-gray);
            border-radius: 10px;
        }
        .no-projects i {
            font-size: 4rem;
            color: #ccc;
            margin-bottom: 20px;
        }
        .project-card-full {
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.07);
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .project-card-full:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        .project-card-full .card-body {
            padding: 25px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        .project-card-full .card-title {
            font-size: 1.3rem;
        }
        .project-card-full .card-text {
            flex-grow: 1;
        }
        .project-card-full .card-footer {
            background: var(--light-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            padding: 15px 25px;
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        .status-badge.completed { background-color: #d1e7dd; color: #0f5132; }
    .status-badge.in_progress { background-color: #cff4fc; color: #055160; }
    .status-badge.ideation { background-color: #fff3cd; color: #664d03; }
    </style>
</head>
<body>
    <!-- Header (shared) -->
    <?php include __DIR__ . '/../includes/public_navbar.php'; ?>
    
    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1>Project Portfolio</h1>
            <p class="lead">A showcase of innovation and collaboration by NACOS members.</p>
        </div>
    </section>
    
    <!-- Main Content -->
    <main class="container py-5">
        <!-- Filters -->
        <div class="filters-bar">
            <form action="projects.php" method="GET">
                <div class="row g-3 align-items-center">
                    <div class="col-lg-6">
                        <input type="text" name="search" class="form-control" placeholder="Search by title or keyword..." value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                    <div class="col-lg-3">
                        <select name="status" class="form-select">
                            <option value="all" <?php echo ($filter_status === 'all') ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="completed" <?php echo ($filter_status === 'completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="in_progress" <?php echo ($filter_status === 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                            <option value="ideation" <?php echo ($filter_status === 'ideation') ? 'selected' : ''; ?>>Planned</option>
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Filter Projects
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Projects Grid -->
        <?php if (empty($projects)): ?>
            <div class="no-projects">
                <i class="fas fa-lightbulb-slash"></i>
                <h2>No Projects Found</h2>
                <p class="lead">Your search or filter criteria did not match any projects. Try a different search.</p>
                <a href="projects.php" class="btn btn-primary mt-3">Clear Filters</a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($projects as $project): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card project-card-full">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($project['title']); ?></h5>
                                <p class="card-text text-muted">
                                    <?php echo substr(htmlspecialchars($project['description']), 0, 150) . '...'; ?>
                                </p>
                                <div class="mt-auto">
                                    <a href="project_details.php?id=<?php echo $project['project_id']; ?>" class="btn btn-outline-primary w-100">View Details</a>
                                </div>
                            </div>
                            <div class="card-footer">
                                <span class="status-badge <?php echo htmlspecialchars($project['project_status']); ?>">
                                    <?php echo ucfirst(str_replace(['_', '-'], ' ', $project['project_status'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    
    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
