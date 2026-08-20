/* Run once on existing POS_STORE databases. Safe to run more than once. */
IF OBJECT_ID('dbo.SalePayments', 'U') IS NULL
CREATE TABLE dbo.SalePayments (
    sale_payment_id INT IDENTITY(1,1) PRIMARY KEY,
    sale_id INT NOT NULL,
    payment_method NVARCHAR(20) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_reference NVARCHAR(255) NULL,
    CONSTRAINT FK_SalePayments_Sale FOREIGN KEY (sale_id) REFERENCES dbo.Sales(sale_id) ON DELETE CASCADE
);
GO

/* Helps the duplicate-reference check stay fast as payment history grows. */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_SalePayments_Reference' AND object_id = OBJECT_ID('dbo.SalePayments'))
    CREATE INDEX IX_SalePayments_Reference ON dbo.SalePayments(payment_reference) WHERE payment_reference IS NOT NULL;
GO

IF NOT EXISTS (SELECT 1 FROM dbo.Settings WHERE setting_key = 'cash_payment_only')
    INSERT INTO dbo.Settings (setting_key, setting_value) VALUES ('cash_payment_only', '0');
GO
