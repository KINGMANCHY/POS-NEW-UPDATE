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
