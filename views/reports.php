<?php
if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-0">Reports</h1>
        <p class="text-muted mb-0">Sales performance for the selected date range.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap align-items-center">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnPrintReport"><i class="bi bi-printer me-1"></i>Print / PDF</button>
        <div class="btn-group btn-group-sm" role="group" id="reportPresets">
            <button type="button" class="btn btn-outline-secondary" data-preset="today">Today</button>
            <button type="button" class="btn btn-outline-secondary" data-preset="yesterday">Yesterday</button>
            <button type="button" class="btn btn-outline-secondary" data-preset="week">This Week</button>
            <button type="button" class="btn btn-outline-secondary active" data-preset="month">This Month</button>
            <button type="button" class="btn btn-outline-secondary" data-preset="year">This Year</button>
        </div>
        <input type="date" class="form-control form-control-sm pos-select" id="reportDateFrom" style="max-width: 150px;">
        <span class="text-muted small">to</span>
        <input type="date" class="form-control form-control-sm pos-select" id="reportDateTo" style="max-width: 150px;">
    </div>
</div>

<!-- KPI cards -->
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="card pos-card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small mb-1">Revenue</div>
            <div class="h4 mb-0" id="statRevenue">₱0.00</div>
            <div class="text-muted small" id="statTransactions">0 transactions</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card pos-card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small mb-1">Gross Profit</div>
            <div class="h4 mb-0" id="statProfit">₱0.00</div>
            <div class="text-muted small" id="statMargin">0% margin</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card pos-card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small mb-1">Average Sale</div>
            <div class="h4 mb-0" id="statAvgSale">₱0.00</div>
            <div class="text-muted small" id="statUnits">0 units sold</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card pos-card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small mb-1">Purchases (spend)</div>
            <div class="h4 mb-0" id="statPurchaseSpend">₱0.00</div>
            <div class="text-muted small" id="statPurchaseCount">0 purchases received</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card pos-card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small mb-1">Expenses</div>
            <div class="h4 mb-0 text-danger" id="statExpenses">₱0.00</div>
            <div class="text-muted small" id="statExpenseCount">0 recorded</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card pos-card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small mb-1">Net Profit</div>
            <div class="h4 mb-0" id="statNetProfit">₱0.00</div>
            <div class="text-muted small">Gross profit minus expenses</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card pos-card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small mb-1">Returns / Refunds</div>
            <div class="h4 mb-0 text-danger" id="statReturns">₱0.00</div>
            <div class="text-muted small" id="statReturnsCount">0 voided sale(s)</div>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <!-- Sales trend -->
    <div class="col-lg-7">
        <div class="card pos-card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 mb-3">Revenue by Day</h2>
                <canvas id="salesTrendChart" height="220"></canvas>
                <div class="text-center text-muted py-5 d-none" id="salesTrendEmpty">No sales in this range.</div>
            </div>
        </div>
    </div>

    <!-- Payment mix -->
    <div class="col-lg-5">
        <div class="card pos-card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 mb-3">Payment Method Mix</h2>
                <div id="paymentBreakdown">
                    <div class="text-center text-muted py-4">No sales in this range.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <!-- Top products by revenue -->
    <div class="col-lg-6">
        <div class="card pos-card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 mb-3">Top Products by Revenue</h2>
                <table class="table pos-table align-middle mb-0">
                    <thead><tr><th>Product</th><th class="text-end">Units</th><th class="text-end">Revenue</th></tr></thead>
                    <tbody id="topByRevenueBody"><tr><td colspan="3" class="text-center text-muted py-3">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Top products by quantity -->
    <div class="col-lg-6">
        <div class="card pos-card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 mb-3">Top Products by Quantity Sold</h2>
                <table class="table pos-table align-middle mb-0">
                    <thead><tr><th>Product</th><th class="text-end">Units</th><th class="text-end">Revenue</th></tr></thead>
                    <tbody id="topByQuantityBody"><tr><td colspan="3" class="text-center text-muted py-3">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-12">
        <div class="card pos-card border-0 shadow-sm">
            <div class="card-body table-responsive" >
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h6 mb-0">Expenses</h2>
                    <button class="btn btn-sm pos-btn-primary" type="button" id="btnAddExpense"><i class="bi bi-plus-lg me-1"></i>Add Expense</button>
                </div>
                <table class="table pos-table align-middle mb-0 ">
                    <thead>
                        <tr><th>Date</th><th>Category</th><th>Description</th><th class="text-end">Amount</th><th>Recorded By</th><th></th></tr>
                    </thead>
                    <tbody id="expensesBody"><tr><td colspan="6" class="text-center text-muted py-3">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Expense modal -->
<div class="modal fade" id="expenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="expenseForm">
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="expenseFormAlert"></div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">Category</label>
                        <select class="form-select pos-select" id="expenseCategory" required>
                            <option value="">Select category...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">Description</label>
                        <input type="text" class="form-control" id="expenseDescription" maxlength="255" placeholder="Optional">
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small text-muted">Amount</label>
                            <input type="number" class="form-control" id="expenseAmount" min="0.01" step="0.01" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted">Date</label>
                            <input type="date" class="form-control" id="expenseDate" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn pos-btn-primary" id="expenseSaveBtn">
                        <span class="spinner-border spinner-border-sm d-none me-1"></span>Save Expense
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
