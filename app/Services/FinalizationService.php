<?php

namespace App\Services;

use App\Domain\Cefr\CefrCalculator;
use App\Exceptions\BusinessRuleException;
use App\Models\Classroom;
use App\Models\Descriptor;
use App\Models\Enrollment;
use App\Models\Finalization;
use App\Models\FinalResult;
use App\Models\LevelMeta;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Finalise / un-finalise a class (PRD-04 §6). */
class FinalizationService
{
    public function __construct(
        private ScoreService $scores,
        private Settings $settings,
        private ActivityLogger $logger,
        private Request $request,
    ) {}

    /**
     * Active students whose four scores (and, in grade 6, NatGeo level) are not all filled in.
     *
     * @return list<array{enrollment_id: int, name: string, missing: list<string>}>
     */
    public function incomplete(Classroom $classroom): array
    {
        $fields = ['listening', 'reading', 'writing', 'speaking', ...($classroom->usesNatGeo() ? ['natgeo_level'] : [])];

        return $classroom->enrollments()->with(['student', 'score'])->get()
            ->filter(fn (Enrollment $e) => $e->student->is_active)
            ->map(fn (Enrollment $e) => [
                'enrollment_id' => $e->id,
                'name' => $e->student->name,
                'missing' => collect($fields)
                    ->filter(fn ($s) => $e->score?->{$s} === null)
                    ->map(fn ($s) => $s === 'natgeo_level' ? 'NatGeo' : ucfirst($s))->values()->all(),
            ])
            ->filter(fn ($r) => $r['missing'] !== [])
            ->values()->all();
    }

    public function finalize(Classroom $classroom): Finalization
    {
        return DB::transaction(function () use ($classroom) {
            $classroom = Classroom::query()->with('term.academicYear')->lockForUpdate()->findOrFail($classroom->id);

            if ($classroom->isFinal()) {
                throw new BusinessRuleException("Kelas {$classroom->name} sudah final.", 'CLASS_FINAL', 423);
            }

            $missing = $this->incomplete($classroom);
            if ($missing) {
                throw new BusinessRuleException(count($missing).' siswa belum lengkap. Lengkapi nilai sebelum memfinalkan.', 'INCOMPLETE');
            }

            $enrollments = $classroom->enrollments()->with(['student', 'score'])->get()->filter(fn ($e) => $e->student->is_active);
            if ($enrollments->isEmpty()) {
                throw new BusinessRuleException("Kelas {$classroom->name} belum memiliki siswa aktif.");
            }

            $config = $this->scores->config($classroom);
            $snapshot = $this->snapshot($classroom, $config->toArray());
            $comments = $snapshot['band_comments'];
            $withStrongest = (bool) ($snapshot['report_layout']['sections']['strongest_skill'] ?? true);

            $finalization = Finalization::query()->create([
                'classroom_id' => $classroom->id,
                'action' => 'finalize',
                'config_snapshot' => $snapshot,
                'ip' => $this->request->ip(),
                'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 255),
                'created_at' => now(),
            ]);

            FinalResult::query()->where('classroom_id', $classroom->id)->update(['is_current' => false]);

            foreach ($enrollments as $e) {
                $raw = $this->scores->scorePayload($e->score)['scores'];
                $r = CefrCalculator::calculate($config, $raw);
                $row = [
                    'finalization_id' => $finalization->id,
                    'enrollment_id' => $e->id,
                    'classroom_id' => $classroom->id,
                    'term_id' => $classroom->term_id,
                    'student_id' => $e->student_id,
                    'grade' => $classroom->grade,
                    'total' => $r->total,
                    'overall_level' => $r->overallLevel,
                    'natgeo_level' => $classroom->usesNatGeo() ? $e->score?->natgeo_level : null,
                    'strongest_skill' => $r->strongest,
                    'comment_final' => CefrCalculator::reportComment($r, $e->student->name, $e->score?->teacher_comment, $comments, $withStrongest),
                    'is_current' => true,
                ];
                foreach (['listening', 'reading', 'writing', 'speaking'] as $s) {
                    $row[$s] = $raw[$s];
                    $row["{$s}_level"] = $r->level[$s];
                }
                FinalResult::query()->create($row);
            }

            $classroom->update([
                'status' => 'final',
                'finalized_at' => now(),
                'current_finalization_id' => $finalization->id,
            ]);

            RecapService::forgetClass($classroom->id);
            $this->logger->log('class.finalized', 'finalisasi', "Kelas {$classroom->name} difinalkan ({$enrollments->count()} siswa).", $classroom);

            return $finalization;
        });
    }

    public function unfinalize(Classroom $classroom, string $reason): Finalization
    {
        return DB::transaction(function () use ($classroom, $reason) {
            $classroom = Classroom::query()->lockForUpdate()->findOrFail($classroom->id);

            if (! $classroom->isFinal()) {
                throw new BusinessRuleException("Kelas {$classroom->name} belum final.");
            }

            $finalization = Finalization::query()->create([
                'classroom_id' => $classroom->id,
                'action' => 'unfinalize',
                'reason' => $reason,
                'ip' => $this->request->ip(),
                'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 255),
                'created_at' => now(),
            ]);

            // The last snapshot stays is_current (dashboard shows it as "sedang dikoreksi").
            $classroom->update(['status' => 'draft', 'finalized_at' => null]);

            RecapService::forgetClass($classroom->id);
            $this->logger->log('class.unfinalized', 'finalisasi', "Finalisasi kelas {$classroom->name} dibatalkan. Alasan: {$reason}", $classroom, ['reason' => $reason]);

            return $finalization;
        });
    }

    /** Content-addressed copy under snapshots/ (identical logos are stored once). */
    private function freezeFile(?string $path): ?string
    {
        $disk = Storage::disk('local');

        if (! $path || ! $disk->exists($path)) {
            return null;
        }

        $target = 'snapshots/'.hash('sha256', $disk->get($path)).'.'.pathinfo($path, PATHINFO_EXTENSION);

        if (! $disk->exists($target)) {
            $disk->copy($path, $target);
        }

        return $target;
    }

    /** Everything the report needs, frozen at finalisation (PRD-04 §6.1, PRD-05 §5.7). */
    private function snapshot(Classroom $classroom, array $config): array
    {
        $s = $this->settings;

        return [
            'scoring' => $config,
            'level_meta' => LevelMeta::query()->orderBy('sort')->get(['level', 'short_name', 'name', 'cambridge', 'color_hex'])->toArray(),
            'descriptors' => Descriptor::query()->get(['level', 'skill', 'can_do', 'next_step'])->toArray(),
            'band_comments' => ScoreService::overallComments(),
            'school' => [
                'name' => $s->get('school_name'),
                'city' => $s->get('city'),
                'principal_name' => $s->get('principal_name'),
                'principal_id_label' => $s->get('principal_id_label'),
                'principal_id_number' => $s->get('principal_id_number'),
                // Copies, so replacing a logo later does not change final reports (PRD-05 §5.7).
                'school_logo_path' => $this->freezeFile($s->get('school_logo_path')),
            ],
            'classroom' => [
                'name' => $classroom->name,
                'grade' => $classroom->grade,
                'homeroom_teacher' => $classroom->homeroom_teacher,
                'homeroom_id_number' => $classroom->homeroom_id_number,
            ],
            'term' => [
                'year' => $classroom->term->academicYear->name,
                'label' => $classroom->term->label,
                'report_date' => $classroom->term->report_date?->toDateString(),
            ],
            'report_layout' => $s->get('report_layout'), // set up in M4
        ];
    }
}
