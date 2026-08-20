<?php
if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}
?>
<div class="pos-v2" id="posV2">
    <header class="pos-v2-header"><div><span class="pos-v2-eyebrow">POINT OF SALE</span><h1>New sale</h1></div><button class="pos-v2-held-button" type="button" id="btnHeldSales"><i class="bi bi-clock-history"></i><span>Held</span><span class="pos-v2-held-count" id="heldCountBadge" style="display:none;">0</span></button></header>
    <section class="pos-v2-items"><div class="pos-v2-item-search"><i class="bi bi-upc-scan"></i><input type="text" id="posScanInput" placeholder="Search barcode, product number, or item name" autofocus><button type="button" id="btnOpenScanner" aria-label="Open barcode / QR scanner" title="Scan barcode or QR code"><i class="bi bi-qr-code-scan"></i></button><button type="button" id="btnOpenCatalog" aria-label="Browse items"><i class="bi bi-grid"></i></button></div><div class="small text-muted mt-2 d-none" id="posScanStatus"></div><div class="pos-v2-cart-head"><span>Item</span><span>Qty</span><span>Total</span></div><div class="pos-v2-cart" id="posCartScroll"><table class="pos-table pos-v2-cart-table"><tbody id="posCartBody"><tr><td colspan="4" class="pos-v2-empty"><i class="bi bi-bag"></i><span>Your cart is empty</span><small>Search or scan an item to begin</small></td></tr></tbody></table></div></section>
    <section class="pos-v2-summary"><div class="pos-v2-count-row"><span><i class="bi bi-boxes"></i> Items: <strong id="posItemCount">0</strong></span><span><i class="bi bi-stack"></i> Total Qty: <strong id="posTotalQuantity">0</strong></span></div><div class="pos-v2-summary-row"><span>Subtotal</span><strong id="posSubtotal">₱0.00</strong></div><div class="pos-v2-summary-row"><span>Tax</span><strong id="posTax">₱0.00</strong></div><div class="pos-v2-summary-row pos-v2-discount-row"><span>Discount</span><div><strong id="posDiscount">₱0.00</strong><input type="number" id="posManualDiscount" placeholder="Manual" min="0" step="0.01" inputmode="decimal"></div></div><div class="pos-v2-total-row"><span>Total</span><strong id="posGrandTotal">₱0.00</strong></div><div class="small text-success d-none" id="posLoyaltyPreview"></div></section>
    <footer class="pos-v2-actions"><button class="pos-v2-hold" type="button" id="btnHoldSale"><i class="bi bi-pause-circle"></i> Hold</button><button class="pos-v2-complete" type="button" id="btnOpenPayment">Complete sale <i class="bi bi-arrow-right"></i></button><button class="pos-v2-clear" type="button" id="btnClearCart" title="Clear cart" aria-label="Clear cart"><i class="bi bi-trash3"></i></button></footer>
</div>

<div class="modal fade" id="catalogModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Add items</h5><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><select class="form-select pos-select mb-3" id="posCategoryFilter"><option value="">All categories</option></select><div class="row g-2" id="posProductGrid"><div class="col-12 text-center text-muted py-4">Loading items...</div></div><div class="d-flex justify-content-center align-items-center gap-2 pt-3" id="posProductPagination"></div></div></div></div></div>

<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down"><div class="modal-content pos-v2-payment-modal"><div class="modal-header"><h5 class="modal-title">Complete sale</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><div class="pos-v2-payment-totals"><div><span>Subtotal</span><strong id="posPaymentSubtotal">₱0.00</strong></div><div><span>Tax</span><strong id="posPaymentTax">₱0.00</strong></div><div><span>Discount</span><strong id="posPaymentDiscount">₱0.00</strong></div><div class="pos-v2-payment-due"><span>Total</span><strong id="posPaymentTotal">₱0.00</strong></div></div><label class="pos-v2-payment-label">Payments</label><div id="posPaymentRows"></div><button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="btnAddPayment"><i class="bi bi-plus-lg me-1"></i>Add payment method</button><div class="small text-muted mt-2" id="posPaymentRemaining"></div><div class="pos-v2-cash-keypad d-none" id="posCashKeypad" aria-label="Cash keypad"><button type="button" data-key="1">1</button><button type="button" data-key="2">2</button><button type="button" data-key="3">3</button><button type="button" data-key="4">4</button><button type="button" data-key="5">5</button><button type="button" data-key="6">6</button><button type="button" data-key="7">7</button><button type="button" data-key="8">8</button><button type="button" data-key="9">9</button><button type="button" class="pos-v2-keypad-clear" data-key="clear">Clear</button><button type="button" data-key="0">0</button><button type="button" data-key="back" aria-label="Backspace"><i class="bi bi-backspace"></i></button><button type="button" class="pos-v2-keypad-dot" data-key=".">.</button></div><div class="pos-v2-change" id="posChangeRow" style="display:none;"><span>Change</span><strong id="posChangeDue">₱0.00</strong></div></div><div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Back</button><button class="pos-v2-complete" type="button" id="btnCheckout">Save sale <i class="bi bi-check2"></i></button></div></div></div></div>

<div class="modal fade" id="scannerModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><i class="bi bi-qr-code-scan me-1"></i>Scan barcode / QR code</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body">
    <ul class="nav nav-pills pos-scanner-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation"><button class="nav-link active" id="scannerCameraTabBtn" data-bs-toggle="pill" data-bs-target="#scannerCameraTab" type="button" role="tab"><i class="bi bi-camera me-1"></i>Camera</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" id="scannerManualTabBtn" data-bs-toggle="pill" data-bs-target="#scannerManualTab" type="button" role="tab"><i class="bi bi-keyboard me-1"></i>Manual input</button></li>
    </ul>
    <div class="tab-content">
        <div class="tab-pane fade show active" id="scannerCameraTab" role="tabpanel">
            <div id="scannerCameraView" class="pos-scanner-camera"></div>
            <div class="small text-muted mt-2" id="scannerCameraStatus">Point a barcode or QR code at the camera.</div>
            <div class="alert alert-warning d-none mt-2" id="scannerCameraError"></div>
        </div>
        <div class="tab-pane fade" id="scannerManualTab" role="tabpanel">
            <label class="form-label small text-muted">Search by</label>
            <select class="form-select pos-select mb-2" id="scannerSearchBy">
                <option value="">Any (barcode, number, or name)</option>
                <option value="barcode">Barcode</option>
                <option value="code">Product number</option>
                <option value="name">Product name</option>
            </select>
            <div class="input-group mb-3">
                <input type="text" class="form-control" id="scannerManualInput" placeholder="Type or paste a code, number, or name" autocomplete="off">
                <button class="btn pos-btn-primary" type="button" id="btnScannerManualSearch"><i class="bi bi-search"></i></button>
            </div>
            <div id="scannerManualResults"></div>
        </div>
    </div>
</div></div></div></div>

<div class="modal fade" id="heldSalesModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Held sales</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="list-group" id="heldSalesList"><div class="text-center text-muted py-4">No held sales.</div></div></div></div></div></div>
<div class="modal fade" id="paymentCompleteModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content pos-payment-complete"><div class="modal-body text-center py-4">
    <div class="pos-payment-complete-check"><i class="bi bi-check-lg"></i></div>
    <h4 class="mt-3 mb-1">Payment Complete</h4>
    <p class="text-muted mb-3">Sale <strong id="paymentCompleteInvoice">—</strong> was saved successfully.</p>
    <div class="pos-payment-complete-figures">
        <div><span>Amount paid</span><strong id="paymentCompleteAmountPaid">₱0.00</strong></div>
        <div class="pos-payment-complete-change"><span>Change</span><strong id="paymentCompleteChange">₱0.00</strong></div>
    </div>
    <div class="pos-payment-complete-progress mt-4"><div class="pos-payment-complete-progress-bar" id="paymentCompleteProgressBar"></div></div>
    <div class="small text-muted mt-2" id="paymentCompleteCountdownText">Continuing in 20s…</div>
    <button type="button" class="pos-v2-complete w-100 mt-4" id="btnPaymentCompleteDone">Done</button>
</div></div></div></div>

<div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Sale complete</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="pos-receipt" id="receiptContent"></div></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" id="btnNewSale" data-bs-dismiss="modal">New sale</button><button class="btn pos-btn-primary" type="button" id="btnPrintReceipt"><i class="bi bi-printer me-1"></i>Print receipt</button></div></div></div></div>
<template id="posProductTileTpl"><div class="col-6 pos-product-col"><button type="button" class="pos-product-tile w-100 text-start"><div class="pos-product-name"></div><div class="pos-product-price"></div><div class="pos-product-stock"></div></button></div></template>
