/**
 * assets/js/profile.js
 * -----------------------------------------------------------------------
 * Drives views/profile.php via app/controllers/ProfileController.php.
 */
(function ($) {
    'use strict';

    const ENDPOINT = (window.APP_URL || '') + '/app/controllers/ProfileController.php';

    function loadProfile() {
        $.get(ENDPOINT, { action: 'get' }).done(function (res) {
            if (!res.success) return;
            const u = res.user;
            $('#profileUsername').val(u.username);
            $('#profileRole').val(u.role_name);
            $('#profileFullName').val(u.full_name);
            $('#profileEmail').val(u.email || '');
        });
    }

    function saveProfile() {
        const $btn = $('#profileSaveBtn');
        $('#profileSuccessAlert, #profileFormAlert').addClass('d-none');
        $btn.prop('disabled', true).find('.spinner-border').removeClass('d-none');

        $.post(ENDPOINT, {
            action: 'update',
            full_name: $('#profileFullName').val(),
            email: $('#profileEmail').val(),
        })
            .done(function (res) {
                if (res.success) { $('#profileSuccessAlert').removeClass('d-none'); }
                else { $('#profileFormAlert').removeClass('d-none').text(res.message || 'Could not save your profile.'); }
            })
            .fail(function (xhr) {
                $('#profileFormAlert').removeClass('d-none').text((xhr.responseJSON && xhr.responseJSON.message) || 'Could not save your profile.');
            })
            .always(function () {
                $btn.prop('disabled', false).find('.spinner-border').addClass('d-none');
            });
    }

    function changePassword() {
        const newPass = $('#newPassword').val();
        const confirm = $('#confirmPassword').val();
        $('#passwordSuccessAlert, #passwordFormAlert').addClass('d-none');

        if (newPass !== confirm) {
            $('#passwordFormAlert').removeClass('d-none').text('New password and confirmation do not match.');
            return;
        }

        const $btn = $('#passwordSaveBtn');
        $btn.prop('disabled', true).find('.spinner-border').removeClass('d-none');

        $.post(ENDPOINT, {
            action: 'change_password',
            current_password: $('#currentPassword').val(),
            new_password: newPass,
        })
            .done(function (res) {
                if (res.success) {
                    $('#passwordSuccessAlert').removeClass('d-none');
                    $('#passwordForm')[0].reset();
                } else {
                    $('#passwordFormAlert').removeClass('d-none').text(res.message || 'Could not change your password.');
                }
            })
            .fail(function (xhr) {
                $('#passwordFormAlert').removeClass('d-none').text((xhr.responseJSON && xhr.responseJSON.message) || 'Could not change your password.');
            })
            .always(function () {
                $btn.prop('disabled', false).find('.spinner-border').addClass('d-none');
            });
    }

    $(function () {
        loadProfile();
        $('#profileForm').on('submit', function (e) { e.preventDefault(); saveProfile(); });
        $('#passwordForm').on('submit', function (e) { e.preventDefault(); changePassword(); });
    });
})(jQuery);
