<?php

namespace App\Http\Controllers;

use App\Domain\Cefr\Level;
use App\Models\Classroom;
use App\Models\Finalization;
use App\Models\Term;
use App\Services\ActivityLogger;
use App\Services\ScoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Menu "Kelas" (PRD-04 §3). M2: class cards and roster; the Input Nilai / Rapor / Riwayat
 * tabs get their content in M3–M4.
 */
class ClassController extends Controller
{
    public function index(Request $request): Response
    {
        $term = $request->integer('term')
            ? Term::query()->with('academicYear')->find($request->integer('term'))
            : Term::active();

        $classes = $term ? self::withProgress($term->classrooms()->getQuery())
            ->orderBy('grade')->orderBy('name')->get()
            ->map(fn (Classroom $c) => PeriodController::classPayload($c) + [
                'total' => $c->enrollments_count,
                'complete' => $c->complete_count,
            ]) : collect();

        return Inertia::render('Classes/Index', [
            'term' => $term ? ['id' => $term->id, 'label' => $term->fullLabel(), 'is_active' => $term->is_active] : null,
            'terms' => PeriodController::termOptions(),
            'classes' => $classes,
        ]);
    }

    public function show(Classroom $classroom): RedirectResponse
    {
        // Draft → Input Nilai, Final → Rapor & Cetak (PRD-04 §3).
        return redirect()->route($classroom->isFinal() ? 'classes.report' : 'classes.scores', $classroom);
    }

    public function tab(Request $request, Classroom $classroom): Response
    {
        $classroom->load('term.academicYear');
        $tab = match ($request->route()->getName()) {
            'classes.report' => 'rapor',
            'classes.history' => 'riwayat',
            default => 'nilai',
        };

        $scores = app(ScoreService::class);
        $progress = $scores->progress($classroom);
        $last = Finalization::query()->where('classroom_id', $classroom->id)->latest('id')->first();

        $props = [
            'classroom' => PeriodController::classPayload($classroom) + [
                'term' => $classroom->term->fullLabel(),
                'term_id' => $classroom->term_id,
                'finalized_at' => $classroom->finalized_at?->toIso8601String(),
                'last_event' => $last ? [
                    'action' => $last->action,
                    'reason' => $last->reason,
                    'at' => $last->created_at->toIso8601String(),
                ] : null,
                ...$progress,
            ],
            'tab' => $tab,
        ];

        if ($tab === 'nilai') {
            $props['config'] = $scores->config($classroom)->toArray();
            $props['comments'] = ScoreService::overallComments();
            // Grade 6 only: National Geographic CEFR placement level per student.
            $props['natgeoLevels'] = $classroom->usesNatGeo() ? Level::values() : null;
            $props['rows'] = $scores->rows($classroom);
        }

        if ($tab === 'rapor') {
            $props += ReportController::tabProps($classroom);
        }

        if ($tab === 'riwayat') {
            $props['history'] = Finalization::query()
                ->where('classroom_id', $classroom->id)
                ->withCount('results')
                ->latest('id')
                ->get()
                ->map(fn (Finalization $f) => [
                    'id' => $f->id,
                    'action' => $f->action,
                    'reason' => $f->reason,
                    'at' => $f->created_at->toIso8601String(),
                    'students' => $f->results_count,
                    'device' => ActivityLogger::device($f->user_agent),
                ]);
        }

        return Inertia::render('Classes/Show', $props);
    }

    /** Adds enrollments_count & complete_count (students with all four scores). */
    public static function withProgress($query)
    {
        return $query->withCount([
            'enrollments',
            'enrollments as complete_count' => fn ($q) => $q->whereHas('score', fn ($s) => $s->complete()),
        ]);
    }
}
