<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Class membership rules (PRD-03 §4.2):
 * - one class per student per period;
 * - nothing changes in a Final class;
 * - a student with scores cannot leave the class (scores would be lost) and can only move
 *   to a class of the same grade (thresholds differ per grade). The score moves along.
 */
class EnrollmentService
{
    public function __construct(private ActivityLogger $logger) {}

    /**
     * Puts the student in $classroom. Returns the enrollment, or null when nothing changed.
     *
     * @param  bool  $allowMove  move from another class of the same period (otherwise: error)
     */
    public function assign(Student $student, Classroom $classroom, bool $allowMove = false, bool $log = true): ?Enrollment
    {
        if ($classroom->isFinal()) {
            throw BusinessRuleException::classLocked($classroom->name);
        }

        if (! $student->is_active) {
            throw new BusinessRuleException("{$student->name} berstatus nonaktif. Aktifkan siswa terlebih dahulu.");
        }

        $existing = Enrollment::query()
            ->with('classroom')
            ->where('term_id', $classroom->term_id)
            ->where('student_id', $student->id)
            ->first();

        if ($existing?->classroom_id === $classroom->id) {
            return null;
        }

        return DB::transaction(function () use ($existing, $student, $classroom, $allowMove, $log) {
            if ($existing) {
                $from = $existing->classroom;

                if (! $allowMove) {
                    throw new BusinessRuleException("Siswa ini sudah terdaftar di {$from->name} pada periode ini.");
                }

                $this->guardLeave($existing, $from, $student);

                if ($from->grade !== $classroom->grade && $existing->hasScores()) {
                    throw new BusinessRuleException(
                        "{$student->name} sudah memiliki nilai di {$from->name}; hanya bisa dipindah ke kelas dengan jenjang yang sama.",
                    );
                }

                $existing->update(['classroom_id' => $classroom->id]);
                $this->renumber($from);
                $this->renumber($classroom);

                if ($log) {
                    $this->logger->log('enrollment.moved', 'data', "{$student->name} dipindah dari {$from->name} ke {$classroom->name}.", $existing);
                }

                return $existing->refresh();
            }

            $enrollment = Enrollment::query()->create([
                'classroom_id' => $classroom->id,
                'student_id' => $student->id,
                'term_id' => $classroom->term_id,
            ]);
            $this->renumber($classroom);

            if ($log) {
                $this->logger->log('enrollment.created', 'data', "{$student->name} dimasukkan ke kelas {$classroom->name}.", $enrollment);
            }

            return $enrollment->refresh();
        });
    }

    public function remove(Enrollment $enrollment): void
    {
        $enrollment->loadMissing('classroom', 'student');
        $this->guardLeave($enrollment, $enrollment->classroom, $enrollment->student);

        if ($enrollment->hasScores()) {
            throw new BusinessRuleException(
                "{$enrollment->student->name} sudah memiliki nilai di {$enrollment->classroom->name} sehingga tidak dapat dikeluarkan.",
            );
        }

        DB::transaction(function () use ($enrollment) {
            $enrollment->delete();
            $this->renumber($enrollment->classroom);
        });

        $this->logger->log('enrollment.deleted', 'data', "{$enrollment->student->name} dikeluarkan dari kelas {$enrollment->classroom->name}.");
    }

    private function guardLeave(Enrollment $enrollment, Classroom $from, Student $student): void
    {
        if ($from->isFinal()) {
            throw new BusinessRuleException(
                "{$student->name} terdaftar di kelas {$from->name} yang sudah final. Batalkan finalisasi terlebih dahulu.",
                'CLASS_FINAL',
                423,
            );
        }
    }

    /** Roll numbers follow the students' names A–Z (PRD-03 §4.2). */
    public function renumber(Classroom $classroom): void
    {
        $ids = Enrollment::query()
            ->join('students', 'students.id', '=', 'enrollments.student_id')
            ->where('enrollments.classroom_id', $classroom->id)
            ->orderBy('students.name')
            ->orderBy('students.nis')
            ->pluck('enrollments.id');

        foreach ($ids as $i => $id) {
            Enrollment::query()->whereKey($id)->update(['roll_no' => $i + 1]);
        }
    }
}
