<?php
if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-0">Customers</h1>
        <p class="text-muted mb-0">Manage the people who shop at your store.</p>
    </div>
    <button class="btn pos-btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#customerModal" id="btnAddCustomer">
        <i class="bi bi-plus-lg me-1"></i> Add Customer
    </button>
</div>

<div class="card pos-card border-0 shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div class="input-group pos-input-group" style="max-width: 320px;">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" id="customerSearch" placeholder="Search customers...">
            </div>
            <select class="form-select pos-select" id="customerPerPage" style="max-width: 140px;">
                <option value="10">10 / page</option>
                <option value="25">25 / page</option>
                <option value="50">50 / page</option>
            </select>
        </div>

        <div class="table-responsive">
            <table class="table pos-table align-middle">
                <thead>
                    <tr>
                        <th class="pos-sortable" data-sort="full_name">Name <i class="bi bi-arrow-down-up"></i></th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th class="pos-sortable" data-sort="loyalty_points">Loyalty Pts <i class="bi bi-arrow-down-up"></i></th>
                        <th class="pos-sortable" data-sort="is_active">Status <i class="bi bi-arrow-down-up"></i></th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="customerTableBody">
                    <tr><td colspan="6" class="text-center text-muted py-4">Loading...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="text-muted small" id="customerPageInfo"></div>
            <nav><ul class="pagination pagination-sm mb-0" id="customerPagination"></ul></nav>
        </div>
    </div>
</div>

<div class="modal fade" id="customerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="customerForm" novalidate>
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= Security::escape($csrfToken) ?>">
                <input type="hidden" id="customerId" name="customer_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="customerModalTitle">Add Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="customerFormAlert" class="alert alert-danger d-none"></div>

                    <div class="mb-3">
                        <label for="customerName" class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="customerName" name="full_name" maxlength="150" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="customerPhone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="customerPhone" name="phone" maxlength="30">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="customerEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="customerEmail" name="email" maxlength="150">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="customerAddress" class="form-label">Address</label>
                        <textarea class="form-control" id="customerAddress" name="address" rows="2" maxlength="255"></textarea>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="customerIsActive" name="is_active" value="1" checked>
                        <label class="form-check-label" for="customerIsActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn pos-btn-primary" id="customerSaveBtn">
                        <span class="btn-label">Save Customer</span>
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
