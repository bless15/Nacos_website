<?php
/**
 * Admin Sidebar Component
 */
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="sidebar-header">
    <img src="../assets/images/nacos_logo.jpg" alt="NACOS Logo" style="height: 60px; margin-bottom: 10px;" onerror="this.style.display='none'">
        <h4>NACOS Dashboard</h4>
        <small>Admin Panel</small>
    </div>
    
    <div class="sidebar-menu">
        <a href="index.php" class="<?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="members.php" class="<?php echo $current_page === 'members.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> Members
        </a>
        <a href="projects.php" class="<?php echo $current_page === 'projects.php' ? 'active' : ''; ?>">
            <i class="fas fa-project-diagram"></i> Projects
        </a>
        <a href="events.php" class="<?php echo $current_page === 'events.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i> Events
        </a>
        <a href="resources.php" class="<?php echo $current_page === 'resources.php' ? 'active' : ''; ?>">
            <i class="fas fa-book"></i> Resources
        </a>
        <a href="partners.php" class="<?php echo $current_page === 'partners.php' ? 'active' : ''; ?>">
            <i class="fas fa-handshake"></i> Partners
        </a>
        <a href="documents.php" class="<?php echo $current_page === 'documents.php' ? 'active' : ''; ?>">
            <i class="fas fa-folder"></i> Documents
        </a>
        <hr style="border-color: rgba(255,255,255,0.1);">
        <a href="../public/index.php" target="_blank">
            <i class="fas fa-external-link-alt"></i> View Public Site
        </a>
        <a href="logout.php">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>
