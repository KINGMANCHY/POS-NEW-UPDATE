<?php
if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-0">Inventory</h1>
        <p class="text-muted mb-0">Current stock levels. Sales and purchases adjust this automatically - use "Adjust" here only for physical counts and corrections.</p>
    </div>
</div>

<div class="card pos-card border-0 shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <div class="input-group pos-input-group" style="max-width: 260px;">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="invSearch" placeholder="Product name, code, barcode...">
                </div>
                <select class="form-select pos-select" id="invCategoryFilter" style="max-width: 180px;">
                    <option value="">All Categories</option>
                </select>
                <div class="form-check form-switch ms-1">
                    <input class="form-check-input" type="checkbox" id="invLowStockOnly">
                    <label class="form-check-label small" for="invLowStockOnly">
                        Low stock only <span class="badge pos-badge-warning" id="invLowStockBadge" style="display:none;">0</span>
                    </label>
                </div>
            </div>
            <select class="form-select pos-select" id="invPerPage" style="max-width: 140px;">
                <option value="10">10 / page</option>
                <option value="25">25 / page</option>
                <option value="50">50 / page</option>
            </select>
        </div>

        <div class="table-responsive">
            <table class="table pos-table align-middle">
                <thead>
                    <tr>
                        <th class="pos-sortable" data-sort="product_name">Product <i class="bi bi-arrow-down-up"></i></th>
                        <th>Category</th>
                        <th class="pos-sortable" data-sort="quantity_on_hand">On Hand <i class="bi bi-arrow-down-up"></i></th>
                        <th>Alert Level</th>
                        <th class="pos-sortable" data-sort="updated_at">Last Updated <i class="bi bi-arrow-down-up"></i></th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="invTableBody">
                    <tr><td colspan="6" class="text-center text-muted py-4">Loading...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="text-muted small" id="invPageInfo"></div>
            <nav><ul class="pagination pagination-sm mb-0" id="invPagination"></ul></nav>
        </div>
    </div>
</div>

<!-- Adjust stock modal -->
<div class="modal fade" id="invAdjustModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="invAdjustForm">
                <div class="modal-header">
                    <h5 class="modal-title">Adjust Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="invAdjustAlert"></div>
                    <div class="mb-2">
                        <div class="fw-medium" id="invAdjustProductName"></div>
                        <div class="text-muted small">Currently <span id="invAdjustCurrentQty">0</span> on hand</div>
                    </div>
                    <input type="hidden" id="invAdjustProductId">
                    <div class="mb-3">
                        <label class="form-label small text-muted">Change Type</label>
                        <select class="form-select" id="invChangeType"><option value="adjustment">Adjustment (recount / correction)</option><option value="stock_in">Stock in (add stock)</option><option value="stock_out">Stock out (remove stock)</option></select>
                    </div><div class="mb-3">
                        <label class="form-label small text-muted" id="invQtyLabel">New Quantity On Hand</label>
                        <input type="number" class="form-control" id="invAdjustQty" min="0" step="1" required>
                    </div>
                    <div class="mb-1">
                        <label class="form-label small text-muted">Note / Reference</label>
                        <input type="text" class="form-control" id="invAdjustReason" placeholder="e.g. Physical count, damaged stock, correction..." required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn pos-btn-primary" type="submit" id="invAdjustSaveBtn">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
