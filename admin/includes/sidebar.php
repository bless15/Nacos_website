<?php
/**
 * Admin Sidebar Component
 */
$current_page = basename($_SERVER['PHP_SELF']);
$can_manage_members = isLoggedIn() || isMemberAdmin();
$is_full_admin = isLoggedIn() || isMemberAdmin();

$can_view_projects = $is_full_admin || canExecutiveAccessPage('projects.php');
$can_view_events = $is_full_admin || canExecutiveAccessPage('events.php');
$can_view_resources = $is_full_admin || canExecutiveAccessPage('resources.php');
$can_view_partners = $is_full_admin;
$can_view_documents = $is_full_admin || canExecutiveAccessPage('documents.php');
$can_view_announcements = $is_full_admin || canExecutiveAccessPage('announcements.php');
$can_view_past_questions = $is_full_admin || canExecutiveAccessPage('past_questions.php');

$pages_with_custom_animations = [
    'index.php',
    'members.php',
    'projects.php',
    'events.php',
    'resources.php',
    'partners.php',
    'documents.php',
    'announcements.php',
    'past_questions.php'
];

$apply_shared_page_animation = !in_array($current_page, $pages_with_custom_animations, true);
?>
<style>
@media (max-width: 992px) {
    .sidebar {
        display: flex !important;
        flex-direction: column !important;
    }

    .sidebar .sidebar-menu {
        display: flex !important;
        flex-direction: column !important;
        flex: 1 1 auto !important;
    }

    .sidebar .sidebar-bottom-links {
        margin-top: auto !important;
        padding-top: 24px !important;
        padding-bottom: 12px !important;
    }
}

<?php if ($apply_shared_page_animation): ?>
.main-content.page-enter-stagger > * {
    opacity: 0;
    animation: sharedIceIn 1s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    animation-delay: calc(0.10s + (var(--enter-index, 0) * 0.16s));
}

@keyframes sharedIceIn {
    from {
        opacity: 0;
        filter: blur(5px);
        transform: translateY(18px) scale(0.985);
    }
    to {
        opacity: 1;
        filter: blur(0);
        transform: translateY(0) scale(1);
    }
}
<?php endif; ?>
</style>
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
        <?php if ($can_manage_members): ?>
            <a href="members.php" class="<?php echo in_array($current_page, ['members.php', 'add_member.php', 'edit_member.php', 'view_member.php', 'approve_member.php', 'delete_member.php'], true) ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Members
            </a>
        <?php endif; ?>
        <?php if ($can_view_projects): ?>
            <a href="projects.php" class="<?php echo in_array($current_page, ['projects.php', 'add_project.php', 'edit_project.php', 'view_project.php'], true) ? 'active' : ''; ?>">
                <i class="fas fa-project-diagram"></i> Projects
            </a>
        <?php endif; ?>
        <?php if ($can_view_events): ?>
            <a href="events.php" class="<?php echo in_array($current_page, ['events.php', 'add_event.php', 'edit_event.php', 'view_event.php', 'event_attendance.php'], true) ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i> Events
            </a>
        <?php endif; ?>
        <?php if ($can_view_resources): ?>
            <a href="resources.php" class="<?php echo in_array($current_page, ['resources.php', 'add_resource.php', 'edit_resource.php', 'view_resource.php'], true) ? 'active' : ''; ?>">
                <i class="fas fa-book"></i> Resources
            </a>
        <?php endif; ?>
        <?php if ($can_view_partners): ?>
            <a href="partners.php" class="<?php echo $current_page === 'partners.php' ? 'active' : ''; ?>">
                <i class="fas fa-handshake"></i> Partners
            </a>
        <?php endif; ?>
        <?php if ($can_view_documents): ?>
            <a href="documents.php" class="<?php echo in_array($current_page, ['documents.php', 'add_document.php', 'edit_document.php', 'view_document.php'], true) ? 'active' : ''; ?>">
                <i class="fas fa-folder"></i> Documents
            </a>
        <?php endif; ?>
        <?php if ($can_view_announcements): ?>
            <a href="announcements.php" class="<?php echo in_array($current_page, ['announcements.php', 'add_announcement.php', 'edit_announcement.php', 'delete_announcement.php'], true) ? 'active' : ''; ?>">
                <i class="fas fa-bullhorn"></i> Announcements
            </a>
        <?php endif; ?>
        <?php if ($can_view_past_questions): ?>
            <a href="past_questions.php" class="<?php echo $current_page === 'past_questions.php' ? 'active' : ''; ?>">
                <i class="fas fa-file-pdf"></i> Past Questions
            </a>
        <?php endif; ?>
        <div class="sidebar-bottom-links">
            <hr style="border-color: rgba(255,255,255,0.1);">
            <a href="../public/index.php" target="_blank">
                <i class="fas fa-external-link-alt"></i> View Public Site
            </a>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    </div>
    <div id="sidebarBackdrop" class="sidebar-backdrop sidebar-overlay" aria-hidden="true"></div>

<?php if ($apply_shared_page_animation): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const mainContent = document.querySelector('.main-content');
    if (!mainContent) return;

    mainContent.classList.add('page-enter-stagger');

    const children = Array.from(mainContent.children).filter((el) => {
        if (!el || !el.tagName) return false;
        const tag = el.tagName.toLowerCase();
        if (tag === 'script' || tag === 'style') return false;
        return true;
    });

    children.forEach((el, index) => {
        el.style.setProperty('--enter-index', String(index));
    });
});
</script>
<?php endif; ?>
