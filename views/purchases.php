<?php
if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-0">Purchases</h1>
        <p class="text-muted mb-0">Record stock received from suppliers.</p>
    </div>
    <button class="btn pos-btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#purchaseModal" id="btnAddPurchase">
        <i class="bi bi-plus-lg me-1"></i> Record Purchase
    </button>
</div>

<div class="card pos-card border-0 shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div class="d-flex gap-2 flex-wrap">
                <div class="input-group pos-input-group" style="max-width: 260px;">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="purchaseSearch" placeholder="Reference, supplier...">
                </div>
                <select class="form-select pos-select" id="purchaseStatusFilter" style="max-width: 160px;">
                    <option value="">All Statuses</option>
                    <option value="received">Received</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <select class="form-select pos-select" id="purchasePerPage" style="max-width: 140px;">
                <option value="10">10 / page</option>
                <option value="25">25 / page</option>
                <option value="50">50 / page</option>
            </select>
        </div>

        <div class="table-responsive">
            <table class="table pos-table align-middle">
                <thead>
                    <tr>
                        <th class="pos-sortable" data-sort="reference_no">Reference <i class="bi bi-arrow-down-up"></i></th>
                        <th class="pos-sortable" data-sort="purchased_at">Date <i class="bi bi-arrow-down-up"></i></th>
                        <th>Supplier</th>
                        <th>Recorded By</th>
                        <th>Items</th>
                        <th class="pos-sortable" data-sort="total_amount">Total <i class="bi bi-arrow-down-up"></i></th>
                        <th class="pos-sortable" data-sort="status">Status <i class="bi bi-arrow-down-up"></i></th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="purchaseTableBody">
                    <tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="text-muted small" id="purchasePageInfo"></div>
            <nav><ul class="pagination pagination-sm mb-0" id="purchasePagination"></ul></nav>
        </div>
    </div>
</div>

<!-- Record purchase modal -->
<div class="modal fade" id="purchaseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="purchaseForm">
                <div class="modal-header">
                    <h5 class="modal-title">Record Purchase</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="purchaseFormAlert"></div>

                    <div class="mb-3">
                        <label class="form-label small text-muted">Supplier</label>
                        <select class="form-select pos-select" id="purchaseSupplier" required>
                            <option value="">Select supplier...</option>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label small text-muted" for="purchaseInvoiceReceipt">Supplier Invoice / Receipt No.</label><input class="form-control" id="purchaseInvoiceReceipt" maxlength="100" placeholder="Invoice or receipt number"></div>

                    <div class="mb-2 d-flex justify-content-between align-items-center">
                        <label class="form-label small text-muted mb-0">Line Items</label>
                        <div class="position-relative" style="max-width: 280px; width: 100%;">
                            <div class="input-group pos-input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control form-control-sm" id="purchaseProductSearch" placeholder="Search product to add...">
                            </div>
                            <div class="list-group position-absolute shadow-sm" id="purchaseProductResults" style="z-index: 30; width: 100%; display:none;"></div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th style="width: 100px;">Qty</th>
                                    <th style="width: 130px;">Unit Cost</th>
                                    <th class="text-end" style="width: 110px;">Line Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="purchaseLineBody">
                                <tr><td colspan="5" class="text-center text-muted py-3">No items added yet.</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end fw-semibold">
                        Total: <span id="purchaseGrandTotal" class="ms-2">₱0.00</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn pos-btn-primary" type="submit" id="purchaseSaveBtn">
                        <span class="spinner-border spinner-border-sm d-none me-1"></span> Save Purchase
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View purchase modal -->
<div class="modal fade" id="purchaseViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Purchase Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="purchaseViewContent"></div>
            <div class="modal-footer">
                <button class="btn btn-outline-danger d-none" type="button" id="btnCancelPurchase">Cancel Purchase</button>
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
