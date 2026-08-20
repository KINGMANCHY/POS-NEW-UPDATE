<?php
if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-0">Roles &amp; Permissions</h1>
        <p class="text-muted mb-0">Control what each role can access, and who has an account.</p>
    </div>
    <button class="btn pos-btn-primary d-none" type="button" id="btnAddRole">
        <i class="bi bi-plus-lg me-1"></i> New Role
    </button>
    <button class="btn pos-btn-primary d-none" type="button" id="btnAddUser">
        <i class="bi bi-plus-lg me-1"></i> New User
    </button>
</div>

<ul class="nav nav-tabs mb-3" id="rolesUsersTabs">
    <li class="nav-item"><button class="nav-link active" data-tab="roles" type="button">Roles</button></li>
    <li class="nav-item"><button class="nav-link" data-tab="users" type="button">Users</button></li>
</ul>

<div id="rolesTabPane">
<div class="row g-3" id="roleCardGrid">
    <div class="col-12 text-center text-muted py-4">Loading roles...</div>
</div>
</div>

<div id="usersTabPane" class="d-none">
    <div class="card pos-card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">Search</label>
                    <div class="input-group pos-input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="userSearch" placeholder="Search by username, name, or email...">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">Role</label>
                    <select class="form-select pos-select" id="userRoleFilter">
                        <option value="">All Roles</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card pos-card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table pos-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="pos-sortable" data-sort="username">Username</th>
                            <th class="pos-sortable" data-sort="full_name">Full Name</th>
                            <th>Email</th>
                            <th class="pos-sortable" data-sort="role_name">Role</th>
                            <th class="pos-sortable" data-sort="is_active">Status</th>
                            <th>Last Login</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="userTableBody"><tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr></tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <span class="text-muted small" id="userPageInfo"></span>
                <nav><ul class="pagination pagination-sm mb-0" id="userPagination"></ul></nav>
            </div>
        </div>
    </div>
</div>

<!-- User add/edit modal -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="userForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalTitle">New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="userFormAlert"></div>
                    <input type="hidden" id="userId">

                    <div class="mb-3">
                        <label class="form-label small text-muted">Username</label>
                        <input type="text" class="form-control" id="userUsername" maxlength="50" required>
                    </div>
                    <div class="mb-3" id="userPasswordWrap">
                        <label class="form-label small text-muted">Password</label>
                        <input type="password" class="form-control" id="userPassword" minlength="8" placeholder="At least 8 characters">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">Full Name</label>
                        <input type="text" class="form-control" id="userFullName" maxlength="150" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">Email</label>
                        <input type="email" class="form-control" id="userEmail" maxlength="150">
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small text-muted">Role</label>
                            <select class="form-select pos-select" id="userRole" required></select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted d-block">&nbsp;</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="userIsActive" checked>
                                <label class="form-check-label" for="userIsActive">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" id="btnResetPassword" style="display:none;">Reset Password</button>
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn pos-btn-primary" type="submit" id="userSaveBtn">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset password modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="resetPasswordForm">
                <div class="modal-header">
                    <h5 class="modal-title">Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="resetPasswordAlert"></div>
                    <input type="hidden" id="resetPasswordUserId">
                    <label class="form-label small text-muted">New Password</label>
                    <input type="password" class="form-control" id="resetPasswordValue" minlength="8" required placeholder="At least 8 characters">
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn pos-btn-primary" type="submit">Set Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Role edit modal -->
<div class="modal fade" id="roleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <form id="roleForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="roleModalTitle">New Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="roleFormAlert"></div>
                    <input type="hidden" id="roleId">

                    <div class="mb-3">
                        <label class="form-label small text-muted">Role Name</label>
                        <input type="text" class="form-control" id="roleName" maxlength="50" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">Description</label>
                        <textarea class="form-control" id="roleDescription" rows="2" maxlength="255"></textarea>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="roleIsActive" checked>
                        <label class="form-check-label" for="roleIsActive">Active</label>
                    </div>

                    <div id="rolePermissionsSection" style="display:none;">
                        <div class="d-flex justify-content-between align-items-center mb-1"><label class="form-label small text-muted mb-0">Permissions</label><div><button class="btn btn-link btn-sm py-0" type="button" id="btnSelectAllPermissions">Select all menus</button><button class="btn btn-link btn-sm py-0" type="button" id="btnClearAllPermissions">Clear</button></div></div>
                        <div id="rolePermissionsList" class="border rounded p-2 overflow-auto" style="max-height: 45vh;"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn pos-btn-primary" type="submit" id="roleSaveBtn">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
