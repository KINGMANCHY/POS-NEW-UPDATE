<?php
if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-0">Item Ledger</h1>
        <p class="text-muted mb-0">Stock card for one product - opening balance, every in/out movement, closing balance.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="#" class="btn btn-outline-secondary btn-sm disabled" id="btnExportExcel" target="_blank" rel="noopener">
            <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
        </a>
        <a href="#" class="btn btn-outline-secondary btn-sm disabled" id="btnExportPdf" target="_blank" rel="noopener">
            <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
        </a>
    </div>
</div>

<div class="card pos-card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-5 position-relative">
                <label class="form-label small text-muted mb-1">Product</label>
                <div class="input-group pos-input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="ledgerProductSearch" placeholder="Search by name, code, or barcode...">
                </div>
                <div class="list-group position-absolute shadow-sm" id="ledgerProductResults" style="z-index: 20; width: 100%; display:none;"></div>
                <div class="small text-muted mt-1" id="ledgerProductSelected">No product selected</div>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">From</label>
                <input type="date" class="form-control pos-select" id="ledgerDateFrom">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">To</label>
                <input type="date" class="form-control pos-select" id="ledgerDateTo">
            </div>
            <div class="col-md-1">
                <button class="btn pos-btn-primary w-100" type="button" id="btnLoadLedger"><i class="bi bi-arrow-repeat"></i></button>
            </div>
        </div>
    </div>
</div>

<div id="ledgerEmptyState" class="text-center text-muted py-5">
    <i class="bi bi-journal-text" style="font-size: 2rem;"></i>
    <p class="mt-2 mb-0">Search for a product above to see its stock card.</p>
</div>

<div id="ledgerContent" class="d-none">
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="card pos-card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small mb-1">Opening Balance</div>
                <div class="h5 mb-0" id="statOpening">0</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card pos-card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small mb-1">Total In</div>
                <div class="h5 mb-0 text-success" id="statIn">0</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card pos-card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small mb-1">Total Out</div>
                <div class="h5 mb-0 text-danger" id="statOut">0</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card pos-card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small mb-1">Closing Balance</div>
                <div class="h5 mb-0" id="statClosing">0</div>
            </div></div>
        </div>
    </div>

    <div class="card pos-card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table pos-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Reference</th>
                            <th class="text-end">In</th>
                            <th class="text-end">Out</th>
                            <th class="text-end">Balance</th>
                            <th>Notes</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody id="ledgerTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
