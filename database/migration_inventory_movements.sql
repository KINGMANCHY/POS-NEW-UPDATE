/* =========================================================================
   database/migration_inventory_movements.sql
   -------------------------------------------------------------------------
   Adds the InventoryMovements table (the Item Ledger's data source) to a
   database that already ran database/pos_store.sql before this table
   existed. Safe to re-run - IF OBJECT_ID guards on everything.

   After running this, every NEW purchase/sale/void/adjustment will be
   logged automatically (the app code already calls InventoryMovement::log()
   at every point stock changes). Movements from BEFORE you run this
   migration were never recorded, so the ledger will start empty and
   build up from here - there's no way to reconstruct history that was
   never logged.
   ========================================================================= */

IF OBJECT_ID('dbo.InventoryMovements', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.InventoryMovements (
        movement_id      INT IDENTITY(1,1) PRIMARY KEY,
        product_id       INT NOT NULL,
        movement_type    NVARCHAR(20)  NOT NULL,
        quantity_change  INT NOT NULL,
        balance_after    INT NOT NULL,
        reference_type   NVARCHAR(20)  NULL,
        reference_id     INT NULL,
        reference_code   NVARCHAR(50)  NULL,
        notes            NVARCHAR(255) NULL,
        user_id          INT NULL,
        created_at       DATETIME NOT NULL DEFAULT GETDATE(),
        CONSTRAINT FK_InvMovements_Product FOREIGN KEY (product_id) REFERENCES dbo.Products(product_id),
        CONSTRAINT FK_InvMovements_User FOREIGN KEY (user_id) REFERENCES dbo.Users(user_id)
    );

    CREATE INDEX IX_InventoryMovements_ProductDate ON dbo.InventoryMovements(product_id, created_at);
END
GO

/* -------------------------------------------------------------------
   Optional: seed one "opening balance" movement per product from
   today's current Inventory.quantity_on_hand, so the ledger has a
   sensible starting point instead of showing 0 for everything that
   existed before this migration ran. Comment this block out if you'd
   rather the ledger start truly empty.
   ------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.InventoryMovements WHERE movement_type = 'adjustment' AND reference_type = 'manual' AND notes = 'Opening balance (ledger migration)')
INSERT INTO dbo.InventoryMovements (product_id, movement_type, quantity_change, balance_after, reference_type, notes, created_at)
SELECT i.product_id, 'adjustment', i.quantity_on_hand, i.quantity_on_hand, 'manual', 'Opening balance (ledger migration)', GETDATE()
FROM dbo.Inventory i;
GO
