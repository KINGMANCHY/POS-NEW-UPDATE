/**
 * assets/js/settings.js
 * -----------------------------------------------------------------------
 * Drives views/settings.php via app/controllers/SettingController.php.
 */
(function ($) {
    'use strict';

    const ENDPOINT = (window.APP_URL || '') + '/app/controllers/SettingController.php';

    function isAppleMobile() {
        return /iPhone|iPad|iPod/.test(navigator.userAgent)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    }

    function setInstallAvailability() {
        const available = !!(window.POS_INSTALL && window.POS_INSTALL.deferredPrompt);
        $('#installPosBtn').prop('disabled', false);
        if (available) {
            $('#installPosHelp').text('Ready to install. Tap the button to open your browser\'s install prompt.');
        }
    }

    function showInstallMessage(message) {
        $('#installPosAlert').removeClass('d-none').text(message);
    }

    function installPos() {
        const installState = window.POS_INSTALL || {};
        const prompt = installState.deferredPrompt;
        if (prompt) {
            prompt.prompt();
            prompt.userChoice.then(function (choice) {
                installState.deferredPrompt = null;
                if (choice.outcome === 'accepted') {
                    showInstallMessage('Installation accepted. POS STORE will appear on your home screen.');
                } else {
                    showInstallMessage('Installation was cancelled. You can tap Install again whenever you are ready.');
                }
                setInstallAvailability();
            });
            return;
        }
        if (isAppleMobile()) {
            showInstallMessage('On iPhone or iPad: tap Safari\'s Share button, then choose “Add to Home Screen” and tap Add. Apple does not allow a website to open that dialog automatically.');
            return;
        }
        if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) {
            showInstallMessage('POS STORE is already installed on this device.');
            return;
        }
        showInstallMessage('Your browser has not made POS STORE installable yet. Use Chrome or Edge over HTTPS, open the POS once, then try again.');
    }

    function populateForm(settings) {
        $('#storeName').val(settings.store_name || '');
        $('#storeAddress').val(settings.store_address || '');
        $('#storePhone').val(settings.store_phone || '');
        $('#currencySymbol').val(settings.currency_symbol || '₱');
        $('#receiptFooter').val(settings.receipt_footer || '');
        $('#taxInclusive').prop('checked', settings.tax_inclusive === '1');
        $('#showStoreOnReceipt').prop('checked', settings.show_store_on_receipt !== '0');
        $('#showReceiptAfterSale').prop('checked', settings.show_receipt_after_sale !== '0');
        $('#autoPrintReceipt').prop('checked', settings.auto_print_receipt === '1');
        $('input[name="receiptTemplate"][value="' + (settings.receipt_template === 'modern' ? 'modern' : 'classic') + '"]').prop('checked', true);
        $('input[name="printerWidth"][value="' + (settings.printer_width === '58' ? '58' : '80') + '"]').prop('checked', true);
        $('#cashPaymentOnly').prop('checked', settings.cash_payment_only === '1');
        $('#mobileFullscreen').prop('checked', settings.mobile_fullscreen === '1');
        $('#loyaltySpendAmount').val(settings.loyalty_spend_amount || 1000);
        $('#loyaltyPointsAwarded').val(settings.loyalty_points_awarded || 10);
        $('#loyaltyPointValue').val(settings.loyalty_point_value === '' || settings.loyalty_point_value == null ? 1 : settings.loyalty_point_value);
        $('#emailPasswordResetEnabled').prop('checked', settings.email_password_reset_enabled === '1');
        $('#emailSmtpHost').val(settings.email_smtp_host || 'smtp.gmail.com');
        $('#emailSmtpPort').val(settings.email_smtp_port || '587');
        $('#emailSmtpUsername').val(settings.email_smtp_username || '');
        $('#emailSmtpPassword').val('');
        $('#emailSmtpPasswordStatus').text(settings.email_smtp_password_set === '1' ? 'An SMTP password is saved. Leave this blank to keep it.' : 'No SMTP password has been saved yet.');
    }

    function loadSettings() {
        $.get(ENDPOINT, { action: 'get' }).done(function (res) {
            if (res.success) populateForm(res.settings);
        });
    }

    function showAlert($el, message) {
        $('#settingsSuccessAlert, #settingsErrorAlert').addClass('d-none');
        if (message) { $el.removeClass('d-none').text(message); }
        else { $el.removeClass('d-none'); }
    }

    function saveSettings() {
        const $btn = $('#settingsSaveBtn');
        $btn.prop('disabled', true).find('.spinner-border').removeClass('d-none');

        const data = new FormData();
        const values = {
            action: 'save', store_name: $('#storeName').val(), store_address: $('#storeAddress').val(), store_phone: $('#storePhone').val(), currency_symbol: $('#currencySymbol').val(), receipt_footer: $('#receiptFooter').val(),
            tax_inclusive: $('#taxInclusive').is(':checked') ? 1 : 0, show_store_on_receipt: $('#showStoreOnReceipt').is(':checked') ? 1 : 0, show_receipt_after_sale: $('#showReceiptAfterSale').is(':checked') ? 1 : 0, auto_print_receipt: $('#autoPrintReceipt').is(':checked') ? 1 : 0,
            receipt_template: $('input[name="receiptTemplate"]:checked').val() || 'classic', printer_width: $('input[name="printerWidth"]:checked').val() || '80',
            cash_payment_only: $('#cashPaymentOnly').is(':checked') ? 1 : 0, mobile_fullscreen: $('#mobileFullscreen').is(':checked') ? 1 : 0,
            loyalty_spend_amount: $('#loyaltySpendAmount').val(), loyalty_points_awarded: $('#loyaltyPointsAwarded').val(), loyalty_point_value: $('#loyaltyPointValue').val(), email_password_reset_enabled: $('#emailPasswordResetEnabled').is(':checked') ? 1 : 0,
            email_smtp_host: $('#emailSmtpHost').val(), email_smtp_port: $('#emailSmtpPort').val(), email_smtp_username: $('#emailSmtpUsername').val(), email_smtp_password: $('#emailSmtpPassword').val(),
        };
        Object.keys(values).forEach(function (key) { data.append(key, values[key]); });
        const icon = $('#storeIcon')[0].files[0];
        if (icon) data.append('store_icon', icon);
        $.ajax({ url: ENDPOINT, method: 'POST', data: data, processData: false, contentType: false })
            .done(function (res) {
                if (res.success) { showAlert($('#settingsSuccessAlert')); }
                else { showAlert($('#settingsErrorAlert'), res.message || 'Could not save settings.'); }
            })
            .fail(function (xhr) {
                showAlert($('#settingsErrorAlert'), (xhr.responseJSON && xhr.responseJSON.message) || 'Could not save settings.');
            })
            .always(function () {
                $btn.prop('disabled', false).find('.spinner-border').addClass('d-none');
            });
    }

    $(function () {
        loadSettings();
        $('#settingsForm').on('submit', function (e) { e.preventDefault(); saveSettings(); });
        setInstallAvailability();
        $('#installPosBtn').on('click', installPos);
        window.addEventListener('posinstallavailable', setInstallAvailability);
        window.addEventListener('posinstalled', function () {
            $('#installPosBtn').prop('disabled', true);
            $('#installPosHelp').text('POS STORE is installed on this device.');
            showInstallMessage('POS STORE was installed successfully.');
        });

        $('#btnPreviewReceipt').on('click', function () {
            const settings = {
                receipt_template: $('input[name="receiptTemplate"]:checked').val() || 'classic',
                printer_width: $('input[name="printerWidth"]:checked').val() || '80',
                show_store_on_receipt: $('#showStoreOnReceipt').is(':checked') ? '1' : '0',
                store_name: $('#storeName').val(),
                store_address: $('#storeAddress').val(),
                store_phone: $('#storePhone').val(),
                receipt_footer: $('#receiptFooter').val(),
            };
            window.POSReceipt.render($('#receiptPreviewContent'), window.POSReceipt.sampleSale(), settings);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('receiptPreviewModal')).show();
        });
    });
})(jQuery);
