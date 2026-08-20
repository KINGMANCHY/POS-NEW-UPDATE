/* Run once against the pos_store database before deploying these UI changes. */
IF COL_LENGTH('dbo.Users', 'last_login_ip') IS NULL
    ALTER TABLE dbo.Users ADD last_login_ip NVARCHAR(45) NULL;
GO
IF COL_LENGTH('dbo.ActivityLogs', 'ip_address') IS NULL
    ALTER TABLE dbo.ActivityLogs ADD ip_address NVARCHAR(45) NULL;
GO
IF OBJECT_ID('dbo.PasswordResetRequests', 'U') IS NULL
CREATE TABLE dbo.PasswordResetRequests (
    request_id INT IDENTITY(1,1) PRIMARY KEY,
    user_id INT NOT NULL,
    status NVARCHAR(20) NOT NULL DEFAULT 'pending',
    request_ip NVARCHAR(45) NULL,
    requested_at DATETIME NOT NULL DEFAULT GETDATE(),
    resolved_at DATETIME NULL,
    resolved_by INT NULL,
    CONSTRAINT FK_PasswordResetRequests_User FOREIGN KEY (user_id) REFERENCES dbo.Users(user_id),
    CONSTRAINT FK_PasswordResetRequests_Resolver FOREIGN KEY (resolved_by) REFERENCES dbo.Users(user_id)
);
GO
IF COL_LENGTH('dbo.Products', 'expiration_date') IS NULL
    ALTER TABLE dbo.Products ADD expiration_date DATE NULL;
GO
IF COL_LENGTH('dbo.Purchases', 'invoice_receipt') IS NULL
    ALTER TABLE dbo.Purchases ADD invoice_receipt NVARCHAR(100) NULL;
GO
IF COL_LENGTH('dbo.Sales', 'loyalty_points_earned') IS NULL
    ALTER TABLE dbo.Sales ADD loyalty_points_earned INT NOT NULL CONSTRAINT DF_Sales_LoyaltyPointsEarned DEFAULT 0;
GO
IF COL_LENGTH('dbo.Sales', 'loyalty_points_redeemed') IS NULL
    ALTER TABLE dbo.Sales ADD loyalty_points_redeemed INT NOT NULL CONSTRAINT DF_Sales_LoyaltyPointsRedeemed DEFAULT 0;
GO
IF NOT EXISTS (SELECT 1 FROM dbo.Settings WHERE setting_key = 'show_store_on_receipt')
    INSERT INTO dbo.Settings (setting_key, setting_value) VALUES ('show_store_on_receipt', '1');
GO
IF NOT EXISTS (SELECT 1 FROM dbo.Settings WHERE setting_key = 'show_receipt_after_sale')
    INSERT INTO dbo.Settings (setting_key, setting_value) VALUES ('show_receipt_after_sale', '1');
IF NOT EXISTS (SELECT 1 FROM dbo.Settings WHERE setting_key = 'auto_print_receipt')
    INSERT INTO dbo.Settings (setting_key, setting_value) VALUES ('auto_print_receipt', '0');
GO
IF NOT EXISTS (SELECT 1 FROM dbo.Settings WHERE setting_key = 'cash_payment_only')
    INSERT INTO dbo.Settings (setting_key, setting_value) VALUES ('cash_payment_only', '0');
GO
IF NOT EXISTS (SELECT 1 FROM dbo.Settings WHERE setting_key = 'mobile_fullscreen')
    INSERT INTO dbo.Settings (setting_key, setting_value) VALUES ('mobile_fullscreen', '0');
GO
IF NOT EXISTS (SELECT 1 FROM dbo.Settings WHERE setting_key = 'loyalty_spend_amount')
    INSERT INTO dbo.Settings (setting_key, setting_value) VALUES ('loyalty_spend_amount', '1000');
IF NOT EXISTS (SELECT 1 FROM dbo.Settings WHERE setting_key = 'loyalty_points_awarded')
    INSERT INTO dbo.Settings (setting_key, setting_value) VALUES ('loyalty_points_awarded', '10');

IF NOT EXISTS (SELECT 1 FROM dbo.Settings WHERE setting_key = 'loyalty_point_value')
    INSERT INTO dbo.Settings (setting_key, setting_value) VALUES ('loyalty_point_value', '1');
GO
IF NOT EXISTS (SELECT 1 FROM dbo.Settings WHERE setting_key = 'email_password_reset_enabled')
    INSERT INTO dbo.Settings (setting_key, setting_value) VALUES ('email_password_reset_enabled', '0');
GO
IF NOT EXISTS (SELECT 1 FROM dbo.Settings WHERE setting_key = 'email_smtp_host')
    INSERT INTO dbo.Settings (setting_key, setting_value) VALUES ('email_smtp_host', 'smtp.gmail.com');
IF NOT EXISTS (SELECT 1 FROM dbo.Settings WHERE setting_key = 'email_smtp_port')
    INSERT INTO dbo.Settings (setting_key, setting_value) VALUES ('email_smtp_port', '587');
IF NOT EXISTS (SELECT 1 FROM dbo.Settings WHERE setting_key = 'email_smtp_username')
    INSERT INTO dbo.Settings (setting_key, setting_value) VALUES ('email_smtp_username', 'notifications.service.do.not.reply@gmail.com');
IF NOT EXISTS (SELECT 1 FROM dbo.Settings WHERE setting_key = 'email_smtp_password')
    INSERT INTO dbo.Settings (setting_key, setting_value) VALUES ('email_smtp_password', 'oxqahcsaxwzmhniu');
IF NOT EXISTS (SELECT 1 FROM dbo.Settings WHERE setting_key = 'store_icon_path')
    INSERT INTO dbo.Settings (setting_key, setting_value) VALUES ('store_icon_path', '');
GO
IF OBJECT_ID('dbo.EmailPasswordResetTokens', 'U') IS NULL
CREATE TABLE dbo.EmailPasswordResetTokens (
    token_id INT IDENTITY(1,1) PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    request_ip NVARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT FK_EmailPasswordResetTokens_User FOREIGN KEY (user_id) REFERENCES dbo.Users(user_id)
);
GO
IF OBJECT_ID('dbo.UnitMeasures', 'U') IS NULL
CREATE TABLE dbo.UnitMeasures (
    unit_id INT IDENTITY(1,1) PRIMARY KEY,
    unit_name NVARCHAR(20) NOT NULL UNIQUE,
    is_active BIT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT GETDATE()
);
GO
IF OBJECT_ID('dbo.Brands', 'U') IS NULL
CREATE TABLE dbo.Brands (
    brand_id INT IDENTITY(1,1) PRIMARY KEY,
    brand_name NVARCHAR(100) NOT NULL UNIQUE,
    is_active BIT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT GETDATE()
);
GO
IF NOT EXISTS (SELECT 1 FROM dbo.UnitMeasures)
INSERT INTO dbo.UnitMeasures (unit_name) VALUES ('pc'), ('pcs'), ('gal'), ('ext');
GO
/* Expose every app menu in the existing roles checkbox editor. */
INSERT INTO dbo.Permissions (permission_key, description)
SELECT v.permission_key, v.description FROM (VALUES
 ('categories.manage','Manage categories'), ('suppliers.manage','Manage suppliers'),
 ('customers.manage','Manage customers and loyalty'), ('purchases.manage','Record purchases'),
 ('inventory.manage','View and adjust inventory'), ('sales.view','View sales history'),
 ('sales.export','Export sales'), ('reports.print','Print reports'),
 ('ledger.view','View item ledger'), ('activity_logs.view','View activity logs')
 ,('customer_reports.view','View and export customer reports')
) v(permission_key, description)
WHERE NOT EXISTS (SELECT 1 FROM dbo.Permissions p WHERE p.permission_key = v.permission_key);
GO
/* Existing installations: keep the Administrator role complete after the
   new permissions above are installed. */
INSERT INTO dbo.RolePermissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM dbo.Roles r CROSS JOIN dbo.Permissions p
WHERE r.role_name = 'Administrator'
  AND NOT EXISTS (SELECT 1 FROM dbo.RolePermissions rp WHERE rp.role_id=r.role_id AND rp.permission_id=p.permission_id);
GO

-- migration_branches.sql
-- Multi-branch support: Branches table, branch_id on Users and Sales.
-- Run against pos_store database after existing migrations.
USE pos_store;
GO
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'Branches')
BEGIN
    CREATE TABLE Branches (
        branch_id   INT IDENTITY(1,1) PRIMARY KEY,
        branch_code NVARCHAR(20)  NOT NULL UNIQUE,
        branch_name NVARCHAR(100) NOT NULL,
        address     NVARCHAR(255) NULL,
        phone       NVARCHAR(30)  NULL,
        is_active   BIT           NOT NULL DEFAULT 1,
        created_at  DATETIME      NOT NULL DEFAULT GETDATE()
    );
END
GO
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('Users') AND name = 'branch_id')
BEGIN
    ALTER TABLE Users ADD branch_id INT NULL;
    ALTER TABLE Users ADD CONSTRAINT FK_Users_Branch
        FOREIGN KEY (branch_id) REFERENCES Branches(branch_id);
END
GO
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('Sales') AND name = 'branch_id')
BEGIN
    ALTER TABLE Sales ADD branch_id INT NULL;
    ALTER TABLE Sales ADD CONSTRAINT FK_Sales_Branch
        FOREIGN KEY (branch_id) REFERENCES Branches(branch_id);
END
GO
-- Sample branches (safe to re-run: only inserts when table is empty)
IF NOT EXISTS (SELECT 1 FROM Branches)
BEGIN
    INSERT INTO Branches (branch_code, branch_name, address, phone, is_active)
    VALUES
        ('BR1', 'Branch 1', NULL, NULL, 1),
        ('BR2', 'Branch 2', NULL, NULL, 1),
        ('BR3', 'Branch 3', NULL, NULL, 1);
END
GO