<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Score;
use App\Models\Term;
use App\Services\ActivityLogger;
use App\Services\TermService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** /periode — academic years & semesters (PRD-03 §3.1). */
class PeriodController extends Controller
{
    public function __construct(private TermService $terms, private ActivityLogger $logger) {}

    public function index(): Response
    {
        $termsWithScores = Score::query()->whereAnyNotNull()
            ->join('enrollments', 'enrollments.id', '=', 'scores.enrollment_id')
            ->distinct()->pluck('enrollments.term_id')->flip();

        $years = AcademicYear::query()
            ->orderByDesc('name')
            ->with(['terms' => fn ($q) => $q
                ->withCount(['classrooms', 'enrollments', 'classrooms as final_count' => fn ($c) => $c->where('status', 'final')])])
            ->get()
            ->map(fn (AcademicYear $y) => [
                'id' => $y->id,
                'name' => $y->name,
                'starts_on' => $y->starts_on?->toDateString(),
                'ends_on' => $y->ends_on?->toDateString(),
                'terms' => $y->terms->map(fn (Term $t) => [
                    'id' => $t->id,
                    'semester' => $t->semester,
                    'label' => $t->label,
                    'report_date' => $t->report_date?->toDateString(),
                    'is_active' => $t->is_active,
                    'classes' => $t->classrooms_count,
                    'students' => $t->enrollments_count,
                    'final' => $t->final_count,
                    'has_scores' => $termsWithScores->has($t->id),
                ]),
            ]);

        return Inertia::render('Periods/Index', ['years' => $years]);
    }

    public function storeYear(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => [
                'required', 'regex:/^\d{4}\/\d{4}$/', 'unique:academic_years,name',
                function ($attr, $value, $fail) {
                    [$a, $b] = array_map('intval', explode('/', $value) + [1 => 0]);
                    if ($b !== $a + 1) {
                        $fail('Tahun kedua harus satu tahun setelah tahun pertama (mis. 2026/2027).');
                    }
                },
            ],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ], [
            'name.required' => 'Nama tahun ajaran wajib diisi.',
            'name.regex' => 'Format tahun ajaran harus YYYY/YYYY, mis. 2026/2027.',
            'name.unique' => 'Tahun ajaran ini sudah ada.',
        ]);

        $year = AcademicYear::query()->create($data);
        $this->logger->log('year.created', 'data', "Tahun ajaran {$year->name} dibuat.", $year);

        return back()->with('success', "Tahun ajaran {$year->name} dibuat. Tambahkan semesternya.");
    }

    public function storeTerm(Request $request, AcademicYear $year): RedirectResponse
    {
        $data = $request->validate([
            'semester' => ['required', Rule::in([1, 2]), Rule::unique('terms')->where('academic_year_id', $year->id)],
            'report_date' => ['nullable', 'date'],
        ], [
            'semester.required' => 'Pilih semester 1 atau 2.',
            'semester.unique' => 'Semester ini sudah ada di tahun ajaran '.$year->name.'.',
        ]);

        [$term, $source] = $this->terms->createTerm($year, (int) $data['semester'], $data['report_date'] ?? null);

        $message = $source
            ? "{$term->fullLabel()} dibuat. Pengaturan penilaian disalin dari {$source}."
            : "{$term->fullLabel()} dibuat dengan pengaturan penilaian bawaan.";

        if ($term->is_active) {
            $message .= ' Periode ini menjadi periode aktif.';
        }

        return back()->with('success', $message);
    }

    public function updateTerm(Request $request, Term $term): RedirectResponse
    {
        $data = $request->validate(['report_date' => ['nullable', 'date']]);
        $term->update($data);
        $term->load('academicYear');
        $this->logger->log('term.updated', 'data', "Tanggal rapor {$term->fullLabel()} diperbarui.", $term);

        return back()->with('success', 'Tanggal rapor tersimpan.');
    }

    public function activate(Term $term): RedirectResponse
    {
        $this->terms->activate($term);

        return back()->with('success', "Periode aktif sekarang {$term->fullLabel()}.");
    }

    public function destroyTerm(Term $term): RedirectResponse
    {
        $label = $term->load('academicYear')->fullLabel();
        $this->terms->delete($term);

        return redirect()->route('periods.index')->with('success', "Periode {$label} dihapus.");
    }

    public function destroyYear(AcademicYear $year): RedirectResponse
    {
        $this->terms->deleteYear($year);

        return back()->with('success', "Tahun ajaran {$year->name} dihapus.");
    }

    /** Options for period pickers (used by several pages). */
    public static function termOptions(): array
    {
        return Term::query()->chronological()->with('academicYear')->get()
            ->map(fn (Term $t) => ['id' => $t->id, 'label' => $t->fullLabel(), 'is_active' => $t->is_active])
            ->all();
    }

    /** @return array<string, mixed> */
    public static function classPayload(Classroom $c): array
    {
        return [
            'id' => $c->id,
            'grade' => $c->grade,
            'name' => $c->name,
            'homeroom_teacher' => $c->homeroom_teacher,
            'homeroom_id_number' => $c->homeroom_id_number,
            'status' => $c->status,
        ];
    }
}
