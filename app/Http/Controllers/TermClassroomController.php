<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Score;
use App\Models\Term;
use App\Services\ClassroomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** /periode/{term} — classes of one period (PRD-03 §3.2). */
class TermClassroomController extends Controller
{
    public function __construct(private ClassroomService $classes) {}

    public function show(Term $term): Response
    {
        $term->load('academicYear');

        $withScores = Score::query()->whereAnyNotNull()
            ->join('enrollments', 'enrollments.id', '=', 'scores.enrollment_id')
            ->where('enrollments.term_id', $term->id)
            ->distinct()->pluck('enrollments.classroom_id')->flip();

        $classes = $term->classrooms()
            ->withCount('enrollments')
            ->orderBy('grade')->orderBy('name')
            ->get()
            ->map(fn (Classroom $c) => PeriodController::classPayload($c) + [
                'students' => $c->enrollments_count,
                'has_scores' => $withScores->has($c->id),
            ]);

        $sources = collect(PeriodController::termOptions())->reject(fn ($t) => $t['id'] === $term->id)->values();

        return Inertia::render('Periods/Show', [
            'term' => [
                'id' => $term->id,
                'label' => $term->fullLabel(),
                'year' => $term->academicYear->name,
                'semester' => $term->semester,
                'report_date' => $term->report_date?->toDateString(),
                'is_active' => $term->is_active,
            ],
            'classes' => $classes,
            'copySources' => $sources,
        ]);
    }

    public function store(Request $request, Term $term): RedirectResponse
    {
        $classroom = $this->classes->create($term, $this->validated($request, $term));

        return back()->with('success', "Kelas {$classroom->name} dibuat.");
    }

    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->classes->update($classroom, $this->validated($request, $classroom->term, $classroom));

        return back()->with('success', "Kelas {$classroom->name} tersimpan.");
    }

    public function destroy(Classroom $classroom): RedirectResponse
    {
        $name = $classroom->name;
        $this->classes->delete($classroom);

        return back()->with('success', "Kelas {$name} dihapus.");
    }

    public function copyPreview(Request $request, Term $term): JsonResponse
    {
        [$source, $ids, $promote, $students] = $this->copyInput($request, $term);

        return response()->json(['plan' => $this->classes->copyPlan($term, $source, $ids, $promote, $students)]);
    }

    public function copy(Request $request, Term $term): RedirectResponse
    {
        [$source, $ids, $promote, $students] = $this->copyInput($request, $term);
        $result = $this->classes->copy($term, $source, $ids, $promote, $students);

        return back()->with('success', $result['classes'] === 0
            ? 'Tidak ada kelas yang disalin.'
            : "{$result['classes']} kelas disalin".($students ? " beserta {$result['students']} siswa" : '').'.');
    }

    /** @return array{0: Term, 1: list<int>, 2: bool, 3: bool} */
    private function copyInput(Request $request, Term $term): array
    {
        $data = $request->validate([
            'source_term_id' => ['required', 'integer', 'exists:terms,id', Rule::notIn([$term->id])],
            'all' => ['boolean'], // preview only: every class of the source
            'class_ids' => ['exclude_if:all,true', 'required', 'array', 'min:1'],
            'class_ids.*' => ['integer'],
            'promote' => ['boolean'],
            'with_students' => ['boolean'],
        ], [
            'source_term_id.required' => 'Pilih periode sumber.',
            'class_ids.required' => 'Centang minimal satu kelas.',
        ]);

        $source = Term::query()->findOrFail($data['source_term_id']);
        $ids = ($data['all'] ?? false)
            ? $source->classrooms()->pluck('id')->all()
            : array_map('intval', $data['class_ids']);

        return [
            $source,
            $ids,
            (bool) ($data['promote'] ?? false),
            (bool) ($data['with_students'] ?? false),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, Term $term, ?Classroom $classroom = null): array
    {
        $data = $request->validate([
            'grade' => ['required', Rule::in([3, 4, 5, 6])],
            'name' => [
                'required', 'string', 'max:10',
                Rule::unique('classrooms', 'name')->where('term_id', $term->id)->ignore($classroom?->id),
            ],
            'homeroom_teacher' => ['nullable', 'string', 'max:100'],
            'homeroom_id_number' => ['nullable', 'string', 'max:30'],
            'english_teacher' => ['nullable', 'string', 'max:100'],
            'english_id_number' => ['nullable', 'string', 'max:30'],
        ], [
            'grade.required' => 'Pilih jenjang kelas (3–6).',
            'name.required' => 'Nama kelas wajib diisi.',
            'name.max' => 'Nama kelas maksimal 10 karakter.',
            'name.unique' => 'Nama kelas ini sudah dipakai di periode yang sama.',
        ]);

        $data['name'] = mb_strtoupper(trim($data['name']));
        $data['grade'] = (int) $data['grade'];

        return $data;
    }
}
