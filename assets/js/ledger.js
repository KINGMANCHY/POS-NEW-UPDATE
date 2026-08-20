/**
 * assets/js/ledger.js
 * -----------------------------------------------------------------------
 * Drives views/ledger.php via app/controllers/LedgerController.php.
 * Excel/PDF export are plain GET links (not AJAX) - file downloads need
 * a real navigation so the browser handles the Content-Disposition
 * response, so this just keeps the two export <a> hrefs in sync with
 * whatever product/date range is currently loaded.
 */
(function ($) {
    'use strict';

    const ENDPOINT = (window.APP_URL || '') + '/app/controllers/LedgerController.php';
    let selectedProduct = null;
    let productSearchDebounce = null;

    function escapeHtml(str) { return $('<div>').text(str == null ? '' : str).html(); }

    const TYPE_BADGE = {
        purchase: 'pos-badge-success',
        sale_void: 'pos-badge-success',
        sale: 'pos-badge-danger',
        purchase_cancel: 'pos-badge-danger',
        adjustment: 'pos-badge-muted',
    };

    function defaultDates() {
        const today = new Date();
        const from = new Date(today.getFullYear(), today.getMonth(), 1);
        $('#ledgerDateFrom').val(from.toISOString().slice(0, 10));
        $('#ledgerDateTo').val(today.toISOString().slice(0, 10));
    }

    // -----------------------------------------------------------------
    // Product search
    // -----------------------------------------------------------------

    function searchProducts(term) {
        if (!term) { $('#ledgerProductResults').hide().empty(); return; }

        $.get(ENDPOINT, { action: 'products', search: term }).done(function (res) {
            if (!res.success) return;
            const $results = $('#ledgerProductResults');
            $results.empty();

            if (!res.products.length) {
                $results.append('<div class="list-group-item text-muted small">No matches</div>').show();
                return;
            }

            res.products.forEach(function (p) {
                $results.append(`
                    <button type="button" class="list-group-item list-group-item-action"
                            data-id="${p.product_id}" data-name="${escapeHtml(p.product_name)}" data-code="${escapeHtml(p.product_code)}">
                        ${escapeHtml(p.product_name)} <span class="text-muted small">${escapeHtml(p.product_code)}</span>
                    </button>
                `);
            });
            $results.show();
        });
    }

    function selectProduct(id, name, code) {
        selectedProduct = { id: id, name: name, code: code };
        $('#ledgerProductSelected').html(`<strong>${escapeHtml(name)}</strong> <span class="text-muted">(${escapeHtml(code)})</span>`);
        $('#ledgerProductSearch').val('');
        $('#ledgerProductResults').hide().empty();
        loadLedger();
    }

    // -----------------------------------------------------------------
    // Ledger
    // -----------------------------------------------------------------

    function loadLedger() {
        if (!selectedProduct) return;

        const dateFrom = $('#ledgerDateFrom').val();
        const dateTo = $('#ledgerDateTo').val();

        $.get(ENDPOINT, { action: 'ledger', product_id: selectedProduct.id, date_from: dateFrom, date_to: dateTo })
            .done(function (res) {
                if (!res.success) { alert(res.message || 'Could not load the ledger.'); return; }
                render(res.result);
                updateExportLinks(dateFrom, dateTo);
            });
    }

    function render(result) {
        $('#ledgerEmptyState').addClass('d-none');
        $('#ledgerContent').removeClass('d-none');

        $('#statOpening').text(result.opening_balance);
        $('#statIn').text('+' + result.total_in);
        $('#statOut').text('-' + result.total_out);
        $('#statClosing').text(result.closing_balance);

        const $body = $('#ledgerTableBody').empty();

        if (!result.movements.length) {
            $body.html('<tr><td colspan="8" class="text-center text-muted py-4">No stock movements in this range.</td></tr>');
            return;
        }

        result.movements.forEach(function (m) {
            const badge = TYPE_BADGE[m.movement_type] || 'pos-badge-muted';
            $body.append(`
                <tr>
                    <td class="text-muted">${escapeHtml(m.created_at)}</td>
                    <td><span class="badge ${badge}">${escapeHtml(m.type_label)}</span></td>
                    <td class="font-monospace">${escapeHtml(m.reference_code || '—')}</td>
                    <td class="text-end text-success">${m.quantity_change > 0 ? '+' + m.quantity_change : ''}</td>
                    <td class="text-end text-danger">${m.quantity_change < 0 ? Math.abs(m.quantity_change) : ''}</td>
                    <td class="text-end fw-medium">${m.balance_after}</td>
                    <td class="text-muted small">${escapeHtml(m.notes || '')}</td>
                    <td class="text-muted small">${escapeHtml(m.user_name || '')}</td>
                </tr>
            `);
        });
    }

    function updateExportLinks(dateFrom, dateTo) {
        if (!selectedProduct) return;
        const params = `product_id=${selectedProduct.id}&date_from=${dateFrom}&date_to=${dateTo}`;
        $('#btnExportExcel').attr('href', ENDPOINT + '?action=export_excel&' + params).removeClass('disabled');
        $('#btnExportPdf').attr('href', ENDPOINT + '?action=export_pdf&' + params).removeClass('disabled');
    }

    // -----------------------------------------------------------------
    // Events
    // -----------------------------------------------------------------

    $(function () {
        defaultDates();

        $('#ledgerProductSearch').on('input', function () {
            const term = $(this).val().trim();
            clearTimeout(productSearchDebounce);
            productSearchDebounce = setTimeout(function () { searchProducts(term); }, 250);
        });
        $('#ledgerProductResults').on('click', 'button', function () {
            selectProduct(Number($(this).data('id')), $(this).data('name'), $(this).data('code'));
        });
        $(document).on('click', function (e) {
            if (!$(e.target).closest('#ledgerProductSearch, #ledgerProductResults').length) {
                $('#ledgerProductResults').hide();
            }
        });

        $('#btnLoadLedger').on('click', loadLedger);
        $('#ledgerDateFrom, #ledgerDateTo').on('change', loadLedger);
    });
})(jQuery);
