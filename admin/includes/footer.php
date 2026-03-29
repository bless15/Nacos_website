<?php
// Admin footer include: loads Bootstrap JS and the reusable confirm modal

$apply_sticky_footer = true;
?>

<style>
	:root {
		--primary-color: #0F6B3E;
		--secondary-color: #ffffff;
		--accent-color: #F4B400;
	}

	body,
	.main-content,
	.top-bar,
	.page-header,
	.filter-card,
	.filters-card,
	.table-card,
	.content-card,
	.card,
	.modal-content {
		background-color: #ffffff;
	}

	.sidebar-header {
		background: linear-gradient(135deg, #0F6B3E, #0b5a34) !important;
	}

	.sidebar {
		background: #0F6B3E !important;
		color: #ffffff !important;
	}

	.sidebar-menu a {
		color: rgba(255, 255, 255, 0.92) !important;
	}

	.sidebar-menu a i {
		color: #F4B400 !important;
	}

	.sidebar-menu a:hover,
	.sidebar-menu a.active {
		background: rgba(244, 180, 0, 0.2) !important;
		color: #ffffff !important;
	}

	.sidebar-menu a:hover i,
	.sidebar-menu a.active i {
		color: #F4B400 !important;
	}

	h1, h2, h3, h4, h5,
	.page-title,
	.header-title h1,
	.top-bar h3,
	.stat-info h3 {
		color: #0F6B3E;
	}

	.btn-primary,
	.action-menu-btn,
	.mobile-filter-toggle,
	.menu-toggle {
		background: #F4B400 !important;
		border-color: #F4B400 !important;
		color: #0F6B3E !important;
		box-shadow: none;
	}

	.btn-primary:hover,
	.btn-primary:focus,
	.action-menu-btn:hover,
	.action-menu-btn:focus,
	.mobile-filter-toggle:hover,
	.mobile-filter-toggle:focus,
	.menu-toggle:hover,
	.menu-toggle:focus {
		background: #dea300 !important;
		border-color: #dea300 !important;
		color: #0F6B3E !important;
	}

	.btn-outline-primary,
	.text-primary,
	.link-primary {
		color: #0F6B3E !important;
	}

	.btn-outline-primary {
		background: #ffffff !important;
		border-color: #0F6B3E !important;
	}

	.btn-outline-primary:hover,
	.btn-outline-primary:focus,
	.btn-outline-primary:active {
		background: #0F6B3E !important;
		border-color: #0F6B3E !important;
		color: #ffffff !important;
	}

	.header-right-icon {
		background: rgba(244, 180, 0, 0.2) !important;
		color: #0F6B3E !important;
	}

	.stat-icon {
		background: linear-gradient(135deg, #F4B400, #dea300) !important;
		color: #0F6B3E !important;
	}

	.event-card .event-header {
		background: linear-gradient(135deg, #0F6B3E, #0b5a34) !important;
		color: #ffffff !important;
	}

	.event-card .event-title {
		color: #ffffff !important;
	}

	.event-card .event-icon {
		background: rgba(255, 255, 255, 0.2) !important;
		color: #ffffff !important;
	}

	.event-card .event-type-badge {
		background: rgba(255, 255, 255, 0.22) !important;
		color: #ffffff !important;
	}

	.filters-card,
	.filter-card,
	.table-card,
	.content-card,
	.project-card,
	.event-card,
	.stat-box,
	.stat-card-mini,
	.document-card,
	.form-control,
	.form-select,
	.page-link,
	.dropdown-menu {
		border-color: rgba(244, 180, 0, 0.35) !important;
	}

	.form-control:focus,
	.form-select:focus {
		border-color: #F4B400 !important;
		box-shadow: 0 0 0 0.2rem rgba(244, 180, 0, 0.2) !important;
	}

	.main-content a:not(.btn):not(.dropdown-item):not(.page-link):not(.nav-link):not(.action-btn):not(.btn-view):not(.btn-edit):not(.btn-delete) {
		color: #0F6B3E;
	}

	.page-item.active .page-link {
		background: #0F6B3E !important;
		border-color: #0F6B3E !important;
		color: #ffffff !important;
	}

	.resource-list-item {
		border-color: rgba(15, 107, 62, 0.18) !important;
	}

	.resource-list-item:hover,
	.resource-list-item.active {
		border-color: #0F6B3E !important;
		background: rgba(15, 107, 62, 0.06) !important;
	}

	.resource-list-item .resource-icon {
		color: #0F6B3E !important;
	}

	.resource-list-item .badge.bg-info,
	.detail-panel .badge.bg-info,
	.detail-badges .badge.bg-info {
		background: #0F6B3E !important;
		color: #ffffff !important;
	}

	.resource-list-item .badge.bg-secondary,
	.detail-panel .badge.bg-secondary,
	.detail-badges .badge.bg-secondary {
		background: #e9ecef !important;
		color: #495057 !important;
	}

	.detail-panel .btn-outline-primary {
		border-color: #0F6B3E !important;
		color: #0F6B3E !important;
	}

	.detail-panel .btn-outline-primary:hover,
	.detail-panel .btn-outline-primary:focus {
		background: #0F6B3E !important;
		border-color: #0F6B3E !important;
		color: #ffffff !important;
	}

	.partners-list-title,
	.partners-list-title.text-primary {
		color: #0F6B3E !important;
	}

	.partners-list-toolbar .btn-primary {
		background: #0F6B3E !important;
		border-color: #0F6B3E !important;
		color: #ffffff !important;
	}

	.partners-list-toolbar .btn-primary:hover,
	.partners-list-toolbar .btn-primary:focus {
		background: #0b5a34 !important;
		border-color: #0b5a34 !important;
		color: #ffffff !important;
	}

	.partners-list-toolbar .form-control,
	.partners-list-toolbar .form-select {
		border-color: rgba(15, 107, 62, 0.35) !important;
	}

	.partners-list-toolbar .form-control:focus,
	.partners-list-toolbar .form-select:focus {
		border-color: #0F6B3E !important;
		box-shadow: 0 0 0 0.2rem rgba(15, 107, 62, 0.18) !important;
	}

	.stats-card-gradient.purple,
	.stats-card-gradient.green,
	.stats-card-gradient.pink,
	.stats-card-gradient.cyan {
		background: linear-gradient(135deg, #0F6B3E, #0b5a34) !important;
		color: #ffffff !important;
	}

	.stats-card-footer a,
	.content-card h5 i,
	.dept-count,
	.tech-tag,
	.timeline-type,
	.project-item:hover {
		color: #0F6B3E !important;
	}

	.project-item:hover {
		border-color: #0F6B3E !important;
		box-shadow: 0 4px 12px rgba(15, 107, 62, 0.14) !important;
	}

	.project-icon,
	.user-avatar,
	.timeline-icon.cyan,
	.timeline-icon.pink,
	.timeline-icon.purple,
	.timeline-icon.green {
		background: linear-gradient(135deg, #0F6B3E, #0b5a34) !important;
		color: #ffffff !important;
	}

	.timeline-type {
		background: rgba(15, 107, 62, 0.08) !important;
		border: 1px solid rgba(15, 107, 62, 0.2) !important;
	}

	.badge.bg-primary,
	.badge.bg-info,
	.badge.bg-secondary {
		background: #0F6B3E !important;
		color: #ffffff !important;
	}

	.member-avatar,
	.dept-icon.cs,
	.dept-icon.cyber,
	.dept-icon.software,
	.dept-icon.it,
	.dept-progress-bar,
	.dept-progress-bar.green,
	.dept-progress-bar.cyan,
	.dept-progress-bar.pink {
		background: linear-gradient(135deg, #0F6B3E, #0b5a34) !important;
		color: #ffffff !important;
	}

	.dept-count {
		color: #0F6B3E !important;
	}

	<?php if ($apply_sticky_footer): ?>
	/* Global sticky footer across admin pages */
	body {
		min-height: 100vh;
		display: flex;
		flex-direction: column;
	}

	body > .wrapper,
	body > .main-content,
	body > .container,
	body > .container-fluid {
		flex: 1 0 auto;
	}

	body > .wrapper > .main-content,
	body > .main-content {
		min-height: auto !important;
	}

	footer {
		margin-top: auto !important;
	}
	<?php endif; ?>

	@media (max-width: 991px) {
		.stats-card-gradient.purple,
		.stats-card-gradient.green,
		.stats-card-gradient.pink,
		.stats-card-gradient.cyan {
			background: transparent !important;
			color: inherit !important;
		}

		.stats-card-gradient.purple .stats-card-icon,
		.stats-card-gradient.green .stats-card-icon,
		.stats-card-gradient.pink .stats-card-icon,
		.stats-card-gradient.cyan .stats-card-icon {
			background: linear-gradient(135deg, #0F6B3E, #0b5a34) !important;
			color: #ffffff !important;
		}
	}
</style>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php // Confirmation modal include (relies on Bootstrap JS above) ?>
<?php
// Include the admin confirm modal from this folder
if (file_exists(__DIR__ . '/confirm_modal.php')) {
	include __DIR__ . '/confirm_modal.php';
}
?>
