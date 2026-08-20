<?php
if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-0">Sales History</h1>
        <p class="text-muted mb-0">Every transaction processed through the POS Screen.</p>
    </div>
    <div class="d-flex gap-2"><a class="btn btn-sm btn-outline-secondary" id="salesExportExcel"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a><a class="btn btn-sm btn-outline-secondary" id="salesExportPdf"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a></div>
</div>

<div class="card pos-card border-0 shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div class="d-flex gap-2 flex-wrap">
                <div class="input-group pos-input-group" style="max-width: 260px;">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="saleSearch" placeholder="Invoice, customer, cashier...">
                </div>
                <select class="form-select pos-select" id="saleStatusFilter" style="max-width: 160px;">
                    <option value="">All Statuses</option>
                    <option value="completed">Completed</option>
                    <option value="voided">Voided</option>
                    <option value="held">Held</option>
                </select>

                <input type="date" class="form-control pos-select" id="saleDateFrom" style="max-width: 160px;">
                <input type="date" class="form-control pos-select" id="saleDateTo" style="max-width: 160px;">
            </div>
            <select class="form-select pos-select" id="salePerPage" style="max-width: 140px;">
                <option value="10">10 / page</option>
                <option value="25">25 / page</option>
                <option value="50">50 / page</option>
            </select>
        </div>

        <div class="table-responsive">
            <table class="table pos-table align-middle">
                <thead>
                    <tr>
                        <th class="pos-sortable" data-sort="invoice_no">Invoice <i class="bi bi-arrow-down-up"></i></th>
                        <th class="pos-sortable" data-sort="created_at">Date <i class="bi bi-arrow-down-up"></i></th>
                        <th>Customer</th>
                        <th>Cashier</th>
                        <th>Items</th>
                        <th>Payment</th>
                        <th class="pos-sortable" data-sort="grand_total">Total <i class="bi bi-arrow-down-up"></i></th>
                        <th class="pos-sortable" data-sort="status">Status <i class="bi bi-arrow-down-up"></i></th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="saleTableBody">
                    <tr><td colspan="9" class="text-center text-muted py-4">Loading...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="text-muted small" id="salePageInfo"></div>
            <nav><ul class="pagination pagination-sm mb-0" id="salePagination"></ul></nav>
        </div>
    </div>
</div>

<!-- Receipt / details modal -->
<div class="modal fade" id="saleReceiptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Sale Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="pos-receipt" id="saleReceiptContent"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-danger d-none" type="button" id="btnVoidSale">Void Sale</button>
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
