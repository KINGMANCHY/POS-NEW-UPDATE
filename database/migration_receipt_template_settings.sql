/* =========================================================================
   database/migration_receipt_template_settings.sql
   -------------------------------------------------------------------------
   Seeds the 'receipt_template' (classic|modern) and 'printer_width'
   (58|80) Settings keys used by the Receipt Templates feature. Safe to
   re-run - only inserts a row if that key is missing.

   Run this against a database that already ran pos_store.sql (or run
   pos_store.sql fresh - both now already include these).
   ========================================================================= */

IF NOT EXISTS (SELECT 1 FROM dbo.Settings WHERE setting_key = 'receipt_template')
INSERT INTO dbo.Settings (setting_key, setting_value) VALUES ('receipt_template', 'classic');
GO

IF NOT EXISTS (SELECT 1 FROM dbo.Settings WHERE setting_key = 'printer_width')
INSERT INTO dbo.Settings (setting_key, setting_value) VALUES ('printer_width', '80');
GO
