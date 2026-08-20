<?php
if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-0">Suppliers</h1>
        <p class="text-muted mb-0">Manage the vendors you purchase stock from.</p>
    </div>
    <button class="btn pos-btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#supplierModal" id="btnAddSupplier">
        <i class="bi bi-plus-lg me-1"></i> Add Supplier
    </button>
</div>

<div class="card pos-card border-0 shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div class="input-group pos-input-group" style="max-width: 320px;">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" id="supplierSearch" placeholder="Search suppliers...">
            </div>
            <select class="form-select pos-select" id="supplierPerPage" style="max-width: 140px;">
                <option value="10">10 / page</option>
                <option value="25">25 / page</option>
                <option value="50">50 / page</option>
            </select>
        </div>

        <div class="table-responsive">
            <table class="table pos-table align-middle">
                <thead>
                    <tr>
                        <th class="pos-sortable" data-sort="supplier_name">Supplier <i class="bi bi-arrow-down-up"></i></th>
                        <th class="pos-sortable" data-sort="contact_person">Contact Person <i class="bi bi-arrow-down-up"></i></th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th class="pos-sortable" data-sort="is_active">Status <i class="bi bi-arrow-down-up"></i></th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="supplierTableBody">
                    <tr><td colspan="6" class="text-center text-muted py-4">Loading...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="text-muted small" id="supplierPageInfo"></div>
            <nav><ul class="pagination pagination-sm mb-0" id="supplierPagination"></ul></nav>
        </div>
    </div>
</div>

<div class="modal fade" id="supplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="supplierForm" novalidate>
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= Security::escape($csrfToken) ?>">
                <input type="hidden" id="supplierId" name="supplier_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="supplierModalTitle">Add Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="supplierFormAlert" class="alert alert-danger d-none"></div>

                    <div class="mb-3">
                        <label for="supplierName" class="form-label">Supplier Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="supplierName" name="supplier_name" maxlength="150" required>
                    </div>
                    <div class="mb-3">
                        <label for="supplierContact" class="form-label">Contact Person</label>
                        <input type="text" class="form-control" id="supplierContact" name="contact_person" maxlength="100">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="supplierPhone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="supplierPhone" name="phone" maxlength="30">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="supplierEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="supplierEmail" name="email" maxlength="150">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="supplierAddress" class="form-label">Address</label>
                        <textarea class="form-control" id="supplierAddress" name="address" rows="2" maxlength="255"></textarea>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="supplierIsActive" name="is_active" value="1" checked>
                        <label class="form-check-label" for="supplierIsActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn pos-btn-primary" id="supplierSaveBtn">
                        <span class="btn-label">Save Supplier</span>
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
