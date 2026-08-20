/**
 * assets/js/reconciliation.js
 * -----------------------------------------------------------------------
 * Drives views/reconciliation.php via app/controllers/ReconciliationController.php.
 * Loads one day at a time: the Transaction Record table on the left,
 * the cash-drawer math + counted-cash form on the right.
 */
(function ($) {
    'use strict';

    const ENDPOINT = (window.APP_URL || '') + '/app/controllers/ReconciliationController.php';

    function escapeHtml(str) { return $('<div>').text(str == null ? '' : str).html(); }
    function money(n) { return '₱' + Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function todayStr() {
        const d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    const STATUS_BADGE = { completed: 'success', voided: 'danger', held: 'secondary' };

    function renderTransactions(transactions) {
        const $body = $('#recTxnBody').empty();
        $('#recTxnCount').text(transactions.length + ' transaction' + (transactions.length === 1 ? '' : 's'));

        if (!transactions.length) {
            $body.html('<tr><td colspan="8" class="text-center text-muted py-4">No transactions on this date.</td></tr>');
            $('#recTxnTotal').text(money(0));
            return;
        }

        let completedTotal = 0;
        transactions.forEach(function (t) {
            if (t.status === 'completed') completedTotal += t.grand_total;
            const time = new Date(t.created_at).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
            $body.append(`
                <tr>
                    <td>${escapeHtml(time)}</td>
                    <td class="fw-medium">${escapeHtml(t.invoice_no)}</td>
                    <td>${escapeHtml(t.customer_name)}</td>
                    <td>${escapeHtml(t.cashier_name)}</td>
                    <td>${t.item_count} item${t.item_count === 1 ? '' : 's'} · ${t.quantity_sold} qty</td>
                    <td class="text-capitalize">${escapeHtml(t.payment_method)}</td>
                    <td class="text-end">${money(t.grand_total)}</td>
                    <td><span class="badge bg-${STATUS_BADGE[t.status] || 'secondary'}-subtle text-${STATUS_BADGE[t.status] || 'secondary'}-emphasis text-capitalize">${escapeHtml(t.status)}</span></td>
                </tr>
            `);
        });
        $('#recTxnTotal').text(money(completedTotal));
    }

    function renderPaymentBreakdown(rows) {
        const $wrap = $('#recPaymentBreakdown').empty();
        if (!rows.length) { $wrap.html('<div class="small text-muted">No completed sales.</div>'); return; }
        rows.forEach(function (row) {
            $wrap.append(`<div class="d-flex justify-content-between small"><span class="text-capitalize">${escapeHtml(row.payment_method)} (${row.payment_count})</span><span class="fw-medium">${money(row.total)}</span></div>`);
        });
    }

    function updateVariance() {
        const expected = Number($('#recExpectedCash').data('value')) || 0;
        const counted = Number($('#recCountedCash').val()) || 0;
        const variance = counted - expected;
        const $variance = $('#recVariance');
        $variance.text((variance > 0 ? '+' : '') + money(variance));
        $variance.removeClass('text-success text-danger text-muted')
            .addClass(variance === 0 ? 'text-success' : (variance > 0 ? 'text-success' : 'text-danger'));
    }

    function recomputeExpected() {
        const opening = Number($('#recOpeningFloat').val()) || 0;
        const collected = Number($('#recCashCollected').data('value')) || 0;
        const given = Number($('#recChangeGiven').data('value')) || 0;
        const expected = opening + collected - given;
        $('#recExpectedCash').data('value', expected).text(money(expected));
        updateVariance();
    }

    function loadDay(date) {
        $('#recDate').val(date);
        $('#recSavedInfo').addClass('d-none');

        $.get(ENDPOINT, { action: 'day', date: date })
            .done(function (res) {
                if (!res.success) return;
                renderTransactions(res.transactions);
                renderPaymentBreakdown(res.payment_breakdown);

                $('#recCashCollected').data('value', res.cash_movement.cash_collected).text(money(res.cash_movement.cash_collected));
                $('#recChangeGiven').data('value', res.cash_movement.change_given).text('-' + money(res.cash_movement.change_given));
                $('#recOpeningFloat').val(res.suggested_opening_float.toFixed(2));

                if (res.existing) {
                    $('#recCountedCash').val(res.existing.counted_cash.toFixed(2));
                    $('#recNotes').val(res.existing.notes || '');
                    $('#recSavedInfo').removeClass('d-none').text(
                        'Closed by ' + res.existing.closed_by + ' · ' + new Date(res.existing.updated_at).toLocaleString()
                    );
                } else {
                    $('#recCountedCash').val('');
                    $('#recNotes').val('');
                }

                recomputeExpected();
            });
    }

    function saveReconciliation() {
        const date = $('#recDate').val();
        const openingFloat = Number($('#recOpeningFloat').val()) || 0;
        const countedCash = $('#recCountedCash').val();

        if (countedCash === '' || Number(countedCash) < 0) {
            alert('Enter the counted cash amount before saving.');
            return;
        }

        $('#btnSaveReconciliation').prop('disabled', true);
        $.post(ENDPOINT, {
            action: 'save', date: date,
            opening_float: openingFloat, counted_cash: countedCash,
            notes: $('#recNotes').val(),
        })
            .done(function (res) {
                if (!res.success) { alert(res.message || 'Could not save the reconciliation.'); return; }
                $('#recSavedInfo').removeClass('d-none').text('Saved just now.');
                loadDay(date);
            })
            .fail(function (xhr) {
                alert((xhr.responseJSON && xhr.responseJSON.message) || 'Could not save the reconciliation.');
            })
            .always(function () { $('#btnSaveReconciliation').prop('disabled', false); });
    }

    function shiftDay(deltaDays) {
        const current = new Date($('#recDate').val() + 'T00:00:00');
        current.setDate(current.getDate() + deltaDays);
        loadDay(current.getFullYear() + '-' + String(current.getMonth() + 1).padStart(2, '0') + '-' + String(current.getDate()).padStart(2, '0'));
    }

    $(function () {
        if (!$('#recDate').length) return;

        loadDay(todayStr());

        $('#recDate').on('change', function () { loadDay($(this).val()); });
        $('#btnRecPrevDay').on('click', function () { shiftDay(-1); });
        $('#btnRecNextDay').on('click', function () { shiftDay(1); });
        $('#btnRecToday').on('click', function () { loadDay(todayStr()); });
        $('#recOpeningFloat').on('input', recomputeExpected);
        $('#recCountedCash').on('input', updateVariance);
        $('#btnSaveReconciliation').on('click', saveReconciliation);
    });
})(jQuery);
