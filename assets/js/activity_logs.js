/**
 * assets/js/activity_logs.js
 * -----------------------------------------------------------------------
 * Drives views/activity_logs.php via app/controllers/ActivityLogController.php.
 */
(function ($) {
    'use strict';

    const ENDPOINT = (window.APP_URL || '') + '/app/controllers/ActivityLogController.php';
    let state = { search: '', userId: '', dateFrom: '', dateTo: '', page: 1, perPage: 25 };
    let searchDebounce = null;

    function escapeHtml(str) { return $('<div>').text(str == null ? '' : str).html(); }

    function renderIpAddress(value) {
        const ip = String(value || '').trim();
        if (!ip) return '<span class="text-muted">—</span>';

        // Private, loopback, and unspecified addresses are internal to the
        // store/network and cannot have a public geographic location.
        const isPrivate = ip === '0.0.0.0' || ip === '::1' || ip === 'localhost'
            || /^127\./.test(ip) || /^10\./.test(ip) || /^192\.168\./.test(ip)
            || /^172\.(1[6-9]|2\d|3[0-1])\./.test(ip) || /^fc/i.test(ip) || /^fe80:/i.test(ip);
        if (isPrivate) return escapeHtml(ip);

        return `<a class="link-secondary text-decoration-underline" href="https://ipinfo.io/${encodeURIComponent(ip)}" target="_blank" rel="noopener noreferrer" title="View approximate IP location">${escapeHtml(ip)} <i class="bi bi-box-arrow-up-right small"></i></a>`;
    }

    function loadFormData() {
        $.get(ENDPOINT, { action: 'form_data' }).done(function (res) {
            if (!res.success) return;
            const $sel = $('#logUserFilter');
            res.users.forEach(function (u) {
                $sel.append(`<option value="${u.user_id}">${escapeHtml(u.full_name)}</option>`);
            });
        });
    }

    function renderRows(rows) {
        const $body = $('#logTableBody');
        $body.empty();

        if (!rows.length) {
            $body.html('<tr><td colspan="5" class="text-center text-muted py-4">No activity found.</td></tr>');
            return;
        }

        rows.forEach(function (row) {
            $body.append(`
                <tr>
                    <td class="text-muted small">${escapeHtml(row.created_at)}</td>
                    <td>${escapeHtml(row.user_name)}</td>
                    <td><span class="badge pos-badge-muted font-monospace">${escapeHtml(row.action)}</span></td>
                    <td class="small">${escapeHtml(row.description || '')}</td>
                    <td class="text-muted small">${renderIpAddress(row.ip_address)}</td>
                </tr>
            `);
        });
    }

    /*function renderPagination(result) {
        const $pg = $('#logPagination').empty();
        $('#logPageInfo').text(
            result.total === 0 ? 'No results' :
            `Showing ${(result.page - 1) * result.per_page + 1}-${Math.min(result.page * result.per_page, result.total)} of ${result.total}`
        );
        if (result.total_pages <= 1) return;

        const addPage = (label, page, disabled, active) => {
            $pg.append(`<li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}"><a class="page-link" href="#" data-page="${page}">${label}</a></li>`);
        };
        addPage('&laquo;', result.page - 1, result.page <= 1, false);
        for (let p = 1; p <= result.total_pages; p++) addPage(p, p, false, p === result.page);
        addPage('&raquo;', result.page + 1, result.page >= result.total_pages, false);
    }*/

    function renderPagination(result) {
        const $pg = $('#logPagination').empty();

        $('#logPageInfo').text(
            result.total === 0 ? 'No results' :
            `Showing ${(result.page - 1) * result.per_page + 1}-${Math.min(result.page * result.per_page, result.total)} of ${result.total}`
        );

        if (result.total_pages <= 1) return;

        const addPage = (label, page, disabled = false, active = false) => {
            $pg.append(`
                <li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${page}">${label}</a>
                </li>
            `);
        };

        const addDots = () => {
            $pg.append(`
                <li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>
            `);
        };

        const total = result.total_pages;
        const current = result.page;

        // Previous
        addPage('&laquo;', current - 1, current <= 1);

        if (total <= 5) {
            // 1 2 3 4 5
            for (let p = 1; p <= total; p++) {
                addPage(p, p, false, p === current);
            }
        } else {
            // First page
            addPage(1, 1, false, current === 1);

            // Left dots
            if (current > 3) {
                addDots();
            }

            // Pages around current
            const start = Math.max(2, current - 1);
            const end = Math.min(total - 1, current + 1);

            for (let p = start; p <= end; p++) {
                addPage(p, p, false, p === current);
            }

            // Right dots
            if (current < total - 2) {
                addDots();
            }

            // Last page
            addPage(total, total, false, current === total);
        }

        // Next
        addPage('&raquo;', current + 1, current >= total);
    }

    function loadList() {
        $('#logTableBody').html('<tr><td colspan="5" class="text-center text-muted py-4">Loading...</td></tr>');
        $.ajax({
            url: ENDPOINT, method: 'GET', dataType: 'json',
            data: {
                action: 'list', search: state.search, user_id: state.userId,
                date_from: state.dateFrom, date_to: state.dateTo,
                page: state.page, per_page: state.perPage,
            },
        }).done(function (res) {
            if (res.success) { renderRows(res.result.rows); renderPagination(res.result); }
            else { $('#logTableBody').html(`<tr><td colspan="5" class="text-center text-danger py-4">${escapeHtml(res.message)}</td></tr>`); }
        }).fail(function () {
            $('#logTableBody').html('<tr><td colspan="5" class="text-center text-danger py-4">Could not load activity logs. Please try again.</td></tr>');
        });
    }

    $(function () {
        loadFormData();
        loadList();

        $('#logSearch').on('input', function () {
            clearTimeout(searchDebounce);
            const value = $(this).val();
            searchDebounce = setTimeout(function () { state.search = value; state.page = 1; loadList(); }, 350);
        });
        $('#logUserFilter').on('change', function () { state.userId = $(this).val(); state.page = 1; loadList(); });
        $('#logDateFrom').on('change', function () { state.dateFrom = $(this).val(); state.page = 1; loadList(); });
        $('#logDateTo').on('change', function () { state.dateTo = $(this).val(); state.page = 1; loadList(); });
        $('#logPerPage').on('change', function () { state.perPage = parseInt($(this).val(), 10); state.page = 1; loadList(); });

        $(document).on('click', '#logPagination a.page-link', function (e) {
            e.preventDefault();
            const page = parseInt($(this).data('page'), 10);
            if (!isNaN(page) && page >= 1) { state.page = page; loadList(); }
        });
    });
})(jQuery);
