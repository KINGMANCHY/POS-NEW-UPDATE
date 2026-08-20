<?php
/**
 * app/helpers/PdfWriter.php
 * -----------------------------------------------------------------------
 * A minimal, dependency-free PDF writer for a single title + table
 * layout (used by the Item Ledger export). No TCPDF/mPDF/dompdf - this
 * environment has no Composer/vendor setup and can't reach the internet
 * to install one, so this writes the raw PDF object structure directly:
 * standard Helvetica (a core font every PDF viewer already has, so
 * nothing needs to be embedded), simple text positioning, and manual
 * pagination when a table runs past one page.
 *
 * This covers exactly what the ledger export needs (a title, a
 * subtitle, and a bordered table) - it is not a general-purpose PDF
 * library.
 */

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class PdfWriter
{
    private const PAGE_WIDTH = 595.28;  // A4 portrait, points
    private const PAGE_HEIGHT = 841.89;
    private const MARGIN = 40;
    private const ROW_HEIGHT = 16;
    private const HEADER_ROW_HEIGHT = 18;

    private array $pageStreams = [];
    private string $currentStream = '';
    private float $cursorY;
    private string $title;
    private string $subtitle;
    private array $headers;
    private array $colWidths;
    private float $tableWidth;

    public function __construct(string $title, string $subtitle, array $headers, array $colWidths)
    {
        $this->title = $title;
        $this->subtitle = $subtitle;
        $this->headers = $headers;
        $this->colWidths = $colWidths;
        $this->tableWidth = array_sum($colWidths);
    }

    /** Builds the document and streams it to the browser as an attachment, then exits. */
    public static function stream(string $filename, string $title, string $subtitle, array $headers, array $colWidths, array $rows): void
    {
        $writer = new self($title, $subtitle, $headers, $colWidths);
        $writer->render($rows);
        $bytes = $writer->build();

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_\-]+/', '_', $filename) . '.pdf"');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: max-age=0');
        echo $bytes;
        exit;
    }

    private function render(array $rows): void
    {
        $this->startPage();

        foreach ($rows as $row) {
            if ($this->cursorY < self::MARGIN + self::ROW_HEIGHT) {
                $this->finishPage();
                $this->startPage(false);
            }
            $this->drawRow(array_values($row), false);
        }

        $this->finishPage();
    }

    private function startPage(bool $withTitleBlock = true): void
    {
        $this->currentStream = '';
        $this->cursorY = self::PAGE_HEIGHT - self::MARGIN;

        if ($withTitleBlock) {
            $this->text($this->title, self::MARGIN, $this->cursorY, 14, true);
            $this->cursorY -= 18;
            $this->text($this->subtitle, self::MARGIN, $this->cursorY, 9, false, [0.4, 0.4, 0.4]);
            $this->cursorY -= 20;
        }

        $this->drawRow($this->headers, true);
    }

    private function finishPage(): void
    {
        $this->pageStreams[] = $this->currentStream;
    }

    private function drawRow(array $cells, bool $isHeader): void
    {
        $height = $isHeader ? self::HEADER_ROW_HEIGHT : self::ROW_HEIGHT;
        $x = self::MARGIN;

        if ($isHeader) {
            $this->rect($x, $this->cursorY - $height + 4, $this->tableWidth, $height, [0.90, 0.90, 0.90]);
        }

        foreach ($cells as $i => $value) {
            $width = $this->colWidths[$i] ?? 60;
            // Keep every cell inside its own column. The customer report has
            // several narrow columns, so overflowing text previously ran into
            // its neighbour and made the exported table look misaligned.
            $maxChars = max(3, (int) floor(($width - 6) / 4.8));
            $value = (string) $value;
            if (strlen($value) > $maxChars) $value = substr($value, 0, max(1, $maxChars - 3)) . '...';
            $this->text($value, $x + 3, $this->cursorY - $height + 8, 9, $isHeader);
            $x += $width;
        }

        $this->line(self::MARGIN, $this->cursorY - $height + 4, self::MARGIN + $this->tableWidth, $this->cursorY - $height + 4);
        $this->cursorY -= $height;
    }

    private function text(string $value, float $x, float $y, float $size, bool $bold, array $rgb = [0, 0, 0]): void
    {
        $font = $bold ? '/F2' : '/F1';
        [$r, $g, $b] = $rgb;
        $this->currentStream .= sprintf(
            "%.3f %.3f %.3f rg\nBT %s %.1f Tf %.2f %.2f Td (%s) Tj ET\n",
            $r, $g, $b, $font, $size, $x, $y, $this->escape($value)
        );
    }

    private function rect(float $x, float $y, float $w, float $h, array $rgb): void
    {
        [$r, $g, $b] = $rgb;
        $this->currentStream .= sprintf("%.3f %.3f %.3f rg\n%.2f %.2f %.2f %.2f re f\n0 0 0 rg\n", $r, $g, $b, $x, $y, $w, $h);
    }

    private function line(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->currentStream .= sprintf("0.75 0.75 0.75 RG\n%.2f %.2f m %.2f %.2f l S\n0 0 0 RG\n", $x1, $y1, $x2, $y2);
    }

    private function escape(string $value): string
    {
        // PDF literal strings only need these three characters escaped.
        $value = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
        // Core Helvetica only covers WinAnsi/Latin-1 - strip anything
        // outside that range (e.g. the Peso sign) rather than corrupt the file.
        return preg_replace('/[^\x20-\x7E]/', '', $value);
    }

    /** Assembles the full PDF byte stream from the collected page content. */
    private function build(): string
    {
        $objects = [];

        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";

        $pageCount = count($this->pageStreams);
        $kids = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $kids[] = (5 + $i) . ' 0 R';
        }
        $objects[2] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count {$pageCount} >>";

        $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

        $pageObjNum = 5;
        $streamObjNum = 5 + $pageCount;
        for ($i = 0; $i < $pageCount; $i++) {
            $objects[$pageObjNum + $i] =
                "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 " . self::PAGE_WIDTH . " " . self::PAGE_HEIGHT . "] "
                . "/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents " . ($streamObjNum + $i) . " 0 R >>";
        }
        for ($i = 0; $i < $pageCount; $i++) {
            $content = $this->pageStreams[$i];
            $objects[$streamObjNum + $i] = "<< /Length " . strlen($content) . " >>\nstream\n{$content}endstream";
        }

        ksort($objects);

        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($out);
            $out .= "{$num} 0 obj\n{$body}\nendobj\n";
        }

        $xrefStart = strlen($out);
        $totalObjects = count($objects) + 1;
        $out .= "xref\n0 {$totalObjects}\n";
        $out .= "0000000000 65535 f \n";
        for ($num = 1; $num < $totalObjects; $num++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$num]);
        }

        $out .= "trailer\n<< /Size {$totalObjects} /Root 1 0 R >>\nstartxref\n{$xrefStart}\n%%EOF";

        return $out;
    }
}
