<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Term;
use App\Support\ScoreDiff;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use Throwable;

/**
 * Migration from the old program ICP_English_Progress_Report.xlsx (PRD-03 §5.1):
 * sheets "Grade 3"–"Grade 6", rows 6–45, columns B (NIPD) … I (comment).
 * Students must already exist (NISN is required, the old file has none); they are placed in the class named in column D and scores are set.
 */
class LegacyExcelImportService
{
    private const COLS = ['B' => 'nipd', 'C' => 'name', 'D' => 'class', 'E' => 'listening', 'F' => 'reading', 'G' => 'writing', 'H' => 'speaking', 'I' => 'comment'];

    public function __construct(
        private ScoreService $scores,
        private EnrollmentService $enrollments,
        private ActivityLogger $logger,
    ) {}

    /** @return array<string, array<string, mixed>> keyed "Grade 5!7" */
    public function read(string $path): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $book = $reader->load($path);
        } catch (Throwable) {
            throw new BusinessRuleException('File tidak dapat dibaca. Pastikan file Excel tidak dikunci kata sandi.', 'FILE_UNREADABLE');
        }

        $rows = [];
        $found = 0;
        foreach ([3, 4, 5, 6] as $grade) {
            $sheet = $book->getSheetByName("Grade {$grade}");
            if (! $sheet) {
                continue;
            }
            $found++;

            for ($r = 6; $r <= 45; $r++) {
                $row = ['grade' => $grade, 'sheet' => "Grade {$grade}", 'line' => $r];
                foreach (self::COLS as $col => $key) {
                    $v = $sheet->getCell("{$col}{$r}")->getValue();
                    if ($v instanceof RichText) {
                        $v = $v->getPlainText();
                    }
                    $row[$key] = is_float($v) && floor($v) === $v && $key === 'nipd' ? (string) (int) $v : trim((string) $v);
                }
                if ($row['nipd'] === '' && $row['name'] === '') {
                    continue;
                }
                $rows["Grade {$grade}!{$r}"] = $row;
            }
        }

        if ($found === 0) {
            throw new BusinessRuleException('Sheet "Grade 3"–"Grade 6" tidak ditemukan. Pilih file ICP_English_Progress_Report.xlsx.', 'HEADER_MISSING');
        }

        return $rows;
    }

    /** @return array{rows: list<array<string, mixed>>, summary: array<string, int>} */
    public function analyse(array $raw, Term $term, bool $overwrite): array
    {
        $term->loadMissing('academicYear');
        $classes = $term->classrooms()->get()->keyBy(fn ($c) => mb_strtoupper($c->name));
        $students = Student::query()->whereIn('nipd', array_column($raw, 'nipd'))->get()->keyBy('nipd');
        $current = Enrollment::query()->with(['classroom', 'score'])->where('term_id', $term->id)
            ->whereIn('student_id', $students->pluck('id'))->get()->keyBy('student_id');
        $seen = [];
        $rows = [];

        foreach ($raw as $r) {
            $where = "Baris {$r['line']} ({$r['sheet']})";
            $errors = [];
            $warnings = [];
            $changes = [];
            $nipd = $r['nipd'];
            $student = $students->get($nipd);
            $class = $classes->get(mb_strtoupper($r['class']));
            $diff = null;

            if ($nipd === '') {
                $errors[] = "{$where}: NIPD wajib diisi.";
            } elseif (! preg_match(StudentImportService::NIPD_PATTERN, $nipd)) {
                $errors[] = "{$where}: NIPD '{$nipd}': ".StudentImportService::NIPD_MESSAGE;
            } elseif (isset($seen[$nipd])) {
                $errors[] = "{$where}: NIPD {$nipd} muncul lebih dari sekali ({$seen[$nipd]}).";
            }
            $seen[$nipd] ??= $where;

            if (! $class) {
                $errors[] = "{$where}: Kelas '{$r['class']}' tidak ada di periode {$term->fullLabel()}.";
            } elseif ($class->grade !== $r['grade']) {
                $errors[] = "{$where}: Kelas {$class->name} bukan kelas {$r['grade']} (sheet {$r['sheet']}).";
            } elseif ($class->isFinal()) {
                $errors[] = "{$where}: Kelas {$class->name} sudah final.";
            }

            if (! $student) {
                // The old file has no NISN (required): students must be imported first.
                $errors[] = "{$where}: Siswa NIPD {$nipd} belum terdaftar. Impor data siswa (dengan NISN) lewat menu Impor → Siswa terlebih dahulu.";
            } elseif (mb_strtolower($r['name']) !== mb_strtolower($student->name) && $r['name'] !== '') {
                $warnings[] = "Nama di file '{$r['name']}' berbeda dengan nama di sistem '{$student->name}'; dipakai nama sistem.";
            }

            $enrollment = $student ? $current->get($student->id) : null;
            if ($class && ! $errors) {
                if (! $enrollment) {
                    $changes[] = "Masuk kelas {$class->name}";
                } elseif ($enrollment->classroom_id !== $class->id) {
                    if ($enrollment->classroom->isFinal() || ($enrollment->classroom->grade !== $class->grade && $enrollment->hasScores())) {
                        $errors[] = "{$where}: Siswa terdaftar di kelas {$enrollment->classroom->name} dan tidak dapat dipindah.";
                    } else {
                        $changes[] = "Kelas {$enrollment->classroom->name} → {$class->name}";
                    }
                }

                $existingScore = $enrollment && $enrollment->classroom_id === $class->id ? $enrollment->score : $enrollment?->score;
                $diff = ScoreDiff::compare($existingScore, array_intersect_key($r, array_flip(ScoreDiff::FIELDS)), $class->usesNatGeo(), $overwrite, $where);
                $errors = [...$errors, ...$diff['errors']];
                $warnings = [...$warnings, ...$diff['warnings']];
                $changes = [...$changes, ...$diff['changes']];
            }

            $status = match (true) {
                $errors !== [] => 'error',
                ! $student => 'new',
                $warnings !== [] => 'warning',
                $changes !== [] => 'updated',
                default => 'unchanged',
            };

            $rows[] = [
                'line' => $r['line'],
                'sheet' => $r['sheet'],
                'nipd' => $nipd,
                'name' => $student->name ?? $r['name'],
                'file_name' => $r['name'],
                'class' => $class->name ?? $r['class'],
                'status' => $status,
                'messages' => [...$errors, ...$warnings],
                'changes' => $changes,
                'set' => $errors ? [] : ($diff['set'] ?? []),
                'create' => ! $errors && (! $student || ! $enrollment || $enrollment->classroom_id !== $class?->id),
            ];
        }

        return ['rows' => $rows, 'summary' => ScoreImportService::summarise($rows)];
    }

    /** @return array{created: int, students: int, values: int} */
    public function commit(array $raw, Term $term, bool $overwrite): array
    {
        $analysis = $this->analyse($raw, $term, $overwrite);
        $apply = array_filter($analysis['rows'], fn ($r) => $r['status'] !== 'error' && ($r['create'] || $r['set']));

        if (! $apply) {
            throw new BusinessRuleException('Tidak ada data baru untuk diimpor.');
        }

        $classes = $term->classrooms()->get()->keyBy('name');

        $result = DB::transaction(function () use ($apply, $classes) {
            $created = 0;
            $values = 0;

            foreach ($apply as $row) {
                $student = Student::query()->where('nipd', $row['nipd'])->firstOrFail();

                $class = $classes[$row['class']];
                $enrollment = $this->enrollments->assign($student, $class, allowMove: true, log: false)
                    ?? Enrollment::query()->where(['classroom_id' => $class->id, 'student_id' => $student->id])->firstOrFail();

                ScoreDiff::apply($enrollment->id, $row['set']);
                $values += count($row['set']);
                RecapService::forgetClass($class->id);
            }

            return ['created' => $created, 'students' => count($apply), 'values' => $values];
        });

        $this->logger->log('import.legacy_excel', 'data', "Impor file Excel lama ke {$term->fullLabel()}: {$result['students']} siswa ({$result['created']} baru), {$result['values']} nilai.");

        return $result;
    }
}
