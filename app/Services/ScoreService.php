<?php

namespace App\Services;

use App\Domain\Cefr\CefrCalculator;
use App\Domain\Cefr\Level;
use App\Domain\Cefr\ScoringConfig;
use App\Exceptions\BusinessRuleException;
use App\Models\ActivityLog;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Score;
use App\Models\ScoringConfig as ScoringConfigModel;
use App\Support\PinSession;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Score grid data & autosave (PRD-04 §4). */
class ScoreService
{
    public const LOG_MERGE_SECONDS = 60;

    /** Request field → scores column (skills map to themselves). */
    public const COLUMNS = ['comment' => 'teacher_comment', 'natgeo' => 'natgeo_level'];

    public function __construct(private ActivityLogger $logger, private Request $request) {}

    public function config(Classroom $classroom): ScoringConfig
    {
        $model = ScoringConfigModel::query()
            ->where('term_id', $classroom->term_id)
            ->where('grade', $classroom->grade)
            ->first();

        if (! $model) {
            throw new BusinessRuleException("Pengaturan penilaian Kelas {$classroom->grade} untuk periode ini belum ada.");
        }

        return ScoringConfig::fromModel($model);
    }

    /** @return list<array<string, mixed>> */
    public function rows(Classroom $classroom): array
    {
        return $classroom->enrollments()->with(['student', 'score'])->get()
            ->map(fn (Enrollment $e) => [
                'enrollment_id' => $e->id,
                'roll_no' => $e->roll_no,
                'student_id' => $e->student_id,
                'nis' => $e->student->nis,
                'name' => $e->student->name,
                'is_active' => $e->student->is_active,
                ...$this->scorePayload($e->score),
            ])
            ->all();
    }

    /** @return array{scores: array<string, float|null>, natgeo: ?string, comment: ?string, version: int} */
    public function scorePayload(?Score $score): array
    {
        return [
            'scores' => array_combine(Score::SKILLS, array_map(fn ($s) => $score?->{$s}, Score::SKILLS)),
            'natgeo' => $score?->natgeo_level,
            'comment' => $score?->teacher_comment,
            'version' => $score?->version ?? 0,
        ];
    }

    /** @return array{complete: int, total: int} */
    public function progress(Classroom $classroom): array
    {
        return [
            'complete' => Score::query()->complete()->whereIn('enrollment_id', $classroom->enrollments()->select('id'))->count(),
            'total' => $classroom->enrollments()->count(),
        ];
    }

    /** @return array<string, string> band => overall comment */
    public static function overallComments(): array
    {
        return (array) app(Settings::class)->get('band_comments');
    }

    /**
     * Saves one cell (a skill score, the NatGeo level or the teacher comment) with optimistic locking.
     *
     * @return array<string, mixed> response body
     */
    public function update(Classroom $classroom, Enrollment $enrollment, string $field, mixed $value, int $version): array
    {
        abort_unless($enrollment->classroom_id === $classroom->id, 404);

        $config = $this->config($classroom);
        $value = $this->validateValue($field, $value, $classroom);

        return DB::transaction(function () use ($classroom, $enrollment, $field, $value, $version, $config) {
            // Re-read under lock so concurrent saves for the same row are serialised.
            $locked = Classroom::query()->lockForUpdate()->findOrFail($classroom->id);

            if ($locked->isFinal()) {
                throw BusinessRuleException::classLocked($classroom->name);
            }

            $score = Score::query()->lockForUpdate()->firstOrNew(['enrollment_id' => $enrollment->id]);
            $current = $score->exists ? $score->version : 0;

            if ($version !== $current) {
                throw new ScoreConflictException($this->scorePayload($score->exists ? $score : null));
            }

            $column = self::COLUMNS[$field] ?? $field;
            $old = $score->{$column};

            if ($old !== $value) {
                $score->{$column} = $value;
                $score->version = $current + 1;
                $score->save();
                $this->log($classroom, $enrollment, $field, $old, $value);
                RecapService::forgetClass($classroom->id);
            }

            $payload = $this->scorePayload($score);

            return [
                ...$payload,
                'result' => CefrCalculator::calculate($config, $payload['scores'])->toArray(),
                'class_progress' => $this->progress($classroom),
            ];
        });
    }

    private function validateValue(string $field, mixed $value, Classroom $classroom): float|string|null
    {
        if ($field === 'natgeo') {
            if (! $classroom->usesNatGeo()) {
                throw ValidationException::withMessages(['value' => 'Level National Geographic hanya untuk Kelas '.Classroom::NATGEO_GRADE.'.']);
            }
            if ($value === null || $value === '') {
                return null;
            }
            if (! in_array($value, Level::values(), true)) {
                throw ValidationException::withMessages(['value' => 'Level harus salah satu dari '.implode(', ', Level::values()).'.']);
            }

            return $value;
        }

        if ($field === 'comment') {
            $text = trim((string) $value);

            if (mb_strlen($text) > 600) {
                throw ValidationException::withMessages(['value' => 'Komentar maksimal 600 karakter.']);
            }

            return $text === '' ? null : $text;
        }

        if ($value === null || $value === '') {
            return null;
        }

        $label = ucfirst($field);
        [$min, $max] = [(int) ScoringConfig::MIN, (int) ScoringConfig::MAX];

        if (! is_numeric(str_replace(',', '.', (string) $value))) {
            throw ValidationException::withMessages(['value' => "{$label} '{$value}' bukan angka."]);
        }

        $number = (float) str_replace(',', '.', (string) $value);

        if ($number < $min || $number > $max) {
            throw ValidationException::withMessages(['value' => "Nilai {$label} harus antara {$min} dan {$max}."]);
        }

        if (fmod($number * 2, 1.0) !== 0.0) {
            throw ValidationException::withMessages(['value' => "Nilai {$label} harus bulat atau kelipatan 0,5."]);
        }

        return $number;
    }

    /** "Nilai Listening Kevin Wijaya (5A) diubah 13 → 15"; edits to one cell within 60 s are merged. */
    private function log(Classroom $classroom, Enrollment $enrollment, string $field, mixed $old, mixed $new): void
    {
        $enrollment->loadMissing('student');
        $session = PinSession::tag($this->request);

        $recent = ActivityLog::query()
            ->where('action', 'score.updated')
            ->where('subject_type', 'Enrollment')
            ->where('subject_id', $enrollment->id)
            ->where('session_short', $session)
            ->where('created_at', '>=', now()->subSeconds(self::LOG_MERGE_SECONDS))
            ->latest('id')
            ->first();

        $fmt = fn ($v) => $v === null ? '–' : (is_float($v) ? rtrim(rtrim(number_format($v, 1, ',', ''), '0'), ',') : '"'.mb_strimwidth((string) $v, 0, 40, '…').'"');

        if ($recent && ($recent->meta['field'] ?? null) === $field) {
            $old = $recent->meta['old'];
            $recent->delete();
        }

        $what = match ($field) {
            'comment' => 'Komentar',
            'natgeo' => 'Level National Geographic',
            default => 'Nilai '.ucfirst($field),
        };
        $this->logger->log(
            'score.updated',
            'nilai',
            "{$what} {$enrollment->student->name} ({$classroom->name}) diubah {$fmt($old)} → {$fmt($new)}",
            $enrollment,
            ['field' => $field, 'old' => $old, 'new' => $new],
        );
    }
}
