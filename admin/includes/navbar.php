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
    <div style="display:flex;align-items:center;gap:12px">
        <button class="menu-toggle" id="menuToggle" type="button" aria-label="Toggle navigation" aria-expanded="false"><i class="fas fa-bars"></i></button>
        <h3><i class="fas <?php echo $page_icon; ?> me-2"></i> <?php echo $page_title; ?></h3>
    </div>
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

<style>
    /* Minimal responsive toggle styles for admin includes */
    .top-bar{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
    }
    .top-bar > div:first-child{
        display:flex;
        align-items:center;
        gap:12px;
        min-width:0;
    }
    .top-bar h3{
        margin:0;
    }
    .user-info{
        margin-left:auto;
        display:flex;
        align-items:center;
        gap:10px;
        text-align:right;
        flex-shrink:0;
    }
    .user-avatar{
        width:38px;
        height:38px;
        border-radius:50%;
        background:linear-gradient(135deg,#667eea,#764ba2);
        color:#fff;
        display:flex;
        align-items:center;
        justify-content:center;
        font-weight:700;
    }
    .menu-toggle{display:none;border:0;background:transparent;color:#4A5BD8;font-size:20px;padding:6px;border-radius:6px}
    .sidebar-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.35);z-index:900}
    @media (max-width:991px){
        .top-bar h3{font-size:1.2rem}
        .user-info strong{font-size:.9rem}
        .user-info small{font-size:.75rem}
        .menu-toggle{display:inline-flex}
        .sidebar{width:var(--sidebar-width,240px);position:fixed;left:0;top:0;height:100vh;transform:translateX(-100%);transition:transform .28s ease;z-index:1000}
        body.sidebar-open .sidebar{transform:translateX(0)}
        .sidebar-backdrop{display:block;opacity:0;transition:opacity .25s ease;pointer-events:none}
        body.sidebar-open .sidebar-backdrop{opacity:1;pointer-events:auto}
        body.sidebar-open{overflow:hidden}
    }
</style>

<script>
    (function(){
        const menuToggle = document.getElementById('menuToggle');
        const backdrop = document.getElementById('sidebarBackdrop');
        const sidebarLinks = document.querySelectorAll('.sidebar-menu a');
        if(!menuToggle) return;

        const closeSidebar = ()=>{document.body.classList.remove('sidebar-open'); menuToggle.setAttribute('aria-expanded','false')};
        menuToggle.addEventListener('click', function(){
            const open = !document.body.classList.contains('sidebar-open');
            document.body.classList.toggle('sidebar-open', open);
            this.setAttribute('aria-expanded', open? 'true':'false');
        });

        if(backdrop) backdrop.addEventListener('click', closeSidebar);
        sidebarLinks.forEach(a=>a.addEventListener('click', closeSidebar));
    })();
</script>
