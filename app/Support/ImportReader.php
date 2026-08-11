<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Spreadsheet/CSV reader for the bulk-import feature.
 *
 * Supports the three formats HR actually produces:
 *   .csv             — native PHP (no dependency)
 *   .xls             — SpreadsheetML 2003 XML (the format our report exports
 *                      produce; parsed with DOMDocument, no extra dependency)
 *   .xlsx / .xlsm    — Open XML workbooks via PhpSpreadsheet (needs ext-zip)
 *
 * The first row is treated as headers; headers are normalized to snake_case
 * ("Employee Number" → employee_number). Fully-blank rows are dropped.
 */
class ImportReader
{
    /**
     * @return array<int, array<string, string>> rows keyed by normalized header
     */
    public static function rows(string $path, string $filename): array
    {
        $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

        $raw = match ($ext) {
            'csv', 'txt' => self::fromCsv($path),
            'xls' => self::fromXls($path),
            'xlsx', 'xlsm' => self::fromXlsx($path),
            default => throw new RuntimeException(
                'Unsupported file type (.'.$ext.'). Use CSV, XLS, or XLSX.'
            ),
        };

        return self::normalize($raw);
    }

    /* ------------------------------------------------------------------ */
    /*  Format readers */
    /* ------------------------------------------------------------------ */

    private static function fromCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new RuntimeException('Unable to read the uploaded file.');
        }

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            $rows[] = $line;
        }
        fclose($handle);

        return $rows;
    }

    private static function fromXls(string $path): array
    {
        $dom = new \DOMDocument;
        $loaded = @$dom->loadXML((string) file_get_contents($path));
        if (! $loaded) {
            throw new RuntimeException('This .xls file is not in the SpreadsheetML format we accept. Save it as Excel XML Spreadsheet 2003 or CSV.');
        }

        $rows = [];
        foreach ($dom->getElementsByTagName('Row') as $rowEl) {
            $cells = [];
            foreach ($rowEl->getElementsByTagName('Cell') as $cellEl) {
                $data = $cellEl->getElementsByTagName('Data')->item(0);
                $cells[] = $data ? trim((string) $data->textContent) : '';
            }
            $rows[] = $cells;
        }

        return $rows;
    }

    private static function fromXlsx(string $path): array
    {
        $spreadsheet = IOFactory::load($path);

        return $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
    }

    /* ------------------------------------------------------------------ */
    /*  Normalization */
    /* ------------------------------------------------------------------ */

    /**
     * Turn a grid into header-keyed rows, dropping blank rows and blank
     * header columns.
     */
    private static function normalize(array $grid): array
    {
        if (empty($grid)) {
            return [];
        }

        $headers = array_map(fn ($h) => self::normalizeHeader($h), $grid[0]);
        $out = [];

        foreach (array_slice($grid, 1) as $row) {
            $item = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $item[$header] = isset($row[$index]) ? trim((string) $row[$index]) : '';
            }

            if (trim(implode('', $item)) === '') {
                continue;
            }

            $out[] = $item;
        }

        return $out;
    }

    private static function normalizeHeader(string $header): string
    {
        $header = strtolower(trim($header));

        return str_replace([' ', '-'], '_', $header);
    }
}
