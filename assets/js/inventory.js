/**
 * assets/js/inventory.js
 * -----------------------------------------------------------------------
 * Drives views/inventory.php via app/controllers/InventoryController.php.
 */
(function ($) {
    'use strict';

    const ENDPOINT = (window.APP_URL || '') + '/app/controllers/InventoryController.php';
    let state = { search: '', categoryId: '', lowStockOnly: false, sortBy: 'product_name', sortDir: 'ASC', page: 1, perPage: 10 };
    let searchDebounce = null;

    function escapeHtml(str) { return $('<div>').text(str == null ? '' : str).html(); }

    function loadFormData() {
        $.get(ENDPOINT, { action: 'form_data' }).done(function (res) {
            if (!res.success) return;
            const $cat = $('#invCategoryFilter');
            res.categories.forEach(function (c) {
                $cat.append(`<option value="${c.category_id}">${escapeHtml(c.category_name)}</option>`);
            });
        });
    }

    function renderRows(rows) {
        const $body = $('#invTableBody');
        $body.empty();

        if (!rows.length) {
            $body.html('<tr><td colspan="6" class="text-center text-muted py-4">No products found.</td></tr>');
            return;
        }

        rows.forEach(function (row) {
            const low = row.quantity_on_hand <= row.stock_alert_qty;
            $body.append($(`
                <tr>
                    <td>
                        <div class="fw-medium">${escapeHtml(row.product_name)}</div>
                        <div class="text-muted small">${escapeHtml(row.product_code)}</div>
                    </td>
                    <td>${escapeHtml(row.category_name || '-')}</td>
                    <td>
                        <span class="fw-medium ${low ? 'text-danger' : ''}">${row.quantity_on_hand} ${escapeHtml(row.unit)}</span>
                        ${low ? '<span class="badge pos-badge-warning ms-1">Low</span>' : ''}
                    </td>
                    <td class="text-muted">${row.stock_alert_qty} ${escapeHtml(row.unit)}</td>
                    <td class="text-muted small">${escapeHtml(row.updated_at || 'Never')}</td>
                    <td class="text-end">
                        <button class="btn btn-sm pos-icon-btn-table btn-adjust"
                                data-id="${row.product_id}" data-name="${escapeHtml(row.product_name)}"
                                data-qty="${row.quantity_on_hand}" title="Adjust stock">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                    </td>
                </tr>
            `));
        });
    }

    /*function renderPagination(result) {
        const $pg = $('#invPagination').empty();
        $('#invPageInfo').text(
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
        const $pg = $('#invPagination').empty();

        $('#invPageInfo').text(
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
        $('#invTableBody').html('<tr><td colspan="6" class="text-center text-muted py-4">Loading...</td></tr>');
        $.ajax({
            url: ENDPOINT, method: 'GET', dataType: 'json',
            data: {
                action: 'list', search: state.search, category_id: state.categoryId,
                low_stock_only: state.lowStockOnly ? 1 : 0,
                sort_by: state.sortBy, sort_dir: state.sortDir, page: state.page, per_page: state.perPage,
            },
        }).done(function (res) {
            if (res.success) {
                renderRows(res.result.rows);
                renderPagination(res.result);
                $('#invLowStockBadge').text(res.low_stock_count).toggle(res.low_stock_count > 0);
            } else {
                $('#invTableBody').html(`<tr><td colspan="6" class="text-center text-danger py-4">${escapeHtml(res.message)}</td></tr>`);
            }
        }).fail(function () {
            $('#invTableBody').html('<tr><td colspan="6" class="text-center text-danger py-4">Could not load inventory. Please try again.</td></tr>');
        });
    }

    function openAdjustModal(id, name, qty) {
        $('#invAdjustAlert').addClass('d-none').text('');
        $('#invAdjustProductId').val(id);
        $('#invAdjustProductName').text(name);
        $('#invAdjustCurrentQty').text(qty);
        $('#invAdjustQty').val(qty);
        $('#invChangeType').val('adjustment');
        $('#invQtyLabel').text('New Quantity On Hand');
        $('#invAdjustReason').val('');
        new bootstrap.Modal('#invAdjustModal').show();
    }

    function submitAdjustment() {
        const $btn = $('#invAdjustSaveBtn');
        $btn.prop('disabled', true);

        $.post(ENDPOINT, {
            action: 'adjust',
            product_id: $('#invAdjustProductId').val(),
            quantity: $('#invAdjustQty').val(),
            current_quantity: $('#invAdjustCurrentQty').text(),
            change_type: $('#invChangeType').val(),
            reason: $('#invAdjustReason').val(),
        })
            .done(function (res) {
                if (!res.success) {
                    $('#invAdjustAlert').removeClass('d-none').text(res.message || 'Could not update stock.');
                    return;
                }
                bootstrap.Modal.getInstance(document.getElementById('invAdjustModal')).hide();
                loadList();
            })
            .fail(function (xhr) {
                $('#invAdjustAlert').removeClass('d-none').text((xhr.responseJSON && xhr.responseJSON.message) || 'Could not update stock.');
            })
            .always(function () { $btn.prop('disabled', false); });
    }

    $(function () {
        loadFormData();
        loadList();

        $('#invSearch').on('input', function () {
            clearTimeout(searchDebounce);
            const value = $(this).val();
            searchDebounce = setTimeout(function () { state.search = value; state.page = 1; loadList(); }, 350);
        });
        $('#invCategoryFilter').on('change', function () { state.categoryId = $(this).val(); state.page = 1; loadList(); });
        $('#invLowStockOnly').on('change', function () { state.lowStockOnly = $(this).is(':checked'); state.page = 1; loadList(); });
        $('#invPerPage').on('change', function () { state.perPage = parseInt($(this).val(), 10); state.page = 1; loadList(); });

        $(document).on('click', '.pos-sortable', function () {
            const col = $(this).data('sort');
            if (!col) return;
            if (state.sortBy === col) { state.sortDir = state.sortDir === 'ASC' ? 'DESC' : 'ASC'; }
            else { state.sortBy = col; state.sortDir = 'ASC'; }
            loadList();
        });

        $(document).on('click', '#invPagination a.page-link', function (e) {
            e.preventDefault();
            const page = parseInt($(this).data('page'), 10);
            if (!isNaN(page) && page >= 1) { state.page = page; loadList(); }
        });

        $(document).on('click', '.btn-adjust', function () {
            openAdjustModal($(this).data('id'), $(this).data('name'), $(this).data('qty'));
        });
        $('#invChangeType').on('change', function () {
            const type = $(this).val();
            $('#invQtyLabel').text(type === 'adjustment' ? 'New Quantity On Hand' : (type === 'stock_in' ? 'Quantity to Add' : 'Quantity to Remove'));
            $('#invAdjustQty').val(type === 'adjustment' ? $('#invAdjustCurrentQty').text() : 1);
        });

        $('#invAdjustForm').on('submit', function (e) { e.preventDefault(); submitAdjustment(); });
    });
})(jQuery);
