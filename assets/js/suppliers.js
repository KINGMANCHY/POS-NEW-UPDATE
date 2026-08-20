/**
 * assets/js/suppliers.js
 * -----------------------------------------------------------------------
 * Drives views/suppliers.php via app/controllers/SupplierController.php.
 */
(function ($) {
    'use strict';

    const ENDPOINT = (window.APP_URL || '') + '/app/controllers/SupplierController.php';
    let state = { search: '', sortBy: 'supplier_name', sortDir: 'ASC', page: 1, perPage: 10 };
    let searchDebounce = null;

    function escapeHtml(str) { return $('<div>').text(str == null ? '' : str).html(); }

    function renderRows(rows) {
        const $body = $('#supplierTableBody');
        $body.empty();

        if (!rows.length) {
            $body.html('<tr><td colspan="6" class="text-center text-muted py-4">No suppliers found.</td></tr>');
            return;
        }

        rows.forEach(function (row) {
            const statusBadge = Number(row.is_active) === 1
                ? '<span class="badge pos-badge-success">Active</span>'
                : '<span class="badge pos-badge-muted">Inactive</span>';

            $body.append($(`
                <tr>
                    <td class="fw-medium">${escapeHtml(row.supplier_name)}</td>
                    <td class="text-muted">${escapeHtml(row.contact_person) || '&mdash;'}</td>
                    <td class="text-muted">${escapeHtml(row.phone) || '&mdash;'}</td>
                    <td class="text-muted">${escapeHtml(row.email) || '&mdash;'}</td>
                    <td>${statusBadge}</td>
                    <td class="text-end">
                        <button class="btn btn-sm pos-icon-btn-table btn-edit" data-id="${row.supplier_id}" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm pos-icon-btn-table text-danger btn-delete" data-id="${row.supplier_id}" data-name="${escapeHtml(row.supplier_name)}" title="Delete"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
            `));
        });
    }

    function renderPagination(result) {
        const $pg = $('#supplierPagination').empty();
        $('#supplierPageInfo').text(
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
        $('#supplierTableBody').html('<tr><td colspan="6" class="text-center text-muted py-4">Loading...</td></tr>');
        $.ajax({
            url: ENDPOINT, method: 'GET', dataType: 'json',
            data: { action: 'list', search: state.search, sort_by: state.sortBy, sort_dir: state.sortDir, page: state.page, per_page: state.perPage },
        }).done(function (res) {
            if (res.success) { renderRows(res.result.rows); renderPagination(res.result); }
            else { $('#supplierTableBody').html(`<tr><td colspan="6" class="text-center text-danger py-4">${escapeHtml(res.message)}</td></tr>`); }
        }).fail(function () {
            $('#supplierTableBody').html('<tr><td colspan="6" class="text-center text-danger py-4">Could not load suppliers. Please try again.</td></tr>');
        });
    }

    function resetForm() {
        $('#supplierForm')[0].reset();
        $('#supplierId').val('');
        $('#supplierIsActive').prop('checked', true);
        $('#supplierFormAlert').addClass('d-none').text('');
        $('#supplierModalTitle').text('Add Supplier');
    }

    function openEdit(id) {
        $.ajax({ url: ENDPOINT, method: 'GET', dataType: 'json', data: { action: 'get', id: id } }).done(function (res) {
            if (!res.success) { alert(res.message || 'Supplier not found.'); return; }
            const s = res.supplier;
            resetForm();
            $('#supplierModalTitle').text('Edit Supplier');
            $('#supplierId').val(s.supplier_id);
            $('#supplierName').val(s.supplier_name);
            $('#supplierContact').val(s.contact_person);
            $('#supplierPhone').val(s.phone);
            $('#supplierEmail').val(s.email);
            $('#supplierAddress').val(s.address);
            $('#supplierIsActive').prop('checked', Number(s.is_active) === 1);
            new bootstrap.Modal('#supplierModal').show();
        });
    }

    $(function () {
        loadList();
        $('#btnAddSupplier').on('click', resetForm);

        $('#supplierSearch').on('input', function () {
            clearTimeout(searchDebounce);
            const value = $(this).val();
            searchDebounce = setTimeout(function () { state.search = value; state.page = 1; loadList(); }, 350);
        });

        $('#supplierPerPage').on('change', function () { state.perPage = parseInt($(this).val(), 10); state.page = 1; loadList(); });

        $(document).on('click', '.pos-sortable', function () {
            const col = $(this).data('sort');
            if (!col) return;
            if (state.sortBy === col) { state.sortDir = state.sortDir === 'ASC' ? 'DESC' : 'ASC'; }
            else { state.sortBy = col; state.sortDir = 'ASC'; }
            loadList();
        });

        $(document).on('click', '#supplierPagination a.page-link', function (e) {
            e.preventDefault();
            const page = parseInt($(this).data('page'), 10);
            if (!isNaN(page) && page >= 1) { state.page = page; loadList(); }
        });

        $(document).on('click', '#supplierTableBody .btn-edit', function () { openEdit($(this).data('id')); });

        $(document).on('click', '#supplierTableBody .btn-delete', function () {
            const id = $(this).data('id'), name = $(this).data('name');
            if (!confirm(`Delete supplier "${name}"? This cannot be undone.`)) return;
            $.ajax({ url: ENDPOINT, method: 'POST', dataType: 'json', data: { action: 'delete', supplier_id: id } })
                .done(function (res) { if (res.success) loadList(); else alert(res.message); })
                .fail(function (jq) { alert((jq.responseJSON && jq.responseJSON.message) || 'Could not delete supplier.'); });
        });

        $('#supplierForm').on('submit', function (e) {
            e.preventDefault();
            $('#supplierFormAlert').addClass('d-none').text('');
            const id = $('#supplierId').val();
            const action = id ? 'update' : 'create';
            const $btn = $('#supplierSaveBtn');
            $btn.prop('disabled', true).find('.spinner-border').removeClass('d-none');

            $.ajax({ url: ENDPOINT, method: 'POST', dataType: 'json', data: $(this).serialize() + '&action=' + action })
                .done(function (res) {
                    if (res.success) { bootstrap.Modal.getInstance(document.getElementById('supplierModal')).hide(); loadList(); }
                    else { $('#supplierFormAlert').removeClass('d-none').text(res.message); }
                })
                .fail(function (jq) {
                    $('#supplierFormAlert').removeClass('d-none').text((jq.responseJSON && jq.responseJSON.message) || 'Something went wrong. Please try again.');
                })
                .always(function () { $btn.prop('disabled', false).find('.spinner-border').addClass('d-none'); });
        });
    });
})(jQuery);
