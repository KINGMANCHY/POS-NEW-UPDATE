/**
 * assets/js/pos.js
 * -----------------------------------------------------------------------
 * Drives views/pos.php via app/controllers/PosController.php.
 *
 * The cart lives entirely in memory here for display/preview math only.
 * Every checkout/hold POST is re-priced and re-validated server-side
 * (Sale.php) against live Products/Inventory data - this file never
 * sends a total the server is expected to trust.
 */
(function ($) {
    'use strict';

    const ENDPOINT = (window.APP_URL || '') + '/app/controllers/PosController.php';

    let cart = [];               // [{ product_id, product_name, unit_price, tax_rate, discount_rate, unit, quantity, quantity_on_hand }]
    let selectedCustomer = null; // { customer_id, full_name, loyalty_points } or null = walk-in
    let lastSaleId = null;
    let searchDebounce = null;
    let customerDebounce = null;
    let productPage = 1;
    let loyaltyRule = { spend: 1000, points: 10, pointValue: 1 };
    let receiptPreferences = { show: true, autoPrint: false };
    let cashPaymentOnly = false;
    let itemToastTimer = null;
    let draftKey = '';
    let draftReady = false;
    const DRAFT_EXPIRY_MS = 3 * 60 * 60 * 1000;

    function escapeHtml(str) { return $('<div>').text(str == null ? '' : str).html(); }
    function money(n) { return '₱' + Number(n || 0).toFixed(2); }
    function toCents(value) { return Math.round((Number(value) || 0) * 100); }
    function fromCents(value) { return value / 100; }

    function notifyItemAdded(product) {
        const $toast = $('#posItemToast');
        $('#posItemToastText').text(product.product_name + ' added to cart');
        $toast.addClass('show');
        clearTimeout(itemToastTimer);
        itemToastTimer = setTimeout(function () { $toast.removeClass('show'); }, 2200);

    }

    function focusScanInput() {
        // Barcode scanners send Enter and immediately start the next code;
        // keep focus in their textbox instead of moving it into the cart.
        setTimeout(function () { $('#posScanInput').trigger('focus'); }, 0);
    }

    /**
     * Looks a code up as an exact barcode match first, then falls back to
     * a name/product-number/brand search. Shared by the keyboard-wedge
     * scanner input, the camera scanner modal, and the scanner's manual
     * input tab, so "search by barcode, product number, or product name"
     * behaves identically everywhere a code can be entered.
     */
    function lookupCode(code, options) {
        options = options || {};
        $('#posScanStatus').addClass('d-none');

        $.get(ENDPOINT, { action: 'barcode', code: code })
            .done(function (res) {
                if (res.success) {
                    addToCart(res.product);
                    focusScanInput();
                    loadProducts();
                    if (options.onDone) options.onDone(res.product);
                } else {
                    handleCodeMiss(code, res.message, options);
                }
            })
            .fail(function (xhr) {
                // jQuery routes ANY non-2xx HTTP status (this endpoint
                // returns 404 for "no product matches that barcode" -
                // a perfectly normal, expected outcome, not a real
                // error) to .fail() instead of .done(), even though the
                // response body is valid JSON. Read the real message
                // from it when present, and only fall back to a scary
                // generic one for an actual network-level failure.
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    handleCodeMiss(code, xhr.responseJSON.message, options);
                } else {
                    $('#posScanStatus').removeClass('d-none').text('Could not reach the server to look up that barcode. Check your connection and try again.');
                    loadProducts();
                    if (options.onMiss) options.onMiss('Could not reach the server.');
                }
            });
    }

    /**
     * No exact barcode match - falls back to a name/code/brand search
     * (this also covers "Product number" and "Product name", since the
     * server-side search already matches product_code and product_name).
     * If that search turns up exactly one product, add it directly:
     * typing a product name/number and hitting Enter is the common flow
     * here, not just literal barcode scanning, so requiring an extra
     * click on an unambiguous single result would be a surprise every time.
     */
    function handleCodeMiss(code, message, options) {
        options = options || {};
        $.get(ENDPOINT, { action: 'products', search: code, category_id: $('#posCategoryFilter').val() })
            .done(function (res) {
                if (res.success) {
                    renderProductGrid(res.products);
                    if (res.products.length === 1) {
                        addToCart(res.products[0]);
                        $('#posScanStatus').addClass('d-none');
                        focusScanInput();
                        if (options.onDone) options.onDone(res.products[0]);
                        return;
                    }
                }
                const text = (message || 'No exact barcode match.') +
                    (res && res.products && res.products.length ? ' Showing ' + res.products.length + ' close matches below.' : ' No close matches found either.');
                $('#posScanStatus').removeClass('d-none').text(text);
                if (options.onMiss) options.onMiss(text, res && res.products);
            });
    }

    // -----------------------------------------------------------------
    // Product grid
    // -----------------------------------------------------------------

    function loadFormData() {
        $.get(ENDPOINT, { action: 'form_data' })
            .done(function (res) {
                if (!res.success) return;
                const $cat = $('#posCategoryFilter');
                res.categories.forEach(function (c) {
                    $cat.append(`<option value="${c.category_id}">${escapeHtml(c.category_name)}</option>`);
                });
                loyaltyRule.spend = Number(res.loyalty && res.loyalty.loyalty_spend_amount) || 0;
                loyaltyRule.points = Number(res.loyalty && res.loyalty.loyalty_points_awarded) || 0;
                const pointValue = res.loyalty && res.loyalty.loyalty_point_value;
                loyaltyRule.pointValue = pointValue === '' || pointValue == null ? 1 : Math.max(0, Number(pointValue) || 0);
                receiptPreferences.show = !res.loyalty || res.loyalty.show_receipt_after_sale !== '0';
                receiptPreferences.autoPrint = !!(res.loyalty && res.loyalty.auto_print_receipt === '1');
                cashPaymentOnly = !!(res.loyalty && res.loyalty.cash_payment_only === '1');
                draftKey = 'pos_store_active_draft_user_' + Number(res.current_user_id || 0);
                restoreDraft();
                draftReady = true;
                renderPaymentRows(paymentRows());
                renderCart();
            })
            .fail(function (xhr) {
                // Non-critical - the category filter just stays empty ("All Categories" only).
                console.error('POS: could not load category filter data.', xhr.status, xhr.responseText);
            });
    }

    function loadProducts() {
        const search = $('#posScanInput').val().trim();
        const categoryId = $('#posCategoryFilter').val();

        $.get(ENDPOINT, { action: 'products', search: search, category_id: categoryId, page: productPage })
            .done(function (res) {
                if (res.success) {
                    renderProductGrid(res.products);
                    renderProductPagination(res.page || 1, res.total_pages || 1);
                } else {
                    renderProductGridError(res.message || 'Could not load products.');
                }
            })
            .fail(function (xhr) {
                console.error('POS: product search request failed.', xhr.status, xhr.responseText);
                renderProductGridError(
                    xhr.status === 0
                        ? 'Could not reach the server. Check your connection and try again.'
                        : 'Server error (' + xhr.status + ') while loading products.'
                );
            });
    }

    function renderProductPagination(page, totalPages) {
        const $p = $('#posProductPagination').empty();
        if (totalPages <= 1) return;
        $p.append(`<button class="btn btn-sm btn-outline-secondary" ${page <= 1 ? 'disabled' : ''} data-pos-page="${page - 1}">&lsaquo;</button><span class="small text-muted">${page} / ${totalPages}</span><button class="btn btn-sm btn-outline-secondary" ${page >= totalPages ? 'disabled' : ''} data-pos-page="${page + 1}">&rsaquo;</button>`);
    }

    function renderProductGridError(message) {
        $('#posProductGrid').html(
            '<div class="col-12 text-center text-danger py-4">' +
            escapeHtml(message) +
            ' <button type="button" class="btn btn-sm btn-outline-secondary ms-2" id="btnRetryLoadProducts">Retry</button></div>'
        );
    }

    function renderProductGrid(products) {
        const $grid = $('#posProductGrid');
        $grid.empty();

        if (!products.length) {
            $grid.html('<div class="col-12 text-center text-muted py-4">No products found.</div>');
            return;
        }

        const $tpl = $('#posProductTileTpl');

        products.forEach(function (p) {
            const $col = $($tpl.html());
            const outOfStock = Number(p.quantity_on_hand) <= 0;

            $col.find('.pos-product-name').text(p.product_name);
            $col.find('.pos-product-price').text(money(p.selling_price));
            $col.find('.pos-product-stock')
                .text(outOfStock ? 'Out of stock' : p.quantity_on_hand + ' ' + p.unit + ' left')
                .toggleClass('text-danger', outOfStock);

            const $btn = $col.find('.pos-product-tile');
            $btn.prop('disabled', outOfStock);
            $btn.on('click', function () { addToCart(p); });

            $grid.append($col);
        });
    }

    // -----------------------------------------------------------------
    // Cart
    // -----------------------------------------------------------------

    function addToCart(product) {
        const existing = cart.find(function (i) { return Number(i.product_id) === Number(product.product_id); });
        const stock = Number(product.quantity_on_hand);

        if (existing) {
            if (existing.quantity >= stock) {
                alert('Only ' + stock + ' ' + product.unit + ' of "' + product.product_name + '" left in stock.');
                return;
            }
            existing.quantity += 1;
        } else {
            cart.push({
                product_id: product.product_id,
                product_name: product.product_name,
                unit_price: Number(product.selling_price),
                tax_rate: Number(product.tax_rate),
                discount_rate: Number(product.discount_rate),
                unit: product.unit,
                quantity: 1,
                quantity_on_hand: stock,
            });
        }
        renderCart();
        notifyItemAdded(product);
    }

    function changeQty(productId, delta) {
        const item = cart.find(function (i) { return Number(i.product_id) === Number(productId); });
        if (!item) return;

        const newQty = item.quantity + delta;
        if (newQty <= 0) {
            cart = cart.filter(function (i) { return Number(i.product_id) !== Number(productId); });
        } else if (newQty > Math.min(item.quantity_on_hand, 10000)) {
            alert('Only ' + item.quantity_on_hand + ' ' + item.unit + ' left in stock.');
            return;
        } else {
            item.quantity = newQty;
        }
        renderCart();
    }

    function removeFromCart(productId) {
        cart = cart.filter(function (i) { return Number(i.product_id) !== Number(productId); });
        renderCart();
    }

    /** Client-side preview only - mirrors Sale::priceCart()'s formula so the number matches what checkout will charge. */
    function computeTotals() {
        let subtotalCents = 0, taxTotalCents = 0, lineDiscountTotalCents = 0;

        cart.forEach(function (item) {
            const lineSubtotalCents = toCents(item.unit_price) * item.quantity;
            const lineTaxCents = Math.round(lineSubtotalCents * (item.tax_rate / 100));
            const lineDiscountCents = Math.round(lineSubtotalCents * (item.discount_rate / 100));
            subtotalCents += lineSubtotalCents;
            taxTotalCents += lineTaxCents;
            lineDiscountTotalCents += lineDiscountCents;
        });

        const manualDiscountCents = Math.max(0, toCents($('#posManualDiscount').val()));
        const requestedPoints = Math.max(0, Math.floor(Number($('#posPointsToRedeem').val()) || 0));
        const availablePoints = selectedCustomer ? Number(selectedCustomer.loyalty_points) || 0 : 0;
        const availableForRedemptionCents = Math.max(0, subtotalCents + taxTotalCents - lineDiscountTotalCents - manualDiscountCents);
        const pointValueCents = Math.max(0, toCents(loyaltyRule.pointValue));
        const redeemedPoints = pointValueCents > 0
            ? Math.min(requestedPoints, availablePoints, Math.floor(availableForRedemptionCents / pointValueCents)) : 0;
        const loyaltyDiscountCents = redeemedPoints * pointValueCents;
        const discountTotalCents = lineDiscountTotalCents + manualDiscountCents + loyaltyDiscountCents;
        const grandTotalCents = Math.max(0, subtotalCents + taxTotalCents - discountTotalCents);

        return {
            subtotal: fromCents(subtotalCents), taxTotal: fromCents(taxTotalCents),
            discountTotal: fromCents(discountTotalCents), grandTotal: fromCents(grandTotalCents),
            manualDiscount: fromCents(manualDiscountCents), redeemedPoints,
            loyaltyDiscount: fromCents(loyaltyDiscountCents),
            availableForRedemptionCents, pointValueCents
        };
    }

    /** Select the largest whole-point discount that does not exceed the amount due. */
    function autoFillRedeemablePoints() {
        if (!selectedCustomer) return;
        const totals = computeTotals();
        const points = totals.pointValueCents > 0
            ? Math.min(selectedCustomer.loyalty_points, Math.floor(totals.availableForRedemptionCents / totals.pointValueCents))
            : 0;
        $('#posPointsToRedeem').val(points > 0 ? points : '');
    }

    function renderCart() {
        const $body = $('#posCartBody');
        $body.empty();

        if (!cart.length) {
            $body.html('<tr><td colspan="4" class="pos-v2-empty"><i class="bi bi-bag"></i><span>Your cart is empty</span><small>Search or scan an item to begin</small></td></tr>');
        } else {
            cart.forEach(function (item) {
                const lineTotal = item.unit_price * item.quantity
                    + Math.round(item.unit_price * item.quantity * (item.tax_rate / 100) * 100) / 100
                    - Math.round(item.unit_price * item.quantity * (item.discount_rate / 100) * 100) / 100;

                $body.append(`
                    <tr data-id="${item.product_id}" class="pos-v2-cart-item" tabindex="-1">
                        <td>
                            <div class="fw-medium">${escapeHtml(item.product_name)}</div>
                            <div class="text-muted small">${money(item.unit_price)} / ${escapeHtml(item.unit)}</div>
                        </td>
                        <td class="pos-cart-qty-cell">
                            <div class="input-group input-group-sm pos-qty-control">
                                <button class="btn btn-outline-secondary btn-qty-minus" type="button" title="Decrease quantity" aria-label="Decrease quantity">&minus;</button>
                                <input type="number" class="form-control text-center cart-qty-input" value="${item.quantity}" min="1" max="${Math.min(item.quantity_on_hand, 10000)}" inputmode="numeric" aria-label="Quantity (maximum 10,000)">
                                <button class="btn btn-outline-secondary btn-qty-plus" type="button" title="Increase quantity" aria-label="Increase quantity">+</button>
                            </div>
                        </td>
                        <td class="text-end fw-medium">${money(lineTotal)}</td>
                        <td class="text-end"><button class="btn btn-sm text-danger btn-remove-item" type="button" title="Remove item" aria-label="Remove item">&times;</button></td>
                    </tr>
                `);
            });
        }

        const totalQuantity = cart.reduce(function (sum, item) { return sum + Number(item.quantity || 0); }, 0);
        $('#posItemCount').text(cart.length);
        $('#posTotalQuantity').text(totalQuantity);

        const totals = computeTotals();
        $('#posSubtotal').text(money(totals.subtotal));
        $('#posTax').text(money(totals.taxTotal));
        $('#posDiscount').text(money(totals.discountTotal));
        $('#posGrandTotal').text(money(totals.grandTotal));
        if ($('#paymentModal').hasClass('show')) {
            $('#posPaymentSubtotal').text(money(totals.subtotal));
            $('#posPaymentTax').text(money(totals.taxTotal));
            $('#posPaymentDiscount').text(money(totals.discountTotal));
            $('#posPaymentTotal').text(money(totals.grandTotal));
        }
        const earned = loyaltyRule.spend > 0 && loyaltyRule.points > 0 ? Math.floor(totals.grandTotal / loyaltyRule.spend) * loyaltyRule.points : 0;
        $('#posLoyaltyPreview').toggleClass('d-none', !selectedCustomer).text(selectedCustomer ? `Loyalty: redeem ${totals.redeemedPoints} point(s) (${money(totals.loyaltyDiscount)}) · earn ${earned} point(s) on this sale.` : '');
        updateChangeDue();
        saveDraft();
    }

    function saveDraft() {
        if (!draftReady || !draftKey) return;
        try {
            if (!cart.length) { localStorage.removeItem(draftKey); return; }
            // References are deliberately not saved in the browser draft.
            localStorage.setItem(draftKey, JSON.stringify({
                cart: cart,
                customer: selectedCustomer,
                manualDiscount: $('#posManualDiscount').val(),
                pointsToRedeem: $('#posPointsToRedeem').val(),
                payments: paymentRows().map(function (payment) { return { method: payment.method, amount: payment.amount, reference: '' }; }),
                savedAt: new Date().toISOString()
            }));
        } catch (error) {
            // Private-mode browsers can deny local storage; POS can still run normally.
            console.warn('POS draft could not be saved.', error);
        }
    }

    function restoreDraft() {
        let draft;
        try { draft = JSON.parse(localStorage.getItem(draftKey) || 'null'); } catch (error) { localStorage.removeItem(draftKey); return; }
        if (!draft || !Array.isArray(draft.cart) || !draft.cart.length) return;
        const savedAtDate = draft.savedAt ? new Date(draft.savedAt) : null;
        if (!savedAtDate || Number.isNaN(savedAtDate.getTime()) || Date.now() - savedAtDate.getTime() > DRAFT_EXPIRY_MS) {
            localStorage.removeItem(draftKey);
            return;
        }
        const savedAt = savedAtDate.toLocaleString();
        if (!confirm('Restore the unfinished sale saved on this device (' + savedAt + ')?')) {
            localStorage.removeItem(draftKey);
            return;
        }
        cart = draft.cart.filter(function (item) { return Number(item.product_id) > 0 && Number(item.quantity) > 0; });
        selectedCustomer = draft.customer && Number(draft.customer.customer_id) > 0 ? draft.customer : null;
        $('#posManualDiscount').val(draft.manualDiscount || '');
        $('#posPointsToRedeem').val(draft.pointsToRedeem || '');
        if (selectedCustomer) {
            $('#posCustomerSelected').text(selectedCustomer.full_name + ' — saved balance: ' + (Number(selectedCustomer.loyalty_points) || 0) + ' point(s)');
            $('#posAvailablePoints').text('— available: ' + (Number(selectedCustomer.loyalty_points) || 0));
            $('#posRedeemPointsWrap').removeClass('d-none');
        }
        renderPaymentRows(Array.isArray(draft.payments) && draft.payments.length ? draft.payments : undefined);
    }

    function paymentRows() {
        return $('#posPaymentRows .pos-payment-row').map(function () {
            return { method: $(this).find('.payment-method').val(), amount: $(this).find('.payment-amount').val(), reference: String($(this).find('.payment-reference').val() || '').trim() };
        }).get();
    }

    function paymentRowHtml(payment, index, allRows) {
        const usedElsewhere = {};
        (allRows || []).forEach(function (row, i) { if (i !== index && row.method) usedElsewhere[row.method] = true; });
        const options = [['cash', 'Cash'], ['card', 'Card'], ['check', 'Check'], ['gcash', 'GCash'], ['maya', 'Maya']];
        const choices = options.filter(function (option) { return !cashPaymentOnly || option[0] === 'cash'; })
            .map(function (option) {
                // Each payment method can only be used once per sale - a
                // second "Card" (or "Cash", etc.) row is just the first
                // row with a different number, and lets the change-due
                // math double count it. Use the amount field on the
                // existing row instead of adding another of the same kind.
                const disabled = !!usedElsewhere[option[0]] && payment.method !== option[0];
                return `<option value="${option[0]}" ${payment.method === option[0] ? 'selected' : ''} ${disabled ? 'disabled' : ''}>${option[1]}${disabled ? ' (already used)' : ''}</option>`;
            }).join('');
        const isCash = payment.method === 'cash';
        return `<div class="pos-payment-row row g-2 mt-2 align-items-center"><div class="col-12 col-sm-4"><select class="form-select payment-method">${choices}</select></div><div class="${isCash ? 'col-10 col-sm-7' : 'col-12 col-sm-5'}"><input type="number" class="form-control payment-amount" min="0.01" step="0.01" inputmode="decimal" placeholder="Amount received" value="${escapeHtml(payment.amount)}"></div>${isCash ? '' : '<div class="col-10 col-sm-3"><input type="text" class="form-control payment-reference" placeholder="Reference no." value="' + escapeHtml(payment.reference) + '"></div>'}<div class="col-2 col-sm-1 text-end">${index && !cashPaymentOnly ? '<button type="button" class="btn btn-sm btn-outline-danger remove-payment" aria-label="Remove payment">×</button>' : ''}</div></div>`;
    }

    function renderPaymentRows(existingRows) {
        const rows = existingRows || paymentRows();
        if (!rows.length) rows.push({ method: 'cash', amount: '', reference: '' });
        if (cashPaymentOnly) rows.splice(1), rows[0] = { method: 'cash', amount: rows[0].amount || '', reference: '' };
        $('#posPaymentRows').html(rows.map(function (row, i) { return paymentRowHtml(row, i, rows); }).join(''));
        const allMethodsUsed = rows.length >= 5; // cash, card, check, gcash, maya
        $('#btnAddPayment').toggle(!cashPaymentOnly && !allMethodsUsed);
        $('#posCashKeypad').toggleClass('d-none', !cashPaymentOnly);
        updateChangeDue();
    }

    function updateChangeDue() {
        const paid = paymentRows().reduce(function (total, payment) { return total + (Number(payment.amount) || 0); }, 0);
        const total = computeTotals().grandTotal;
        const remaining = Math.max(0, total - paid);
        const change = Math.max(0, paid - total);
        $('#posPaymentRemaining').text(remaining > 0 ? 'Remaining balance: ' + money(remaining) : 'Payment complete');
        $('#posChangeRow').toggle(paid > 0).find('#posChangeDue').text(money(change));
    }

    function resetCart() {
        cart = [];
        selectedCustomer = null;
        $('#posCustomerSelected').text('Walk-in customer');
        $('#posCustomerSearch').val('');
        $('#posManualDiscount').val('');
        $('#posPointsToRedeem').val('');
        $('#posRedeemPointsWrap').addClass('d-none');
        $('#posPaymentRows').empty();
        renderPaymentRows();
        renderCart();
    }

    // -----------------------------------------------------------------
    // Customer search
    // -----------------------------------------------------------------

    function searchCustomers(term) {
        if (!term) { $('#posCustomerResults').hide().empty(); return; }

        $.get(ENDPOINT, { action: 'customers', search: term })
            .done(function (res) {
                if (!res.success) return;
                const $results = $('#posCustomerResults');
                $results.empty();

                if (!res.customers.length) {
                    $results.append('<div class="list-group-item text-muted small">No matches</div>').show();
                    return;
                }

                res.customers.forEach(function (c) {
                    $results.append(`
                        <button type="button" class="list-group-item list-group-item-action" data-id="${c.customer_id}" data-name="${escapeHtml(c.full_name)}" data-points="${Number(c.loyalty_points) || 0}">
                            ${escapeHtml(c.full_name)} <span class="text-muted small">${escapeHtml(c.phone || '')} · ${Number(c.loyalty_points) || 0} point(s)</span>
                        </button>
                    `);
                });
                $results.show();
            })
            .fail(function (xhr) {
                console.error('POS: customer search request failed.', xhr.status, xhr.responseText);
                $('#posCustomerResults').empty()
                    .append('<div class="list-group-item text-danger small">Could not reach the server. Try again.</div>')
                    .show();
            });
    }

    // -----------------------------------------------------------------
    // Checkout / Hold
    // -----------------------------------------------------------------

    function buildPayload(extra) {
        const totals = computeTotals();
        return $.extend({
            items: JSON.stringify(cart.map(function (i) { return { product_id: i.product_id, quantity: i.quantity }; })),
            customer_id: selectedCustomer ? selectedCustomer.customer_id : '',
            payments: JSON.stringify(paymentRows()),
            manual_discount: totals.manualDiscount,
            loyalty_points_redeemed: totals.redeemedPoints,
        }, extra || {});
    }

    function doCheckout() {
        if (!cart.length) { alert('The cart is empty.'); return; }
        const totals = computeTotals();
        const payments = paymentRows();
        const paid = payments.reduce(function (total, payment) { return total + (Number(payment.amount) || 0); }, 0);
        if (!payments.length || paid < totals.grandTotal) {
            alert('The combined payments are less than the total due.');
            return;
        }
        const missingReference = payments.find(function (payment) { return payment.method !== 'cash' && !payment.reference; });
        if (missingReference) {
            alert('Please enter reference details for every non-cash payment.');
            return;
        }

        $('#btnCheckout').prop('disabled', true);
        $.post(ENDPOINT, buildPayload({ action: 'checkout' }))
            .done(function (res) {
                if (!res.success) { alert(res.message || 'Checkout failed.'); return; }
                lastSaleId = res.sale_id;
                const change = Math.max(0, paid - totals.grandTotal);
                bootstrap.Modal.getOrCreateInstance(document.getElementById('paymentModal')).hide();
                resetCart();
                loadProducts();
                // Receipt first, then the Payment Complete confirmation once
                // the cashier closes (or finishes printing) the receipt. If
                // receipts are turned off in Settings, skip straight to it.
                if (receiptPreferences.show || receiptPreferences.autoPrint) {
                    $('#receiptModal').off('hidden.bs.modal.paymentFlow').one('hidden.bs.modal.paymentFlow', function () {
                        showPaymentComplete(res.sale_id, res.invoice_no, paid, change);
                    });
                    showReceipt(res.sale_id, receiptPreferences.autoPrint);
                } else {
                    showPaymentComplete(res.sale_id, res.invoice_no, paid, change);
                }
            })
            .fail(function (xhr) {
                alert((xhr.responseJSON && xhr.responseJSON.message) || 'Checkout failed.');
            })
            .always(function () { $('#btnCheckout').prop('disabled', false); });
    }

    /**
     * "Payment Complete" confirmation shown after the receipt has been
     * shown/printed. Auto-closes after a 20s countdown (handy at a
     * counter where the cashier's hands are busy with cash/card), but the
     * Done button lets them dismiss it immediately.
     */
    let paymentCompleteTimer = null;
    function showPaymentComplete(saleId, invoiceNo, amountPaid, change) {
        $('#paymentCompleteInvoice').text(invoiceNo || ('#' + saleId));
        $('#paymentCompleteAmountPaid').text(money(amountPaid));
        $('#paymentCompleteChange').text(money(change));

        const totalSeconds = 20;
        let secondsLeft = totalSeconds;
        $('#paymentCompleteCountdownText').text('Continuing in ' + secondsLeft + 's…');
        $('#paymentCompleteProgressBar').css('width', '100%');

        const modalEl = document.getElementById('paymentCompleteModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

        function finish() {
            clearInterval(paymentCompleteTimer);
            paymentCompleteTimer = null;
            modal.hide();
        }

        clearInterval(paymentCompleteTimer);
        paymentCompleteTimer = setInterval(function () {
            secondsLeft -= 1;
            $('#paymentCompleteProgressBar').css('width', Math.max(0, (secondsLeft / totalSeconds) * 100) + '%');
            if (secondsLeft <= 0) { finish(); return; }
            $('#paymentCompleteCountdownText').text('Continuing in ' + secondsLeft + 's…');
        }, 1000);

        $('#btnPaymentCompleteDone').off('click').on('click', finish);
        $(modalEl).off('hidden.bs.modal.paymentComplete').one('hidden.bs.modal.paymentComplete', function () {
            clearInterval(paymentCompleteTimer);
            paymentCompleteTimer = null;
        });

        modal.show();
    }

    function doHold() {
        if (!cart.length) { alert('The cart is empty.'); return; }

        $.post(ENDPOINT, buildPayload({ action: 'hold' }))
            .done(function (res) {
                if (!res.success) { alert(res.message || 'Could not hold this sale.'); return; }
                resetCart();
                refreshHeldBadge();
            })
            .fail(function (xhr) {
                alert((xhr.responseJSON && xhr.responseJSON.message) || 'Could not hold this sale.');
            });
    }

    // -----------------------------------------------------------------
    // Held sales
    // -----------------------------------------------------------------

    function refreshHeldBadge() {
        $.get(ENDPOINT, { action: 'held_list' })
            .done(function (res) {
                if (!res.success) return;
                const count = res.held.length;
                $('#heldCountBadge').text(count).toggle(count > 0);
            })
            .fail(function (xhr) {
                console.error('POS: could not refresh held-sales badge.', xhr.status, xhr.responseText);
            });
    }

    function loadHeldList() {
        $('#heldSalesList').html('<div class="text-center text-muted py-4">Loading...</div>');
        $.get(ENDPOINT, { action: 'held_list' })
            .done(function (res) {
                if (!res.success) return;
                const $list = $('#heldSalesList');
                $list.empty();

                if (!res.held.length) {
                    $list.html('<div class="text-center text-muted py-4">No held sales.</div>');
                    return;
                }

                res.held.forEach(function (sale) {
                    $list.append(`
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-medium">${escapeHtml(sale.invoice_no)}</div>
                                <div class="text-muted small">${sale.item_count} item(s) · ${escapeHtml(sale.customer_name || 'Walk-in')} · ${money(sale.grand_total)}</div>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm pos-btn-primary btn-resume-held" data-id="${sale.sale_id}">Resume</button>
                                <button class="btn btn-sm btn-outline-danger btn-void-held" data-id="${sale.sale_id}">Void</button>
                            </div>
                        </div>
                    `);
                });
            })
            .fail(function (xhr) {
                console.error('POS: could not load held sales.', xhr.status, xhr.responseText);
                $('#heldSalesList').html('<div class="text-center text-danger py-4">Could not load held sales. Please try again.</div>');
            });
    }

    function resumeHeld(saleId) {
        $.get(ENDPOINT, { action: 'held_get', id: saleId }).done(function (res) {
            if (!res.success) { alert(res.message || 'Could not load that held sale.'); return; }

            const sale = res.sale;
            cart = sale.items.map(function (item) {
                return {
                    product_id: item.product_id,
                    product_name: item.product_name,
                    unit_price: Number(item.selling_price),
                    tax_rate: 0, discount_rate: 0, // recomputed server-side at checkout anyway; preview only
                    unit: item.unit,
                    quantity: item.quantity,
                    quantity_on_hand: Number(item.quantity_on_hand),
                };
            });

            if (sale.customer_id) {
                selectedCustomer = { customer_id: sale.customer_id, full_name: sale.customer_name, loyalty_points: 0 };
                $('#posCustomerSelected').text(sale.customer_name);
            }

            // Clear the held row now that its cart has been restored into the active cart.
            $.post(ENDPOINT, { action: 'held_delete', id: saleId }).always(function () {
                refreshHeldBadge();
            });

            renderCart();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('heldSalesModal')).hide();
        }).fail(function (xhr) {
            alert((xhr.responseJSON && xhr.responseJSON.message) || 'Could not load that held sale.');
        });
    }

    function voidHeld(saleId) {
        if (!confirm('Void this held sale? This cannot be undone.')) return;

        $.post(ENDPOINT, { action: 'held_delete', id: saleId })
            .done(function (res) {
                if (res.success) { loadHeldList(); refreshHeldBadge(); }
            })
            .fail(function (xhr) {
                alert((xhr.responseJSON && xhr.responseJSON.message) || 'Could not void that held sale.');
            });
    }

    // -----------------------------------------------------------------
    // Receipt
    // -----------------------------------------------------------------

    function showReceipt(saleId, autoPrint) {
        $.get(ENDPOINT, { action: 'receipt', id: saleId })
            .done(function (res) {
                if (!res.success) return;
                window.POSReceipt.render($('#receiptContent'), res.sale, res.settings || {});
                const modalEl = document.getElementById('receiptModal');
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                if (autoPrint) {
                    $(modalEl).one('shown.bs.modal', function () { setTimeout(function () { window.print(); }, 250); });
                }
                modal.show();
            })
            .fail(function (xhr) {
                alert((xhr.responseJSON && xhr.responseJSON.message) || 'Could not load the receipt for that sale.');
            });
    }

    // -----------------------------------------------------------------
    // Events
    // -----------------------------------------------------------------

    $(function () {
        // Customer selection belongs to the final sale review, immediately above its totals.
        $('.pos-v2-payment-totals').first().before(`
            <section class="pos-v2-customer position-relative pos-v2-modal-customer">
                <label for="posCustomerSearch">Customer <span>optional</span></label>
                <div class="pos-v2-search-field"><i class="bi bi-person"></i><input type="text" id="posCustomerSearch" placeholder="Search customer"><button type="button" id="btnClearCustomer" title="Use walk-in customer" aria-label="Clear customer"><i class="bi bi-x"></i></button></div>
                <div class="pos-v2-selected-customer" id="posCustomerSelected">Walk-in customer</div>
                <div class="mt-2 d-none" id="posRedeemPointsWrap"><label class="form-label small mb-1" for="posPointsToRedeem">Redeem loyalty points <span id="posAvailablePoints"></span></label><input type="number" class="form-control form-control-sm" id="posPointsToRedeem" min="0" step="1" inputmode="numeric" placeholder="Points to use"><div class="form-text" id="posPointValueHint"></div></div>
                <div class="list-group position-absolute shadow-sm" id="posCustomerResults" style="z-index:20;width:100%;display:none;"></div>
            </section>
        `);
        $('body').append('<div class="pos-v2-toast" id="posItemToast" role="status" aria-live="polite"><i class="bi bi-check-circle-fill"></i><span id="posItemToastText"></span></div>');
        renderPaymentRows();
        loadFormData();
        loadProducts();
        renderCart();
        refreshHeldBadge();

        $('#posScanInput').on('keydown', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            const code = $(this).val().trim();
            if (!code) return;
            lookupCode(code, { onDone: function () { $('#posScanInput').val(''); } });
        });

        $('#posScanInput').on('input', function () {
            $('#posScanStatus').addClass('d-none');
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(loadProducts, 300);
        });

        $('#posCategoryFilter').on('change', loadProducts);
        $('#posProductPagination').on('click', '[data-pos-page]', function () { productPage = Number($(this).data('pos-page')); loadProducts(); });
        $(document).on('click', '#btnRetryLoadProducts', loadProducts);

        $(document).on('click', '#posCartBody .btn-qty-plus', function (event) {
            event.preventDefault();
            changeQty(Number($(this).closest('tr').data('id')), 1);
        });
        $(document).on('click', '#posCartBody .btn-qty-minus', function (event) {
            event.preventDefault();
            changeQty(Number($(this).closest('tr').data('id')), -1);
        });
        $(document).on('click', '#posCartBody .btn-remove-item', function (event) {
            event.preventDefault();
            removeFromCart(Number($(this).closest('tr').data('id')));
        });
        $(document).on('change', '#posCartBody .cart-qty-input', function () {
            const item = cart.find(i => Number(i.product_id) === Number($(this).closest('tr').data('id')));
            const value = Math.floor(Number($(this).val()));
            if (!item || !Number.isFinite(value) || value < 1 || value > Math.min(item.quantity_on_hand, 10000)) { renderCart(); return; }
            item.quantity = value; renderCart();
        });

        $('#posManualDiscount, #posPointsToRedeem').on('input', renderCart);
        $('#posPaymentRows').on('input', '.payment-amount, .payment-reference', function () { updateChangeDue(); saveDraft(); });
        $('#posPaymentRows').on('change', '.payment-method', function () { renderPaymentRows(paymentRows()); saveDraft(); });
        $('#btnAddPayment').on('click', function () {
            const rows = paymentRows();
            const paid = rows.reduce(function (total, payment) { return total + (Number(payment.amount) || 0); }, 0);
            const usedMethods = rows.map(function (r) { return r.method; });
            // Cash is prioritised elsewhere via the keypad, so default a new
            // row to the next unused non-cash method instead of always
            // "card" - once card's already in use that option is disabled,
            // and defaulting to it would add a row nothing can be selected in.
            const nextMethod = ['card', 'gcash', 'maya', 'check', 'cash'].find(function (m) { return usedMethods.indexOf(m) === -1; }) || 'card';
            rows.push({ method: nextMethod, amount: Math.max(0, computeTotals().grandTotal - paid).toFixed(2), reference: '' });
            renderPaymentRows(rows);
            saveDraft();
        });
        $('#posPaymentRows').on('click', '.remove-payment', function () { $(this).closest('.pos-payment-row').remove(); updateChangeDue(); saveDraft(); });
        $(document).on('click', '#posCashKeypad button', function () {
            const $amount = $('#posPaymentRows .payment-amount').first();
            const key = String($(this).data('key')); let value = $amount.val() || '';
            if (key === 'clear') value = ''; else if (key === 'back') value = value.slice(0, -1);
            else if (key === '.') { if (value.includes('.')) return; value = value ? value + '.' : '0.'; }
            else if (!value.includes('.') || value.split('.')[1].length < 2) value = value === '0' ? key : value + key;
            $amount.val(value).trigger('input');
        });

        $('#posCustomerSearch').on('input', function () {
            const term = $(this).val().trim();
            clearTimeout(customerDebounce);
            customerDebounce = setTimeout(function () { searchCustomers(term); }, 250);
        });
        $('#posCustomerResults').on('click', 'button', function () {
            selectedCustomer = { customer_id: Number($(this).data('id')), full_name: $(this).data('name'), loyalty_points: Number($(this).data('points')) || 0 };
            $('#posCustomerSelected').text(selectedCustomer.full_name + ' — current balance: ' + selectedCustomer.loyalty_points + ' point(s)');
            $('#posAvailablePoints').text('— available: ' + selectedCustomer.loyalty_points);
            $('#posPointValueHint').text('1 point = ' + money(loyaltyRule.pointValue) + ' discount.');
            $('#posRedeemPointsWrap').removeClass('d-none');
            autoFillRedeemablePoints();
            $('#posCustomerSearch').val('');
            $('#posCustomerResults').hide().empty();
            renderCart();
        });
        $('#btnClearCustomer').on('click', function () {
            selectedCustomer = null;
            $('#posCustomerSelected').text('Walk-in customer');
            $('#posPointsToRedeem').val('');
            $('#posRedeemPointsWrap').addClass('d-none');
            $('#posCustomerSearch').val('');
            $('#posCustomerResults').hide().empty();
            renderCart();
        });
        $(document).on('click', function (e) {
            if (!$(e.target).closest('#posCustomerSearch, #posCustomerResults').length) {
                $('#posCustomerResults').hide();
            }
        });

        $('#btnClearCart').on('click', function () {
            if (cart.length && !confirm('Clear the current cart?')) return;
            resetCart();
        });
        $('#btnOpenPayment').on('click', function () {
            if (!cart.length) { alert('The cart is empty.'); return; }
            const totals = computeTotals();
            $('#posPaymentSubtotal').text(money(totals.subtotal));
            $('#posPaymentTax').text(money(totals.taxTotal));
            $('#posPaymentDiscount').text(money(totals.discountTotal));
            $('#posPaymentTotal').text(money(totals.grandTotal));
            updateChangeDue();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('paymentModal')).show();
        });
        $('#btnCheckout').on('click', doCheckout);
        $('#btnHoldSale').on('click', doHold);

        $('#btnHeldSales').on('click', function () {
            loadHeldList();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('heldSalesModal')).show();
        });
        $('#heldSalesList').on('click', '.btn-resume-held', function () { resumeHeld(Number($(this).data('id'))); });
        $('#heldSalesList').on('click', '.btn-void-held', function () { voidHeld(Number($(this).data('id'))); });

        $('#btnPrintReceipt').on('click', function () { window.print(); });

        $('#btnOpenCatalog').on('click', function () {
            loadProducts();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('catalogModal')).show();
        });

        $('#btnOpenScanner').on('click', function () {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('scannerModal')).show();
        });

    });

    // Exposed so assets/js/pos-scanner.js (the camera/QR/manual-input
    // modal) can reuse the exact same lookup + add-to-cart pipeline as
    // the keyboard-wedge scanner input, instead of duplicating it.
    window.POSCart = { lookupCode: lookupCode, addToCart: addToCart, focusScanInput: focusScanInput };
})(jQuery);
