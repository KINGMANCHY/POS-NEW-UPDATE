<?php
if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-0">Activity Logs</h1>
        <p class="text-muted mb-0">Every recorded action taken across the app, newest first.</p>
    </div>
</div>

<div class="card pos-card border-0 shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div class="d-flex gap-2 flex-wrap">
                <div class="input-group pos-input-group" style="max-width: 260px;">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="logSearch" placeholder="Action, description, user...">
                </div>
                <select class="form-select pos-select" id="logUserFilter" style="max-width: 180px;">
                    <option value="">All Users</option>
                </select>
                <input type="date" class="form-control pos-select" id="logDateFrom" style="max-width: 160px;">
                <input type="date" class="form-control pos-select" id="logDateTo" style="max-width: 160px;">
            </div>
            <select class="form-select pos-select" id="logPerPage" style="max-width: 140px;">
                <option value="25">25 / page</option>
                <option value="50">50 / page</option>
                <option value="100">100 / page</option>
            </select>
        </div>

        <div class="table-responsive">
            <table class="table pos-table align-middle">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody id="logTableBody">
                    <tr><td colspan="5" class="text-center text-muted py-4">Loading...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="text-muted small" id="logPageInfo"></div>
            <nav><ul class="pagination pagination-sm mb-0" id="logPagination"></ul></nav>
        </div>
    </div>
</div>
