/**
 * assets/js/sales.js
 * -----------------------------------------------------------------------
 * Drives views/sales.php via app/controllers/SalesController.php.
 */
(function ($) {
    'use strict';

    const ENDPOINT = (window.APP_URL || '') + '/app/controllers/SalesController.php';
    let state = { search: '', status: '', dateFrom: '', dateTo: '', sortBy: 'created_at', sortDir: 'DESC', page: 1, perPage: 10 };
    let searchDebounce = null;
    let activeSaleId = null;

    function escapeHtml(str) { return $('<div>').text(str == null ? '' : str).html(); }
    function money(n) { return '₱' + Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    function statusBadge(status) {
        const map = { completed: 'pos-badge-success', held: 'pos-badge-warning', voided: 'pos-badge-danger' };
        return `<span class="badge ${map[status] || 'pos-badge-muted'}">${escapeHtml(status)}</span>`;
    }

    function renderRows(rows) {
        const $body = $('#saleTableBody');
        $body.empty();

        if (!rows.length) {
            $body.html('<tr><td colspan="9" class="text-center text-muted py-4">No sales found.</td></tr>');
            return;
        }

        rows.forEach(function (row) {
            $body.append($(`
                <tr>
                    <td class="font-monospace">${escapeHtml(row.invoice_no)}</td>
                    <td class="text-muted">${escapeHtml(row.created_at)}</td>
                    <td>${escapeHtml(row.customer_name || 'Walk-in')}</td>
                    <td>${escapeHtml(row.cashier_name)}</td>
                    <td>${row.item_count}</td>
                    <td class="text-uppercase text-muted small">${escapeHtml(row.payment_method)}</td>
                    <td class="font-monospace">${money(row.grand_total)}</td>
                    <td>${statusBadge(row.status)}</td>
                    <td class="text-end">
                        <button class="btn btn-sm pos-icon-btn-table btn-view" data-id="${row.sale_id}" title="View"><i class="bi bi-eye"></i></button>
                    </td>
                </tr>
            `));
        });
    }

    function renderPagination(result) {
        const $pg = $('#salePagination').empty();
        $('#salePageInfo').text(
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
    }

    function loadList() {
        $('#saleTableBody').html('<tr><td colspan="9" class="text-center text-muted py-4">Loading...</td></tr>');
        $.ajax({
            url: ENDPOINT, method: 'GET', dataType: 'json',
            data: {
                action: 'list', search: state.search, status: state.status,
                date_from: state.dateFrom, date_to: state.dateTo,
                sort_by: state.sortBy, sort_dir: state.sortDir, page: state.page, per_page: state.perPage,
            },
        }).done(function (res) {
            if (res.success) { renderRows(res.result.rows); renderPagination(res.result); updateExportLinks(); }
            else { $('#saleTableBody').html(`<tr><td colspan="9" class="text-center text-danger py-4">${escapeHtml(res.message)}</td></tr>`); }
        }).fail(function () {
            $('#saleTableBody').html('<tr><td colspan="9" class="text-center text-danger py-4">Could not load sales. Please try again.</td></tr>');
        });
    }

    function updateExportLinks() {
        const query = $.param({ search: state.search, status: state.status, date_from: state.dateFrom, date_to: state.dateTo });
        $('#salesExportExcel').attr('href', ENDPOINT + '?action=export_excel&' + query);
        $('#salesExportPdf').attr('href', ENDPOINT + '?action=export_pdf&' + query);
    }

    function renderReceipt(sale) {
        let itemLines = '';
        sale.items.forEach(function (item) {
            itemLines += `
                <div class="pos-receipt-line">
                    <span>${escapeHtml(item.product_name)}</span>
                    <span>${item.quantity}${escapeHtml(item.unit)}</span>
                    <span>${money(item.line_total)}</span>
                </div>
            `;
        });

        $('#saleReceiptContent').html(`
            <div class="pos-receipt-head text-center">
                <div class="fw-semibold">${escapeHtml(sale.invoice_no)}</div>
                <div>${escapeHtml(sale.created_at)} &middot; Cashier: ${escapeHtml(sale.cashier_name)}</div>
                <div>${escapeHtml(sale.customer_name || 'Walk-in customer')}</div>
                <div class="mt-1">${statusBadge(sale.status)}</div>
            </div>
            ${itemLines}
            <div class="pos-receipt-divider"></div>
            <div class="pos-receipt-line"><span>Subtotal</span><span></span><span>${money(sale.subtotal)}</span></div>
            <div class="pos-receipt-line"><span>Tax</span><span></span><span>${money(sale.tax_total)}</span></div>
            <div class="pos-receipt-line"><span>Discount${Number(sale.loyalty_points_redeemed || 0) > 0 ? ' (Loyalty redeemed: ' + Number(sale.loyalty_points_redeemed) + ' point(s))' : ''}</span><span></span><span>-${money(sale.discount_total)}</span></div>
            <div class="pos-receipt-divider"></div>
            <div class="pos-receipt-line pos-receipt-total"><span>Total</span><span></span><span>${money(sale.grand_total)}</span></div>
            ${(sale.payments || [{ payment_method: sale.payment_method, amount: sale.amount_paid }]).map(function (payment) { return `<div class="pos-receipt-line"><span>${escapeHtml(String(payment.payment_method).toUpperCase())}${payment.payment_reference ? ' · ' + escapeHtml(payment.payment_reference) : ''}</span><span></span><span>${money(payment.amount)}</span></div>`; }).join('')}
            <div class="pos-receipt-line"><span>Change</span><span></span><span>${money(sale.change_due)}</span></div>
        `);

        activeSaleId = sale.sale_id;
        $('#btnVoidSale').toggleClass('d-none', sale.status !== 'completed');
    }

    function viewSale(id) {
        $.ajax({ url: ENDPOINT, method: 'GET', dataType: 'json', data: { action: 'get', id: id } }).done(function (res) {
            if (!res.success) { alert(res.message || 'Sale not found.'); return; }
            renderReceipt(res.sale);
            new bootstrap.Modal('#saleReceiptModal').show();
        });
    }

    $(function () {
        loadList();

        $('#saleSearch').on('input', function () {
            clearTimeout(searchDebounce);
            const value = $(this).val();
            searchDebounce = setTimeout(function () { state.search = value; state.page = 1; loadList(); }, 350);
        });
        $('#saleStatusFilter').on('change', function () { state.status = $(this).val(); state.page = 1; loadList(); });
        $('#saleDateFrom').on('change', function () { state.dateFrom = $(this).val(); state.page = 1; loadList(); });
        $('#saleDateTo').on('change', function () { state.dateTo = $(this).val(); state.page = 1; loadList(); });
        $('#salePerPage').on('change', function () { state.perPage = parseInt($(this).val(), 10); state.page = 1; loadList(); });

        $(document).on('click', '.pos-sortable', function () {
            const col = $(this).data('sort');
            if (!col) return;
            if (state.sortBy === col) { state.sortDir = state.sortDir === 'ASC' ? 'DESC' : 'ASC'; }
            else { state.sortBy = col; state.sortDir = 'ASC'; }
            loadList();
        });

        $(document).on('click', '#salePagination a.page-link', function (e) {
            e.preventDefault();
            const page = parseInt($(this).data('page'), 10);
            if (!isNaN(page) && page >= 1) { state.page = page; loadList(); }
        });

        $(document).on('click', '#saleTableBody .btn-view', function () { viewSale($(this).data('id')); });

        $('#btnVoidSale').on('click', function () {
            if (!activeSaleId || !confirm('Void this sale and restore its stock? This cannot be undone.')) return;
            $.ajax({ url: ENDPOINT, method: 'POST', dataType: 'json', data: { action: 'void', sale_id: activeSaleId } })
                .done(function (res) {
                    if (res.success) {
                        bootstrap.Modal.getInstance(document.getElementById('saleReceiptModal')).hide();
                        loadList();
                    } else {
                        alert(res.message);
                    }
                })
                .fail(function (jq) { alert((jq.responseJSON && jq.responseJSON.message) || 'Could not void this sale.'); });
        });
    });
})(jQuery);
