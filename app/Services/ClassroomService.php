<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Support\Facades\DB;

/** Classes inside a period (PRD-03 §3.2). */
class ClassroomService
{
    public function __construct(private ActivityLogger $logger, private EnrollmentService $enrollments) {}

    /** @param array<string, mixed> $data */
    public function create(Term $term, array $data): Classroom
    {
        $classroom = $term->classrooms()->create($data);
        $this->logger->log('class.created', 'data', "Kelas {$classroom->name} dibuat.", $classroom);

        return $classroom;
    }

    /** @param array<string, mixed> $data */
    public function update(Classroom $classroom, array $data): Classroom
    {
        if ($classroom->isFinal()) {
            throw BusinessRuleException::classLocked($classroom->name);
        }

        if ((int) $data['grade'] !== $classroom->grade && $classroom->hasScores()) {
            throw new BusinessRuleException(
                "Jenjang kelas {$classroom->name} tidak dapat diubah karena sudah ada nilai (ambang batas tiap jenjang berbeda).",
            );
        }

        $classroom->update($data);
        $this->logger->log('class.updated', 'data', "Data kelas {$classroom->name} diperbarui.", $classroom);

        return $classroom;
    }

    public function delete(Classroom $classroom): void
    {
        if ($classroom->isFinal()) {
            throw BusinessRuleException::classLocked($classroom->name);
        }

        if ($classroom->hasScores()) {
            throw new BusinessRuleException("Kelas {$classroom->name} sudah memiliki nilai sehingga tidak dapat dihapus.");
        }

        $name = $classroom->name;
        $classroom->delete();
        $this->logger->log('class.deleted', 'data', "Kelas {$name} dihapus.");
    }

    /**
     * "Salin kelas dari periode lain": preview what would be created.
     *
     * @param  list<int>  $classIds
     * @return list<array{source_id: int, source_name: string, name: string, grade: int, students: int, skip: ?string}>
     */
    public function copyPlan(Term $target, Term $source, array $classIds, bool $promote, bool $withStudents): array
    {
        $existingNames = $target->classrooms()->pluck('name')->map(fn ($n) => mb_strtoupper($n))->all();
        $taken = Enrollment::query()->where('term_id', $target->id)->pluck('student_id')->flip();
        $plan = [];
        $planned = [];

        $classes = $source->classrooms()
            ->whereIn('id', $classIds)
            ->withCount(['enrollments as active_students' => fn ($q) => $q->whereHas('student', fn ($s) => $s->where('is_active', true))])
            ->orderBy('grade')->orderBy('name')
            ->get();

        foreach ($classes as $c) {
            $grade = $promote ? $c->grade + 1 : $c->grade;
            $name = $promote ? self::promotedName($c->name, $c->grade) : $c->name;

            $skip = match (true) {
                $grade > 6 => 'Kelas 6 tidak ikut dinaikkan.',
                in_array(mb_strtoupper($name), $existingNames, true) => "Kelas {$name} sudah ada di periode tujuan.",
                in_array(mb_strtoupper($name), $planned, true) => "Nama {$name} ganda dalam salinan.",
                default => null,
            };

            $students = 0;
            if ($withStudents && ! $skip) {
                $students = Enrollment::query()
                    ->where('classroom_id', $c->id)
                    ->whereHas('student', fn ($s) => $s->where('is_active', true))
                    ->pluck('student_id')
                    ->reject(fn ($id) => $taken->has($id))
                    ->count();
            }

            if (! $skip) {
                $planned[] = mb_strtoupper($name);
            }

            $plan[] = [
                'source_id' => $c->id,
                'source_name' => $c->name,
                'name' => $name,
                'grade' => $grade,
                'students' => $students,
                'skip' => $skip,
            ];
        }

        return $plan;
    }

    /** @return array{classes: int, students: int} */
    public function copy(Term $target, Term $source, array $classIds, bool $promote, bool $withStudents): array
    {
        $plan = $this->copyPlan($target, $source, $classIds, $promote, $withStudents);

        return DB::transaction(function () use ($plan, $target, $withStudents) {
            $classes = 0;
            $students = 0;

            foreach ($plan as $row) {
                if ($row['skip']) {
                    continue;
                }

                $src = Classroom::query()->findOrFail($row['source_id']);
                $new = $target->classrooms()->create([
                    'grade' => $row['grade'],
                    'name' => $row['name'],
                    'homeroom_teacher' => $src->homeroom_teacher,
                    'homeroom_id_number' => $src->homeroom_id_number,
                    'english_teacher' => $src->english_teacher,
                    'english_id_number' => $src->english_id_number,
                ]);
                $classes++;

                if ($withStudents) {
                    $taken = Enrollment::query()->where('term_id', $target->id)->pluck('student_id')->flip();
                    $ids = Enrollment::query()->where('classroom_id', $src->id)->pluck('student_id');

                    foreach (Student::query()->whereIn('id', $ids)->where('is_active', true)->get() as $student) {
                        if (! $taken->has($student->id)) {
                            Enrollment::query()->create(['classroom_id' => $new->id, 'student_id' => $student->id, 'term_id' => $target->id]);
                            $students++;
                        }
                    }

                    $this->enrollments->renumber($new);
                }
            }

            $target->loadMissing('academicYear');
            $this->logger->log('class.copied', 'data', "{$classes} kelas dan {$students} siswa disalin ke periode {$target->fullLabel()}.");

            return ['classes' => $classes, 'students' => $students];
        });
    }

    /** "3A" → "4A" when the name starts with the grade number; otherwise unchanged. */
    public static function promotedName(string $name, int $grade): string
    {
        return str_starts_with($name, (string) $grade) ? ($grade + 1).substr($name, strlen((string) $grade)) : $name;
    }
}
