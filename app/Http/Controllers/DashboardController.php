<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Term;
use App\Services\RecapService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard (PRD-06 §3, §5). KPIs and the progress table load first; charts and
 * highlights come as deferred props so the page is usable immediately.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, RecapService $recap): Response
    {
        $f = $request->validate([
            'term' => ['nullable', 'integer'],
            'grade' => ['nullable', Rule::in([3, 4, 5, 6])],
            'final_only' => ['nullable', 'boolean'],
            'tab' => ['nullable', Rule::in(['ringkasan', 'perbandingan'])],
            'a' => ['nullable', 'integer'],
            'b' => ['nullable', 'integer'],
            'cohort' => ['nullable', Rule::in(['same', 'all'])],
        ]);

        $term = isset($f['term']) ? Term::query()->with('academicYear')->find($f['term']) : Term::active();
        $grade = isset($f['grade']) ? (int) $f['grade'] : null;
        $finalOnly = (bool) ($f['final_only'] ?? false);
        $tab = $f['tab'] ?? 'ringkasan';

        $base = [
            'hasActiveTerm' => (bool) Term::active(),
            'classCount' => $term?->classrooms()->count() ?? 0,
            'studentCount' => $term ? Enrollment::query()->where('term_id', $term->id)->count() : 0,
            'terms' => PeriodController::termOptions(),
            'filters' => [
                'term' => $term?->id,
                'grade' => $grade,
                'final_only' => $finalOnly,
                'tab' => $tab,
                'cohort' => $f['cohort'] ?? 'same',
            ],
            'term' => $term ? ['id' => $term->id, 'label' => $term->fullLabel()] : null,
        ];

        if (! $term || $base['classCount'] === 0) {
            return Inertia::render('Dashboard', $base);
        }

        if ($tab === 'perbandingan') {
            $options = collect(PeriodController::termOptions());
            $a = Term::query()->with('academicYear')->find($f['a'] ?? $options->skip(1)->first()['id'] ?? null);
            $b = Term::query()->with('academicYear')->find($f['b'] ?? $term->id);

            return Inertia::render('Dashboard', $base + [
                'comparison' => $a && $b && $a->id !== $b->id ? [
                    'a_id' => $a->id,
                    'b_id' => $b->id,
                    ...$recap->compare($a, $b, $base['filters']['cohort']),
                ] : null,
            ]);
        }

        $rows = $recap->rows($term, $grade, $finalOnly);

        return Inertia::render('Dashboard', $base + [
            'kpis' => $recap->kpis($term, $rows, $grade, $finalOnly),
            'progress' => $recap->progressTable($term, $grade),
            'charts' => Inertia::defer(fn () => $recap->charts($term, $rows)),
        ]);
    }
}
