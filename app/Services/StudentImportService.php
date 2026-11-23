<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Term;
use App\Support\SpreadsheetReader;
use Illuminate\Support\Facades\DB;

/**
 * Two-step student import (PRD-03 §5): analyse() for the preview (no writes),
 * commit() re-analyses against the current database and applies valid rows in one transaction.
 */
class StudentImportService
{
    public const COLUMNS = [
        'nipd' => ['nipd', 'nis', 'no induk', 'nomor induk', 'student id'],
        'nisn' => ['nisn'],
        'name' => ['nama', 'nama lengkap', 'nama siswa', 'student name', 'name'],
        'gender' => ['l/p', 'jk', 'jenis kelamin', 'gender'],
        'class' => ['kelas', 'class', 'rombel'],
    ];

    public const NAME_PATTERN = "/^[\\p{L}\\s'.\\-]{2,100}$/u";

    public const NIPD_PATTERN = '/^\d{1,5}$/';

    public const NISN_PATTERN = '/^\d{1,10}$/';

    public const NIPD_MESSAGE = 'NIPD harus berupa angka, maksimal 5 digit.';

    public const NISN_MESSAGE = 'NISN harus berupa angka, maksimal 10 digit.';

    public function __construct(private EnrollmentService $enrollments, private ActivityLogger $logger) {}

    /** @return array<int, array<string, string>> raw rows keyed by spreadsheet row */
    public function read(string $path, string $extension): array
    {
        return SpreadsheetReader::read($path, $extension, self::COLUMNS, ['nipd', 'nisn', 'name', 'class'])['rows'];
    }

    /**
     * @param  array<int, array<string, string>>  $raw
     * @return array{rows: list<array<string, mixed>>, summary: array<string, int>}
     */
    public function analyse(array $raw, Term $term): array
    {
        $term->loadMissing('academicYear');
        $classes = $term->classrooms()->get()->keyBy(fn (Classroom $c) => mb_strtoupper($c->name));
        $students = Student::query()->whereIn('nipd', array_column($raw, 'nipd'))->get()->keyBy('nipd');
        $byNisn = Student::query()->whereIn('nisn', array_column($raw, 'nisn'))->get()->keyBy('nisn');
        $current = Enrollment::query()
            ->with('classroom')
            ->where('term_id', $term->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->keyBy('student_id');

        $firstLine = [];
        $firstNisn = [];
        $rows = [];

        foreach ($raw as $line => $r) {
            $errors = [];
            $warnings = [];
            $changes = [];
            $nipd = $r['nipd'];
            $nisn = $r['nisn'] ?? '';
            $name = preg_replace('/\s+/', ' ', $r['name']);
            $gender = self::gender($r['gender'] ?? '');
            $className = mb_strtoupper($r['class']);

            if ($nipd === '') {
                $errors[] = "Baris {$line}: NIPD wajib diisi.";
            } elseif (! preg_match(self::NIPD_PATTERN, $nipd)) {
                $errors[] = "Baris {$line}: NIPD '{$nipd}': ".self::NIPD_MESSAGE;
            } elseif (isset($firstLine[$nipd])) {
                $errors[] = "Baris {$line}: NIPD {$nipd} muncul lebih dari sekali (baris {$firstLine[$nipd]} dan {$line}).";
            } else {
                $firstLine[$nipd] = $line;
            }

            if ($nisn === '') {
                $errors[] = "Baris {$line}: NISN wajib diisi.";
            } elseif (! preg_match(self::NISN_PATTERN, $nisn)) {
                $errors[] = "Baris {$line}: NISN '{$nisn}': ".self::NISN_MESSAGE;
            } elseif (isset($firstNisn[$nisn])) {
                $errors[] = "Baris {$line}: NISN {$nisn} muncul lebih dari sekali (baris {$firstNisn[$nisn]} dan {$line}).";
            } elseif (($owner = $byNisn->get($nisn)) && $owner->nipd !== $nipd) {
                $errors[] = "Baris {$line}: NISN {$nisn} sudah dipakai siswa {$owner->name} (NIPD {$owner->nipd}).";
            } else {
                $firstNisn[$nisn] = $line;
            }

            if ($gender === false) {
                $errors[] = "Baris {$line}: L/P '{$r['gender']}' harus L atau P.";
                $gender = null;
            }

            $classroom = $classes->get($className);
            if ($className === '') {
                $errors[] = "Baris {$line}: Kelas wajib diisi.";
            } elseif (! $classroom) {
                $errors[] = "Baris {$line}: Kelas '{$r['class']}' tidak ada di periode {$term->fullLabel()}.";
            } elseif ($classroom->isFinal()) {
                $errors[] = "Baris {$line}: Kelas {$classroom->name} sudah final. Batalkan finalisasi untuk menambah siswa.";
            }

            $student = $students->get($nipd);

            if (! $student) {
                if ($name === '') {
                    $errors[] = "Baris {$line}: Nama wajib diisi.";
                } elseif (! preg_match(self::NAME_PATTERN, $name)) {
                    $errors[] = "Baris {$line}: Nama '{$name}' hanya boleh berisi huruf, spasi, apostrof, titik, atau tanda hubung (2–100 karakter).";
                }
            } else {
                if (! $student->is_active) {
                    $errors[] = "Baris {$line}: Siswa NIPD {$nipd} berstatus nonaktif. Aktifkan di menu Siswa terlebih dahulu.";
                }

                if ($name !== '' && mb_strtolower($name) !== mb_strtolower($student->name)) {
                    $warnings[] = "Nama di file '{$name}' berbeda dengan nama di sistem '{$student->name}'; dipakai nama sistem.";
                }

                if ($nisn !== '' && $nisn !== $student->nisn) {
                    $changes[] = 'NISN '.($student->nisn ?? '–')." → {$nisn}";
                }

                if ($gender && $gender !== $student->gender) {
                    $changes[] = 'L/P '.($student->gender ?? '–')." → {$gender}";
                }

                $enrollment = $current->get($student->id);
                if ($classroom && ! $errors) {
                    if (! $enrollment) {
                        $changes[] = "Masuk kelas {$classroom->name}";
                    } elseif ($enrollment->classroom_id !== $classroom->id) {
                        $from = $enrollment->classroom;
                        if ($from->isFinal()) {
                            $errors[] = "Baris {$line}: Siswa terdaftar di kelas {$from->name} yang sudah final.";
                        } elseif ($from->grade !== $classroom->grade && $enrollment->hasScores()) {
                            $errors[] = "Baris {$line}: Siswa sudah memiliki nilai di {$from->name}; hanya bisa dipindah ke kelas jenjang yang sama.";
                        } else {
                            $changes[] = "Kelas {$from->name} → {$classroom->name}";
                        }
                    }
                }
            }

            $status = match (true) {
                $errors !== [] => 'error',
                ! $student => 'new',
                $warnings !== [] => 'warning',
                $changes !== [] => 'updated',
                default => 'unchanged',
            };

            $rows[] = [
                'line' => $line,
                'nipd' => $nipd,
                'nisn' => $nisn,
                'name' => $student->name ?? $name,
                'file_name' => $name,
                'gender' => $gender ?: ($student->gender ?? null),
                'class' => $classroom->name ?? $r['class'],
                'status' => $status,
                'messages' => [...$errors, ...$warnings],
                'changes' => $changes,
            ];
        }

        $summary = ['total' => count($rows)];
        foreach (['new', 'updated', 'unchanged', 'warning', 'error'] as $s) {
            $summary[$s] = count(array_filter($rows, fn ($r) => $r['status'] === $s));
        }
        $summary['importable'] = $summary['new'] + $summary['updated'] + $summary['warning'];

        return ['rows' => $rows, 'summary' => $summary];
    }

    /**
     * @param  array<int, array<string, string>>  $raw
     * @return array{created: int, updated: int, skipped: int}
     */
    public function commit(array $raw, Term $term): array
    {
        $analysis = $this->analyse($raw, $term);

        if ($analysis['summary']['importable'] === 0) {
            throw new BusinessRuleException('Tidak ada baris valid untuk diimpor.');
        }

        $classes = $term->classrooms()->get()->keyBy('name');

        $result = DB::transaction(function () use ($analysis, $classes) {
            $created = 0;
            $updated = 0;

            foreach ($analysis['rows'] as $row) {
                if (! in_array($row['status'], ['new', 'updated', 'warning'], true)) {
                    continue;
                }

                $student = Student::query()->firstOrNew(['nipd' => $row['nipd']]);
                $isNew = ! $student->exists;

                if ($isNew) {
                    $student->fill(['nisn' => $row['nisn'], 'name' => $row['file_name'], 'gender' => $row['gender'], 'is_active' => true])->save();
                } else {
                    $student->update(['nisn' => $row['nisn']] + ($row['gender'] ? ['gender' => $row['gender']] : []));
                }

                $this->enrollments->assign($student, $classes[$row['class']], allowMove: true, log: false);
                $isNew ? $created++ : $updated++;
            }

            return ['created' => $created, 'updated' => $updated, 'skipped' => $analysis['summary']['error']];
        });

        $term->loadMissing('academicYear');
        $this->logger->log(
            'import.students',
            'data',
            "Impor siswa ke {$term->fullLabel()}: {$result['created']} baru, {$result['updated']} diperbarui, {$result['skipped']} baris galat dilewati.",
        );

        return $result;
    }

    /** "L"/"P" for recognised values, null for blank, false for anything else. */
    public static function gender(string $value): string|false|null
    {
        $v = mb_strtolower(trim($value));

        return match (true) {
            $v === '' => null,
            in_array($v, ['l', 'lk', 'laki-laki', 'laki laki', 'laki', 'male', 'm'], true) => 'L',
            in_array($v, ['p', 'pr', 'perempuan', 'wanita', 'female', 'f'], true) => 'P',
            default => false,
        };
    }
}
