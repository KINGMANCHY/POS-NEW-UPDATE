/**
 * assets/js/categories.js
 * -----------------------------------------------------------------------
 * Drives views/categories.php: loads the table via AJAX, and handles
 * create/update/delete through app/controllers/CategoryController.php.
 */
(function ($) {
    'use strict';

    const ENDPOINT = (window.APP_URL || '') + '/app/controllers/CategoryController.php';

    let state = { search: '', sortBy: 'category_name', sortDir: 'ASC', page: 1, perPage: 10 };
    let searchDebounce = null;

    function escapeHtml(str) {
        return $('<div>').text(str == null ? '' : str).html();
    }

    function formatDate(value) {
        if (!value) return '';
        const d = new Date(value);
        return isNaN(d) ? value : d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function renderRows(rows) {
        const $body = $('#categoryTableBody');
        $body.empty();

        if (!rows.length) {
            $body.html('<tr><td colspan="5" class="text-center text-muted py-4">No categories found.</td></tr>');
            return;
        }

        rows.forEach(function (row) {
            const statusBadge = Number(row.is_active) === 1
                ? '<span class="badge pos-badge-success">Active</span>'
                : '<span class="badge pos-badge-muted">Inactive</span>';

            const $tr = $(`
                <tr>
                    <td class="fw-medium">${escapeHtml(row.category_name)}</td>
                    <td class="text-muted">${escapeHtml(row.description) || '&mdash;'}</td>
                    <td>${statusBadge}</td>
                    <td class="text-muted small">${formatDate(row.created_at)}</td>
                    <td class="text-end">
                        <button class="btn btn-sm pos-icon-btn-table btn-edit" data-id="${row.category_id}" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm pos-icon-btn-table text-danger btn-delete" data-id="${row.category_id}" data-name="${escapeHtml(row.category_name)}" title="Delete"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
            `);
            $body.append($tr);
        });
    }

    function renderPagination(result) {
        const $pg = $('#categoryPagination').empty();
        $('#categoryPageInfo').text(
            result.total === 0 ? 'No results' :
            `Showing ${(result.page - 1) * result.per_page + 1}-${Math.min(result.page * result.per_page, result.total)} of ${result.total}`
        );

        if (result.total_pages <= 1) return;

        const addPage = (label, page, disabled, active) => {
            $pg.append(`
                <li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${page}">${label}</a>
                </li>
            `);
        };

        addPage('&laquo;', result.page - 1, result.page <= 1, false);
        for (let p = 1; p <= result.total_pages; p++) {
            addPage(p, p, false, p === result.page);
        }
        addPage('&raquo;', result.page + 1, result.page >= result.total_pages, false);
    }

    function loadList() {
        $('#categoryTableBody').html('<tr><td colspan="5" class="text-center text-muted py-4">Loading...</td></tr>');

        $.ajax({
            url: ENDPOINT,
            method: 'GET',
            dataType: 'json',
            data: {
                action: 'list',
                search: state.search,
                sort_by: state.sortBy,
                sort_dir: state.sortDir,
                page: state.page,
                per_page: state.perPage,
            },
        }).done(function (res) {
            if (res.success) {
                renderRows(res.result.rows);
                renderPagination(res.result);
            } else {
                $('#categoryTableBody').html(`<tr><td colspan="5" class="text-center text-danger py-4">${escapeHtml(res.message)}</td></tr>`);
            }
        }).fail(function () {
            $('#categoryTableBody').html('<tr><td colspan="5" class="text-center text-danger py-4">Could not load categories. Please try again.</td></tr>');
        });
    }

    function resetForm() {
        $('#categoryForm')[0].reset();
        $('#categoryId').val('');
        $('#categoryIsActive').prop('checked', true);
        $('#categoryFormAlert').addClass('d-none').text('');
        $('#categoryModalTitle').text('Add Category');
    }

    function openEdit(id) {
        $.ajax({ url: ENDPOINT, method: 'GET', dataType: 'json', data: { action: 'get', id: id } })
            .done(function (res) {
                if (!res.success) {
                    alert(res.message || 'Category not found.');
                    return;
                }
                const cat = res.category;
                resetForm();
                $('#categoryModalTitle').text('Edit Category');
                $('#categoryId').val(cat.category_id);
                $('#categoryName').val(cat.category_name);
                $('#categoryDescription').val(cat.description);
                $('#categoryIsActive').prop('checked', Number(cat.is_active) === 1);
                new bootstrap.Modal('#categoryModal').show();
            });
    }

    $(function () {
        loadList();

        $('#btnAddCategory').on('click', resetForm);

        $('#categorySearch').on('input', function () {
            clearTimeout(searchDebounce);
            const value = $(this).val();
            searchDebounce = setTimeout(function () {
                state.search = value;
                state.page = 1;
                loadList();
            }, 350);
        });

        $('#categoryPerPage').on('change', function () {
            state.perPage = parseInt($(this).val(), 10);
            state.page = 1;
            loadList();
        });

        $(document).on('click', '.pos-sortable', function () {
            const col = $(this).data('sort');
            if (state.sortBy === col) {
                state.sortDir = state.sortDir === 'ASC' ? 'DESC' : 'ASC';
            } else {
                state.sortBy = col;
                state.sortDir = 'ASC';
            }
            loadList();
        });

        $(document).on('click', '#categoryPagination a.page-link', function (e) {
            e.preventDefault();
            const page = parseInt($(this).data('page'), 10);
            if (!isNaN(page) && page >= 1) {
                state.page = page;
                loadList();
            }
        });

        $(document).on('click', '.btn-edit', function () { openEdit($(this).data('id')); });

        $(document).on('click', '.btn-delete', function () {
            const id = $(this).data('id');
            const name = $(this).data('name');
            if (!confirm(`Delete category "${name}"? This cannot be undone.`)) return;

            $.ajax({
                url: ENDPOINT, method: 'POST', dataType: 'json',
                data: { action: 'delete', category_id: id },
            }).done(function (res) {
                if (res.success) { loadList(); } else { alert(res.message); }
            }).fail(function (jq) {
                alert((jq.responseJSON && jq.responseJSON.message) || 'Could not delete category.');
            });
        });

        $('#categoryForm').on('submit', function (e) {
            e.preventDefault();
            $('#categoryFormAlert').addClass('d-none').text('');

            const id = $('#categoryId').val();
            const action = id ? 'update' : 'create';
            const $btn = $('#categorySaveBtn');
            $btn.prop('disabled', true).find('.spinner-border').removeClass('d-none');

            $.ajax({
                url: ENDPOINT, method: 'POST', dataType: 'json',
                data: $(this).serialize() + '&action=' + action,
            }).done(function (res) {
                if (res.success) {
                    bootstrap.Modal.getInstance(document.getElementById('categoryModal')).hide();
                    loadList();
                } else {
                    $('#categoryFormAlert').removeClass('d-none').text(res.message);
                }
            }).fail(function (jq) {
                const msg = (jq.responseJSON && jq.responseJSON.message) || 'Something went wrong. Please try again.';
                $('#categoryFormAlert').removeClass('d-none').text(msg);
            }).always(function () {
                $btn.prop('disabled', false).find('.spinner-border').addClass('d-none');
            });
        });
    });
})(jQuery);
