/* =========================================================================
   database/migration_payment_reference_and_expenses.sql
   -------------------------------------------------------------------------
   Adds:
     1. Sales.payment_reference - GCash/Maya sender name + date, or a
        card holder name + last 4 digits + approval code. Safe to re-run.
     2. dbo.Expenses - a new table for the Reports page's expense tracker.

   Run this against a database that already ran pos_store.sql (or run
   pos_store.sql fresh - both now already include these).
   ========================================================================= */

IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('dbo.Sales') AND name = 'payment_reference'
)
ALTER TABLE dbo.Sales ADD payment_reference NVARCHAR(255) NULL;
GO

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
