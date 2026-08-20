/**
 * assets/js/roles.js
 * -----------------------------------------------------------------------
 * Drives views/roles.php via app/controllers/RoleController.php.
 */
(function ($) {
    'use strict';

    const ENDPOINT = (window.APP_URL || '') + '/app/controllers/RoleController.php';
    let currentPermissions = []; // permissions for the role currently open in the modal

    function escapeHtml(str) { return $('<div>').text(str == null ? '' : str).html(); }

    function loadRoles() {
        $.get(ENDPOINT, { action: 'list' }).done(function (res) {
            if (res.success) renderCards(res.roles);
        });
    }

    function renderCards(roles) {
        const $grid = $('#roleCardGrid').empty();

        if (!roles.length) {
            $grid.html('<div class="col-12 text-center text-muted py-4">No roles yet.</div>');
            return;
        }

        roles.forEach(function (role) {
            $grid.append(`
                <div class="col-md-6 col-xl-4">
                    <div class="card pos-card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h2 class="h6 mb-0">${escapeHtml(role.role_name)}</h2>
                                <span class="badge ${role.is_active ? 'pos-badge-success' : 'pos-badge-muted'}">${role.is_active ? 'Active' : 'Inactive'}</span>
                            </div>
                            <p class="text-muted small mb-3">${escapeHtml(role.description || 'No description.')}</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-muted small">
                                    <i class="bi bi-people me-1"></i>${role.user_count} user${role.user_count === 1 ? '' : 's'}
                                    &middot; <i class="bi bi-shield-check me-1"></i>${role.permission_count} permission${role.permission_count === 1 ? '' : 's'}
                                </div>
                                <button class="btn btn-sm btn-outline-secondary btn-edit-role" data-id="${role.role_id}">Edit</button>
                            </div>
                        </div>
                    </div>
                </div>
            `);
        });
    }

    function resetForm() {
        $('#roleForm')[0].reset();
        $('#roleId').val('');
        $('#roleModalTitle').text('New Role');
        $('#roleFormAlert').addClass('d-none').text('');
        $('#roleIsActive').prop('checked', true);
        $('#rolePermissionsSection').hide();
        $('#rolePermissionsList').empty();
        currentPermissions = [];
    }

    function openEditModal(id) {
        $.get(ENDPOINT, { action: 'get', id: id }).done(function (res) {
            if (!res.success) { alert(res.message || 'Role not found.'); return; }

            resetForm();
            const role = res.role;
            $('#roleModalTitle').text('Edit Role');
            $('#roleId').val(role.role_id);
            $('#roleName').val(role.role_name);
            $('#roleDescription').val(role.description || '');
            $('#roleIsActive').prop('checked', !!role.is_active);

            currentPermissions = res.permissions;
            renderPermissionChecklist(res.permissions);
            $('#rolePermissionsSection').show();

            new bootstrap.Modal('#roleModal').show();
        });
    }

    function renderPermissionChecklist(permissions) {
        const $list = $('#rolePermissionsList').empty();
        permissions.forEach(function (perm) {
            $list.append(`
                <div class="form-check">
                    <input class="form-check-input perm-checkbox" type="checkbox" value="${perm.permission_id}"
                           id="perm-${perm.permission_id}" ${perm.granted ? 'checked' : ''}>
                    <label class="form-check-label" for="perm-${perm.permission_id}">
                        <span class="font-monospace small">${escapeHtml(perm.permission_key)}</span>
                        <span class="text-muted small d-block">${escapeHtml(perm.description || '')}</span>
                    </label>
                </div>
            `);
        });
    }

    function submitRoleForm() {
        const id = $('#roleId').val();
        const isEdit = !!id;
        const $btn = $('#roleSaveBtn');
        $btn.prop('disabled', true);

        $.post(ENDPOINT, {
            action: isEdit ? 'update' : 'create',
            role_id: id,
            role_name: $('#roleName').val(),
            description: $('#roleDescription').val(),
            is_active: $('#roleIsActive').is(':checked') ? 1 : 0,
        })
            .done(function (res) {
                if (!res.success) {
                    $('#roleFormAlert').removeClass('d-none').text(res.message || 'Could not save this role.');
                    $btn.prop('disabled', false);
                    return;
                }

                const roleId = isEdit ? id : res.role_id;
                // New roles start with no permissions granted; save the checked state either way
                // so a freshly-created role can have permissions set in the same modal session.
                savePermissions(roleId, function () {
                    bootstrap.Modal.getInstance(document.getElementById('roleModal')).hide();
                    loadRoles();
                });
            })
            .fail(function (xhr) {
                $('#roleFormAlert').removeClass('d-none').text((xhr.responseJSON && xhr.responseJSON.message) || 'Could not save this role.');
                $btn.prop('disabled', false);
            });
    }

    function savePermissions(roleId, onDone) {
        if (!$('#rolePermissionsSection').is(':visible')) { onDone(); return; }

        const ids = $('.perm-checkbox:checked').map(function () { return Number($(this).val()); }).get();
        $.post(ENDPOINT, { action: 'save_permissions', role_id: roleId, permission_ids: JSON.stringify(ids) })
            .always(function () {
                $('#roleSaveBtn').prop('disabled', false);
                onDone();
            });
    }

    $(function () {
        loadRoles();
        $('#btnAddRole').removeClass('d-none');

        $('#rolesUsersTabs .nav-link').on('click', function () {
            const tab = $(this).data('tab');
            $('#rolesUsersTabs .nav-link').removeClass('active');
            $(this).addClass('active');

            $('#rolesTabPane').toggleClass('d-none', tab !== 'roles');
            $('#usersTabPane').toggleClass('d-none', tab !== 'users');
            $('#btnAddRole').toggleClass('d-none', tab !== 'roles');
            $('#btnAddUser').toggleClass('d-none', tab !== 'users');

            if (tab === 'users') {
                $(document).trigger('users-tab-shown');
            }
        });

        $('#btnAddRole').on('click', function () {
            resetForm();
            $.get(ENDPOINT, { action: 'permissions' }).done(function (res) {
                if (res.success) {
                    currentPermissions = res.permissions;
                    renderPermissionChecklist(res.permissions);
                    $('#rolePermissionsSection').show();
                }
                new bootstrap.Modal('#roleModal').show();
            }).fail(function () { new bootstrap.Modal('#roleModal').show(); });
        });

        $(document).on('click', '.btn-edit-role', function () { openEditModal($(this).data('id')); });

        $('#btnSelectAllPermissions').on('click', function () { $('#rolePermissionsList .perm-checkbox').prop('checked', true); });
        $('#btnClearAllPermissions').on('click', function () { $('#rolePermissionsList .perm-checkbox').prop('checked', false); });

        $('#roleForm').on('submit', function (e) { e.preventDefault(); submitRoleForm(); });
    });
})(jQuery);
