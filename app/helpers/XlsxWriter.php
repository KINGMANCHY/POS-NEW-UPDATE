<?php
/**
 * app/helpers/XlsxWriter.php
 * -----------------------------------------------------------------------
 * A minimal, dependency-free .xlsx (Office Open XML) writer. This app
 * has no Composer/vendor setup and this environment can't reach the
 * internet to install one (PhpSpreadsheet, etc.), so this hand-writes
 * just enough of the OOXML spec for a single flat sheet: a bold header
 * row plus data rows, using inline strings (skips the separate
 * sharedStrings.xml part entirely - simpler, and fine at this scale).
 *
 * Requires the `zip` PHP extension (ext-zip / ZipArchive), which ships
 * enabled by default in most PHP builds (XAMPP/WAMP/Laragon included).
 */

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class XlsxWriter
{
    /**
     * Streams a one-sheet .xlsx file directly to the browser and exits.
     * $headers is a flat list of column titles. $rows is a list of flat
     * arrays (same column order as $headers); values are written as
     * numbers when numeric, inline strings otherwise.
     */
    public static function stream(string $filename, array $headers, array $rows, string $sheetName = 'Sheet1'): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        self::write($tmpFile, $headers, $rows, $sheetName);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . self::sanitizeFilename($filename) . '.xlsx"');
        header('Content-Length: ' . filesize($tmpFile));
        header('Cache-Control: max-age=0');

        readfile($tmpFile);
        unlink($tmpFile);
        exit;
    }

    private static function write(string $path, array $headers, array $rows, string $sheetName): void
    {
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', self::contentTypesXml());
        $zip->addFromString('_rels/.rels', self::relsXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRelsXml());
        $zip->addFromString('xl/workbook.xml', self::workbookXml($sheetName));
        $zip->addFromString('xl/styles.xml', self::stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::sheetXml($headers, $rows));

        $zip->close();
    }

    private static function sheetXml(array $headers, array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<cols>';
        foreach ($headers as $i => $header) {
            $longest = strlen((string) $header);
            foreach ($rows as $row) $longest = max($longest, strlen((string) (array_values($row)[$i] ?? '')));
            $width = min(32, max(10, $longest + 2));
            $xml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $width . '" customWidth="1"/>';
        }
        $xml .= '</cols>';
        $xml .= '<sheetData>';

        // Header row - bold (style index 1, defined in styles.xml)
        $xml .= '<row r="1">';
        foreach ($headers as $i => $label) {
            $ref = self::cellRef($i, 1);
            $xml .= '<c r="' . $ref . '" t="inlineStr" s="1"><is><t xml:space="preserve">' . self::escape((string) $label) . '</t></is></c>';
        }
        $xml .= '</row>';

        // Data rows
        foreach ($rows as $rowIndex => $row) {
            $r = $rowIndex + 2; // row 1 is the header
            $xml .= '<row r="' . $r . '">';
            foreach (array_values($row) as $i => $value) {
                $ref = self::cellRef($i, $r);
                if ($value === null || $value === '') {
                    $xml .= '<c r="' . $ref . '"/>';
                } elseif (is_numeric($value) && !preg_match('/^0[0-9]/', (string) $value)) {
                    // Guard against numeric-looking codes with a leading zero
                    // (e.g. "0917...") losing their leading zero as a real number.
                    $xml .= '<c r="' . $ref . '"><v>' . self::escape((string) $value) . '</v></c>';
                } else {
                    $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . self::escape((string) $value) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    /** Converts a zero-based column index + one-based row number into an "A1"-style reference. */
    private static function cellRef(int $colIndex, int $row): string
    {
        $letters = '';
        $colIndex++;
        while ($colIndex > 0) {
            $rem = ($colIndex - 1) % 26;
            $letters = chr(65 + $rem) . $letters;
            $colIndex = intdiv($colIndex - 1, 26);
        }
        return $letters . $row;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function sanitizeFilename(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9_\-]+/', '_', $name);
    }

    private static function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private static function relsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private static function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private static function workbookXml(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . self::escape($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    /** Two cell styles: 0 = default, 1 = bold (used for the header row). */
    private static function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="10"/><name val="Calibri"/></font><font><sz val="10"/><name val="Calibri"/><b/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }
}
