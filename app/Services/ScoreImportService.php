<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Classroom;
use App\Support\ScoreDiff;
use App\Support\SpreadsheetReader;
use Illuminate\Support\Facades\DB;

/** "Impor nilai kelas" from template_nilai_{kelas}.xlsx (PRD-03 §5). */
class ScoreImportService
{
    public const COLUMNS = [
        'nipd' => ['nipd', 'no induk', 'student id', 'student id (nipd)'],
        'name' => ['nama', 'nama siswa', 'student name', 'name'],
        'listening' => ['listening'],
        'reading' => ['reading'],
        'writing' => ['writing'],
        'speaking' => ['speaking'],
        'natgeo' => ['natgeo', 'natgeo (cefr)', 'national geographic', 'cefr'],
        'comment' => ['komentar', 'komentar guru', 'comment', "teacher's comment", "teacher's comment (optional)"],
    ];

    public function __construct(private ScoreService $scores, private ActivityLogger $logger) {}

    public function read(string $path, string $extension): array
    {
        return SpreadsheetReader::read($path, $extension, self::COLUMNS, ['nipd'])['rows'];
    }

    /** @return array{rows: list<array<string, mixed>>, summary: array<string, int>} */
    public function analyse(array $raw, Classroom $classroom, bool $overwrite): array
    {
        if ($classroom->isFinal()) {
            throw new BusinessRuleException("Kelas {$classroom->name} sudah final. Batalkan finalisasi untuk mengimpor nilai.", 'CLASS_FINAL', 423);
        }

        $enrollments = $classroom->enrollments()->with(['student', 'score'])->get()->keyBy(fn ($e) => $e->student->nipd);
        $seen = [];
        $rows = [];

        foreach ($raw as $line => $r) {
            $where = "Baris {$line}";
            $nipd = $r['nipd'];
            $errors = [];
            $warnings = [];
            $diff = null;
            $e = null;

            if ($nipd === '') {
                $errors[] = "{$where}: NIPD wajib diisi.";
            } elseif (isset($seen[$nipd])) {
                $errors[] = "{$where}: NIPD {$nipd} muncul lebih dari sekali (baris {$seen[$nipd]} dan {$line}).";
            } elseif (! ($e = $enrollments->get($nipd))) {
                $errors[] = "{$where}: NIPD {$nipd} tidak terdaftar di kelas {$classroom->name}.";
            }
            $seen[$nipd] ??= $line;

            if ($e) {
                if (($r['name'] ?? '') !== '' && mb_strtolower(trim($r['name'])) !== mb_strtolower($e->student->name)) {
                    $warnings[] = "Nama di file '{$r['name']}' berbeda dengan nama di sistem '{$e->student->name}'; dipakai nama sistem.";
                }
                $diff = ScoreDiff::compare($e->score, array_intersect_key($r, array_flip(ScoreDiff::FIELDS)), $classroom->usesNatGeo(), $overwrite, $where);
                $errors = [...$errors, ...$diff['errors']];
                $warnings = [...$warnings, ...$diff['warnings']];
            }

            $status = match (true) {
                $errors !== [] => 'error',
                $warnings !== [] => 'warning',
                $diff && $diff['set'] && $diff['replaces'] => 'updated',
                $diff && $diff['set'] => 'new',
                default => 'unchanged',
            };

            $rows[] = [
                'line' => $line,
                'nipd' => $nipd,
                'name' => $e?->student->name ?? ($r['name'] ?? ''),
                'file_name' => $r['name'] ?? '',
                'class' => $classroom->name,
                'enrollment_id' => $e?->id,
                'status' => $status,
                'messages' => [...$errors, ...$warnings],
                'changes' => $diff['changes'] ?? [],
                'set' => $errors ? [] : ($diff['set'] ?? []),
            ];
        }

        return ['rows' => $rows, 'summary' => self::summarise($rows)];
    }

    /** @return array{students: int, values: int} */
    public function commit(array $raw, Classroom $classroom, bool $overwrite): array
    {
        $analysis = $this->analyse($raw, $classroom, $overwrite);
        $apply = array_filter($analysis['rows'], fn ($r) => $r['set'] !== []);

        if (! $apply) {
            throw new BusinessRuleException('Tidak ada nilai baru untuk diimpor.');
        }

        $values = DB::transaction(function () use ($apply, $classroom) {
            if ($classroom->fresh()->isFinal()) {
                throw BusinessRuleException::classLocked($classroom->name);
            }

            $n = 0;
            foreach ($apply as $row) {
                ScoreDiff::apply($row['enrollment_id'], $row['set']);
                $n += count($row['set']);
            }

            return $n;
        });

        RecapService::forgetClass($classroom->id);
        $this->logger->log('import.scores', 'nilai', "Impor nilai kelas {$classroom->name}: {$values} nilai pada ".count($apply).' siswa'.($overwrite ? ' (timpa aktif).' : '.'), $classroom);

        return ['students' => count($apply), 'values' => $values];
    }

    public static function summarise(array $rows): array
    {
        $summary = ['total' => count($rows)];
        foreach (['new', 'updated', 'unchanged', 'warning', 'error'] as $s) {
            $summary[$s] = count(array_filter($rows, fn ($r) => $r['status'] === $s));
        }
        $summary['importable'] = count(array_filter($rows, fn ($r) => ($r['set'] ?? []) !== [] || ($r['create'] ?? false)));

        return $summary;
    }
}
