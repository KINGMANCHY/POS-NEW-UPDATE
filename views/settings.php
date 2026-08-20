<?php if (!defined('POS_APP')) die('Direct access not permitted.'); ?>
<div class="mb-4"><h1 class="h4 mb-0">Settings</h1><p class="text-muted mb-0">Manage your store, POS, loyalty, and recovery preferences.</p></div>
<form id="settingsForm" style="max-width:760px;">
<div class="alert alert-success d-none" id="settingsSuccessAlert">Settings saved.</div><div class="alert alert-danger d-none" id="settingsErrorAlert"></div>
<section class="card pos-card border-0 shadow-sm mb-4"><div class="card-body"><h2 class="h6 mb-3">Store Details</h2><div class="mb-3"><label class="form-label small text-muted">Store Name</label><input class="form-control" id="storeName" maxlength="150" required></div><div class="mb-3"><label class="form-label small text-muted">Store Address</label><textarea class="form-control" id="storeAddress" rows="2" maxlength="255"></textarea></div><div class="row g-3"><div class="col-sm-6"><label class="form-label small text-muted">Store Phone</label><input class="form-control" id="storePhone" maxlength="30"></div><div class="col-sm-6"><label class="form-label small text-muted">Currency Symbol</label><input class="form-control" id="currencySymbol" maxlength="5"></div></div></div></section>
<section class="card pos-card border-0 shadow-sm mb-4"><div class="card-body"><h2 class="h6 mb-3">POS Receipt &amp; Payments</h2><div class="mb-3"><label class="form-label small text-muted">Receipt Footer</label><textarea class="form-control" id="receiptFooter" rows="2" maxlength="255" placeholder="e.g. Thank you for shopping with us!"></textarea></div><div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="taxInclusive"><label class="form-check-label" for="taxInclusive">Prices are tax-inclusive</label></div><div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="showStoreOnReceipt"><label class="form-check-label" for="showStoreOnReceipt">Show store details on receipt / invoice</label></div><div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="showReceiptAfterSale"><label class="form-check-label" for="showReceiptAfterSale">Show receipt after completing a POS sale</label></div><div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="autoPrintReceipt"><label class="form-check-label" for="autoPrintReceipt">Automatically open print for POS receipts</label><div class="form-text">Uses the browser's selected default printer.</div></div><div class="form-check"><input class="form-check-input" type="checkbox" id="cashPaymentOnly"><label class="form-check-label" for="cashPaymentOnly">Cash payments only</label><div class="form-text">Turn off for Cash, Card, Check, GCash and Maya split payments.</div></div></div></section>
<section class="card pos-card border-0 shadow-sm mb-4"><div class="card-body">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <h2 class="h6 mb-0">Receipt Templates</h2>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnPreviewReceipt"><i class="bi bi-eye me-1"></i>Preview receipt</button>
    </div>
    <label class="form-label small text-muted d-block mb-2">Template</label>
    <div class="row g-2 mb-4" id="receiptTemplateOptions">
        <div class="col-6 col-sm-4">
            <input type="radio" class="btn-check" name="receiptTemplate" id="receiptTemplateClassic" value="classic" autocomplete="off">
            <label class="btn btn-outline-secondary w-100 text-start" for="receiptTemplateClassic">
                <span class="d-block fw-semibold">Classic</span>
                <span class="d-block small text-muted">Plain monospace layout</span>
            </label>
        </div>
        <div class="col-6 col-sm-4">
            <input type="radio" class="btn-check" name="receiptTemplate" id="receiptTemplateModern" value="modern" autocomplete="off">
            <label class="btn btn-outline-secondary w-100 text-start" for="receiptTemplateModern">
                <span class="d-block fw-semibold">Modern</span>
                <span class="d-block small text-muted">Bold totals &amp; header</span>
            </label>
        </div>
    </div>
    <label class="form-label small text-muted d-block mb-2">Printer width</label>
    <div class="row g-2">
        <div class="col-6 col-sm-4">
            <input type="radio" class="btn-check" name="printerWidth" id="printerWidth58" value="58" autocomplete="off">
            <label class="btn btn-outline-secondary w-100" for="printerWidth58">58mm</label>
        </div>
        <div class="col-6 col-sm-4">
            <input type="radio" class="btn-check" name="printerWidth" id="printerWidth80" value="80" autocomplete="off">
            <label class="btn btn-outline-secondary w-100" for="printerWidth80">80mm</label>
        </div>
    </div>
    <div class="form-text mt-2">This template and width are used for every printed and previewed receipt across the POS.</div>
</div></section>
<div class="modal fade" id="receiptPreviewModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Receipt preview</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="pos-receipt" id="receiptPreviewContent"></div></div><div class="modal-footer"><span class="text-muted small me-auto">Sample data - not a real sale.</span><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Close</button></div></div></div></div>
<section class="card pos-card border-0 shadow-sm mb-4"><div class="card-body"><h2 class="h6 mb-3">Mobile Display</h2><div class="form-check mb-4"><input class="form-check-input" type="checkbox" id="mobileFullscreen"><label class="form-check-label" for="mobileFullscreen">Use full screen on mobile devices</label><div class="form-text">The first tap after reopening enters full screen in supported browsers.</div></div><label class="form-label small text-muted">Install POS</label><div><button class="btn btn-outline-primary" type="button" id="installPosBtn"><i class="bi bi-phone me-1"></i> Install POS on this device</button></div><div class="form-text" id="installPosHelp">Open this page on the phone you want to use, then tap Install.</div><div class="alert alert-info d-none mt-2 mb-0" id="installPosAlert" role="status"></div></div></section>
<section class="card pos-card border-0 shadow-sm mb-4"><div class="card-body"><h2 class="h6 mb-2">Loyalty Points</h2><p class="small text-muted">Set either earning value to 0 to disable earning. Set redemption value to 0 to disable redemption.</p><div class="row g-3"><div class="col-sm-4"><label class="form-label small text-muted" for="loyaltySpendAmount">Amount spent</label><input type="number" class="form-control" id="loyaltySpendAmount" min="0" step="0.01" value="1000"></div><div class="col-sm-4"><label class="form-label small text-muted" for="loyaltyPointsAwarded">Points awarded</label><input type="number" class="form-control" id="loyaltyPointsAwarded" min="0" step="1" value="10"></div><div class="col-sm-4"><label class="form-label small text-muted" for="loyaltyPointValue">Value per point (₱)</label><input type="number" class="form-control" id="loyaltyPointValue" min="0" step="0.01" value="1"></div></div></div></section>
<section class="card pos-card border-0 shadow-sm mb-4"><div class="card-body"><h2 class="h6 mb-3">Password Recovery Email</h2><div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="emailPasswordResetEnabled"><label class="form-check-label" for="emailPasswordResetEnabled">Allow users to reset their password by email</label></div><p class="form-text mb-4">The password stays on the server and is never shown here.</p><div class="row g-3 mb-3"><div class="col-sm-8"><label class="form-label small text-muted" for="emailSmtpHost">SMTP host</label><input class="form-control" id="emailSmtpHost" maxlength="255" placeholder="smtp.gmail.com"></div><div class="col-sm-4"><label class="form-label small text-muted" for="emailSmtpPort">SMTP port</label><input type="number" class="form-control" id="emailSmtpPort" min="1" max="65535" placeholder="587"></div></div><div class="mb-3"><label class="form-label small text-muted" for="emailSmtpUsername">SMTP email address</label><input type="email" class="form-control" id="emailSmtpUsername" maxlength="150" placeholder="notifications@example.com"></div><div><label class="form-label small text-muted" for="emailSmtpPassword">SMTP app password</label><input type="password" class="form-control" id="emailSmtpPassword" maxlength="255" autocomplete="new-password" placeholder="Leave blank to keep the saved password"><div class="form-text" id="emailSmtpPasswordStatus"></div></div></div></section>
<section class="card pos-card border-0 shadow-sm mb-4"><div class="card-body"><h2 class="h6 mb-2">Store Icon</h2><p class="small text-muted">Upload a `.ico` icon (maximum 1 MB) for the browser and new POS installations.</p><label class="form-label small text-muted" for="storeIcon">Icon file</label><input class="form-control" type="file" id="storeIcon" accept=".ico,image/x-icon,image/vnd.microsoft.icon"></div></section>
<div class="pb-4"><button class="btn pos-btn-primary" type="submit" id="settingsSaveBtn"><span class="spinner-border spinner-border-sm d-none me-1"></span> Save Settings</button></div>
</form>
