/* =========================================================================
   database/migration_cash_reconciliation.sql
   -------------------------------------------------------------------------
   Adds dbo.CashReconciliation, used by the End of Day Reconciliation page
   to record, per business day: the opening cash float, the system's
   expected cash total (opening float + cash collected - change given
   out, at the moment reconciliation was saved), what was actually
   counted, and the resulting over/short variance.

   One row per business_date - re-saving a day updates that same row
   (see Reconciliation::save(), an upsert) so a cashier can correct a
   count before shift close without creating duplicates.

   Run this against a database that already ran pos_store.sql (or run
   pos_store.sql fresh - both now already include this).
   ========================================================================= */

IF OBJECT_ID('dbo.CashReconciliation', 'U') IS NULL
CREATE TABLE dbo.CashReconciliation (
    reconciliation_id INT IDENTITY(1,1) PRIMARY KEY,
    business_date      DATE NOT NULL UNIQUE,
    user_id            INT NOT NULL,               -- who closed out this day
    opening_float      DECIMAL(12,2) NOT NULL DEFAULT 0,
    expected_cash      DECIMAL(12,2) NOT NULL DEFAULT 0,
    counted_cash       DECIMAL(12,2) NOT NULL DEFAULT 0,
    variance           DECIMAL(12,2) NOT NULL DEFAULT 0,  -- counted_cash - expected_cash; positive = over, negative = short
    notes              NVARCHAR(500) NULL,
    created_at         DATETIME NOT NULL DEFAULT GETDATE(),
    updated_at         DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT FK_CashReconciliation_User FOREIGN KEY (user_id) REFERENCES dbo.Users(user_id)
);
GO
