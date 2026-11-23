<?php

namespace App\Services;

use App\Domain\Cefr\Band;
use App\Domain\Cefr\CefrCalculator;
use App\Domain\Cefr\Level;
use App\Domain\Cefr\ScoringConfig;
use App\Domain\Cefr\Skill;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\FinalResult;
use App\Models\Term;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Score recaps & the per-class score template (PRD-03 §5.1, §6).
 * Final classes come from final_results (snapshot); Draft classes from a live calculation,
 * marked "(DRAFT)" in the sheet title.
 */
class RecapExportService
{
    private const NAVY = '1F3A68';

    private const INPUT = 'FFF8E1';

    private const COMPUTED = 'F3F4F6';

    public function __construct(private ScoreService $scores) {}

    /** @return list<array<string, mixed>> one row per student in roll order */
    public function classRows(Classroom $classroom): array
    {
        $finals = $classroom->isFinal()
            ? FinalResult::query()->where('classroom_id', $classroom->id)->where('is_current', true)->get()->keyBy('enrollment_id')
            : collect();
        $config = $this->scores->config($classroom);

        return $classroom->enrollments()->with(['student', 'score'])->get()->map(function (Enrollment $e) use ($finals, $config) {
            $f = $finals->get($e->id);
            $payload = $this->scores->scorePayload($e->score);
            $raw = $f
                ? ['listening' => $f->listening, 'reading' => $f->reading, 'writing' => $f->writing, 'speaking' => $f->speaking]
                : $payload['scores'];
            $r = $f ? null : CefrCalculator::calculate($config, $raw);

            return [
                'no' => $e->roll_no,
                'nipd' => $e->student->nipd,
                'name' => $e->student->name,
                'raw' => $raw,
                'comment' => $e->score?->teacher_comment,
                'level' => $f ? array_combine(Skill::values(), array_map(fn ($s) => $f->{"{$s}_level"}, Skill::values())) : $r->level,
                'total' => $f ? $f->total : $r->total,
                'overall_level' => $f ? $f->overall_level : $r->overallLevel,
                'natgeo' => $f ? $f->natgeo_level : $payload['natgeo'],
                'strongest' => $f?->strongest_skill ?? $r->strongest,
            ];
        })->all();
    }

    public function classRecap(Classroom $classroom): StreamedResponse
    {
        $classroom->loadMissing('term.academicYear');
        $book = new Spreadsheet;
        $this->fillClassSheet($book->getActiveSheet(), $classroom);

        return $this->download($book, "Rekap_{$classroom->name}_{$classroom->term->fileSlug()}.xlsx");
    }

    public function termRecap(Term $term): StreamedResponse
    {
        $term->loadMissing('academicYear');
        $book = new Spreadsheet;
        $summary = $book->getActiveSheet()->setTitle('Ringkasan');
        $classes = $term->classrooms()->orderBy('grade')->orderBy('name')->get();

        $summary->setCellValue('A1', 'REKAP PERIODE — '.$term->fullLabel());
        $summary->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB(self::NAVY);
        $head = ['Kelas', 'Status', 'Siswa', 'Dinilai', 'Avg Listening', 'Avg Reading', 'Avg Writing', 'Avg Speaking', 'Avg Total', ...Band::values(), ...array_map(fn ($l) => "NatGeo {$l}", Level::values())];
        $this->header($summary, 3, $head);

        $r = 4;
        foreach ($classes as $c) {
            $rows = collect($this->classRows($c));
            $done = $rows->whereNotNull('overall_level');
            $avg = fn ($fn) => $done->count() ? round($done->avg($fn), 1) : null;
            $summary->fromArray([
                $c->name.($c->isFinal() ? '' : ' (DRAFT)'),
                $c->isFinal() ? 'Final' : 'Draft',
                $rows->count(),
                $done->count(),
                ...array_map(fn ($s) => $avg(fn ($x) => $x['raw'][$s]), Skill::values()),
                $avg('total'),
                ...array_map(fn ($l) => $done->where('overall_level', $l)->count(), Band::values()),
                ...array_map(fn ($l) => $c->usesNatGeo() ? $rows->where('natgeo', $l)->count() : null, Level::values()),
            ], null, "A{$r}", true);
            $r++;

            $sheet = $book->createSheet();
            $this->fillClassSheet($sheet, $c);
        }

        foreach (range(1, count($head)) as $i) {
            $summary->getColumnDimensionByColumn($i)->setWidth($i === 1 ? 14 : 12);
        }
        $summary->freezePane('B4');
        $book->setActiveSheetIndex(0);

        return $this->download($book, "Rekap_Periode_{$term->fileSlug()}.xlsx");
    }

    /** Same columns as the Excel "Grade X" sheet + a class summary block on the right. */
    private function fillClassSheet(Worksheet $sheet, Classroom $c): void
    {
        $c->loadMissing('term.academicYear');
        $draft = ! $c->isFinal();
        $sheet->setTitle(mb_substr($c->name.($draft ? ' (DRAFT)' : ''), 0, 31));
        $rows = $this->classRows($c);
        $config = $this->scores->config($c);

        $sheet->setCellValue('A1', "REKAP NILAI KELAS {$c->name} — {$c->term->fullLabel()}".($draft ? ' (DRAFT)' : ''));
        $sheet->setCellValue('A2', "Wali kelas: {$c->homeroom_teacher} • Intermediate ≥ {$config->intermediate} • Advanced ≥ {$config->advanced} (per skill, 5–25)");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB(self::NAVY);

        $head = ['No', 'NIPD', 'Nama', 'Kelas', 'Listening', 'Reading', 'Writing', 'Speaking', 'Komentar guru',
            'Level Listening', 'Level Reading', 'Level Writing', 'Level Speaking',
            'Total', 'Level Total', 'NatGeo (CEFR)', 'Skill terkuat'];
        $this->header($sheet, 4, $head);

        $r = 5;
        foreach ($rows as $row) {
            $sheet->fromArray([$row['no']], null, "A{$r}");
            $sheet->setCellValueExplicit("B{$r}", $row['nipd'], 's');
            $sheet->fromArray([
                $row['name'], $c->name,
                ...array_values($row['raw']),
                $row['comment'],
                ...array_values($row['level']),
                $row['total'],
                $row['overall_level'],
                $c->usesNatGeo() ? $row['natgeo'] : null,
                $row['strongest'] ? ucfirst($row['strongest']) : null,
            ], null, "C{$r}", true);

            foreach (['J', 'K', 'L', 'M', 'O'] as $i => $col) {
                $level = $i < 4 ? array_values($row['level'])[$i] : $row['overall_level'];
                if ($level) {
                    $this->levelFill($sheet, "{$col}{$r}", $level);
                }
            }
            $r++;
        }

        $last = max(5, $r - 1);
        $sheet->getStyle("N5:N{$last}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COMPUTED);
        $sheet->getStyle("E5:H{$last}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::INPUT);
        $sheet->getStyle("I5:I{$last}")->getAlignment()->setWrapText(true);
        $widths = [5, 12, 28, 7, 9, 9, 9, 9, 40, 13, 13, 13, 13, 8, 13, 13, 13];
        foreach ($widths as $i => $w) {
            $sheet->getColumnDimensionByColumn($i + 1)->setWidth($w);
        }
        $sheet->freezePane('D5');

        // Class summary (like columns W–AB of the Excel sheet).
        $sx = 19; // S
        $done = collect($rows)->whereNotNull('overall_level');
        $cell = fn (int $col, int $row) => Coordinate::stringFromColumnIndex($col).$row;
        $sheet->setCellValue($cell($sx, 3), 'RINGKASAN KELAS');
        $sheet->getStyle($cell($sx, 3))->getFont()->setBold(true)->getColor()->setRGB(self::NAVY);
        $this->header($sheet, 4, ['Level', 'Listening', 'Reading', 'Writing', 'Speaking', 'Total'], $sx);
        $y = 5;
        foreach (Band::values() as $l) {
            $sheet->fromArray([
                $l,
                ...array_map(fn ($s) => collect($rows)->filter(fn ($x) => $x['level'][$s] === $l)->count(), Skill::values()),
                $done->where('overall_level', $l)->count(),
            ], null, $cell($sx, $y), true);
            $this->levelFill($sheet, $cell($sx, $y), $l);
            $y++;
        }
        $sheet->fromArray(['Rata-rata', ...array_map(
            fn ($s) => ($n = collect($rows)->filter(fn ($x) => $x['raw'][$s] !== null))->count() ? round($n->avg(fn ($x) => $x['raw'][$s]), 1) : '-',
            Skill::values(),
        ), $done->count() ? round($done->avg('total'), 1) : '-'], null, $cell($sx, $y), true);
        $sheet->fromArray(['Siswa dinilai', ...array_map(fn ($s) => collect($rows)->filter(fn ($x) => $x['raw'][$s] !== null)->count(), Skill::values()), $done->count()], null, $cell($sx, $y + 1), true);
        foreach (range($sx, $sx + 5) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setWidth($col === $sx ? 13 : 10);
        }
    }

    /** template_nilai_{kelas}.xlsx: student list, 5–25 validation, NatGeo level for grade 6 (PRD-03 §5.1). */
    public function scoreTemplate(Classroom $classroom): StreamedResponse
    {
        $classroom->loadMissing('term.academicYear');
        $natgeo = $classroom->usesNatGeo();
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle(mb_substr("Nilai {$classroom->name}", 0, 31));

        $sheet->setCellValue('A1', "TEMPLATE NILAI KELAS {$classroom->name} — {$classroom->term->fullLabel()}");
        $sheet->setCellValue('A2', 'Isi skor 5–25 (boleh ,5).'.($natgeo ? ' NatGeo: Pre-A1, A1, A2, B1 atau B2.' : '')
            .' Sel kosong = nilai tidak diubah. Ketik - untuk mengosongkan (dengan opsi "Timpa"). Jangan ubah kolom NIPD.');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB(self::NAVY);
        $sheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('4B5563');
        $this->header($sheet, 3, ['No', 'NIPD*', 'Nama', 'Listening', 'Reading', 'Writing', 'Speaking', 'Komentar', ...($natgeo ? ['NatGeo'] : [])]);

        $r = 4;
        foreach ($this->classRows($classroom) as $row) {
            $sheet->fromArray([$row['no']], null, "A{$r}");
            $sheet->setCellValueExplicit("B{$r}", $row['nipd'], 's');
            $sheet->fromArray([$row['name'], ...array_values($row['raw']), $row['comment'], ...($natgeo ? [$row['natgeo']] : [])], null, "C{$r}", true);
            $r++;
        }
        $last = max(4, $r - 1);

        $sheet->getStyle("D4:H{$last}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::INPUT);
        foreach (['D' => 'listening', 'E' => 'reading', 'F' => 'writing', 'G' => 'speaking'] as $col => $skill) {
            $v = new DataValidation;
            $v->setType(DataValidation::TYPE_DECIMAL)->setOperator(DataValidation::OPERATOR_BETWEEN)
                ->setFormula1((string) ScoringConfig::MIN)->setFormula2((string) ScoringConfig::MAX)
                ->setAllowBlank(true)->setShowErrorMessage(true)
                ->setErrorTitle('Nilai tidak valid')->setError('Nilai harus 5–25 (kelipatan 0,5).');
            $sheet->setDataValidation("{$col}4:{$col}{$last}", $v);
        }
        if ($natgeo) {
            $v = new DataValidation;
            $v->setType(DataValidation::TYPE_LIST)->setFormula1('"'.implode(',', Level::values()).'"')
                ->setAllowBlank(true)->setShowDropDown(true)->setShowErrorMessage(true)
                ->setErrorTitle('Level tidak valid')->setError('Pilih Pre-A1, A1, A2, B1 atau B2.');
            $sheet->setDataValidation("I4:I{$last}", $v);
            $sheet->getStyle("I4:I{$last}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::INPUT);
        }
        foreach ([5, 14, 30, 10, 10, 10, 10, 50, 10] as $i => $w) {
            $sheet->getColumnDimensionByColumn($i + 1)->setWidth($w);
        }
        $sheet->getStyle("H4:H{$last}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $sheet->freezePane('D4');

        return $this->download($book, "template_nilai_{$classroom->name}.xlsx");
    }

    private function header(Worksheet $sheet, int $row, array $labels, int $startCol = 1): void
    {
        $sheet->fromArray($labels, null, Coordinate::stringFromColumnIndex($startCol).$row);
        $range = Coordinate::stringFromColumnIndex($startCol).$row.':'.Coordinate::stringFromColumnIndex($startCol + count($labels) - 1).$row;
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::NAVY);
        $style->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function levelFill(Worksheet $sheet, string $cell, string $level): void
    {
        $bg = ltrim(Band::BG[$level] ?? '#FFFFFF', '#');
        $fg = ltrim(Band::FG[$level] ?? '#111827', '#');
        $style = $sheet->getStyle($cell);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg);
        $style->getFont()->setBold(true)->getColor()->setRGB($fg);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function download(Spreadsheet $book, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
