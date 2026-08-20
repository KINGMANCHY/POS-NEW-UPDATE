/* =========================================================================
   database/seed_test_data.sql
   -------------------------------------------------------------------------
   Sample data for testing the app end to end: categories, suppliers,
   products with stock, customers, and two extra user profiles (Manager,
   Cashier) alongside the Administrator account that database/pos_store.sql
   already seeds.

   Run this AFTER database/pos_store.sql (it needs the Roles/Users tables
   and the Products/Inventory/Customers/Suppliers/Categories tables to
   already exist). Safe to re-run - every insert is guarded with an
   IF NOT EXISTS check, so running it twice won't create duplicates.

   Test login credentials (all three share the same password so you only
   need to remember one thing):
       Administrator : admin    / Admin@12345
       Manager       : manager  / Admin@12345
       Cashier       : cashier  / Admin@12345
   CHANGE THESE before this ever sees a real deployment.
   ========================================================================= */

-- -------------------------------------------------------------------
-- Extra user profiles (Manager, Cashier) - reuses the same verified
-- bcrypt hash as the admin seed in pos_store.sql (both = 'Admin@12345').
-- -------------------------------------------------------------------
IF NOT EXISTS (SELECT 1 FROM dbo.Users WHERE username = 'manager')
INSERT INTO dbo.Users (role_id, username, password_hash, full_name, email, is_active)
VALUES (
    (SELECT role_id FROM dbo.Roles WHERE role_name = 'Manager'),
    'manager',
    '$2b$12$ae4BteAxMbrEFomZWxxWnexrZs.rKYZxstkYmswps6sx.5DpLjaDC',
    'Maria Santos',
    'manager@example.com',
    1
);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.Users WHERE username = 'cashier')
INSERT INTO dbo.Users (role_id, username, password_hash, full_name, email, is_active)
VALUES (
    (SELECT role_id FROM dbo.Roles WHERE role_name = 'Cashier'),
    'cashier',
    '$2b$12$ae4BteAxMbrEFomZWxxWnexrZs.rKYZxstkYmswps6sx.5DpLjaDC',
    'Juan Dela Cruz',
    'cashier@example.com',
    1
);
GO

-- -------------------------------------------------------------------
-- Categories
-- -------------------------------------------------------------------
IF NOT EXISTS (SELECT 1 FROM dbo.Categories WHERE category_name = 'Beverages')
INSERT INTO dbo.Categories (category_name, description, is_active) VALUES
    ('Beverages',     'Soft drinks, juices, water, coffee', 1),
    ('Snacks',        'Chips, biscuits, candy',              1),
    ('Grocery',       'Canned goods, rice, condiments',      1),
    ('Personal Care', 'Soap, shampoo, toothpaste',           1),
    ('Household',     'Cleaning supplies, paper goods',      1);
GO

-- -------------------------------------------------------------------
-- Suppliers
-- -------------------------------------------------------------------
IF NOT EXISTS (SELECT 1 FROM dbo.Suppliers WHERE supplier_name = 'Sunrise Distributors')
INSERT INTO dbo.Suppliers (supplier_name, contact_person, phone, email, address, is_active) VALUES
    ('Sunrise Distributors', 'Ana Reyes',    '0917-100-2001', 'ana@sunrisedist.example',    'Davao City', 1),
    ('Metro Wholesale',      'Ben Cruz',     '0917-100-2002', 'ben@metrowholesale.example',  'Cebu City',  1),
    ('GreenLeaf Supply Co.', 'Carla Uy',     '0917-100-2003', 'carla@greenleaf.example',     'Manila',     1);
GO

-- -------------------------------------------------------------------
-- Products (10) - spread across categories/suppliers, prices in PHP.
-- -------------------------------------------------------------------
IF NOT EXISTS (SELECT 1 FROM dbo.Products WHERE product_code = 'BEV-001')
INSERT INTO dbo.Products (category_id, supplier_id, product_code, barcode, product_name, brand, cost_price, selling_price, tax_rate, discount_rate, unit, stock_alert_qty, is_active) VALUES
    ((SELECT category_id FROM dbo.Categories WHERE category_name = 'Beverages'),
     (SELECT supplier_id FROM dbo.Suppliers WHERE supplier_name = 'Sunrise Distributors'),
     'BEV-001', '4800000000011', 'Bottled Water 500ml',      'AquaPure',   8.00,  15.00, 0,  0, 'pc', 24, 1),

    ((SELECT category_id FROM dbo.Categories WHERE category_name = 'Beverages'),
     (SELECT supplier_id FROM dbo.Suppliers WHERE supplier_name = 'Sunrise Distributors'),
     'BEV-002', '4800000000028', 'Cola 1.5L',                'FizzCo',     28.00, 55.00, 12, 0, 'pc', 12, 1),

    ((SELECT category_id FROM dbo.Categories WHERE category_name = 'Beverages'),
     (SELECT supplier_id FROM dbo.Suppliers WHERE supplier_name = 'Metro Wholesale'),
     'BEV-003', '4800000000035', 'Instant Coffee 3-in-1 (Pack of 10)', 'MorningBrew', 45.00, 79.00, 0, 5, 'pack', 10, 1),

    ((SELECT category_id FROM dbo.Categories WHERE category_name = 'Snacks'),
     (SELECT supplier_id FROM dbo.Suppliers WHERE supplier_name = 'Metro Wholesale'),
     'SNK-001', '4800000000042', 'Potato Chips 60g',         'CrispKing',  16.00, 29.00, 12, 0, 'pc', 30, 1),

    ((SELECT category_id FROM dbo.Categories WHERE category_name = 'Snacks'),
     (SELECT supplier_id FROM dbo.Suppliers WHERE supplier_name = 'Metro Wholesale'),
     'SNK-002', '4800000000059', 'Chocolate Bar 45g',        'CocoaDream', 12.00, 22.00, 12, 0, 'pc', 30, 1),

    ((SELECT category_id FROM dbo.Categories WHERE category_name = 'Grocery'),
     (SELECT supplier_id FROM dbo.Suppliers WHERE supplier_name = 'GreenLeaf Supply Co.'),
     'GRO-001', '4800000000066', 'Rice 5kg',                 'HarvestGold',180.00, 245.00, 0, 0, 'sack', 15, 1),

    ((SELECT category_id FROM dbo.Categories WHERE category_name = 'Grocery'),
     (SELECT supplier_id FROM dbo.Suppliers WHERE supplier_name = 'GreenLeaf Supply Co.'),
     'GRO-002', '4800000000073', 'Canned Sardines 155g',     'SeaCatch',   14.00, 24.00, 0, 0, 'can', 40, 1),

    ((SELECT category_id FROM dbo.Categories WHERE category_name = 'Personal Care'),
     (SELECT supplier_id FROM dbo.Suppliers WHERE supplier_name = 'Sunrise Distributors'),
     'PC-001',  '4800000000080', 'Bar Soap 90g',             'PureClean',  10.00, 18.00, 12, 0, 'pc', 25, 1),

    ((SELECT category_id FROM dbo.Categories WHERE category_name = 'Personal Care'),
     (SELECT supplier_id FROM dbo.Suppliers WHERE supplier_name = 'Sunrise Distributors'),
     'PC-002',  '4800000000097', 'Shampoo Sachet 12ml',      'SilkStrand', 3.00,  7.00,  12, 0, 'pc', 50, 1),

    ((SELECT category_id FROM dbo.Categories WHERE category_name = 'Household'),
     (SELECT supplier_id FROM dbo.Suppliers WHERE supplier_name = 'Metro Wholesale'),
     'HH-001',  '4800000000103', 'Dishwashing Liquid 250ml', 'SparkleWash',22.00, 38.00, 12, 0, 'bottle', 20, 1);
GO

-- -------------------------------------------------------------------
-- Inventory - one row per product above. A couple are set low/at zero
-- on purpose so you have something to test low-stock and out-of-stock
-- states with on the POS Screen and Inventory page.
-- -------------------------------------------------------------------
IF NOT EXISTS (SELECT 1 FROM dbo.Inventory i INNER JOIN dbo.Products p ON p.product_id = i.product_id WHERE p.product_code = 'BEV-001')
INSERT INTO dbo.Inventory (product_id, quantity_on_hand)
SELECT product_id, qty FROM (VALUES
    ('BEV-001', 120), ('BEV-002', 60), ('BEV-003', 8),
    ('SNK-001', 90),  ('SNK-002', 75), ('GRO-001', 20),
    ('GRO-002', 100), ('PC-001', 5),   ('PC-002', 0),
    ('HH-001', 40)
) AS seed(code, qty)
INNER JOIN dbo.Products p ON p.product_code = seed.code;
GO

-- -------------------------------------------------------------------
-- Customers (5)
-- -------------------------------------------------------------------
IF NOT EXISTS (SELECT 1 FROM dbo.Customers WHERE full_name = 'Liza Fernandez')
INSERT INTO dbo.Customers (full_name, phone, email, address, loyalty_points, is_active) VALUES
    ('Liza Fernandez',  '0917-200-3001', 'liza.f@example.com',    'Davao City', 40, 1),
    ('Mark Villanueva',  '0917-200-3002', 'mark.v@example.com',    'Davao City', 15, 1),
    ('Grace Tan',         '0917-200-3003', 'grace.t@example.com',   'Davao City', 0,  1),
    ('Ramon Aquino',       '0917-200-3004', 'ramon.a@example.com',   'Davao City', 5,  1),
    ('Sofia Reyes',         '0917-200-3005', 'sofia.r@example.com',   'Davao City', 22, 1);
GO

-- -------------------------------------------------------------------
-- A couple of sample completed sales, so Sales History / Reports /
-- the dashboard have something to show right away instead of being
-- empty on first login. Backdated a few days so they show up
-- distinctly in the "Revenue by Day" trend chart.
-- -------------------------------------------------------------------
IF NOT EXISTS (SELECT 1 FROM dbo.Sales WHERE invoice_no = 'INV-SEED-0001')
BEGIN
    DECLARE @saleId1 INT, @saleId2 INT;
    DECLARE @adminId INT = (SELECT user_id FROM dbo.Users WHERE username = 'admin');
    DECLARE @cashierId INT = (SELECT user_id FROM dbo.Users WHERE username = 'cashier');
    DECLARE @liza INT = (SELECT customer_id FROM dbo.Customers WHERE full_name = 'Liza Fernandez');

    INSERT INTO dbo.Sales (invoice_no, customer_id, user_id, subtotal, tax_total, discount_total, grand_total, amount_paid, change_due, payment_method, status, created_at)
    VALUES ('INV-SEED-0001', @liza, @adminId, 99.00, 6.60, 0, 105.60, 110.00, 4.40, 'cash', 'completed', DATEADD(DAY, -2, GETDATE()));

    SET @saleId1 = SCOPE_IDENTITY();

    INSERT INTO dbo.SaleDetails (sale_id, product_id, quantity, unit_price, tax_amount, discount_amount, line_total)
    SELECT @saleId1, product_id, 2, selling_price, ROUND(selling_price * 2 * 0.12, 2), 0, ROUND(selling_price * 2 * 1.12, 2)
    FROM dbo.Products WHERE product_code = 'BEV-002'
    UNION ALL
    SELECT @saleId1, product_id, 1, selling_price, 0, 0, selling_price
    FROM dbo.Products WHERE product_code = 'BEV-001';

    INSERT INTO dbo.Sales (invoice_no, customer_id, user_id, subtotal, tax_total, discount_total, grand_total, amount_paid, change_due, payment_method, status, created_at)
    VALUES ('INV-SEED-0002', NULL, @cashierId, 51.00, 4.60, 0, 55.60, 55.60, 0, 'gcash', 'completed', DATEADD(DAY, -1, GETDATE()));

    SET @saleId2 = SCOPE_IDENTITY();

    INSERT INTO dbo.SaleDetails (sale_id, product_id, quantity, unit_price, tax_amount, discount_amount, line_total)
    SELECT @saleId2, product_id, 1, selling_price, ROUND(selling_price * 0.12, 2), 0, ROUND(selling_price * 1.12, 2)
    FROM dbo.Products WHERE product_code = 'SNK-001'
    UNION ALL
    SELECT @saleId2, product_id, 1, selling_price, ROUND(selling_price * 0.12, 2), 0, ROUND(selling_price * 1.12, 2)
    FROM dbo.Products WHERE product_code = 'PC-001';
END
GO
