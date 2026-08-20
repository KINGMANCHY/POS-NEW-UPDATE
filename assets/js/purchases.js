/**
 * assets/js/purchases.js
 * -----------------------------------------------------------------------
 * Drives views/purchases.php via app/controllers/PurchaseController.php.
 */
(function ($) {
    'use strict';

    const ENDPOINT = (window.APP_URL || '') + '/app/controllers/PurchaseController.php';
    let state = { search: '', status: '', sortBy: 'purchased_at', sortDir: 'DESC', page: 1, perPage: 10 };
    let searchDebounce = null;
    let productSearchDebounce = null;
    let lineItems = [];          // [{ product_id, product_name, unit, quantity, unit_cost }]
    let activePurchaseId = null;

    function escapeHtml(str) { return $('<div>').text(str == null ? '' : str).html(); }
    function money(n) { return '₱' + Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    function statusBadge(status) {
        const map = { received: 'pos-badge-success', cancelled: 'pos-badge-danger' };
        return `<span class="badge ${map[status] || 'pos-badge-muted'}">${escapeHtml(status)}</span>`;
    }

    // -----------------------------------------------------------------
    // List / pagination
    // -----------------------------------------------------------------

    function renderRows(rows) {
        const $body = $('#purchaseTableBody');
        $body.empty();

        if (!rows.length) {
            $body.html('<tr><td colspan="8" class="text-center text-muted py-4">No purchases found.</td></tr>');
            return;
        }

        rows.forEach(function (row) {
            $body.append($(`
                <tr>
                    <td class="font-monospace">${escapeHtml(row.reference_no)}</td>
                    <td class="text-muted">${escapeHtml(row.purchased_at)}</td>
                    <td>${escapeHtml(row.supplier_name)}</td>
                    <td>${escapeHtml(row.recorded_by)}</td>
                    <td>${row.item_count}</td>
                    <td class="font-monospace">${money(row.total_amount)}</td>
                    <td>${statusBadge(row.status)}</td>
                    <td class="text-end">
                        <button class="btn btn-sm pos-icon-btn-table btn-view" data-id="${row.purchase_id}" title="View"><i class="bi bi-eye"></i></button>
                    </td>
                </tr>
            `));
        });
    }

    function renderPagination(result) {
        const $pg = $('#purchasePagination').empty();
        $('#purchasePageInfo').text(
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
        $('#purchaseTableBody').html('<tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr>');
        $.ajax({
            url: ENDPOINT, method: 'GET', dataType: 'json',
            data: {
                action: 'list', search: state.search, status: state.status,
                sort_by: state.sortBy, sort_dir: state.sortDir, page: state.page, per_page: state.perPage,
            },
        }).done(function (res) {
            if (res.success) { renderRows(res.result.rows); renderPagination(res.result); }
            else { $('#purchaseTableBody').html(`<tr><td colspan="8" class="text-center text-danger py-4">${escapeHtml(res.message)}</td></tr>`); }
        }).fail(function () {
            $('#purchaseTableBody').html('<tr><td colspan="8" class="text-center text-danger py-4">Could not load purchases. Please try again.</td></tr>');
        });
    }

    // -----------------------------------------------------------------
    // Record purchase form
    // -----------------------------------------------------------------

    function loadFormData() {
        $.get(ENDPOINT, { action: 'form_data' }).done(function (res) {
            if (!res.success) return;
            const $sel = $('#purchaseSupplier');
            $sel.find('option:not(:first)').remove();
            res.suppliers.forEach(function (s) {
                $sel.append(`<option value="${s.supplier_id}">${escapeHtml(s.supplier_name)}</option>`);
            });
        });
    }

    function searchProducts(term) {
        if (!term) { $('#purchaseProductResults').hide().empty(); return; }

        $.get(ENDPOINT, { action: 'products', search: term }).done(function (res) {
            if (!res.success) return;
            const $results = $('#purchaseProductResults');
            $results.empty();

            if (!res.products.length) {
                $results.append('<div class="list-group-item text-muted small">No matches</div>').show();
                return;
            }

            res.products.forEach(function (p) {
                $results.append(`
                    <button type="button" class="list-group-item list-group-item-action"
                            data-id="${p.product_id}" data-name="${escapeHtml(p.product_name)}"
                            data-unit="${escapeHtml(p.unit)}" data-cost="${p.cost_price}">
                        ${escapeHtml(p.product_name)} <span class="text-muted small">${escapeHtml(p.product_code)}</span>
                    </button>
                `);
            });
            $results.show();
        });
    }

    function addLine(product) {
        if (lineItems.find(function (i) { return i.product_id === product.product_id; })) {
            alert('That product is already on this purchase. Adjust its quantity instead.');
            return;
        }
        lineItems.push({
            product_id: product.product_id,
            product_name: product.product_name,
            unit: product.unit,
            quantity: 1,
            unit_cost: Number(product.cost_price) || 0,
        });
        renderLines();
    }

    function renderLines() {
        const $body = $('#purchaseLineBody');
        $body.empty();

        if (!lineItems.length) {
            $body.html('<tr><td colspan="5" class="text-center text-muted py-3">No items added yet.</td></tr>');
        } else {
            lineItems.forEach(function (item, idx) {
                $body.append(`
                    <tr data-idx="${idx}">
                        <td>${escapeHtml(item.product_name)} <span class="text-muted small">/ ${escapeHtml(item.unit)}</span></td>
                        <td><input type="number" class="form-control form-control-sm line-qty" min="1" step="1" value="${item.quantity}"></td>
                        <td><input type="number" class="form-control form-control-sm line-cost" min="0" step="0.01" value="${item.unit_cost}"></td>
                        <td class="text-end font-monospace line-total">${money(item.quantity * item.unit_cost)}</td>
                        <td class="text-end"><button type="button" class="btn btn-sm text-danger btn-remove-line"><i class="bi bi-x-lg"></i></button></td>
                    </tr>
                `);
            });
        }

        const grandTotal = lineItems.reduce(function (sum, i) { return sum + i.quantity * i.unit_cost; }, 0);
        $('#purchaseGrandTotal').text(money(grandTotal));
    }

    function resetForm() {
        lineItems = [];
        $('#purchaseForm')[0].reset();
        $('#purchaseFormAlert').addClass('d-none').text('');
        renderLines();
    }

    function submitPurchase() {
        const supplierId = $('#purchaseSupplier').val();
        if (!supplierId) { showFormError('Please select a supplier.'); return; }
        if (!lineItems.length) { showFormError('Add at least one product line.'); return; }

        const $btn = $('#purchaseSaveBtn');
        $btn.prop('disabled', true).find('.spinner-border').removeClass('d-none');

        $.post(ENDPOINT, {
            action: 'create',
            supplier_id: supplierId,
            items: JSON.stringify(lineItems.map(function (i) {
                return { product_id: i.product_id, quantity: i.quantity, unit_cost: i.unit_cost };
            })), invoice_receipt: $('#purchaseInvoiceReceipt').val(),
        })
            .done(function (res) {
                if (!res.success) { showFormError(res.message || 'Could not record this purchase.'); return; }
                bootstrap.Modal.getInstance(document.getElementById('purchaseModal')).hide();
                resetForm();
                state.page = 1;
                loadList();
            })
            .fail(function (xhr) {
                showFormError((xhr.responseJSON && xhr.responseJSON.message) || 'Could not record this purchase.');
            })
            .always(function () {
                $btn.prop('disabled', false).find('.spinner-border').addClass('d-none');
            });
    }

    function showFormError(message) {
        $('#purchaseFormAlert').removeClass('d-none').text(message);
    }

    // -----------------------------------------------------------------
    // View / cancel
    // -----------------------------------------------------------------

    function viewPurchase(id) {
        $.get(ENDPOINT, { action: 'get', id: id }).done(function (res) {
            if (!res.success) { alert(res.message || 'Purchase not found.'); return; }
            renderView(res.purchase);
            new bootstrap.Modal('#purchaseViewModal').show();
        });
    }

    function renderView(purchase) {
        activePurchaseId = purchase.purchase_id;

        let rows = '';
        purchase.items.forEach(function (item) {
            rows += `
                <tr>
                    <td>${escapeHtml(item.product_name)}</td>
                    <td>${item.quantity} ${escapeHtml(item.unit)}</td>
                    <td class="font-monospace">${money(item.unit_cost)}</td>
                    <td class="text-end font-monospace">${money(item.line_total)}</td>
                </tr>
            `;
        });

        $('#purchaseViewContent').html(`
            <div class="d-flex justify-content-between mb-3">
                <div>
                    <div class="fw-semibold">${escapeHtml(purchase.reference_no)}</div>
                    <div class="text-muted small">${escapeHtml(purchase.purchased_at)} &middot; ${escapeHtml(purchase.supplier_name)}</div>
                    <div class="text-muted small">Recorded by ${escapeHtml(purchase.recorded_by)}</div>
                </div>
                <div>${statusBadge(purchase.status)}</div>
            </div>
            <table class="table table-sm align-middle">
                <thead><tr><th>Product</th><th>Qty</th><th>Unit Cost</th><th class="text-end">Line Total</th></tr></thead>
                <tbody>${rows}</tbody>
            </table>
            <div class="d-flex justify-content-end fw-semibold">Total: <span class="ms-2">${money(purchase.total_amount)}</span></div>
        `);

        $('#btnCancelPurchase').toggleClass('d-none', purchase.status !== 'received');
    }

    // -----------------------------------------------------------------
    // Events
    // -----------------------------------------------------------------

    $(function () {
        loadList();

        $('#purchaseSearch').on('input', function () {
            clearTimeout(searchDebounce);
            const value = $(this).val();
            searchDebounce = setTimeout(function () { state.search = value; state.page = 1; loadList(); }, 350);
        });
        $('#purchaseStatusFilter').on('change', function () { state.status = $(this).val(); state.page = 1; loadList(); });
        $('#purchasePerPage').on('change', function () { state.perPage = parseInt($(this).val(), 10); state.page = 1; loadList(); });

        $(document).on('click', '.pos-sortable', function () {
            const col = $(this).data('sort');
            if (!col) return;
            if (state.sortBy === col) { state.sortDir = state.sortDir === 'ASC' ? 'DESC' : 'ASC'; }
            else { state.sortBy = col; state.sortDir = 'ASC'; }
            loadList();
        });

        $(document).on('click', '#purchasePagination a.page-link', function (e) {
            e.preventDefault();
            const page = parseInt($(this).data('page'), 10);
            if (!isNaN(page) && page >= 1) { state.page = page; loadList(); }
        });

        $(document).on('click', '#purchaseTableBody .btn-view', function () { viewPurchase($(this).data('id')); });

        // Record-purchase modal
        $('#btnAddPurchase').on('click', function () { resetForm(); loadFormData(); });

        $('#purchaseProductSearch').on('input', function () {
            const term = $(this).val().trim();
            clearTimeout(productSearchDebounce);
            productSearchDebounce = setTimeout(function () { searchProducts(term); }, 250);
        });
        $('#purchaseProductResults').on('click', 'button', function () {
            addLine({
                product_id: Number($(this).data('id')),
                product_name: $(this).data('name'),
                unit: $(this).data('unit'),
                cost_price: $(this).data('cost'),
            });
            $('#purchaseProductSearch').val('');
            $('#purchaseProductResults').hide().empty();
        });
        $(document).on('click', function (e) {
            if (!$(e.target).closest('#purchaseProductSearch, #purchaseProductResults').length) {
                $('#purchaseProductResults').hide();
            }
        });

        $('#purchaseLineBody').on('input', '.line-qty, .line-cost', function () {
            const idx = Number($(this).closest('tr').data('idx'));
            const item = lineItems[idx];
            if (!item) return;
            item.quantity = Math.max(1, Number($(this).closest('tr').find('.line-qty').val()) || 1);
            item.unit_cost = Math.max(0, Number($(this).closest('tr').find('.line-cost').val()) || 0);
            $(this).closest('tr').find('.line-total').text(money(item.quantity * item.unit_cost));
            const grandTotal = lineItems.reduce(function (sum, i) { return sum + i.quantity * i.unit_cost; }, 0);
            $('#purchaseGrandTotal').text(money(grandTotal));
        });
        $('#purchaseLineBody').on('click', '.btn-remove-line', function () {
            const idx = Number($(this).closest('tr').data('idx'));
            lineItems.splice(idx, 1);
            renderLines();
        });

        $('#purchaseForm').on('submit', function (e) { e.preventDefault(); submitPurchase(); });

        $('#btnCancelPurchase').on('click', function () {
            if (!activePurchaseId || !confirm('Cancel this purchase and reverse its stock? This cannot be undone.')) return;
            $.post(ENDPOINT, { action: 'cancel', purchase_id: activePurchaseId })
                .done(function (res) {
                    if (res.success) {
                        bootstrap.Modal.getInstance(document.getElementById('purchaseViewModal')).hide();
                        loadList();
                    } else {
                        alert(res.message);
                    }
                })
                .fail(function (jq) { alert((jq.responseJSON && jq.responseJSON.message) || 'Could not cancel this purchase.'); });
        });
    });
})(jQuery);
