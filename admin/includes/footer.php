<?php
// Admin footer include: loads Bootstrap JS and the reusable confirm modal
?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>

<?php // Confirmation modal include (relies on Bootstrap JS above) ?>
<?php
// Include the admin confirm modal from this folder
if (file_exists(__DIR__ . '/confirm_modal.php')) {
	include __DIR__ . '/confirm_modal.php';
}
?>
