<?php

namespace App\Services;

use App\Domain\Cefr\Band;
use App\Domain\Cefr\CefrCalculator;
use App\Domain\Cefr\Level;
use App\Domain\Cefr\Skill;
use App\Http\Controllers\ClassController;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\FinalResult;
use App\Models\Term;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Per-student results for the dashboard (PRD-06 §1, §6):
 * - Final classes → final_results (is_current)
 * - Draft classes → live calculation (cached 60 s per class, cleared on autosave)
 * - "Hanya kelas final": Draft classes are left out, except un-finalised classes that still
 *   have a snapshot — those use it, flagged "sedang dikoreksi".
 * A row is assessed when overall_level is set (all four scores filled in).
 */
class RecapService
{
    public const CACHE_SECONDS = 60;

    public function __construct(private ScoreService $scores) {}

    public static function forgetClass(int $classroomId): void
    {
        Cache::forget("recap:class:{$classroomId}");
    }

    /**
     * @return Collection<int, array<string, mixed>> one row per active student
     */
    public function rows(Term $term, ?int $grade = null, bool $finalOnly = false): Collection
    {
        $classes = $term->classrooms()->when($grade, fn ($q) => $q->where('grade', $grade))->orderBy('grade')->orderBy('name')->get();
        $rows = collect();

        foreach ($classes as $c) {
            if ($c->isFinal()) {
                $rows = $rows->merge($this->finalRows($c, 'final'));
            } elseif ($finalOnly) {
                $rows = $rows->merge($this->finalRows($c, 'correcting'));
            } else {
                $rows = $rows->merge(Cache::remember("recap:class:{$c->id}", self::CACHE_SECONDS, fn () => $this->liveRows($c)));
            }
        }

        return $rows->values();
    }

    /** @return list<array<string, mixed>> */
    private function finalRows(Classroom $c, string $source): array
    {
        return FinalResult::query()
            ->with('student:id,name,nipd')
            ->where('classroom_id', $c->id)
            ->where('is_current', true)
            ->get()
            ->map(fn (FinalResult $r) => [
                'student_id' => $r->student_id,
                'name' => $r->student->name,
                'classroom_id' => $c->id,
                'class' => $c->name,
                'grade' => $c->grade,
                'scores' => array_combine(Skill::values(), array_map(fn ($s) => $r->{$s}, Skill::values())),
                'total' => $r->total,
                'overall_level' => $r->overall_level,
                'natgeo' => $r->natgeo_level,
                'source' => $source,
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function liveRows(Classroom $c): array
    {
        $config = $this->scores->config($c);

        return $c->enrollments()->with(['student:id,name,nipd,is_active', 'score'])->get()
            ->filter(fn (Enrollment $e) => $e->student->is_active)
            ->map(function (Enrollment $e) use ($c, $config) {
                $payload = $this->scores->scorePayload($e->score);
                $r = CefrCalculator::calculate($config, $payload['scores']);

                return [
                    'student_id' => $e->student_id,
                    'name' => $e->student->name,
                    'classroom_id' => $c->id,
                    'class' => $c->name,
                    'grade' => $c->grade,
                    'scores' => $payload['scores'],
                    'total' => $r->total,
                    'overall_level' => $r->overallLevel,
                    'natgeo' => $c->usesNatGeo() ? $payload['natgeo'] : null,
                    'source' => 'draft',
                ];
            })->values()->all();
    }

    private static function assessed(Collection $rows): Collection
    {
        return $rows->whereNotNull('overall_level');
    }

    /** KPI cards (PRD-06 §3.2). Averages are of the total score (20–100). */
    public function kpis(Term $term, Collection $rows, ?int $grade, bool $finalOnly): array
    {
        $assessed = self::assessed($rows);
        $classes = $term->classrooms()->when($grade, fn ($q) => $q->where('grade', $grade))->orderBy('grade')->orderBy('name')->get();
        $avg = $assessed->avg('total');
        $previous = $this->previousAverage($term, $grade);

        return [
            'assessed' => $assessed->count(),
            'students' => $rows->count(),
            'final' => $classes->where('status', 'final')->count(),
            'classes' => $classes->count(),
            'not_final' => $classes->where('status', '!=', 'final')->pluck('name')->values(),
            'average' => $avg === null ? null : round($avg, 1),
            'previous_average' => $previous,
            'delta' => $avg !== null && $previous !== null ? round($avg - $previous, 1) : null,
            'intermediate_or_above' => $assessed->count() ? round($assessed->where('overall_level', '!=', Band::Beginner->value)->count() / $assessed->count() * 100) : null,
        ];
    }

    /** Average total of the period before $term (final results only). */
    private function previousAverage(Term $term, ?int $grade): ?float
    {
        $prev = Term::query()->chronological()->with('academicYear')->get()
            ->skipUntil(fn (Term $t) => $t->id === $term->id)->skip(1)->first();

        if (! $prev) {
            return null;
        }

        $avg = FinalResult::query()->where('term_id', $prev->id)->where('is_current', true)
            ->when($grade, fn ($q) => $q->where('grade', $grade))->avg('total');

        return $avg === null ? null : round((float) $avg, 1);
    }

    /** Charts & insights (deferred props). */
    public function charts(Term $term, Collection $rows): array
    {
        $assessed = self::assessed($rows);
        $bands = Band::values();
        $avgScore = fn (Collection $r, string $s) => $r->count() ? round($r->avg(fn ($x) => $x['scores'][$s]), 1) : null;

        $byClass = $rows->groupBy('classroom_id')->map(function (Collection $students) use ($bands, $avgScore) {
            $first = $students->first();
            $done = self::assessed($students);

            return [
                'id' => $first['classroom_id'],
                'class' => $first['class'],
                'grade' => $first['grade'],
                'correcting' => $students->contains('source', 'correcting'),
                'levels' => collect($bands)->mapWithKeys(fn ($l) => [$l => $done->where('overall_level', $l)->pluck('name')->sort()->values()])->all(),
                'skills' => collect(Skill::values())->mapWithKeys(fn ($s) => [$s => $avgScore($done, $s)])->all(),
                'average' => $done->count() ? round($done->avg('total'), 1) : null,
            ];
        })->values();

        $byGrade = collect(TermService::GRADES)->map(fn ($g) => [
            'grade' => $g,
            'levels' => collect($bands)->mapWithKeys(fn ($l) => [$l => $assessed->where('grade', $g)->where('overall_level', $l)->count()])->all(),
            'skills' => collect(Skill::values())->mapWithKeys(fn ($s) => [$s => $avgScore($assessed->where('grade', $g), $s)])->all(),
        ])->filter(fn ($g) => array_sum($g['levels']) > 0)->values();

        $natgeo = $rows->where('grade', Classroom::NATGEO_GRADE);

        return [
            'byClass' => $byClass,
            'byGrade' => $byGrade,
            // Grade 6 National Geographic placement levels (Pre-A1–B2) + students not yet entered.
            'natgeo' => [
                ...collect(Level::values())->mapWithKeys(fn ($l) => [$l => $natgeo->where('natgeo', $l)->count()])->all(),
                'empty' => $natgeo->whereNull('natgeo')->count(),
            ],
            'trend' => $this->trend(),
            'insights' => $this->insights($rows, $term),
        ];
    }

    /** Average total per grade for the last 6 periods with final results (PRD-06 §3.3). */
    private function trend(): array
    {
        $terms = Term::query()->chronological()->with('academicYear')
            ->whereIn('terms.id', FinalResult::query()->where('is_current', true)->select('term_id'))
            ->limit(6)->get()->reverse()->values();

        $avg = FinalResult::query()->where('is_current', true)->whereIn('term_id', $terms->pluck('id'))
            ->selectRaw('term_id, grade, AVG(total) as avg_total, COUNT(*) as n')
            ->groupBy('term_id', 'grade')->get();

        return [
            'labels' => $terms->map(fn (Term $t) => $t->shortLabel())->all(),
            'series' => collect(TermService::GRADES)->map(fn ($g) => [
                'grade' => $g,
                'values' => $terms->map(fn ($t) => ($v = $avg->first(fn ($a) => $a->term_id === $t->id && (int) $a->grade === $g)) ? round((float) $v->avg_total, 1) : null)->all(),
            ])->filter(fn ($s) => collect($s['values'])->filter(fn ($v) => $v !== null)->isNotEmpty())->values()->all(),
        ];
    }

    /** Up to 3 rule-based highlights (PRD-06 §3.5). */
    private function insights(Collection $rows, Term $term): array
    {
        $out = [];
        $assessed = self::assessed($rows);
        $fmt = fn (float $v) => str_replace('.', ',', (string) round($v, 1));

        // Weakest skill in a grade.
        foreach ($assessed->groupBy('grade') as $g => $students) {
            $avgs = collect(Skill::values())->mapWithKeys(fn ($s) => [$s => $students->avg(fn ($r) => $r['scores'][$s])]);
            $weak = $avgs->sort()->keys()->first();
            $out[] = ['tone' => 'info', 'text' => Skill::from($weak)->label()." adalah skill dengan rata-rata terendah di Kelas {$g} ({$fmt($avgs[$weak])} dari 25).", 'href' => '/kelas', 'weight' => 1];
        }

        // Students still at Beginner per grade.
        foreach ($assessed->where('overall_level', Band::Beginner->value)->groupBy('grade') as $g => $students) {
            $out[] = ['tone' => 'warning', 'text' => "{$students->count()} siswa Kelas {$g} masih di level Beginner.", 'href' => '/kelas', 'weight' => 2];
        }

        // Grade 6 students without a National Geographic level.
        $noNatgeo = $rows->where('grade', Classroom::NATGEO_GRADE)->whereNull('natgeo')->count();
        if ($noNatgeo) {
            $out[] = ['tone' => 'warning', 'text' => "{$noNatgeo} siswa Kelas ".Classroom::NATGEO_GRADE.' belum diisi level National Geographic.', 'href' => '/kelas', 'weight' => 2];
        }

        // Classes with no scores at all.
        $empty = $term->classrooms()->whereHas('enrollments')
            ->whereDoesntHave('enrollments.score', fn ($q) => $q->whereAnyNotNull())
            ->orderBy('grade')->orderBy('name')->get();
        foreach ($empty as $c) {
            $out[] = ['tone' => 'warning', 'text' => "Kelas {$c->name} belum diisi sama sekali.", 'href' => "/kelas/{$c->id}/nilai", 'weight' => 3];
        }

        return collect($out)->sortByDesc('weight')->take(3)->map(fn ($i) => collect($i)->except('weight')->all())->values()->all();
    }

    /** Progress table rows (PRD-06 §3.4): drafts with the lowest completion first. */
    public function progressTable(Term $term, ?int $grade): array
    {
        return ClassController::withProgress($term->classrooms()->getQuery())
            ->when($grade, fn ($q) => $q->where('grade', $grade))
            ->get()
            ->map(fn (Classroom $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'grade' => $c->grade,
                'teacher' => $c->homeroom_teacher,
                'status' => $c->status,
                'total' => $c->enrollments_count,
                'complete' => $c->complete_count,
            ])
            ->sortBy(fn ($c) => [$c['status'] === 'final' ? 1 : 0, $c['total'] ? $c['complete'] / $c['total'] : 0, $c['grade'], $c['name']])
            ->values()->all();
    }

    /**
     * Period comparison (PRD-06 §5). Band shares are the primary metric; the average total is secondary.
     *
     * @param  'same'|'all'  $cohort
     */
    public function compare(Term $a, Term $b, string $cohort): array
    {
        $ra = FinalResult::query()->where('term_id', $a->id)->where('is_current', true)->get(['student_id', 'grade', 'total', 'overall_level']);
        $rb = FinalResult::query()->where('term_id', $b->id)->where('is_current', true)->get(['student_id', 'grade', 'total', 'overall_level']);

        if ($cohort === 'same') {
            $common = $ra->pluck('student_id')->intersect($rb->pluck('student_id'));
            $ra = $ra->whereIn('student_id', $common);
            $rb = $rb->whereIn('student_id', $common);
        }

        $summary = fn (Collection $r) => [
            'n' => $r->count(),
            'average' => $r->count() ? round($r->avg('total'), 1) : null,
            'levels' => collect(Band::values())->mapWithKeys(fn ($l) => [$l => $r->count() ? round($r->where('overall_level', $l)->count() / $r->count() * 100) : 0])->all(),
        ];

        // "Same students": compare each student's band across the two periods.
        $moves = null;
        if ($cohort === 'same' && $ra->isNotEmpty()) {
            $bBy = $rb->keyBy('student_id');
            $moves = ['up' => 0, 'same' => 0, 'down' => 0];
            foreach ($ra as $r) {
                $d = Band::from($bBy[$r->student_id]->overall_level)->index() - Band::from($r->overall_level)->index();
                $moves[$d > 0 ? 'up' : ($d < 0 ? 'down' : 'same')]++;
            }
        }

        return [
            'a' => ['label' => $a->fullLabel(), ...$summary($ra)],
            'b' => ['label' => $b->fullLabel(), ...$summary($rb)],
            'grades' => collect(TermService::GRADES)->map(fn ($g) => [
                'grade' => $g,
                'a' => $summary($ra->where('grade', $g)),
                'b' => $summary($rb->where('grade', $g)),
            ])->filter(fn ($row) => $row['a']['n'] || $row['b']['n'])->values()->all(),
            'moves' => $moves,
        ];
    }
}
