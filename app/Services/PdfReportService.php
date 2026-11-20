<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

/** One report = one PDF page, F4 (default) or A4 (PRD-05 §7.1). */
class PdfReportService
{
    /** F4 / Folio 215 × 330 mm in points. */
    public const F4 = [0, 0, 609.45, 935.43];

    /** @param array<string, mixed> $data from ReportDataBuilder */
    public function render(array $data): string
    {
        return Pdf::loadView('pdf.report', ['r' => $data, 'pdf' => true])
            ->setPaper($data['paper'] === 'a4' ? 'a4' : self::F4)
            ->setOption([
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'isFontSubsettingEnabled' => true, // keeps a report around 100 KB
                'isHtml5ParserEnabled' => true,
                'dpi' => 96,
            ])
            ->output();
    }

    /** HTML of the same template, for the on-screen preview (identical layout). */
    public function html(array $data): string
    {
        return view('pdf.report', ['r' => $data, 'pdf' => false])->render();
    }

    public static function pageCount(string $pdf): int
    {
        return (new Fpdi)->setSourceFile(StreamReader::createByString($pdf));
    }
}
