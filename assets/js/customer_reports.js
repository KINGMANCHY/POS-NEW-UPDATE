(function ($) {
    'use strict';
    const endpoint = (window.APP_URL || '') + '/app/controllers/CustomerReportController.php';
    let searchTimer = null;
    const esc = v => $('<div>').text(v == null ? '' : v).html();
    const money = v => '₱' + Number(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const query = () => ({
        search: $('#customerReportCustomerId').val() ? '' : $('#customerReportSearch').val().trim(),
        customer_id: $('#customerReportCustomerId').val(),
        date_from: $('#customerReportFrom').val(), date_to: $('#customerReportTo').val()
    });

    function loadDetails() {
        const params = query(), $details = $('#customerReportDetails');
        if (!params.customer_id) { $details.addClass('d-none').empty(); return; }
        $.get(endpoint, $.extend({ action: 'details' }, params)).done(res => {
            if (!res.success) return;
            const c = res.customer;
            const rows = res.sales.length ? res.sales.map(s => `<tr><td>${esc(s.invoice_no)}</td><td>${esc(s.created_at)}</td><td>${esc(String(s.payment_method).toUpperCase())}</td><td class="text-success">+${s.loyalty_points_earned}</td><td class="text-danger">-${s.loyalty_points_redeemed}</td><td class="text-end">${money(s.grand_total)}</td></tr>`).join('') : '<tr><td colspan="6" class="text-center text-muted py-3">No completed sales in this date range.</td></tr>';
            $details.html(`<div class="border rounded p-3"><div class="fw-semibold mb-2">${esc(c.full_name)} — point balance: ${c.loyalty_points}</div><div class="small text-muted mb-3">${esc(c.phone || c.email || 'No contact details')}</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Invoice</th><th>Date</th><th>Payment</th><th>Earned</th><th>Redeemed</th><th class="text-end">Total</th></tr></thead><tbody>${rows}</tbody></table></div></div>`).removeClass('d-none');
        });
    }

    function load() {
        const params = query();
        const showSummary = !params.customer_id;
        $('#customerReportSummary').toggleClass('d-none', !showSummary);
        if (showSummary) {
            $.get(endpoint, $.extend({ action: 'list' }, params)).done(res => {
                const $body = $('#customerReportBody').empty();
                if (!res.success || !res.rows.length) { $body.html('<tr><td colspan="8" class="text-center text-muted py-4">No customer records found.</td></tr>'); return; }
                res.rows.forEach(x => $body.append(`<tr><td class="fw-medium">${esc(x.full_name)}</td><td>${esc(x.phone || x.email || '—')}</td><td>${x.loyalty_points}</td><td class="text-success">+${x.points_earned}</td><td class="text-danger">-${x.points_redeemed}</td><td>${x.transaction_count}</td><td class="text-end font-monospace">${money(x.total_spent)}</td><td>${esc(x.last_purchase || '—')}</td></tr>`));
            });
        }
        const encoded = $.param(params);
        $('#customerExportExcel').attr('href', endpoint + '?action=export_excel&' + encoded);
        $('#customerExportPdf').attr('href', endpoint + '?action=export_pdf&' + encoded);
        loadDetails();
    }

    function searchCustomers(term) {
        if (!term) { $('#customerReportResults').hide().empty(); return; }
        $.get(endpoint, { action: 'customers', search: term }).done(res => {
            const $results = $('#customerReportResults').empty();
            if (!res.success || !res.customers.length) { $results.html('<div class="list-group-item text-muted small">No customers found.</div>').show(); return; }
            res.customers.forEach(c => $results.append(`<button type="button" class="list-group-item list-group-item-action" data-id="${c.customer_id}" data-name="${esc(c.full_name)}">${esc(c.full_name)} <span class="text-muted small">${esc(c.phone || c.email || '')} · ${c.loyalty_points} points</span></button>`));
            $results.show();
        });
    }

    $(function () {
        load();
        $('#customerReportFilter').on('click', load);
        $('#customerReportSearch').on('input', function () {
            $('#customerReportCustomerId').val(''); clearTimeout(searchTimer);
            searchTimer = setTimeout(() => searchCustomers($(this).val().trim()), 200);
        }).on('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); load(); } });
        $('#customerReportResults').on('click', 'button', function () {
            $('#customerReportCustomerId').val($(this).data('id'));
            $('#customerReportSearch').val($(this).data('name'));
            $('#customerReportResults').hide().empty(); load();
        });
        $(document).on('click', e => { if (!$(e.target).closest('#customerReportSearch, #customerReportResults').length) $('#customerReportResults').hide(); });
    });
})(jQuery);
