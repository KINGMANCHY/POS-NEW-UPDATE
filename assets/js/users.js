/**
 * assets/js/users.js
 * -----------------------------------------------------------------------
 * Drives the "Users" tab on views/roles.php via app/controllers/UserController.php.
 * Loaded alongside roles.js on roles.php; only starts fetching once the
 * Users tab is actually shown (roles.js fires 'users-tab-shown').
 */
(function ($) {
    'use strict';

    const ENDPOINT = (window.APP_URL || '') + '/app/controllers/UserController.php';
    let state = { search: '', role_id: '', sortBy: 'full_name', sortDir: 'ASC', page: 1, perPage: 10 };
    let loadedOnce = false;
    let searchDebounce = null;

    function escapeHtml(str) { return $('<div>').text(str == null ? '' : str).html(); }

    function loadFormData() {
        $.get(ENDPOINT, { action: 'form_data' }).done(function (res) {
            if (!res.success) return;
            const $filter = $('#userRoleFilter');
            const $modalSelect = $('#userRole');
            $filter.find('option:not(:first)').remove();
            $modalSelect.empty();

            res.roles.forEach(function (r) {
                $filter.append(`<option value="${r.role_id}">${escapeHtml(r.role_name)}</option>`);
                $modalSelect.append(`<option value="${r.role_id}">${escapeHtml(r.role_name)}</option>`);
            });
        });
    }

    function loadUsers() {
        $('#userTableBody').html('<tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr>');
        $.get(ENDPOINT, {
            action: 'list', search: state.search, role_id: state.role_id,
            sort_by: state.sortBy, sort_dir: state.sortDir, page: state.page, per_page: state.perPage,
        }).done(function (res) {
            if (res.success) { renderRows(res.result.rows); renderPagination(res.result); }
            else { $('#userTableBody').html(`<tr><td colspan="7" class="text-center text-danger py-4">${escapeHtml(res.message)}</td></tr>`); }
        }).fail(function () {
            $('#userTableBody').html('<tr><td colspan="7" class="text-center text-danger py-4">Could not load users. Please try again.</td></tr>');
        });
    }

    function renderRows(rows) {
        const $body = $('#userTableBody').empty();
        if (!rows.length) {
            $body.html('<tr><td colspan="7" class="text-center text-muted py-4">No users found.</td></tr>');
            return;
        }

        rows.forEach(function (u) {
            $body.append($(`
                <tr>
                    <td class="font-monospace">${escapeHtml(u.username)}</td>
                    <td>${escapeHtml(u.full_name)}</td>
                    <td class="text-muted small">${escapeHtml(u.email || '—')}</td>
                    <td><span class="badge pos-badge-muted">${escapeHtml(u.role_name)}</span></td>
                    <td><span class="badge ${u.is_active ? 'pos-badge-success' : 'pos-badge-muted'}">${u.is_active ? 'Active' : 'Inactive'}</span></td>
                    <td class="text-muted small">${escapeHtml(u.last_login || 'Never')}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-secondary btn-edit-user"
                                data-id="${u.user_id}" data-username="${escapeHtml(u.username)}"
                                data-fullname="${escapeHtml(u.full_name)}" data-email="${escapeHtml(u.email || '')}"
                                data-role="${u.role_id}" data-active="${u.is_active ? 1 : 0}">Edit</button>
                    </td>
                </tr>
            `));
        });
    }

    function renderPagination(result) {
        const $pg = $('#userPagination').empty();
        $('#userPageInfo').text(
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

    function openAddModal() {
        $('#userForm')[0].reset();
        $('#userFormAlert').addClass('d-none').text('');
        $('#userId').val('');
        $('#userModalTitle').text('New User');
        $('#userUsername').prop('disabled', false);
        $('#userPasswordWrap').show();
        $('#userPassword').prop('required', true);
        $('#btnResetPassword').hide();
        $('#userIsActive').prop('checked', true);
        new bootstrap.Modal('#userModal').show();
    }

    function openEditModal($btn) {
        $('#userForm')[0].reset();
        $('#userFormAlert').addClass('d-none').text('');
        $('#userModalTitle').text('Edit User');
        $('#userId').val($btn.data('id'));
        $('#userUsername').val($btn.data('username')).prop('disabled', true);
        $('#userFullName').val($btn.data('fullname'));
        $('#userEmail').val($btn.data('email'));
        $('#userRole').val($btn.data('role'));
        $('#userIsActive').prop('checked', $btn.data('active') === 1);
        $('#userPasswordWrap').hide();
        $('#userPassword').prop('required', false);
        $('#btnResetPassword').show();
        new bootstrap.Modal('#userModal').show();
    }

    function submitUserForm() {
        const id = $('#userId').val();
        const isEdit = !!id;
        const $btn = $('#userSaveBtn');
        $btn.prop('disabled', true);

        const payload = {
            action: isEdit ? 'update' : 'create',
            user_id: id,
            username: $('#userUsername').val(),
            full_name: $('#userFullName').val(),
            email: $('#userEmail').val(),
            role_id: $('#userRole').val(),
            is_active: $('#userIsActive').is(':checked') ? 1 : 0,
        };
        if (!isEdit) {
            payload.password = $('#userPassword').val();
        }

        $.post(ENDPOINT, payload)
            .done(function (res) {
                if (!res.success) {
                    $('#userFormAlert').removeClass('d-none').text(res.message || 'Could not save this user.');
                    return;
                }
                bootstrap.Modal.getInstance(document.getElementById('userModal')).hide();
                loadUsers();
            })
            .fail(function (xhr) {
                $('#userFormAlert').removeClass('d-none').text((xhr.responseJSON && xhr.responseJSON.message) || 'Could not save this user.');
            })
            .always(function () { $btn.prop('disabled', false); });
    }

    $(function () {
        $(document).on('users-tab-shown', function () {
            if (loadedOnce) return;
            loadedOnce = true;
            loadFormData();
            loadUsers();
        });

        $('#btnAddUser').on('click', openAddModal);
        $(document).on('click', '.btn-edit-user', function () { openEditModal($(this)); });

        $('#userForm').on('submit', function (e) { e.preventDefault(); submitUserForm(); });

        $('#userSearch').on('input', function () {
            clearTimeout(searchDebounce);
            const value = $(this).val();
            searchDebounce = setTimeout(function () { state.search = value; state.page = 1; loadUsers(); }, 350);
        });
        $('#userRoleFilter').on('change', function () { state.role_id = $(this).val(); state.page = 1; loadUsers(); });

        $(document).on('click', '#userPagination a.page-link', function (e) {
            e.preventDefault();
            const page = parseInt($(this).data('page'), 10);
            if (!isNaN(page) && page >= 1) { state.page = page; loadUsers(); }
        });
        $(document).on('click', '#usersTabPane .pos-sortable', function () {
            const col = $(this).data('sort');
            if (!col) return;
            if (state.sortBy === col) { state.sortDir = state.sortDir === 'ASC' ? 'DESC' : 'ASC'; }
            else { state.sortBy = col; state.sortDir = 'ASC'; }
            loadUsers();
        });

        $('#btnResetPassword').on('click', function () {
            $('#resetPasswordForm')[0].reset();
            $('#resetPasswordAlert').addClass('d-none').text('');
            $('#resetPasswordUserId').val($('#userId').val());
            bootstrap.Modal.getInstance(document.getElementById('userModal')).hide();
            new bootstrap.Modal('#resetPasswordModal').show();
        });

        $('#resetPasswordForm').on('submit', function (e) {
            e.preventDefault();
            $.post(ENDPOINT, {
                action: 'reset_password',
                user_id: $('#resetPasswordUserId').val(),
                password: $('#resetPasswordValue').val(),
            })
                .done(function (res) {
                    if (!res.success) {
                        $('#resetPasswordAlert').removeClass('d-none').text(res.message || 'Could not reset the password.');
                        return;
                    }
                    bootstrap.Modal.getInstance(document.getElementById('resetPasswordModal')).hide();
                })
                .fail(function (xhr) {
                    $('#resetPasswordAlert').removeClass('d-none').text((xhr.responseJSON && xhr.responseJSON.message) || 'Could not reset the password.');
                });
        });
    });
})(jQuery);
