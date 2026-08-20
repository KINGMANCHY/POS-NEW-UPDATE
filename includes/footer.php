<?php
/**
 * includes/footer.php
 * -----------------------------------------------------------------------
 * Shared closing scripts, included near the end of <body> on
 * authenticated pages.
 */
if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}
?>
<footer class="pos-footer text-center text-muted small py-3">
    &copy; <?= date('Y') ?> <?= Security::escape(APP_NAME) ?> · v<?= Security::escape(APP_VERSION) ?>
</footer>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Bootstrap 5 Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- App scripts -->
<script src="<?= Helper::versionedAsset('/js/app.js') ?>"></script>
<script src="<?= Helper::versionedAsset('/js/notifications.js') ?>"></script>
