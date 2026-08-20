/**
 * assets/js/notifications.js
 * -----------------------------------------------------------------------
 * Loaded globally (includes/footer.php) on every authenticated page.
 * Drives the navbar's low-stock bell: fetches on load, then polls so a
 * cashier ringing up the last few units of something sees the alert
 * without needing to refresh or go looking for it on the Inventory page.
 */
(function ($) {
    'use strict';

    const ENDPOINT = (window.APP_URL || '') + '/app/controllers/InventoryController.php';
    const POLL_MS = 45000;

    function escapeHtml(str) { return $('<div>').text(str == null ? '' : str).html(); }

    function render(count, items) {
        const $dot = $('#notifDot');
        const $badge = $('#notifCountBadge');
        const $list = $('#notifList');

        $dot.toggleClass('d-none', count === 0);
        if (count > 0) { $badge.show().text(count); } else { $badge.hide(); }

        if (!items.length) {
            $list.html('<div class="text-center text-muted small py-3">No low-stock items right now.</div>');
            return;
        }

        $list.empty();
        items.forEach(function (item) {
            const outOfStock = Number(item.quantity_on_hand) <= 0;
            $list.append(`
                <div class="px-3 py-2 border-bottom pos-notif-item">
                    <div class="d-flex justify-content-between">
                        <span class="small fw-medium">${escapeHtml(item.product_name)}</span>
                        <span class="badge ${outOfStock ? 'pos-badge-danger' : 'pos-badge-warning'}">
                            ${outOfStock ? 'Out of stock' : item.quantity_on_hand + ' ' + escapeHtml(item.unit) + ' left'}
                        </span>
                    </div>
                    <div class="text-muted" style="font-size: 0.72rem;">Reorder point: ${item.stock_alert_qty} ${escapeHtml(item.unit)}</div>
                </div>
            `);
        });
    }

    function poll() {
        $.get(ENDPOINT, { action: 'low_stock_alerts' })
            .done(function (res) {
                if (res.success) render(res.count, res.items);
            });
        // Silently skip on failure (e.g. session hiccup) - the global
        // ajaxError handler in app.js already deals with real 401s, and
        // a missed poll here just means the bell updates next cycle.
    }

    $(function () {
        if (!$('#notifBell').length) return;
        poll();
        setInterval(poll, POLL_MS);
    });
})(jQuery);
