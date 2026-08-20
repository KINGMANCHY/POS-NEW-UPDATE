/* =========================================================================
   POS STORE v1.0 - SQL Server Database Schema
   -------------------------------------------------------------------------
   Target: SQL Server 2014+
   Run this script against a fresh database (or it will create one named
   pos_store). Phase 1 actively uses: Roles, Permissions, RolePermissions,
   Users, UserTokens, LoginLogs, ActivityLogs, Settings.
   The remaining tables (Products, Sales, Inventory, etc.) are included
   now so later phases can build directly on top of a finished schema.
   ========================================================================= */

IF DB_ID('pos_store') IS NULL
BEGIN
    CREATE DATABASE pos_store;
END
GO

USE pos_store;
GO

/* -------------------------------------------------------------------
   Roles & Permissions
   ------------------------------------------------------------------- */

IF OBJECT_ID('dbo.Roles', 'U') IS NULL
CREATE TABLE dbo.Roles (
    role_id      INT IDENTITY(1,1) PRIMARY KEY,
    role_name    NVARCHAR(50)  NOT NULL UNIQUE,
    description  NVARCHAR(255) NULL,
    is_active    BIT           NOT NULL DEFAULT 1,
    created_at   DATETIME      NOT NULL DEFAULT GETDATE()
);
GO

IF OBJECT_ID('dbo.Permissions', 'U') IS NULL
CREATE TABLE dbo.Permissions (
    permission_id   INT IDENTITY(1,1) PRIMARY KEY,
    permission_key  NVARCHAR(100) NOT NULL UNIQUE,   -- e.g. 'products.create'
    description     NVARCHAR(255) NULL
);
GO

IF OBJECT_ID('dbo.RolePermissions', 'U') IS NULL
CREATE TABLE dbo.RolePermissions (
    role_id        INT NOT NULL,
    permission_id  INT NOT NULL,
    CONSTRAINT PK_RolePermissions PRIMARY KEY (role_id, permission_id),
    CONSTRAINT FK_RolePermissions_Role FOREIGN KEY (role_id) REFERENCES dbo.Roles(role_id) ON DELETE CASCADE,
    CONSTRAINT FK_RolePermissions_Permission FOREIGN KEY (permission_id) REFERENCES dbo.Permissions(permission_id) ON DELETE CASCADE
);
GO

/* -------------------------------------------------------------------
   Users (+ auth support tables)
   ------------------------------------------------------------------- */

IF OBJECT_ID('dbo.Users', 'U') IS NULL
CREATE TABLE dbo.Users (
    user_id         INT IDENTITY(1,1) PRIMARY KEY,
    role_id         INT NOT NULL,
    username        NVARCHAR(50)  NOT NULL UNIQUE,
    password_hash   NVARCHAR(255) NOT NULL,
    full_name       NVARCHAR(150) NOT NULL,
    email           NVARCHAR(150) NULL,
    phone           NVARCHAR(30)  NULL,
    is_active       BIT           NOT NULL DEFAULT 1,
    failed_attempts INT           NOT NULL DEFAULT 0,
    locked_until    DATETIME      NULL,
    last_login      DATETIME      NULL,
    last_login_ip   NVARCHAR(45)  NULL,
    created_at      DATETIME      NOT NULL DEFAULT GETDATE(),
    updated_at      DATETIME      NOT NULL DEFAULT GETDATE(),
    CONSTRAINT FK_Users_Role FOREIGN KEY (role_id) REFERENCES dbo.Roles(role_id)
);
GO

-- Selector/validator remember-me tokens (never store the raw token)
IF OBJECT_ID('dbo.UserTokens', 'U') IS NULL
CREATE TABLE dbo.UserTokens (
    token_id       INT IDENTITY(1,1) PRIMARY KEY,
    user_id        INT NOT NULL,
    selector       NVARCHAR(32)  NOT NULL UNIQUE,
    validator_hash NVARCHAR(64)  NOT NULL,
    expires_at     DATETIME      NOT NULL,
    created_at     DATETIME      NOT NULL DEFAULT GETDATE(),
    CONSTRAINT FK_UserTokens_User FOREIGN KEY (user_id) REFERENCES dbo.Users(user_id) ON DELETE CASCADE
);
GO

IF OBJECT_ID('dbo.LoginLogs', 'U') IS NULL
CREATE TABLE dbo.LoginLogs (
    log_id        INT IDENTITY(1,1) PRIMARY KEY,
    user_id       INT NULL,
    username      NVARCHAR(50) NOT NULL,
    is_success    BIT NOT NULL,
    ip_address    NVARCHAR(45) NULL,
    user_agent    NVARCHAR(255) NULL,
    attempted_at  DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT FK_LoginLogs_User FOREIGN KEY (user_id) REFERENCES dbo.Users(user_id) ON DELETE SET NULL
);
GO

IF OBJECT_ID('dbo.ActivityLogs', 'U') IS NULL
CREATE TABLE dbo.ActivityLogs (
    activity_id  INT IDENTITY(1,1) PRIMARY KEY,
    user_id      INT NOT NULL,
    action       NVARCHAR(100) NOT NULL,     -- e.g. LOGIN, LOGOUT, PRODUCT_CREATE
    description  NVARCHAR(500) NULL,
    ip_address   NVARCHAR(45) NULL,
    created_at   DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT FK_ActivityLogs_User FOREIGN KEY (user_id) REFERENCES dbo.Users(user_id)
);
GO

IF OBJECT_ID('dbo.PasswordResetRequests', 'U') IS NULL
CREATE TABLE dbo.PasswordResetRequests (
    request_id  INT IDENTITY(1,1) PRIMARY KEY,
    user_id     INT NOT NULL,
    status      NVARCHAR(20) NOT NULL DEFAULT 'pending',
    request_ip  NVARCHAR(45) NULL,
    requested_at DATETIME NOT NULL DEFAULT GETDATE(),
    resolved_at DATETIME NULL,
    resolved_by INT NULL,
    CONSTRAINT FK_PasswordResetRequests_User FOREIGN KEY (user_id) REFERENCES dbo.Users(user_id),
    CONSTRAINT FK_PasswordResetRequests_Resolver FOREIGN KEY (resolved_by) REFERENCES dbo.Users(user_id)
);
GO

/* -------------------------------------------------------------------
   Catalog: Categories, Suppliers, Products
   ------------------------------------------------------------------- */

IF OBJECT_ID('dbo.Categories', 'U') IS NULL
CREATE TABLE dbo.Categories (
    category_id   INT IDENTITY(1,1) PRIMARY KEY,
    category_name NVARCHAR(100) NOT NULL UNIQUE,
    description   NVARCHAR(255) NULL,
    is_active     BIT NOT NULL DEFAULT 1,
    created_at    DATETIME NOT NULL DEFAULT GETDATE()
);
GO

IF OBJECT_ID('dbo.Suppliers', 'U') IS NULL
CREATE TABLE dbo.Suppliers (
    supplier_id    INT IDENTITY(1,1) PRIMARY KEY,
    supplier_name  NVARCHAR(150) NOT NULL,
    contact_person NVARCHAR(100) NULL,
    phone          NVARCHAR(30)  NULL,
    email          NVARCHAR(150) NULL,
    address        NVARCHAR(255) NULL,
    is_active      BIT NOT NULL DEFAULT 1,
    created_at     DATETIME NOT NULL DEFAULT GETDATE()
);
GO

IF OBJECT_ID('dbo.Products', 'U') IS NULL
CREATE TABLE dbo.Products (
    product_id      INT IDENTITY(1,1) PRIMARY KEY,
    category_id     INT NULL,
    supplier_id     INT NULL,
    product_code    NVARCHAR(50)  NOT NULL UNIQUE,
    barcode         NVARCHAR(50)  NULL UNIQUE,
    qr_code         NVARCHAR(100) NULL,
    product_name    NVARCHAR(150) NOT NULL,
    brand           NVARCHAR(100) NULL,
    image_path      NVARCHAR(255) NULL,
    cost_price      DECIMAL(12,2) NOT NULL DEFAULT 0,
    selling_price   DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_rate        DECIMAL(5,2)  NOT NULL DEFAULT 0,     -- percent
    discount_rate   DECIMAL(5,2)  NOT NULL DEFAULT 0,     -- percent
    unit            NVARCHAR(20)  NOT NULL DEFAULT 'pc',
    stock_alert_qty INT NOT NULL DEFAULT 10,
    is_active       BIT NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT GETDATE(),
    updated_at      DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT FK_Products_Category FOREIGN KEY (category_id) REFERENCES dbo.Categories(category_id),
    CONSTRAINT FK_Products_Supplier FOREIGN KEY (supplier_id) REFERENCES dbo.Suppliers(supplier_id)
);
GO

IF OBJECT_ID('dbo.Inventory', 'U') IS NULL
CREATE TABLE dbo.Inventory (
    inventory_id   INT IDENTITY(1,1) PRIMARY KEY,
    product_id     INT NOT NULL,
    quantity_on_hand INT NOT NULL DEFAULT 0,
    last_counted_at  DATETIME NULL,
    updated_at       DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT FK_Inventory_Product FOREIGN KEY (product_id) REFERENCES dbo.Products(product_id) ON DELETE CASCADE,
    CONSTRAINT UQ_Inventory_Product UNIQUE (product_id)
);
GO

/* Product catalog lookups. Products keep the unit/brand text deliberately so
   historic products remain readable even if a catalog entry is retired. */
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

/* -------------------------------------------------------------------
   Inventory Movements - the Item Ledger's data source. Every change to
   Inventory.quantity_on_hand (a purchase received, a sale, a voided
   sale, a cancelled purchase, or a manual count correction) gets one
   row here with the resulting balance, so stock history can be
   reconstructed and audited after the fact instead of only ever
   knowing the current total.
   ------------------------------------------------------------------- */

IF OBJECT_ID('dbo.InventoryMovements', 'U') IS NULL
CREATE TABLE dbo.InventoryMovements (
    movement_id      INT IDENTITY(1,1) PRIMARY KEY,
    product_id       INT NOT NULL,
    movement_type    NVARCHAR(20)  NOT NULL,  -- purchase, purchase_cancel, sale, sale_void, adjustment
    quantity_change  INT NOT NULL,             -- positive = stock in, negative = stock out
    balance_after     INT NOT NULL,
    reference_type    NVARCHAR(20)  NULL,      -- 'purchase', 'sale', 'manual'
    reference_id       INT NULL,
    reference_code     NVARCHAR(50)  NULL,     -- invoice_no / purchase reference_no snapshot
    notes               NVARCHAR(255) NULL,
    user_id             INT NULL,
    created_at          DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT FK_InvMovements_Product FOREIGN KEY (product_id) REFERENCES dbo.Products(product_id),
    CONSTRAINT FK_InvMovements_User FOREIGN KEY (user_id) REFERENCES dbo.Users(user_id)
);
GO

CREATE INDEX IX_InventoryMovements_ProductDate ON dbo.InventoryMovements(product_id, created_at);
GO

/* -------------------------------------------------------------------
   Customers
   ------------------------------------------------------------------- */

IF OBJECT_ID('dbo.Customers', 'U') IS NULL
CREATE TABLE dbo.Customers (
    customer_id   INT IDENTITY(1,1) PRIMARY KEY,
    full_name     NVARCHAR(150) NOT NULL,
    phone         NVARCHAR(30)  NULL,
    email         NVARCHAR(150) NULL,
    address       NVARCHAR(255) NULL,
    loyalty_points INT NOT NULL DEFAULT 0,
    is_active     BIT NOT NULL DEFAULT 1,
    created_at    DATETIME NOT NULL DEFAULT GETDATE()
);
GO

/* -------------------------------------------------------------------
   Sales / Sale Details
   ------------------------------------------------------------------- */

IF OBJECT_ID('dbo.Sales', 'U') IS NULL
CREATE TABLE dbo.Sales (
    sale_id        INT IDENTITY(1,1) PRIMARY KEY,
    invoice_no     NVARCHAR(50) NOT NULL UNIQUE,
    customer_id    INT NULL,
    user_id        INT NOT NULL,               -- cashier who processed the sale
    subtotal       DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_total      DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    grand_total    DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount_paid    DECIMAL(12,2) NOT NULL DEFAULT 0,
    change_due     DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_method NVARCHAR(20)  NOT NULL DEFAULT 'cash',  -- cash, gcash, maya, card, check, multiple
    payment_reference NVARCHAR(255) NULL,  -- GCash/Maya: sender name + ref date; Card: holder name + last 4 + approval code. Never store full card numbers/CVV here.
    loyalty_points_earned INT NOT NULL DEFAULT 0,
    loyalty_points_redeemed INT NOT NULL DEFAULT 0,
    status         NVARCHAR(20)  NOT NULL DEFAULT 'completed', -- completed, held, voided
    created_at     DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT FK_Sales_Customer FOREIGN KEY (customer_id) REFERENCES dbo.Customers(customer_id),
    CONSTRAINT FK_Sales_User FOREIGN KEY (user_id) REFERENCES dbo.Users(user_id)
);
GO

IF OBJECT_ID('dbo.SalePayments', 'U') IS NULL
CREATE TABLE dbo.SalePayments (
    sale_payment_id INT IDENTITY(1,1) PRIMARY KEY,
    sale_id         INT NOT NULL,
    payment_method  NVARCHAR(20) NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    payment_reference NVARCHAR(255) NULL,
    CONSTRAINT FK_SalePayments_Sale FOREIGN KEY (sale_id) REFERENCES dbo.Sales(sale_id) ON DELETE CASCADE
);
GO

CREATE INDEX IX_SalePayments_Reference ON dbo.SalePayments(payment_reference) WHERE payment_reference IS NOT NULL;
GO

IF OBJECT_ID('dbo.SaleDetails', 'U') IS NULL
CREATE TABLE dbo.SaleDetails (
    sale_detail_id INT IDENTITY(1,1) PRIMARY KEY,
    sale_id        INT NOT NULL,
    product_id     INT NOT NULL,
    quantity       INT NOT NULL,
    unit_price     DECIMAL(12,2) NOT NULL,
    tax_amount     DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    line_total     DECIMAL(12,2) NOT NULL,
    CONSTRAINT FK_SaleDetails_Sale FOREIGN KEY (sale_id) REFERENCES dbo.Sales(sale_id) ON DELETE CASCADE,
    CONSTRAINT FK_SaleDetails_Product FOREIGN KEY (product_id) REFERENCES dbo.Products(product_id)
);
GO

/* -------------------------------------------------------------------
   Purchases / Purchase Details
   ------------------------------------------------------------------- */

IF OBJECT_ID('dbo.Purchases', 'U') IS NULL
CREATE TABLE dbo.Purchases (
    purchase_id     INT IDENTITY(1,1) PRIMARY KEY,
    reference_no    NVARCHAR(50) NOT NULL UNIQUE,
    invoice_receipt NVARCHAR(100) NULL,
    supplier_id     INT NOT NULL,
    user_id         INT NOT NULL,               -- who recorded the purchase
    total_amount    DECIMAL(12,2) NOT NULL DEFAULT 0,
    status          NVARCHAR(20)  NOT NULL DEFAULT 'received', -- pending, received, cancelled
    purchased_at    DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT FK_Purchases_Supplier FOREIGN KEY (supplier_id) REFERENCES dbo.Suppliers(supplier_id),
    CONSTRAINT FK_Purchases_User FOREIGN KEY (user_id) REFERENCES dbo.Users(user_id)
);
GO

IF OBJECT_ID('dbo.PurchaseDetails', 'U') IS NULL
CREATE TABLE dbo.PurchaseDetails (
    purchase_detail_id INT IDENTITY(1,1) PRIMARY KEY,
    purchase_id     INT NOT NULL,
    product_id      INT NOT NULL,
    quantity        INT NOT NULL,
    unit_cost       DECIMAL(12,2) NOT NULL,
    line_total      DECIMAL(12,2) NOT NULL,
    CONSTRAINT FK_PurchaseDetails_Purchase FOREIGN KEY (purchase_id) REFERENCES dbo.Purchases(purchase_id) ON DELETE CASCADE,
    CONSTRAINT FK_PurchaseDetails_Product FOREIGN KEY (product_id) REFERENCES dbo.Products(product_id)
);
GO

/* -------------------------------------------------------------------
   Expenses - operating costs tracked separately from Purchases (which
   are strictly stock coming in). Feeds the Reports page's profit
   figures alongside revenue and cost of goods sold.
   ------------------------------------------------------------------- */

IF OBJECT_ID('dbo.Expenses', 'U') IS NULL
CREATE TABLE dbo.Expenses (
    expense_id    INT IDENTITY(1,1) PRIMARY KEY,
    category      NVARCHAR(50)   NOT NULL,
    description   NVARCHAR(255)  NULL,
    amount        DECIMAL(12,2)  NOT NULL,
    expense_date  DATE           NOT NULL,
    user_id       INT            NOT NULL,
    created_at    DATETIME       NOT NULL DEFAULT GETDATE(),
    CONSTRAINT FK_Expenses_User FOREIGN KEY (user_id) REFERENCES dbo.Users(user_id)
);
GO

CREATE INDEX IX_Expenses_Date ON dbo.Expenses(expense_date);
GO

/* -------------------------------------------------------------------
   CashReconciliation - End of Day Reconciliation. One row per business
   day: opening float, the system's expected cash total at the moment
   of closing, what was actually counted, and the variance. See
   database/migration_cash_reconciliation.sql for the full comment.
   ------------------------------------------------------------------- */

IF OBJECT_ID('dbo.CashReconciliation', 'U') IS NULL
CREATE TABLE dbo.CashReconciliation (
    reconciliation_id INT IDENTITY(1,1) PRIMARY KEY,
    business_date      DATE NOT NULL UNIQUE,
    user_id            INT NOT NULL,
    opening_float      DECIMAL(12,2) NOT NULL DEFAULT 0,
    expected_cash      DECIMAL(12,2) NOT NULL DEFAULT 0,
    counted_cash       DECIMAL(12,2) NOT NULL DEFAULT 0,
    variance           DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes              NVARCHAR(500) NULL,
    created_at         DATETIME NOT NULL DEFAULT GETDATE(),
    updated_at         DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT FK_CashReconciliation_User FOREIGN KEY (user_id) REFERENCES dbo.Users(user_id)
);
GO

/* -------------------------------------------------------------------
   Settings
   ------------------------------------------------------------------- */

IF OBJECT_ID('dbo.Settings', 'U') IS NULL
CREATE TABLE dbo.Settings (
    setting_key    NVARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value  NVARCHAR(500) NULL,
    updated_at     DATETIME NOT NULL DEFAULT GETDATE()
);
GO

/* =========================================================================
   Seed data - Roles, Permissions, default Admin user, base Settings
   ========================================================================= */

IF NOT EXISTS (SELECT 1 FROM dbo.Roles WHERE role_name = 'Administrator')
INSERT INTO dbo.Roles (role_name, description) VALUES
    ('Administrator', 'Full access to all modules and settings'),
    ('Manager',        'Access to sales, inventory, and reports'),
    ('Cashier',        'Access to the POS screen only');
GO

IF NOT EXISTS (SELECT 1 FROM dbo.Permissions WHERE permission_key = 'dashboard.view')
INSERT INTO dbo.Permissions (permission_key, description) VALUES
    ('dashboard.view',   'View the dashboard'),
    ('products.manage',  'Create, edit, delete products'),
    ('pos.access',       'Access the POS screen'),
    ('reports.view',     'View sales and inventory reports'),
    ('users.manage',     'Create, edit, delete users and roles'),
    ('settings.manage',  'Change system settings');
GO

/* Every sidebar menu can be granted independently. */
INSERT INTO dbo.Permissions (permission_key, description)
SELECT v.permission_key, v.description FROM (VALUES
 ('categories.manage','Manage categories, units and brands'), ('suppliers.manage','Manage suppliers'),
 ('customers.manage','Manage customers and loyalty'), ('purchases.manage','Record purchases'),
 ('inventory.manage','View and adjust inventory'), ('ledger.view','View item ledger'),
 ('sales.view','View sales history'), ('sales.export','Export sales'), ('reports.print','Print reports'),
 ('activity_logs.view','View activity logs'), ('customer_reports.view','View and export customer reports')
) v(permission_key, description)
WHERE NOT EXISTS (SELECT 1 FROM dbo.Permissions p WHERE p.permission_key=v.permission_key);
GO

-- Administrator gets every permission
IF NOT EXISTS (SELECT 1 FROM dbo.RolePermissions rp
               INNER JOIN dbo.Roles r ON r.role_id = rp.role_id
               WHERE r.role_name = 'Administrator')
INSERT INTO dbo.RolePermissions (role_id, permission_id)
SELECT (SELECT role_id FROM dbo.Roles WHERE role_name = 'Administrator'), permission_id
FROM dbo.Permissions;
GO

-- Default administrator account.
-- Username: admin   Password: Admin@12345   <-- CHANGE THIS IMMEDIATELY AFTER FIRST LOGIN
-- The hash below is a password_hash() (bcrypt) value generated for 'Admin@12345'.
IF NOT EXISTS (SELECT 1 FROM dbo.Users WHERE username = 'admin')
INSERT INTO dbo.Users (role_id, username, password_hash, full_name, email, is_active)
VALUES (
    (SELECT role_id FROM dbo.Roles WHERE role_name = 'Administrator'),
    'admin',
    '$2b$12$ae4BteAxMbrEFomZWxxWnexrZs.rKYZxstkYmswps6sx.5DpLjaDC', -- valid bcrypt hash of 'Admin@12345' - CHANGE after first login
    'System Administrator',
    'admin@example.com',
    1
);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.Settings WHERE setting_key = 'store_name')
INSERT INTO dbo.Settings (setting_key, setting_value) VALUES
    ('store_name',      'My POS Store'),
    ('store_address',   ''),
    ('store_phone',     ''),
    ('currency_symbol', '₱'),
    ('tax_inclusive',   '0'),
    ('receipt_footer',  'Thank you for shopping with us!'),
    ('show_store_on_receipt', '1'),
    ('show_receipt_after_sale', '1'),
    ('auto_print_receipt', '0'),
    ('cash_payment_only', '0'),
    ('mobile_fullscreen', '0'),
    ('receipt_template', 'classic'),
    ('printer_width', '80'),
    ('loyalty_spend_amount', '1000'),
    ('loyalty_points_awarded', '10'),
    ('loyalty_point_value', '1'),
    ('email_password_reset_enabled', '0'),
    ('email_smtp_host', 'smtp.gmail.com'),
    ('email_smtp_port', '587'),
    ('email_smtp_username', 'notifications.service.do.not.reply@gmail.com'),
    ('email_smtp_password', 'oxqahcsaxwzmhniu'),
    ('store_icon_path', '');
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

/* -------------------------------------------------------------------
   Helpful indexes for frequent lookups
   ------------------------------------------------------------------- */
CREATE INDEX IX_Products_Name        ON dbo.Products(product_name);
CREATE INDEX IX_Sales_CreatedAt      ON dbo.Sales(created_at);
CREATE INDEX IX_LoginLogs_Username   ON dbo.LoginLogs(username);
CREATE INDEX IX_ActivityLogs_UserId  ON dbo.ActivityLogs(user_id);
GO
