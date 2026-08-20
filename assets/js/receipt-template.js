/**
 * assets/js/receipt-template.js
 * -----------------------------------------------------------------------
 * Shared receipt markup + Code 39 barcode builder. Used by:
 *   - assets/js/pos.js       (the real "sale complete" receipt)
 *   - assets/js/settings.js  (the "Preview receipt" button, with sample data)
 *
 * Keeping this in one file means the Classic/Modern template and the
 * 58mm/80mm printer-width sizing behave identically everywhere a receipt
 * is shown, instead of two copies quietly drifting apart.
 */
(function (window) {
    'use strict';

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : str;
        return div.innerHTML;
    }

    function money(n) { return '₱' + Number(n || 0).toFixed(2); }

    /** Builds a real Code 39 barcode as an inline SVG from a receipt reference string. */
    function buildCode39Barcode(value) {
        const patterns = {
            '0': 'nnnwwnwnn', '1': 'wnnwnnnnw', '2': 'nnwwnnnnw', '3': 'wnwwnnnnn', '4': 'nnnwwnnnw',
            '5': 'wnnwwnnnn', '6': 'nnwwwnnnn', '7': 'nnnwnnwnw', '8': 'wnnwnnwnn', '9': 'nnwwnnwnn',
            'A': 'wnnnnwnnw', 'B': 'nnwnnwnnw', 'C': 'wnwnnwnnn', 'D': 'nnnnwwnnw', 'E': 'wnnnwwnnn',
            'F': 'nnwnwwnnn', 'G': 'nnnnnwwnw', 'H': 'wnnnnwwnn', 'I': 'nnwnnwwnn', 'J': 'nnnnwwwnn',
            'K': 'wnnnnnnww', 'L': 'nnwnnnnww', 'M': 'wnwnnnnwn', 'N': 'nnnnwnnww', 'O': 'wnnnwnnwn',
            'P': 'nnwnwnnwn', 'Q': 'nnnnnnwww', 'R': 'wnnnnnwwn', 'S': 'nnwnnnwwn', 'T': 'nnnnwnwwn',
            'U': 'wwnnnnnnw', 'V': 'nwwnnnnnw', 'W': 'wwwnnnnnn', 'X': 'nwnnwnnnw', 'Y': 'wwnnwnnnn',
            'Z': 'nwwnwnnnn', '-': 'nwnnnnwnw', '.': 'wwnnnnwnn', ' ': 'nwwnnnwnn', '$': 'nwnwnwnnn',
            '/': 'nwnwnnnwn', '+': 'nwnnnwnwn', '%': 'nnnwnwnwn', '*': 'nwnnwnwnn'
        };
        const characters = ('*' + String(value).toUpperCase() + '*').split('');
        let x = 16;
        const rectangles = [];
        characters.forEach(function (character, characterIndex) {
            const pattern = patterns[character] || patterns['-'];
            pattern.split('').forEach(function (width, index) {
                const barWidth = width === 'w' ? 6 : 2;
                if (index % 2 === 0) rectangles.push(`<rect x="${x}" y="0" width="${barWidth}" height="48"/>`);
                x += barWidth;
            });
            if (characterIndex < characters.length - 1) x += 2;
        });
        const width = x + 16;
        return `<svg class="pos-code39-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${width} 48" role="img" aria-hidden="true" preserveAspectRatio="none">${rectangles.join('')}</svg>`;
    }

    /**
     * Renders a receipt into $target (a jQuery-wrapped element, or a raw
     * DOM element / selector string). Applies the Classic/Modern template
     * class and the 58mm/80mm width class from `settings`, so the same
     * function produces identical output for a live sale and for the
     * Settings preview.
     */
    function render(target, sale, settings) {
        settings = settings || {};
        const el = (target && target.jquery) ? target[0] : (typeof target === 'string' ? document.querySelector(target) : target);
        if (!el) return;

        const template = settings.receipt_template === 'modern' ? 'modern' : 'classic';
        const width = settings.printer_width === '58' ? '58' : '80';
        el.className = el.className
            .split(' ')
            .filter(function (c) { return c && c.indexOf('pos-receipt-tpl-') !== 0 && c.indexOf('pos-receipt-w') !== 0; })
            .concat(['pos-receipt-tpl-' + template, 'pos-receipt-w' + width])
            .join(' ');

        let itemLines = '';
        (sale.items || []).forEach(function (item) {
            itemLines += `
                <div class="pos-receipt-line">
                    <span>${escapeHtml(item.product_name)}</span>
                    <span>${item.quantity}${escapeHtml(item.unit || '')}</span>
                    <span>${money(item.line_total)}</span>
                </div>
            `;
        });

        // Code 39 is widely supported by receipt/printer scanners. Use the
        // numeric sale ID rather than customer data so the receipt remains
        // private while retaining a unique, scannable reference.
        const barcodeValue = 'S' + sale.sale_id;
        const barcodeBars = buildCode39Barcode(barcodeValue);

        el.innerHTML = `
            <div class="pos-receipt-head pos-receipt-header text-center">
                ${settings.show_store_on_receipt !== '0' ? `
                    <div class="pos-receipt-store-name">${escapeHtml(settings.store_name || '')}</div>
                    <div class="pos-receipt-store-address">${escapeHtml(settings.store_address || '')}</div>
                    <div class="pos-receipt-store-contact">${escapeHtml(settings.store_phone || '')}</div>` : ''}
                <div class="pos-receipt-invoice">Invoice: ${escapeHtml(sale.invoice_no)}</div>
                <div class="pos-receipt-meta">Date: ${escapeHtml(sale.created_at)} · Cashier: ${escapeHtml(sale.cashier_name)}</div>
                <div class="pos-receipt-customer">Customer: ${escapeHtml(sale.customer_name || 'Walk-in customer')}</div>
            </div>
            <div class="pos-receipt-line pos-receipt-column-head">
                <span>Item</span><span>Quantity</span><span>Amount</span>
            </div>
            ${itemLines}
            <div class="pos-receipt-divider"></div>
            <div class="pos-receipt-line"><span>Subtotal</span><span></span><span>${money(sale.subtotal)}</span></div>
            <div class="pos-receipt-line"><span>Tax</span><span></span><span>${money(sale.tax_total)}</span></div>
            <div class="pos-receipt-line"><span>Discount${Number(sale.loyalty_points_redeemed || 0) > 0 ? ' (Loyalty redeemed: ' + Number(sale.loyalty_points_redeemed) + ' point(s))' : ''}</span><span></span><span>-${money(sale.discount_total)}</span></div>
            <div class="pos-receipt-divider"></div>
            <div class="pos-receipt-line pos-receipt-total"><span>Total</span><span></span><span>${money(sale.grand_total)}</span></div>
            ${(sale.payments || []).map(function (payment) { return `<div class="pos-receipt-line"><span>${escapeHtml(String(payment.payment_method).toUpperCase())}${payment.payment_reference ? ' · ' + escapeHtml(payment.payment_reference) : ''}</span><span></span><span>${money(payment.amount)}</span></div>`; }).join('')}
            <div class="pos-receipt-line"><span>Change</span><span></span><span>${money(sale.change_due)}</span></div>
            <div class="pos-receipt-barcode" role="img" aria-label="Receipt barcode: ${escapeHtml(barcodeValue)}">${barcodeBars}</div>
            <div class="pos-receipt-code">SALE-${escapeHtml(sale.sale_id)} · ${escapeHtml(sale.invoice_no)}</div>
            ${settings.receipt_footer ? `<div class="text-center small mt-2">${escapeHtml(settings.receipt_footer)}</div>` : ''}
        `;
    }

    /** Sample data for the Settings "Preview receipt" button - no server round trip needed. */
    function sampleSale() {
        return {
            sale_id: 1024,
            invoice_no: 'INV-001024',
            created_at: new Date().toLocaleString(),
            cashier_name: 'Jordan Cruz',
            customer_name: 'Walk-in customer',
            subtotal: 486.00,
            tax_total: 58.32,
            discount_total: 20.00,
            grand_total: 524.32,
            change_due: 75.68,
            loyalty_points_redeemed: 0,
            payments: [{ payment_method: 'cash', payment_reference: '', amount: 600.00 }],
            items: [
                { product_name: 'Bottled Water 500ml', quantity: 3, unit: 'pc', line_total: 90.00 },
                { product_name: 'Instant Noodles', quantity: 5, unit: 'pack', line_total: 175.00 },
                { product_name: 'Canned Sardines', quantity: 4, unit: 'can', line_total: 221.00 },
            ],
        };
    }

    window.POSReceipt = { render: render, buildCode39Barcode: buildCode39Barcode, sampleSale: sampleSale, money: money, escapeHtml: escapeHtml };
})(window);
