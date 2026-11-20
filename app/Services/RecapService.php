<?php

namespace App\Services;

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
            ->with('student:id,name,nis')
            ->where('classroom_id', $c->id)
            ->where('is_current', true)
            ->get()
            ->map(fn (FinalResult $r) => [
                'student_id' => $r->student_id,
                'name' => $r->student->name,
                'classroom_id' => $c->id,
                'class' => $c->name,
                'grade' => $c->grade,
                'pct' => array_combine(Skill::values(), array_map(fn ($s) => $r->{"{$s}_pct"}, Skill::values())),
                'overall_pct' => $r->overall_pct,
                'overall_level' => $r->overall_level,
                'status' => $r->status,
                'source' => $source,
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function liveRows(Classroom $c): array
    {
        $config = $this->scores->config($c);

        return $c->enrollments()->with(['student:id,name,nis,is_active', 'score'])->get()
            ->filter(fn (Enrollment $e) => $e->student->is_active)
            ->map(function (Enrollment $e) use ($c, $config) {
                $r = CefrCalculator::calculate($config, $this->scores->scorePayload($e->score)['scores']);

                return [
                    'student_id' => $e->student_id,
                    'name' => $e->student->name,
                    'classroom_id' => $c->id,
                    'class' => $c->name,
                    'grade' => $c->grade,
                    'pct' => $r->percent,
                    'overall_pct' => $r->overallPercent,
                    'overall_level' => $r->overallLevel,
                    'status' => $r->status,
                    'source' => 'draft',
                ];
            })->values()->all();
    }

    /** KPI cards (PRD-06 §3.2). */
    public function kpis(Term $term, Collection $rows, ?int $grade, bool $finalOnly): array
    {
        $assessed = $rows->where('status', '!=', 'incomplete');
        $classes = $term->classrooms()->when($grade, fn ($q) => $q->where('grade', $grade))->orderBy('grade')->orderBy('name')->get();
        $avg = $assessed->avg('overall_pct');
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
            'meeting_or_above' => $assessed->count() ? round($assessed->whereIn('status', ['meeting', 'above'])->count() / $assessed->count() * 100) : null,
        ];
    }

    /** Average overall % of the period before $term (final results only). */
    private function previousAverage(Term $term, ?int $grade): ?float
    {
        $prev = Term::query()->chronological()->with('academicYear')->get()
            ->skipUntil(fn (Term $t) => $t->id === $term->id)->skip(1)->first();

        if (! $prev) {
            return null;
        }

        $avg = FinalResult::query()->where('term_id', $prev->id)->where('is_current', true)
            ->when($grade, fn ($q) => $q->where('grade', $grade))->avg('overall_pct');

        return $avg === null ? null : round((float) $avg, 1);
    }

    /** Charts & insights (deferred props). */
    public function charts(Term $term, Collection $rows): array
    {
        $assessed = $rows->where('status', '!=', 'incomplete');
        $levels = Level::values();

        $byClass = $rows->groupBy('classroom_id')->map(function (Collection $students) use ($levels) {
            $first = $students->first();
            $done = $students->where('status', '!=', 'incomplete');

            return [
                'id' => $first['classroom_id'],
                'class' => $first['class'],
                'grade' => $first['grade'],
                'correcting' => $students->contains('source', 'correcting'),
                'levels' => collect($levels)->mapWithKeys(fn ($l) => [$l => $done->where('overall_level', $l)->pluck('name')->sort()->values()])->all(),
                'skills' => collect(Skill::values())->mapWithKeys(fn ($s) => [$s => $done->count() ? round($done->avg(fn ($r) => $r['pct'][$s]), 1) : null])->all(),
                'average' => $done->count() ? round($done->avg('overall_pct'), 1) : null,
            ];
        })->values();

        $byGrade = collect([3, 4, 5, 6])->map(fn ($g) => [
            'grade' => $g,
            'levels' => collect($levels)->mapWithKeys(fn ($l) => [$l => $assessed->where('grade', $g)->where('overall_level', $l)->count()])->all(),
            'skills' => collect(Skill::values())->mapWithKeys(fn ($s) => [$s => $assessed->where('grade', $g)->count() ? round($assessed->where('grade', $g)->avg(fn ($r) => $r['pct'][$s]), 1) : null])->all(),
        ])->filter(fn ($g) => array_sum($g['levels']) > 0)->values();

        return [
            'byClass' => $byClass,
            'byGrade' => $byGrade,
            'status' => [
                'below' => $assessed->where('status', 'below')->count(),
                'meeting' => $assessed->where('status', 'meeting')->count(),
                'above' => $assessed->where('status', 'above')->count(),
                'incomplete' => $rows->where('status', 'incomplete')->count(),
            ],
            'trend' => $this->trend(),
            'insights' => $this->insights($rows, $byClass, $term),
        ];
    }

    /** Average overall % per grade for the last 6 periods with final results (PRD-06 §3.3). */
    private function trend(): array
    {
        $terms = Term::query()->chronological()->with('academicYear')
            ->whereIn('terms.id', FinalResult::query()->where('is_current', true)->select('term_id'))
            ->limit(6)->get()->reverse()->values();

        $avg = FinalResult::query()->where('is_current', true)->whereIn('term_id', $terms->pluck('id'))
            ->selectRaw('term_id, grade, AVG(overall_pct) as avg_pct, COUNT(*) as n')
            ->groupBy('term_id', 'grade')->get();

        return [
            'labels' => $terms->map(fn (Term $t) => $t->shortLabel())->all(),
            'series' => collect([3, 4, 5, 6])->map(fn ($g) => [
                'grade' => $g,
                'values' => $terms->map(fn ($t) => ($v = $avg->first(fn ($a) => $a->term_id === $t->id && (int) $a->grade === $g)) ? round((float) $v->avg_pct, 1) : null)->all(),
            ])->filter(fn ($s) => collect($s['values'])->filter(fn ($v) => $v !== null)->isNotEmpty())->values()->all(),
        ];
    }

    /** Up to 3 rule-based highlights (PRD-06 §3.5). */
    private function insights(Collection $rows, Collection $byClass, Term $term): array
    {
        $out = [];
        $labels = ['listening' => 'Listening', 'reading' => 'Reading', 'writing' => 'Writing', 'speaking' => 'Speaking'];

        // Weakest skill in a grade.
        $assessed = $rows->where('status', '!=', 'incomplete');
        foreach ($assessed->groupBy('grade') as $g => $students) {
            $avgs = collect(Skill::values())->mapWithKeys(fn ($s) => [$s => $students->avg(fn ($r) => $r['pct'][$s])]);
            $weak = $avgs->sort()->keys()->first();
            $out[] = ['tone' => 'info', 'text' => "{$labels[$weak]} adalah skill dengan rata-rata terendah di Kelas {$g} (".str_replace('.', ',', (string) round($avgs[$weak])).'%).', 'href' => '/kelas', 'weight' => 1];
        }

        // Students below the expected level per grade.
        foreach ($assessed->where('status', 'below')->groupBy('grade') as $g => $students) {
            $config = $term->scoringConfigs()->where('grade', $g)->first();
            $range = $config ? ($config->expected_min === $config->expected_max ? $config->expected_min : "{$config->expected_min}–{$config->expected_max}") : '';
            $out[] = ['tone' => 'warning', 'text' => "{$students->count()} siswa Kelas {$g} berada di bawah level harapan ({$range}).", 'href' => '/kelas', 'weight' => 2];
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
                'teacher' => $c->english_teacher,
                'status' => $c->status,
                'total' => $c->enrollments_count,
                'complete' => $c->complete_count,
            ])
            ->sortBy(fn ($c) => [$c['status'] === 'final' ? 1 : 0, $c['total'] ? $c['complete'] / $c['total'] : 0, $c['grade'], $c['name']])
            ->values()->all();
    }

    /**
     * Period comparison (PRD-06 §5). Level shares are the primary metric; % is secondary.
     *
     * @param  'same'|'all'  $cohort
     */
    public function compare(Term $a, Term $b, string $cohort): array
    {
        $ra = FinalResult::query()->where('term_id', $a->id)->where('is_current', true)->get(['student_id', 'grade', 'overall_pct', 'overall_level']);
        $rb = FinalResult::query()->where('term_id', $b->id)->where('is_current', true)->get(['student_id', 'grade', 'overall_pct', 'overall_level']);

        if ($cohort === 'same') {
            $common = $ra->pluck('student_id')->intersect($rb->pluck('student_id'));
            $ra = $ra->whereIn('student_id', $common);
            $rb = $rb->whereIn('student_id', $common);
        }

        $summary = fn (Collection $r) => [
            'n' => $r->count(),
            'average' => $r->count() ? round($r->avg('overall_pct'), 1) : null,
            'levels' => collect(Level::values())->mapWithKeys(fn ($l) => [$l => $r->count() ? round($r->where('overall_level', $l)->count() / $r->count() * 100) : 0])->all(),
        ];

        // "Same students": compare each student's level index across the two periods.
        $moves = null;
        if ($cohort === 'same' && $ra->isNotEmpty()) {
            $idx = fn ($l) => array_search($l, Level::values(), true);
            $bBy = $rb->keyBy('student_id');
            $moves = ['up' => 0, 'same' => 0, 'down' => 0];
            foreach ($ra as $r) {
                $d = $idx($bBy[$r->student_id]->overall_level) - $idx($r->overall_level);
                $moves[$d > 0 ? 'up' : ($d < 0 ? 'down' : 'same')]++;
            }
        }

        return [
            'a' => ['label' => $a->fullLabel(), ...$summary($ra)],
            'b' => ['label' => $b->fullLabel(), ...$summary($rb)],
            'grades' => collect([3, 4, 5, 6])->map(fn ($g) => [
                'grade' => $g,
                'a' => $summary($ra->where('grade', $g)),
                'b' => $summary($rb->where('grade', $g)),
            ])->filter(fn ($row) => $row['a']['n'] || $row['b']['n'])->values()->all(),
            'moves' => $moves,
        ];
    }
}
