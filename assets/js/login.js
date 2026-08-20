/**
 * assets/js/login.js
 * -----------------------------------------------------------------------
 * Handles the AJAX login form submit on views/login.php.
 * Posts to app/controllers/LoginController.php and reacts to the JSON
 * response - no full page reload on failure, redirect on success.
 */
(function ($) {
    'use strict';

    const LOGIN_ENDPOINT = (window.APP_URL || '') + '/app/controllers/LoginController.php';
    const RESET_ENDPOINT = (window.APP_URL || '') + '/app/controllers/PasswordResetController.php';

    function showAlert(type, message) {
        const $alert = $('#loginAlert');
        $alert
            .removeClass('d-none alert-danger alert-success alert-warning')
            .addClass('alert-' + type)
            .text(message);
    }

    function hideAlert() {
        $('#loginAlert').addClass('d-none').text('');
    }

    function clearFieldErrors() {
        $('#usernameError, #passwordError').addClass('d-none').text('');
        $('#username, #password').removeClass('is-invalid');
    }

    function setLoading(isLoading) {
        const $btn = $('#loginBtn');
        $btn.prop('disabled', isLoading);
        $btn.find('.btn-label').text(isLoading ? 'Signing in...' : 'Sign In');
        $('#loginSpinner').toggleClass('d-none', !isLoading);
    }

    function validateClientSide(username, password) {
        let valid = true;
        clearFieldErrors();

        if (!username) {
            $('#username').addClass('is-invalid');
            $('#usernameError').removeClass('d-none').text('Username is required.');
            valid = false;
        }

        if (!password) {
            $('#password').addClass('is-invalid');
            $('#passwordError').removeClass('d-none').text('Password is required.');
            valid = false;
        }

        return valid;
    }

    $(function () {
        // Show/hide password
        $('#togglePassword').on('click', function () {
            const $pwd = $('#password');
            const isHidden = $pwd.attr('type') === 'password';
            $pwd.attr('type', isHidden ? 'text' : 'password');
            $(this).find('i').toggleClass('bi-eye bi-eye-slash');
            $(this).attr('aria-label', isHidden ? 'Hide password' : 'Show password');
        });

        $('#loginForm').on('submit', function (e) {
            e.preventDefault();
            hideAlert();

            const username = $('#username').val().trim();
            const password = $('#password').val();

            if (!validateClientSide(username, password)) {
                return;
            }

            setLoading(true);

            $.ajax({
                url: LOGIN_ENDPOINT,
                method: 'POST',
                dataType: 'json',
                data: $('#loginForm').serialize(),
            })
                .done(function (response) {
                    if (response.success) {
                        showAlert('success', response.message || 'Login successful. Redirecting...');
                        window.location.href = response.redirect || ((window.APP_URL || '') + '/index.php');
                    } else {
                        showAlert('danger', response.message || 'Login failed. Please try again.');
                    }
                })
                .fail(function (jqXHR) {
                    const response = jqXHR.responseJSON;
                    const message = (response && response.message)
                        ? response.message
                        : 'Something went wrong. Please try again in a moment.';
                    showAlert('danger', message);
                })
                .always(function () {
                    setLoading(false);
                });
        });

        $('#forgotPasswordForm').on('submit', function (e) {
            e.preventDefault();
            const email = $('#forgotEmail').val().trim();
            const $alert = $('#forgotPasswordAlert');
            const $btn = $('#forgotPasswordBtn');
            $alert.addClass('d-none').text('');
            if (!/^\S+@\S+\.\S+$/.test(email)) {
                $alert.removeClass('d-none alert-success').addClass('alert-danger').text('Please enter a valid email address.');
                return;
            }
            $btn.prop('disabled', true).find('.btn-label').text('Sending...');
            $btn.find('.spinner-border').removeClass('d-none');
            $.post(RESET_ENDPOINT, { action: 'request', email: email, csrf_token: $('#loginForm input[name="csrf_token"]').val() })
                .done(function (res) { $alert.removeClass('d-none alert-danger').addClass('alert-success').text(res.message); })
                .fail(function (xhr) { $alert.removeClass('d-none alert-success').addClass('alert-danger').text((xhr.responseJSON && xhr.responseJSON.message) || 'Could not send the reset email.'); })
                .always(function () { $btn.prop('disabled', false).find('.btn-label').text('Send reset link'); $btn.find('.spinner-border').addClass('d-none'); });
        });

        // Clear inline errors as the user types
        $('#username, #password').on('input', function () {
            $(this).removeClass('is-invalid');
            $('#' + $(this).attr('id') + 'Error').addClass('d-none');
        });
    });
})(jQuery);
