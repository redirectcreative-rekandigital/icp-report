<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Term;
use App\Services\ActivityLogger;
use App\Services\EnrollmentService;
use App\Services\StudentHistoryService;
use App\Services\StudentImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** /siswa — PRD-03 §4. History charts on the profile arrive in M5 (PRD-06 §4). */
class StudentController extends Controller
{
    public function __construct(private EnrollmentService $enrollments, private ActivityLogger $logger) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'term' => ['nullable', 'integer'],
            'class' => ['nullable', 'string'], // classroom id or "none"
            'status' => ['nullable', Rule::in(['active', 'inactive', 'all'])],
            'per_page' => ['nullable', Rule::in([25, 50, 100])],
        ]);

        $term = isset($filters['term']) ? Term::query()->with('academicYear')->find($filters['term']) : Term::active();
        $status = $filters['status'] ?? 'active';
        $class = $filters['class'] ?? null;

        $students = Student::query()
            ->when($term, fn ($q) => $q->with(['enrollments' => fn ($e) => $e->where('term_id', $term->id)->with('classroom:id,name,status')]))
            ->when($filters['q'] ?? null, function ($q, $s) {
                $like = '%'.addcslashes($s, '%_\\').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('nipd', 'like', $like)->orWhere('nisn', 'like', $like));
            })
            ->when($status !== 'all', fn ($q) => $q->where('is_active', $status === 'active'))
            ->when($term && $class === 'none', fn ($q) => $q->whereDoesntHave('enrollments', fn ($e) => $e->where('term_id', $term->id)))
            ->when($term && $class && $class !== 'none', fn ($q) => $q->whereHas('enrollments', fn ($e) => $e->where('classroom_id', (int) $class)))
            ->orderBy('name')
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString();

        $lastLevels = StudentHistoryService::latestFinalLevels($students->getCollection()->pluck('id'));

        $students = $students
            ->through(function (Student $s) use ($lastLevels) {
                $enrollment = $s->relationLoaded('enrollments') ? $s->enrollments->first() : null;

                return [
                    'id' => $s->id,
                    'nipd' => $s->nipd,
                    'nisn' => $s->nisn,
                    'name' => $s->name,
                    'gender' => $s->gender,
                    'is_active' => $s->is_active,
                    'notes' => $s->notes,
                    'classroom' => $enrollment ? [
                        'id' => $enrollment->classroom->id,
                        'name' => $enrollment->classroom->name,
                        'final' => $enrollment->classroom->status === 'final',
                    ] : null,
                    'last_level' => $lastLevels[$s->id] ?? null,
                ];
            });

        return Inertia::render('Students/Index', [
            'students' => $students,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'term' => $term?->id,
                'class' => $class ?? '',
                'status' => $status,
                'per_page' => (int) ($filters['per_page'] ?? 25),
            ],
            'terms' => PeriodController::termOptions(),
            'classes' => $term ? $this->classOptions($term) : [],
            'activeTermId' => Term::query()->where('is_active', true)->value('id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data) {
            $student = Student::query()->create(collect($data)->except('classroom_id')->all() + ['is_active' => true]);
            $this->logger->log('student.created', 'data', "Siswa {$student->name} ({$student->nipd}) ditambahkan.", $student);

            if (! empty($data['classroom_id'])) {
                $this->enrollments->assign($student, Classroom::query()->findOrFail($data['classroom_id']));
            }
        });

        return back()->with('success', "Siswa {$data['name']} ditambahkan.");
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $data = $this->validated($request, $student);
        $term = Term::active();

        DB::transaction(function () use ($data, $student, $term, $request) {
            $student->update(collect($data)->except('classroom_id')->all());
            $this->logger->log('student.updated', 'data', "Data siswa {$student->name} ({$student->nipd}) diperbarui.", $student);

            if (! $term || ! $request->has('classroom_id')) {
                return;
            }

            $current = Enrollment::query()->where('term_id', $term->id)->where('student_id', $student->id)->first();

            if (! empty($data['classroom_id'])) {
                $this->enrollments->assign($student, Classroom::query()->findOrFail($data['classroom_id']), allowMove: true);
            } elseif ($current) {
                $this->enrollments->remove($current);
            }
        });

        return back()->with('success', "Data {$student->name} tersimpan.");
    }

    public function destroy(Student $student): RedirectResponse
    {
        if ($student->hasScores()) {
            throw new BusinessRuleException("{$student->name} sudah memiliki nilai sehingga tidak dapat dihapus. Pilih Nonaktifkan.");
        }

        $final = $student->enrollments()->whereHas('classroom', fn ($q) => $q->where('status', 'final'))->with('classroom')->first();
        if ($final) {
            throw new BusinessRuleException("{$student->name} terdaftar di kelas {$final->classroom->name} yang sudah final.");
        }

        $label = "{$student->name} ({$student->nipd})";
        DB::transaction(function () use ($student) {
            $classes = Classroom::query()->whereIn('id', $student->enrollments()->pluck('classroom_id'))->get();
            $student->delete();
            $classes->each(fn ($c) => $this->enrollments->renumber($c));
        });
        $this->logger->log('student.deleted', 'data', "Siswa {$label} dihapus.");

        return redirect()->route('students.index')->with('success', "Siswa {$label} dihapus.");
    }

    /** Bulk actions (PRD-03 §4.1): move to class, remove from class, deactivate, activate. */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['move', 'remove', 'deactivate', 'activate'])],
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
            'classroom_id' => ['required_if:action,move', 'nullable', 'integer', 'exists:classrooms,id'],
            'term_id' => ['required_if:action,remove', 'nullable', 'integer', 'exists:terms,id'],
        ], ['ids.required' => 'Pilih minimal satu siswa.', 'classroom_id.required_if' => 'Pilih kelas tujuan.']);

        $students = Student::query()->whereIn('id', $data['ids'])->orderBy('name')->get();
        $done = 0;
        $failed = [];

        foreach ($students as $student) {
            try {
                match ($data['action']) {
                    'move' => $this->enrollments->assign($student, Classroom::query()->findOrFail($data['classroom_id']), allowMove: true),
                    'remove' => ($e = Enrollment::query()->where('term_id', $data['term_id'])->where('student_id', $student->id)->first())
                        ? $this->enrollments->remove($e) : null,
                    'deactivate' => $student->update(['is_active' => false]),
                    'activate' => $student->update(['is_active' => true]),
                };
                $done++;
            } catch (BusinessRuleException $e) {
                $failed[] = $e->getMessage();
            }
        }

        if (in_array($data['action'], ['deactivate', 'activate'], true) && $done) {
            $verb = $data['action'] === 'deactivate' ? 'dinonaktifkan' : 'diaktifkan';
            $this->logger->log("student.bulk_{$data['action']}", 'data', "{$done} siswa {$verb}.");
        }

        $labels = ['move' => 'dipindahkan', 'remove' => 'dikeluarkan dari kelas', 'deactivate' => 'dinonaktifkan', 'activate' => 'diaktifkan'];
        $redirect = back();

        if ($done) {
            $redirect = $redirect->with('success', "{$done} siswa {$labels[$data['action']]}.");
        }

        if ($failed) {
            $more = count($failed) > 3 ? ' (+'.(count($failed) - 3).' lainnya)' : '';
            $redirect = $redirect->with('error', count($failed).' siswa gagal: '.implode(' ', array_slice($failed, 0, 3)).$more);
        }

        return $redirect;
    }

    public function show(Student $student, StudentHistoryService $historyService): Response
    {
        $points = $historyService->points($student);
        $latestFinal = collect($points)->whereIn('source', ['final', 'correcting'])->last();

        $history = $student->enrollments()
            ->with(['classroom', 'term.academicYear'])
            ->get()
            ->sortByDesc(fn (Enrollment $e) => $e->term->academicYear->name.$e->term->semester)
            ->values()
            ->map(fn (Enrollment $e) => [
                'id' => $e->id,
                'term' => $e->term->fullLabel(),
                'term_active' => $e->term->is_active,
                'classroom' => ['id' => $e->classroom->id, 'name' => $e->classroom->name, 'grade' => $e->classroom->grade, 'status' => $e->classroom->status],
                'roll_no' => $e->roll_no,
            ]);

        $term = Term::active();

        return Inertia::render('Students/Show', [
            'student' => [
                'id' => $student->id,
                'nipd' => $student->nipd,
                'nisn' => $student->nisn,
                'name' => $student->name,
                'gender' => $student->gender,
                'is_active' => $student->is_active,
                'notes' => $student->notes,
                'has_scores' => $student->hasScores(),
                'classroom_id' => $term ? $student->enrollments()->where('term_id', $term->id)->value('classroom_id') : null,
            ],
            'history' => $history,
            'points' => $points,
            'latestLevel' => $latestFinal['overall_level'] ?? null,
            'narrative' => $historyService->narrative($student, $points),
            'classes' => $term ? $this->classOptions($term) : [],
        ]);
    }

    /** @return list<array{id: int, name: string, grade: int, final: bool}> */
    private function classOptions(Term $term): array
    {
        return $term->classrooms()->orderBy('grade')->orderBy('name')->get()
            ->map(fn (Classroom $c) => ['id' => $c->id, 'name' => $c->name, 'grade' => $c->grade, 'final' => $c->isFinal()])
            ->all();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Student $student = null): array
    {
        $activeTermId = Term::query()->where('is_active', true)->value('id');

        $data = $request->validate([
            'nipd' => ['required', 'regex:'.StudentImportService::NIPD_PATTERN, Rule::unique('students', 'nipd')->ignore($student?->id)],
            'nisn' => ['required', 'regex:'.StudentImportService::NISN_PATTERN, Rule::unique('students', 'nisn')->ignore($student?->id)],
            'name' => ['required', 'regex:'.StudentImportService::NAME_PATTERN],
            'gender' => ['nullable', Rule::in(['L', 'P'])],
            'classroom_id' => ['nullable', 'integer', Rule::exists('classrooms', 'id')->where('term_id', $activeTermId)],
            'notes' => ['nullable', 'string', 'max:300'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'nipd.required' => 'NIPD wajib diisi.',
            'nipd.regex' => StudentImportService::NIPD_MESSAGE,
            'nipd.unique' => 'NIPD ini sudah dipakai siswa lain.',
            'nisn.required' => 'NISN wajib diisi.',
            'nisn.regex' => StudentImportService::NISN_MESSAGE,
            'nisn.unique' => 'NISN ini sudah dipakai siswa lain.',
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.regex' => 'Nama 2–100 karakter, hanya huruf, spasi, apostrof, titik, atau tanda hubung.',
            'classroom_id.exists' => 'Pilih kelas dari periode aktif.',
            'notes.max' => 'Catatan maksimal 300 karakter.',
        ]);

        $data['nipd'] = trim($data['nipd']);
        $data['nisn'] = trim($data['nisn']);
        $data['name'] = preg_replace('/\s+/', ' ', trim($data['name']));

        return $data;
    }
}
