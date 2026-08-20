/* =========================================================================
   database/add_inventory_movements.sql
   -------------------------------------------------------------------------
   Adds a proper stock-movement ledger. Until now the app only tracked the
   *current* quantity_on_hand - there was no history of every purchase-in,
   sale-out, void-reversal, or manual count correction. This table gives
   the Item Ledger page something real to read from, with an accurate
   running balance per movement.

   Run this once, after database/pos_store.sql (and after
   database/seed_test_data.sql if you've already run that - order between
   those two doesn't matter, this one just needs Products/Users to exist).
   ========================================================================= */

IF OBJECT_ID('dbo.InventoryMovements', 'U') IS NULL
CREATE TABLE dbo.InventoryMovements (
    movement_id     INT IDENTITY(1,1) PRIMARY KEY,
    product_id      INT NOT NULL,
    movement_type   NVARCHAR(20) NOT NULL,   -- 'purchase', 'purchase_cancel', 'sale', 'sale_void', 'adjustment'
    quantity_change INT NOT NULL,             -- positive = stock IN, negative = stock OUT
    balance_after   INT NOT NULL,             -- quantity_on_hand immediately after this movement
    reference_type  NVARCHAR(20) NULL,        -- 'purchase', 'sale', 'manual'
    reference_id    INT NULL,                 -- purchase_id or sale_id this movement came from
    reference_code  NVARCHAR(50) NULL,        -- invoice_no / reference_no, stored directly to avoid extra joins when listing
    notes           NVARCHAR(255) NULL,       -- e.g. the reason given for a manual adjustment
    user_id         INT NULL,
    created_at      DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT FK_InvMovements_Product FOREIGN KEY (product_id) REFERENCES dbo.Products(product_id),
    CONSTRAINT FK_InvMovements_User FOREIGN KEY (user_id) REFERENCES dbo.Users(user_id)
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_InvMovements_Product_Date')
CREATE INDEX IX_InvMovements_Product_Date ON dbo.InventoryMovements(product_id, created_at);
GO
