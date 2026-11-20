<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Term;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Excel templates, exports and error reports (PRD-03 §5–6). */
class SpreadsheetExportService
{
    private const NAVY = '1F3A68';

    private const INPUT = 'FFF8E1';

    /** template_siswa.xlsx: instructions, header, L/P and class drop-downs. */
    public function studentTemplate(?Term $term): StreamedResponse
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Siswa');

        $sheet->setCellValue('A1', 'TEMPLATE IMPOR SISWA — ICP CEFR Report');
        $sheet->setCellValue('A2', 'Isi mulai baris 4. Kolom bertanda * wajib. Kelas harus sudah ada di periode aktif'
            .($term ? ' ('.$term->fullLabel().')' : '').'. L/P: L atau P.');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB(self::NAVY);
        $sheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('4B5563');

        $this->header($sheet, 3, ['NIS*', 'Nama*', 'L/P', 'Kelas*']);
        $sheet->getStyle('A4:D203')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::INPUT);
        $sheet->getStyle('A4:A203')->getNumberFormat()->setFormatCode('@'); // keep leading zeros in NIS
        $this->widths($sheet, [16, 40, 8, 10]);
        $sheet->freezePane('A4');

        $this->listValidation($sheet, 'C4:C203', '"L,P"');

        $classes = $term?->classrooms()->orderBy('grade')->orderBy('name')->pluck('name')->all() ?? [];
        if ($classes) {
            $list = $book->createSheet()->setTitle('Daftar Kelas');
            $list->setCellValue('A1', 'Kelas');
            foreach ($classes as $i => $name) {
                $list->setCellValue('A'.($i + 2), $name);
            }
            $this->listValidation($sheet, 'D4:D203', "'Daftar Kelas'!\$A\$2:\$A\$".(count($classes) + 1));
        }

        $book->setActiveSheetIndex(0);

        return $this->download($book, 'template_siswa.xlsx');
    }

    /** Daftar siswa: NIS, Nama, L/P, Kelas (for one period). */
    public function studentList(Term $term): StreamedResponse
    {
        $term->loadMissing('academicYear');
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Daftar Siswa');

        $this->header($sheet, 1, ['No', 'NIS', 'Nama', 'L/P', 'Kelas', 'Status']);

        $rows = Enrollment::query()
            ->where('enrollments.term_id', $term->id)
            ->join('classrooms', 'classrooms.id', '=', 'enrollments.classroom_id')
            ->join('students', 'students.id', '=', 'enrollments.student_id')
            ->orderBy('classrooms.grade')->orderBy('classrooms.name')->orderBy('enrollments.roll_no')
            ->get(['students.nis', 'students.name', 'students.gender', 'students.is_active', 'classrooms.name as class_name']);

        $r = 2;
        foreach ($rows as $i => $row) {
            $sheet->setCellValueExplicit("B{$r}", $row->nis, 's');
            $sheet->fromArray([$i + 1], null, "A{$r}");
            $sheet->fromArray([$row->name, $row->gender, $row->class_name, $row->is_active ? 'Aktif' : 'Nonaktif'], null, "C{$r}");
            $r++;
        }

        // Students without a class this period are listed at the end.
        $unassigned = Student::query()
            ->whereNotIn('id', Enrollment::query()->where('term_id', $term->id)->select('student_id'))
            ->orderBy('name')->get();
        foreach ($unassigned as $s) {
            $sheet->fromArray([$r - 1], null, "A{$r}");
            $sheet->setCellValueExplicit("B{$r}", $s->nis, 's');
            $sheet->fromArray([$s->name, $s->gender, '–', $s->is_active ? 'Aktif' : 'Nonaktif'], null, "C{$r}");
            $r++;
        }

        $this->widths($sheet, [6, 16, 40, 6, 10, 12]);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:F'.max(1, $r - 1));

        return $this->download($book, 'Daftar_Siswa_'.$term->fileSlug().'.xlsx');
    }

    /** @param list<array<string, mixed>> $rows analysed import rows */
    public function importErrorReport(array $rows, string $filename): StreamedResponse
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Laporan Galat');
        $this->header($sheet, 1, ['Baris', 'Status', 'NIS', 'Nama', 'Kelas', 'Keterangan']);

        $labels = ['error' => 'Galat', 'warning' => 'Peringatan'];
        $r = 2;
        foreach ($rows as $row) {
            if (! isset($labels[$row['status']])) {
                continue;
            }
            $sheet->fromArray([$row['line'], $labels[$row['status']]], null, "A{$r}");
            $sheet->setCellValueExplicit("C{$r}", (string) $row['nis'], 's');
            $sheet->fromArray([$row['file_name'] ?? $row['name'], $row['class'], implode("\n", $row['messages'])], null, "D{$r}");
            $sheet->getStyle("F{$r}")->getAlignment()->setWrapText(true);
            $color = $row['status'] === 'error' ? 'FDECEA' : 'FFF4E5';
            $sheet->getStyle("A{$r}:F{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
            $r++;
        }

        $this->widths($sheet, [8, 12, 14, 30, 8, 80]);
        $sheet->freezePane('A2');

        return $this->download($book, $filename);
    }

    private function header(Worksheet $sheet, int $row, array $labels): void
    {
        $sheet->fromArray($labels, null, "A{$row}");
        $last = chr(ord('A') + count($labels) - 1);
        $style = $sheet->getStyle("A{$row}:{$last}{$row}");
        $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::NAVY);
    }

    private function widths(Worksheet $sheet, array $widths): void
    {
        foreach ($widths as $i => $w) {
            $sheet->getColumnDimension(chr(ord('A') + $i))->setWidth($w);
        }
    }

    private function listValidation(Worksheet $sheet, string $range, string $formula): void
    {
        $v = new DataValidation;
        $v->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowDropDown(true)
            ->setShowErrorMessage(true)
            ->setErrorTitle('Nilai tidak valid')
            ->setError('Pilih dari daftar.')
            ->setFormula1($formula);
        $sheet->setDataValidation($range, $v);
    }

    private function download(Spreadsheet $book, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
