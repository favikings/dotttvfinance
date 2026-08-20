<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Shared Excel renderer for the reporting suite (Build Prompt 3.3a / Tech
 * Spec §14b). One data-fetch per report, two renderers: PDF (dompdf via the
 * report's pdf/* view) and this — both consume the SAME array from Report::,
 * never a separately derived version of the numbers.
 *
 * Basic formatting per Tech Spec §14b: report title + date range in merged
 * cells above the table, bold header row, currency number format on amount
 * columns, auto-sized columns. Deliberately not a clone of the PDF's visual
 * design — this is a working document for an accountant/auditor.
 *
 * Currency uses the ASCII-safe "NGN" prefix rather than the ₦ glyph: same
 * reasoning as naira_pdf() (dompdf has no ₦ glyph in its base-14 fonts, and
 * spreadsheet software varies in how it renders the sign) — amounts are
 * still real numbers with a currency number format, not text.
 *
 * Usage: ExcelExporter::download($title, $rangeLabel, $companyName,
 *   $headers, $rows, $columnFormats, $filenameBase);
 */
class ExcelExporter
{
    public const FORMAT_TEXT = 'text';
    public const FORMAT_CURRENCY = 'currency';
    public const FORMAT_NUMBER = 'number';
    public const FORMAT_DATE = 'date';
    // Value is in percentage points (0-100, e.g. 34.5) and renders as "34.5%" —
    // do NOT pass a 0-1 fraction here, that would print as 0.345%.
    public const FORMAT_PERCENT = 'percent';

    /**
     * Streams an .xlsx download and exits. $rows are positional arrays with
     * one element per header; $columnFormats maps each column to a FORMAT_*
     * constant.
     */
    public static function download(
        string $reportTitle,
        string $rangeLabel,
        string $companyName,
        array $headers,
        array $rows,
        array $columnFormats,
        string $filenameBase
    ): never {
        $spreadsheet = self::build($reportTitle, $rangeLabel, $companyName, $headers, $rows, $columnFormats);

        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $filenameBase) . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $safeName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Builds the workbook for a report. Split from download() so tests and
     * any future caller can build without streaming.
     *
     * @return Spreadsheet
     */
    public static function build(
        string $reportTitle,
        string $rangeLabel,
        string $companyName,
        array $headers,
        array $rows,
        array $columnFormats
    ): Spreadsheet {
        if (count($headers) !== count($columnFormats)) {
            throw new LogicException('Excel export column formats must match header count.');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));

        // Report title (row 1), company + date range (row 2), blank (row 3),
        // header (row 4), data (row 5+).
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', $reportTitle);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getFont()->getColor()->setARGB('FF1A1B20');

        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->setCellValue('A2', $companyName . ' — ' . $rangeLabel);
        $sheet->getStyle('A2')->getFont()->setSize(10);
        $sheet->getStyle('A2')->getFont()->getColor()->setARGB('FF444650');

        $headerRow = 4;
        foreach ($headers as $i => $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $headerRow, $header);
        }
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FFF4F3F9');
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")
            ->getBorders()
            ->getBottom()
            ->setBorderStyle(Border::BORDER_THIN);

        $firstDataRow = $headerRow + 1;
        foreach ($rows as $r => $row) {
            if (count($row) !== count($headers)) {
                throw new LogicException('Excel export row does not match header count.');
            }
            foreach ($row as $c => $value) {
                $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($c + 1) . ($firstDataRow + $r));
                $format = $columnFormats[$c] ?? self::FORMAT_TEXT;

                switch ($format) {
                    case self::FORMAT_CURRENCY:
                    case self::FORMAT_NUMBER:
                    case self::FORMAT_PERCENT:
                        // A non-numeric value (e.g. "Not set" for an unconfigured
                        // budget) must fall through to text — casting it with
                        // (float) would silently turn it into 0.00, a far worse
                        // kind of wrong for a financial document.
                        if (!is_numeric($value)) {
                            $cell->setValue((string) $value);
                            break;
                        }
                        $cell->setValue((float) $value);
                        if ($format === self::FORMAT_CURRENCY) {
                            $cell->getStyle()->getNumberFormat()->setFormatCode('"NGN" #,##0.00');
                        } elseif ($format === self::FORMAT_PERCENT) {
                            $cell->getStyle()->getNumberFormat()->setFormatCode('0.0"%"');
                        } else {
                            $cell->getStyle()->getNumberFormat()->setFormatCode('#,##0.00');
                        }
                        break;
                    case self::FORMAT_DATE:
                        if ($value instanceof DateTimeInterface) {
                            $ts = $value->getTimestamp();
                        } else {
                            $ts = strtotime((string) $value);
                        }
                        if ($ts !== false) {
                            $cell->setValue(
                                \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel((int) $ts)
                            );
                            $cell->getStyle()->getNumberFormat()->setFormatCode('yyyy-mm-dd');
                        } else {
                            $cell->setValue((string) $value);
                        }
                        break;
                    default:
                        $cell->setValue((string) $value);
                        break;
                }
            }
        }

        // Auto-size every column against its widest cell (titles excluded so
        // a long report title doesn't blow up column widths).
        $lastDataRow = $firstDataRow + max(count($rows) - 1, 0);
        foreach ($headers as $i => $header) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $sheet->getStyle("{$col}{$firstDataRow}:{$col}{$lastDataRow}")
                ->getBorders()
                ->getBottom()
                ->setBorderStyle(Border::BORDER_THIN);
        }

        return $spreadsheet;
    }
}