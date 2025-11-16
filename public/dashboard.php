<?php
/**
 * ============================================
 * NACOS DASHBOARD - MEMBER DASHBOARD
 * ============================================
 * Purpose: Main dashboard for logged-in members
 * Access: Members only
 * Created: November 3, 2025
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Include required files
require_once '../config/database.php';
require_once '../includes/auth.php';

// Require member to be logged in
requireMemberLogin();

// Initialize database
$db = getDB();

// Get current member data
$member = getCurrentMember();
$member_id = $member['member_id'];

// Fetch detailed member information
$member_details = $db->fetchOne("SELECT * FROM MEMBERS WHERE member_id = :id", [':id' => $member_id]);

// Fetch member's registered events (upcoming)
$registered_events = $db->fetchAll(
    "SELECT e.* FROM EVENTS e
     JOIN EVENT_REGISTRATIONS er ON e.event_id = er.event_id
     WHERE er.member_id = :member_id AND e.event_date >= CURDATE()
     ORDER BY e.event_date ASC",
    [':member_id' => $member_id]
);

// Fetch member's projects
$member_projects = $db->fetchAll(
    "SELECT p.* FROM PROJECTS p
     JOIN MEMBER_PROJECTS mp ON p.project_id = mp.project_id
     WHERE mp.member_id = :member_id
     ORDER BY p.start_date DESC",
    [':member_id' => $member_id]
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard - NACOS</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/public.css">
    <style>
        .dashboard-header {
            background: var(--gradient);
            color: #fff;
            padding: 60px 0;
        }
        .dashboard-header h1 {
            color: #fff;
        }
        .profile-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            padding: 30px;
        }
        .section-title {
            margin-bottom: 30px;
            font-weight: 600;
        }
        .empty-state {
            text-align: center;
            padding: 50px;
            background: var(--light-gray);
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="index.php"><img src="../assets/images/nacos_logo.jpg" alt="NACOS Logo" style="height: 40px; margin-right: 10px;"> NACOS</a>
            <div class="ms-auto d-flex align-items-center">
                <span class="navbar-text me-3">
                    Welcome, <?php echo htmlspecialchars($member['full_name']); ?>
                </span>
                <a href="profile.php" class="btn btn-secondary me-2">Edit Profile</a>
                <a href="logout.php" class="btn btn-outline-primary">Logout</a>
            </div>
        </div>
    </header>
    
    <!-- Dashboard Header -->
    <section class="dashboard-header">
        <div class="container">
            <h1>Your Dashboard</h1>
            <p>Manage your profile, events, and projects.</p>
        </div>
    </section>
    
    <!-- Main Content -->
    <main class="container py-5">
        <div class="row">
            <!-- Profile Section -->
            <div class="col-lg-4">
                <h3 class="section-title">My Profile</h3>
                <div class="profile-card">
                    <h5><?php echo htmlspecialchars($member_details['full_name']); ?></h5>
                    <p class="text-muted"><?php echo htmlspecialchars($member_details['matric_no']); ?></p>
                    <hr>
                    <p><i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($member_details['email']); ?></p>
                    <p><i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($member_details['phone_number'] ?? 'Not provided'); ?></p>
                    <p><i class="fas fa-layer-group me-2"></i> <?php echo ucfirst($member_details['level']); ?> Level</p>
                    <p><i class="fas fa-check-circle me-2 text-success"></i> <?php echo ucfirst($member_details['membership_status']); ?> Member</p>
                    <a href="profile.php" class="btn btn-primary w-100 mt-3">Edit Profile</a>
                </div>
            </div>
            
            <!-- Events and Projects Section -->
            <div class="col-lg-8">
                <!-- My Upcoming Events -->
                <h3 class="section-title">My Upcoming Events</h3>
                <?php if (empty($registered_events)): ?>
                    <div class="empty-state">
                        <p>You are not registered for any upcoming events.</p>
                        <a href="events.php" class="btn btn-primary">Explore Events</a>
                    </div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($registered_events as $event): ?>
                            <a href="event_details.php?id=<?php echo $event['event_id']; ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($event['event_name']); ?></h5>
                                    <small><?php echo date('M d, Y', strtotime($event['event_date'])); ?></small>
                                </div>
                                <p class="mb-1"><?php echo htmlspecialchars($event['location']); ?></p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- My Projects -->
                <h3 class="section-title mt-5">My Projects</h3>
                <?php if (empty($member_projects)): ?>
                    <div class="empty-state">
                        <p>You are not yet part of any projects.</p>
                        <a href="projects.php" class="btn btn-primary">Explore Projects</a>
                    </div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($member_projects as $project): ?>
                            <a href="project_details.php?id=<?php echo $project['project_id']; ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($project['title']); ?></h5>
                                    <span class="badge bg-info text-dark"><?php echo ucfirst(str_replace('-', ' ', $project['project_status'])); ?></span>
                                </div>
                                <p class="mb-1"><?php echo substr(htmlspecialchars($project['description']), 0, 100) . '...'; ?></p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
