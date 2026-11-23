<?php

namespace App\Support;

use App\Exceptions\BusinessRuleException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use Throwable;

/** Reads the first sheet of .xlsx/.xls/.csv into rows keyed by real spreadsheet row number. */
class SpreadsheetReader
{
    /**
     * @param  array<string, list<string>>  $columns  key => accepted header names (lower-case)
     * @param  list<string>  $required  keys that must be present
     * @return array{header_row: int, rows: array<int, array<string, mixed>>}
     */
    public static function read(string $path, string $extension, array $columns, array $required, int $maxRows = 1000): array
    {
        try {
            $reader = IOFactory::createReader(match (strtolower($extension)) {
                'xlsx' => 'Xlsx',
                'xls' => 'Xls',
                'csv', 'txt' => 'Csv',
                default => throw new BusinessRuleException('Format file harus .xlsx, .xls, atau .csv.'),
            });

            if ($reader instanceof Csv) {
                $reader->setInputEncoding(Csv::GUESS_ENCODING);
                $reader->setDelimiter(self::guessCsvDelimiter($path));
            } else {
                $reader->setReadDataOnly(true);
            }

            $sheet = $reader->load($path)->getSheet(0);
            $data = $sheet->toArray(null, true, false, false);
        } catch (BusinessRuleException $e) {
            throw $e;
        } catch (Throwable) {
            throw new BusinessRuleException('File tidak dapat dibaca. Pastikan file Excel tidak dikunci kata sandi.', 'FILE_UNREADABLE');
        }

        // Header = first row (within the top 10) that contains all required headers.
        $map = null;
        $headerRow = 0;
        foreach (array_slice($data, 0, 10, true) as $i => $row) {
            $found = [];
            foreach ($row as $col => $cell) {
                $label = self::normalizeHeader((string) $cell);
                foreach ($columns as $key => $names) {
                    if (! isset($found[$key]) && in_array($label, $names, true)) {
                        $found[$key] = $col;
                    }
                }
            }

            if (count(array_intersect($required, array_keys($found))) === count($required)) {
                $map = $found;
                $headerRow = $i;
                break;
            }
        }

        if ($map === null) {
            $labels = implode(', ', array_map(fn ($k) => in_array($columns[$k][0], ['nipd', 'nisn', 'l/p'], true) ? strtoupper($columns[$k][0]) : ucfirst($columns[$k][0]), $required));
            throw new BusinessRuleException("Kolom wajib tidak ditemukan: {$labels}. Gunakan template terbaru.", 'HEADER_MISSING');
        }

        $rows = [];
        foreach (array_slice($data, $headerRow + 1, null, true) as $i => $row) {
            $values = [];
            foreach ($map as $key => $col) {
                $values[$key] = self::cell($row[$col] ?? null);
            }

            if (array_filter($values, fn ($v) => $v !== '') === []) {
                continue; // blank line
            }

            $rows[$i + 1] = $values; // 1-based spreadsheet row
        }

        if (count($rows) > $maxRows) {
            throw new BusinessRuleException('File berisi lebih dari '.number_format($maxRows, 0, ',', '.').' baris. Pecah menjadi beberapa file.', 'TOO_MANY_ROWS');
        }

        return ['header_row' => $headerRow + 1, 'rows' => $rows];
    }

    private static function normalizeHeader(string $label): string
    {
        return trim(preg_replace('/\s+/', ' ', str_replace(['*', '(wajib)'], '', mb_strtolower($label))));
    }

    /** Trimmed string; whole numbers lose the ".0" Excel adds (NIPD 3001.0 → "3001"). */
    private static function cell(mixed $v): string
    {
        if ($v === null) {
            return '';
        }

        if (is_float($v) && floor($v) === $v && abs($v) < 1e15) {
            return (string) (int) $v;
        }

        return trim((string) $v);
    }

    private static function guessCsvDelimiter(string $path): string
    {
        $line = (string) fgets(fopen($path, 'r'));

        return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
    }
}
