<?php
if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}
?>
<div class="mb-4">
    <h1 class="h4 mb-0">My Profile</h1>
    <p class="text-muted mb-0">Your account details and password.</p>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card pos-card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h6 mb-3">Account Details</h2>
                <div class="alert alert-success d-none" id="profileSuccessAlert">Profile updated.</div>
                <div class="alert alert-danger d-none" id="profileFormAlert"></div>

                <form id="profileForm">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small text-muted">Username</label>
                            <input type="text" class="form-control" id="profileUsername" disabled>
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted">Role</label>
                            <input type="text" class="form-control" id="profileRole" disabled>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">Full Name</label>
                        <input type="text" class="form-control" id="profileFullName" maxlength="150" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">Email</label>
                        <input type="email" class="form-control" id="profileEmail" maxlength="150">
                    </div>
                    <button class="btn pos-btn-primary" type="submit" id="profileSaveBtn">
                        <span class="spinner-border spinner-border-sm d-none me-1"></span>Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card pos-card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h6 mb-3">Change Password</h2>
                <div class="alert alert-success d-none" id="passwordSuccessAlert">Password changed.</div>
                <div class="alert alert-danger d-none" id="passwordFormAlert"></div>

                <form id="passwordForm">
                    <div class="mb-3">
                        <label class="form-label small text-muted">Current Password</label>
                        <input type="password" class="form-control" id="currentPassword" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">New Password</label>
                        <input type="password" class="form-control" id="newPassword" minlength="8" required placeholder="At least 8 characters">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirmPassword" minlength="8" required>
                    </div>
                    <button class="btn pos-btn-primary" type="submit" id="passwordSaveBtn">
                        <span class="spinner-border spinner-border-sm d-none me-1"></span>Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
