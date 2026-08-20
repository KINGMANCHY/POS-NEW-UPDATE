/**
 * assets/js/reports.js
 * -----------------------------------------------------------------------
 * Drives views/reports.php via app/controllers/ReportController.php.
 * One bundled "summary" call per date-range change.
 */
(function ($) {
    'use strict';

    const ENDPOINT = (window.APP_URL || '') + '/app/controllers/ReportController.php';
    let trendChart = null;

    function escapeHtml(str) { return $('<div>').text(str == null ? '' : str).html(); }
    function money(n) { return '₱' + Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    function toDateInputValue(date) {
        return date.toISOString().slice(0, 10);
    }

    function applyPreset(preset) {
        const today = new Date();
        let from = new Date(today);
        let to = new Date(today);

        if (preset === 'today') {
            // from/to stay today
        } else if (preset === 'yesterday') {
            from.setDate(today.getDate() - 1);
            to.setDate(today.getDate() - 1);
        } else if (preset === 'week') {
            const day = today.getDay(); // 0 = Sunday
            from.setDate(today.getDate() - day);
        } else if (preset === 'month') {
            from = new Date(today.getFullYear(), today.getMonth(), 1);
        } else if (preset === 'year') {
            from = new Date(today.getFullYear(), 0, 1);
        }

        $('#reportDateFrom').val(toDateInputValue(from));
        $('#reportDateTo').val(toDateInputValue(to));
        loadSummary();
    }

    function loadSummary() {
        const dateFrom = $('#reportDateFrom').val();
        const dateTo = $('#reportDateTo').val();
        if (!dateFrom || !dateTo) return;

        $.get(ENDPOINT, { action: 'summary', date_from: dateFrom, date_to: dateTo })
            .done(function (res) {
                if (!res.success) return;
                renderStats(res.summary, res.purchase_summary, res.returns_summary);
                renderTrend(res.sales_by_day);
                renderPaymentMix(res.payment_breakdown);
                renderTopProducts('#topByRevenueBody', res.top_products.by_revenue);
                renderTopProducts('#topByQuantityBody', res.top_products.by_quantity);
                renderExpenses(res.expenses);
                populateExpenseCategories(res.expense_categories);
            });
    }

    function renderStats(summary, purchaseSummary, returnsSummary) {
        $('#statRevenue').text(money(summary.revenue));
        $('#statTransactions').text(summary.transaction_count + ' transaction' + (summary.transaction_count === 1 ? '' : 's'));

        $('#statProfit').text(money(summary.gross_profit));
        $('#statMargin').text(summary.gross_margin_pct + '% margin');

        $('#statAvgSale').text(money(summary.average_sale));
        $('#statUnits').text(summary.units_sold + ' units sold');

        $('#statPurchaseSpend').text(money(purchaseSummary.total_spend));
        $('#statPurchaseCount').text(purchaseSummary.purchase_count + ' purchase' + (purchaseSummary.purchase_count === 1 ? '' : 's') + ' received');

        $('#statExpenses').text(money(summary.expense_total));
        $('#statExpenseCount').text(summary.expense_total > 0 ? 'in this range' : '0 recorded');
        $('#statNetProfit').text(money(summary.net_profit));

        const returns = returnsSummary || { voided_count: 0, voided_amount: 0 };
        $('#statReturns').text(money(returns.voided_amount));
        $('#statReturnsCount').text(returns.voided_count + ' voided sale' + (returns.voided_count === 1 ? '' : 's'));
    }

    function renderExpenses(expenses) {
        const $body = $('#expensesBody').empty();
        if (!expenses.length) {
            $body.html('<tr><td colspan="6" class="text-center text-muted py-3">No expenses in this range.</td></tr>');
            return;
        }
        expenses.forEach(function (e) {
            $body.append(`
                <tr data-id="${e.expense_id}">
                    <td class="text-muted">${escapeHtml(e.expense_date)}</td>
                    <td><span class="badge pos-badge-muted">${escapeHtml(e.category)}</span></td>
                    <td class="text-muted small">${escapeHtml(e.description || '')}</td>
                    <td class="text-end font-monospace">${money(e.amount)}</td>
                    <td class="text-muted small">${escapeHtml(e.recorded_by)}</td>
                    <td class="text-end"><button class="btn btn-sm text-danger btn-delete-expense" data-id="${e.expense_id}" title="Delete"><i class="bi bi-x-lg"></i></button></td>
                </tr>
            `);
        });
    }

    function populateExpenseCategories(categories) {
        const $sel = $('#expenseCategory');
        if ($sel.find('option').length > 1) return; // already populated
        categories.forEach(function (c) { $sel.append(`<option value="${c}">${c}</option>`); });
    }

    function renderTrend(rows) {
        const $canvas = $('#salesTrendChart');
        const $empty = $('#salesTrendEmpty');

        if (!rows.length) {
            $canvas.addClass('d-none');
            $empty.removeClass('d-none');
            if (trendChart) { trendChart.destroy(); trendChart = null; }
            return;
        }
        $canvas.removeClass('d-none');
        $empty.addClass('d-none');

        const labels = rows.map(function (r) { return r.date; });
        const data = rows.map(function (r) { return r.revenue; });

        if (trendChart) {
            trendChart.data.labels = labels;
            trendChart.data.datasets[0].data = data;
            trendChart.update();
            return;
        }

        trendChart = new Chart($canvas[0].getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenue',
                    data: data,
                    borderColor: '#2f6f4f',
                    backgroundColor: 'rgba(47,111,79,0.12)',
                    tension: 0.25,
                    fill: true,
                    pointRadius: 3,
                }],
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } },
            },
        });
    }

    function renderPaymentMix(rows) {
        const $wrap = $('#paymentBreakdown').empty();

        if (!rows.length) {
            $wrap.html('<div class="text-center text-muted py-4">No sales in this range.</div>');
            return;
        }

        const total = rows.reduce(function (sum, r) { return sum + r.revenue; }, 0);
        const colors = { cash: 'bg-success', gcash: 'bg-primary', maya: 'bg-info', card: 'bg-warning' };

        rows.forEach(function (row) {
            const pct = total > 0 ? Math.round((row.revenue / total) * 100) : 0;
            $wrap.append(`
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-uppercase fw-medium">${escapeHtml(row.payment_method)}</span>
                        <span class="text-muted">${money(row.revenue)} &middot; ${row.transaction_count} txn</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar ${colors[row.payment_method] || 'bg-secondary'}" style="width: ${pct}%"></div>
                    </div>
                </div>
            `);
        });
    }

    function renderTopProducts(selector, rows) {
        const $body = $(selector).empty();
        if (!rows.length) {
            $body.html('<tr><td colspan="3" class="text-center text-muted py-3">No sales in this range.</td></tr>');
            return;
        }
        rows.forEach(function (row) {
            $body.append(`
                <tr>
                    <td>${escapeHtml(row.product_name)}</td>
                    <td class="text-end">${row.quantity_sold}</td>
                    <td class="text-end font-monospace">${money(row.revenue)}</td>
                </tr>
            `);
        });
    }

    $(function () {
        $('#btnPrintReport').on('click', function () {
            document.body.classList.add('printing-report');
            window.print();
            window.setTimeout(function () { document.body.classList.remove('printing-report'); }, 1000);
        });
        applyPreset('month');

        $('#reportPresets').on('click', 'button', function () {
            $('#reportPresets button').removeClass('active');
            $(this).addClass('active');
            applyPreset($(this).data('preset'));
        });

        $('#reportDateFrom, #reportDateTo').on('change', function () {
            $('#reportPresets button').removeClass('active');
            loadSummary();
        });

        $('#btnAddExpense').on('click', function () {
            $('#expenseForm')[0].reset();
            $('#expenseFormAlert').addClass('d-none').text('');
            $('#expenseDate').val(new Date().toISOString().slice(0, 10));
            new bootstrap.Modal('#expenseModal').show();
        });

        $('#expenseForm').on('submit', function (e) {
            e.preventDefault();
            const $btn = $('#expenseSaveBtn');
            $btn.prop('disabled', true).find('.spinner-border').removeClass('d-none');

            $.post(ENDPOINT, {
                action: 'add_expense',
                category: $('#expenseCategory').val(),
                description: $('#expenseDescription').val(),
                amount: $('#expenseAmount').val(),
                expense_date: $('#expenseDate').val(),
            })
                .done(function (res) {
                    if (!res.success) {
                        $('#expenseFormAlert').removeClass('d-none').text(res.message || 'Could not save this expense.');
                        return;
                    }
                    bootstrap.Modal.getInstance(document.getElementById('expenseModal')).hide();
                    loadSummary();
                })
                .fail(function (xhr) {
                    $('#expenseFormAlert').removeClass('d-none').text((xhr.responseJSON && xhr.responseJSON.message) || 'Could not save this expense.');
                })
                .always(function () {
                    $btn.prop('disabled', false).find('.spinner-border').addClass('d-none');
                });
        });

        $('#expensesBody').on('click', '.btn-delete-expense', function () {
            if (!confirm('Delete this expense?')) return;
            const id = $(this).data('id');
            $.post(ENDPOINT, { action: 'delete_expense', expense_id: id }).done(function (res) {
                if (res.success) loadSummary();
                else alert(res.message || 'Could not delete this expense.');
            });
        });
    });
})(jQuery);
