<?php
/**
 * Admin Top Navbar Component
 */
$page_title = 'Dashboard';
$page_icon = 'fa-chart-line';

// Determine page title based on current page
$current_page = basename($_SERVER['PHP_SELF']);
switch ($current_page) {
    case 'members.php':
    case 'add_member.php':
    case 'edit_member.php':
    case 'view_member.php':
        $page_title = 'Members Management';
        $page_icon = 'fa-users';
        break;
    case 'events.php':
    case 'add_event.php':
    case 'edit_event.php':
    case 'view_event.php':
    case 'event_attendance.php':
        $page_title = 'Events Management';
        $page_icon = 'fa-calendar-alt';
        break;
    case 'projects.php':
    case 'add_project.php':
    case 'edit_project.php':
    case 'view_project.php':
        $page_title = 'Projects Management';
        $page_icon = 'fa-project-diagram';
        break;
    case 'resources.php':
    case 'add_resource.php':
    case 'edit_resource.php':
        $page_title = 'Resources Management';
        $page_icon = 'fa-book';
        break;
    case 'partners.php':
    case 'add_partner.php':
    case 'edit_partner.php':
        $page_title = 'Partners Management';
        $page_icon = 'fa-handshake';
        break;
    case 'documents.php':
    case 'add_document.php':
    case 'edit_document.php':
    case 'view_document.php':
        $page_title = 'Documents Management';
        $page_icon = 'fa-folder';
        break;
}

// Get current user if not already set
if (!isset($current_user)) {
    $current_user = getCurrentMember();
}
?>
<!-- Top Bar -->
<div class="top-bar">
    <h3><i class="fas <?php echo $page_icon; ?> me-2"></i> <?php echo $page_title; ?></h3>
    <div class="user-info">
        <div>
            <strong><?php echo htmlspecialchars($current_user['full_name']); ?></strong><br>
            <small class="text-muted"><?php echo ucfirst($current_user['role']); ?></small>
        </div>
        <div class="user-avatar">
            <?php echo strtoupper(substr($current_user['full_name'], 0, 1)); ?>
        </div>
    </div>
</div>

<!-- Flash Message -->
<?php 
$flash = getFlashMessage();
if ($flash): 
?>
    <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'error' ? 'danger' : 'info'); ?> alert-dismissible fade show" role="alert">
        <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check' : ($flash['type'] === 'error' ? 'exclamation' : 'info'); ?>-circle me-2"></i>
        <?php echo htmlspecialchars($flash['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
