/**
 * assets/js/pos-scanner.js
 * -----------------------------------------------------------------------
 * Drives the "Scan barcode / QR code" modal on the POS Screen. Two tabs:
 *   - Camera: live-decodes barcodes/QR codes via the html5-qrcode library
 *     and feeds the result into the same lookup pipeline as the keyboard
 *     scanner input (assets/js/pos.js -> window.POSCart.lookupCode).
 *   - Manual input: a text field + "Search by" selector (Barcode /
 *     Product number / Product name) for devices without a camera, or
 *     when a code doesn't scan cleanly.
 *
 * Depends on window.POSCart (exposed at the bottom of pos.js) and the
 * html5-qrcode library (loaded from CDN, see pos.php).
 */
(function ($) {
    'use strict';

    const ENDPOINT = (window.APP_URL || '') + '/app/controllers/PosController.php';
    let html5QrCode = null;
    let cameraRunning = false;

    function escapeHtml(str) { return $('<div>').text(str == null ? '' : str).html(); }
    function money(n) { return '₱' + Number(n || 0).toFixed(2); }

    function stopCamera() {
        if (!cameraRunning || !html5QrCode) return;
        cameraRunning = false;
        html5QrCode.stop().catch(function () { /* already stopped - fine */ });
    }

    function startCamera() {
        if (typeof Html5Qrcode === 'undefined') {
            $('#scannerCameraError').removeClass('d-none').text('The camera scanner library could not be loaded. Check your connection, or use Manual input instead.');
            return;
        }
        $('#scannerCameraError').addClass('d-none');
        if (!html5QrCode) html5QrCode = new Html5Qrcode('scannerCameraView');

        html5QrCode.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 240, height: 240 } },
            function onScanSuccess(decodedText) {
                $('#scannerCameraStatus').text('Scanned: ' + decodedText);
                window.POSCart.lookupCode(decodedText, {
                    onDone: function (product) {
                        $('#scannerCameraStatus').text('Added "' + (product ? product.product_name : decodedText) + '" to the cart. Scan the next item, or close this window.');
                    },
                    onMiss: function (message) {
                        $('#scannerCameraStatus').text(message || ('No product matched "' + decodedText + '".'));
                    },
                });
            },
            function onScanFailure() { /* fired continuously while nothing is in frame - not an error */ }
        ).then(function () {
            cameraRunning = true;
        }).catch(function (error) {
            cameraRunning = false;
            $('#scannerCameraError').removeClass('d-none').text(
                'Could not access the camera. Grant camera permission for this site, or use Manual input instead. (' + error + ')'
            );
        });
    }

    function renderManualResults(products) {
        const $wrap = $('#scannerManualResults').empty();
        if (!products || !products.length) {
            $wrap.html('<div class="text-center text-muted small py-3">No matching products.</div>');
            return;
        }
        products.forEach(function (p) {
            const outOfStock = Number(p.quantity_on_hand) <= 0;
            $wrap.append(`
                <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center pos-scanner-result" data-id="${p.product_id}" ${outOfStock ? 'disabled' : ''}>
                    <span>
                        <span class="d-block fw-medium">${escapeHtml(p.product_name)}</span>
                        <span class="d-block small text-muted">${escapeHtml(p.product_code || '')}${p.barcode ? ' · ' + escapeHtml(p.barcode) : ''}</span>
                    </span>
                    <span class="text-end">
                        <span class="d-block fw-medium">${money(p.selling_price)}</span>
                        <span class="d-block small ${outOfStock ? 'text-danger' : 'text-muted'}">${outOfStock ? 'Out of stock' : p.quantity_on_hand + ' ' + p.unit + ' left'}</span>
                    </span>
                </button>
            `);
        });
        $wrap.addClass('list-group');
    }

    function manualSearch() {
        const code = $('#scannerManualInput').val().trim();
        const searchBy = $('#scannerSearchBy').val();
        if (!code) { renderManualResults([]); return; }

        $.get(ENDPOINT, { action: 'products', search: code, search_by: searchBy })
            .done(function (res) {
                if (!res.success) { renderManualResults([]); return; }
                if (res.products.length === 1) {
                    window.POSCart.addToCart(res.products[0]);
                    $('#scannerManualInput').val('').trigger('focus');
                    renderManualResults([]);
                    return;
                }
                renderManualResults(res.products);
            });
    }

    $(function () {
        if (!$('#scannerModal').length) return; // scanner only exists on the POS Screen

        const modalEl = document.getElementById('scannerModal');
        $(modalEl).on('shown.bs.modal', function () {
            if ($('#scannerCameraTab').hasClass('active')) startCamera();
        });
        $(modalEl).on('hidden.bs.modal', function () {
            stopCamera();
            $('#scannerManualInput').val('');
            renderManualResults([]);
            $('#scannerCameraStatus').text('Point a barcode or QR code at the camera.');
        });

        $('#scannerCameraTabBtn').on('shown.bs.tab', startCamera);
        $('#scannerManualTabBtn').on('shown.bs.tab', function () {
            stopCamera();
            $('#scannerManualInput').trigger('focus');
        });

        $('#btnScannerManualSearch').on('click', manualSearch);
        $('#scannerManualInput').on('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); manualSearch(); }
        });
        $('#scannerManualResults').on('click', '.pos-scanner-result:not([disabled])', function () {
            const id = Number($(this).data('id'));
            // Manual results come from the same /products endpoint as the
            // catalog grid, so re-searching by the visible code/name and
            // reusing addToCart keeps this in sync with stock/pricing.
            $.get(ENDPOINT, { action: 'products', search: $('#scannerManualInput').val().trim(), search_by: $('#scannerSearchBy').val() }).done(function (res) {
                const product = res.success ? res.products.find(function (p) { return Number(p.product_id) === id; }) : null;
                if (product) {
                    window.POSCart.addToCart(product);
                    $('#scannerManualInput').val('').trigger('focus');
                    renderManualResults([]);
                }
            });
        });
    });
})(jQuery);
