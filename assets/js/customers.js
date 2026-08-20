/**
 * assets/js/customers.js
 * -----------------------------------------------------------------------
 * Drives views/customers.php via app/controllers/CustomerController.php.
 */
(function ($) {
    'use strict';

    const ENDPOINT = (window.APP_URL || '') + '/app/controllers/CustomerController.php';
    let state = { search: '', sortBy: 'full_name', sortDir: 'ASC', page: 1, perPage: 10 };
    let searchDebounce = null;

    function escapeHtml(str) { return $('<div>').text(str == null ? '' : str).html(); }

    function renderRows(rows) {
        const $body = $('#customerTableBody');
        $body.empty();

        if (!rows.length) {
            $body.html('<tr><td colspan="6" class="text-center text-muted py-4">No customers found.</td></tr>');
            return;
        }

        rows.forEach(function (row) {
            const statusBadge = Number(row.is_active) === 1
                ? '<span class="badge pos-badge-success">Active</span>'
                : '<span class="badge pos-badge-muted">Inactive</span>';

            $body.append($(`
                <tr>
                    <td class="fw-medium">${escapeHtml(row.full_name)}</td>
                    <td class="text-muted">${escapeHtml(row.phone) || '&mdash;'}</td>
                    <td class="text-muted">${escapeHtml(row.email) || '&mdash;'}</td>
                    <td class="font-monospace">${row.loyalty_points}</td>
                    <td>${statusBadge}</td>
                    <td class="text-end">
                        <button class="btn btn-sm pos-icon-btn-table btn-edit" data-id="${row.customer_id}" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm pos-icon-btn-table text-danger btn-delete" data-id="${row.customer_id}" data-name="${escapeHtml(row.full_name)}" title="Delete"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
            `));
        });
    }

    function renderPagination(result) {
        const $pg = $('#customerPagination').empty();
        $('#customerPageInfo').text(
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
        $('#customerTableBody').html('<tr><td colspan="6" class="text-center text-muted py-4">Loading...</td></tr>');
        $.ajax({
            url: ENDPOINT, method: 'GET', dataType: 'json',
            data: { action: 'list', search: state.search, sort_by: state.sortBy, sort_dir: state.sortDir, page: state.page, per_page: state.perPage },
        }).done(function (res) {
            if (res.success) { renderRows(res.result.rows); renderPagination(res.result); }
            else { $('#customerTableBody').html(`<tr><td colspan="6" class="text-center text-danger py-4">${escapeHtml(res.message)}</td></tr>`); }
        }).fail(function () {
            $('#customerTableBody').html('<tr><td colspan="6" class="text-center text-danger py-4">Could not load customers. Please try again.</td></tr>');
        });
    }

    function resetForm() {
        $('#customerForm')[0].reset();
        $('#customerId').val('');
        $('#customerIsActive').prop('checked', true);
        $('#customerFormAlert').addClass('d-none').text('');
        $('#customerModalTitle').text('Add Customer');
    }

    function openEdit(id) {
        $.ajax({ url: ENDPOINT, method: 'GET', dataType: 'json', data: { action: 'get', id: id } }).done(function (res) {
            if (!res.success) { alert(res.message || 'Customer not found.'); return; }
            const c = res.customer;
            resetForm();
            $('#customerModalTitle').text('Edit Customer');
            $('#customerId').val(c.customer_id);
            $('#customerName').val(c.full_name);
            $('#customerPhone').val(c.phone);
            $('#customerEmail').val(c.email);
            $('#customerAddress').val(c.address);
            $('#customerIsActive').prop('checked', Number(c.is_active) === 1);
            new bootstrap.Modal('#customerModal').show();
        });
    }

    $(function () {
        loadList();
        $('#btnAddCustomer').on('click', resetForm);

        $('#customerSearch').on('input', function () {
            clearTimeout(searchDebounce);
            const value = $(this).val();
            searchDebounce = setTimeout(function () { state.search = value; state.page = 1; loadList(); }, 350);
        });

        $('#customerPerPage').on('change', function () { state.perPage = parseInt($(this).val(), 10); state.page = 1; loadList(); });

        $(document).on('click', '.pos-sortable', function () {
            const col = $(this).data('sort');
            if (!col) return;
            if (state.sortBy === col) { state.sortDir = state.sortDir === 'ASC' ? 'DESC' : 'ASC'; }
            else { state.sortBy = col; state.sortDir = 'ASC'; }
            loadList();
        });

        $(document).on('click', '#customerPagination a.page-link', function (e) {
            e.preventDefault();
            const page = parseInt($(this).data('page'), 10);
            if (!isNaN(page) && page >= 1) { state.page = page; loadList(); }
        });

        $(document).on('click', '#customerTableBody .btn-edit', function () { openEdit($(this).data('id')); });

        $(document).on('click', '#customerTableBody .btn-delete', function () {
            const id = $(this).data('id'), name = $(this).data('name');
            if (!confirm(`Delete customer "${name}"? This cannot be undone.`)) return;
            $.ajax({ url: ENDPOINT, method: 'POST', dataType: 'json', data: { action: 'delete', customer_id: id } })
                .done(function (res) { if (res.success) loadList(); else alert(res.message); })
                .fail(function (jq) { alert((jq.responseJSON && jq.responseJSON.message) || 'Could not delete customer.'); });
        });

        $('#customerForm').on('submit', function (e) {
            e.preventDefault();
            $('#customerFormAlert').addClass('d-none').text('');
            const id = $('#customerId').val();
            const action = id ? 'update' : 'create';
            const $btn = $('#customerSaveBtn');
            $btn.prop('disabled', true).find('.spinner-border').removeClass('d-none');

            $.ajax({ url: ENDPOINT, method: 'POST', dataType: 'json', data: $(this).serialize() + '&action=' + action })
                .done(function (res) {
                    if (res.success) { bootstrap.Modal.getInstance(document.getElementById('customerModal')).hide(); loadList(); }
                    else { $('#customerFormAlert').removeClass('d-none').text(res.message); }
                })
                .fail(function (jq) {
                    $('#customerFormAlert').removeClass('d-none').text((jq.responseJSON && jq.responseJSON.message) || 'Something went wrong. Please try again.');
                })
                .always(function () { $btn.prop('disabled', false).find('.spinner-border').addClass('d-none'); });
        });
    });
})(jQuery);
