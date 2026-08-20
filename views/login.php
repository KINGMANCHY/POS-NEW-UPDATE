<?php
/**
 * views/login.php
 * -----------------------------------------------------------------------
 * Login screen. Submits via AJAX (assets/js/login.js) to
 * app/controllers/LoginController.php - no full page POST/reload.
 */
if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
</head>
<body class="pos-login-body">

    <div class="pos-login-wrap">

        <!-- Left panel: brand / signature visual -->
        <div class="pos-login-aside">
            <div class="pos-login-aside-inner">
                <div class="pos-brand pos-brand-lg">
                    <span class="pos-brand-mark">POS</span><span class="pos-brand-rest">STORE</span>
                </div>
                <p class="pos-tagline">Point of sale, inventory, and reporting<br>for stores that never stop moving.</p>

                <div class="pos-receipt" aria-hidden="true">
                    <div class="pos-receipt-line pos-receipt-head">
                        <span>ITEM</span><span>QTY</span><span>TOTAL</span>
                    </div>
                    <div class="pos-receipt-line"><span>Espresso Blend 250g</span><span>02</span><span>₱ 398.00</span></div>
                    <div class="pos-receipt-line"><span>Steel Tumbler 500ml</span><span>01</span><span>₱ 549.00</span></div>
                    <div class="pos-receipt-line"><span>Notebook A5 Ruled</span><span>03</span><span>₱ 267.00</span></div>
                    <div class="pos-receipt-divider"></div>
                    <div class="pos-receipt-line pos-receipt-total"><span>TOTAL</span><span></span><span>₱ 1,214.00</span></div>
                    <div class="pos-receipt-barcode">
                        <span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>
                    </div>
                    <div class="pos-receipt-code">SKU-<?= date('Ymd') ?>-0417</div>
                </div>

                <ul class="pos-feature-list">
                    <li><i class="bi bi-shield-check"></i> Encrypted sessions &amp; hashed passwords</li>
                    <li><i class="bi bi-lock"></i> CSRF &amp; SQL-injection protected</li>
                    <li><i class="bi bi-phone"></i> Works on desktop, tablet &amp; mobile</li>
                </ul>
            </div>
        </div>

        <!-- Right panel: login form -->
        <div class="pos-login-main">
            <div class="pos-login-card">
                <h1 class="pos-login-title">Sign in to your register</h1>
                <p class="pos-login-subtitle">Enter your credentials to continue.</p>

                <div id="loginAlert" class="alert d-none" role="alert"></div>

                <form id="loginForm" novalidate autocomplete="off">
                    <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= Security::escape($csrfToken) ?>">

                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group pos-input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" id="username" name="username"
                                   placeholder="e.g. jdelacruz" required maxlength="50" autocomplete="username">
                        </div>
                        <div class="invalid-feedback d-block d-none" id="usernameError"></div>
                    </div>

                    <div class="mb-2">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group pos-input-group">
                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                            <input type="password" class="form-control" id="password" name="password"
                                   placeholder="Enter your password" required autocomplete="current-password">
                            <button class="btn pos-toggle-pass" type="button" id="togglePassword" tabindex="-1" aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback d-block d-none" id="passwordError"></div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="rememberMe" name="remember_me" value="1">
                            <label class="form-check-label small" for="rememberMe">Remember me for 30 days</label>
                        </div>
                        <a href="#forgotPasswordModal" class="small pos-link" data-bs-toggle="modal">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn pos-btn-primary w-100" id="loginBtn">
                        <span class="btn-label">Sign In</span>
                        <span class="spinner-border spinner-border-sm d-none" id="loginSpinner" role="status" aria-hidden="true"></span>
                    </button>
                </form>

                <div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-hidden="true" aria-labelledby="forgotPasswordTitle">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form id="forgotPasswordForm" novalidate>
                                <div class="modal-header"><h2 class="modal-title fs-5" id="forgotPasswordTitle">Reset your password</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                                <div class="modal-body">
                                    <p class="small text-muted">Enter the email address linked to your account. We’ll send a one-time reset link if it exists.</p>
                                    <div id="forgotPasswordAlert" class="alert d-none" role="alert"></div>
                                    <label for="forgotEmail" class="form-label">Email address</label>
                                    <input type="email" class="form-control" id="forgotEmail" autocomplete="email" maxlength="150" required>
                                </div>
                                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn pos-btn-primary" id="forgotPasswordBtn"><span class="btn-label">Send reset link</span><span class="spinner-border spinner-border-sm d-none"></span></button></div>
                            </form>
                        </div>
                    </div>
                </div>

                <p class="pos-login-footnote">&copy; <?= date('Y') ?> <?= Security::escape(APP_NAME) ?> · v<?= Security::escape(APP_VERSION) ?></p>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
    <script src="<?= Helper::versionedAsset('/js/login.js') ?>"></script>
</body>
</html>
