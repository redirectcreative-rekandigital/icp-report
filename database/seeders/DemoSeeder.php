<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Score;
use App\Models\Student;
use App\Services\EnrollmentService;
use App\Services\TermService;
use Illuminate\Database\Seeder;

/**
 * Optional sample data (PRD-01 §4.2): php artisan db:seed --class=DemoSeeder
 * Students & scores are the sample rows 6–10 of the Excel "Grade 3–6" sheets, plus two
 * students without scores in 5A to try the "Belum lengkap" flow. Safe to run twice.
 */
class DemoSeeder extends Seeder
{
    private const STUDENTS = [
        ['3001', 'Aditya Pratama', '3A', [16, 15, 14, 15]],
        ['3002', 'Bunga Lestari', '3A', [18, 17, 16, 17]],
        ['3003', 'Citra Ayu', '3A', [10, 11, 9, 12]],
        ['3004', 'Dimas Saputra', '3B', [13, 12, 12, 11]],
        ['3005', 'Eka Wulandari', '3B', [7, 8, 6, 9]],
        ['4001', 'Farhan Hidayat', '4A', [14, 15, 12, 13]],
        ['4002', 'Gita Permata', '4A', [17, 18, 16, 17]],
        ['4003', 'Hana Safitri', '4A', [9, 10, 8, 9]],
        ['4004', 'Irfan Maulana', '4B', [12, 11, 10, 12]],
        ['4005', 'Jihan Aulia', '4B', [16, 15, 14, 16]],
        ['5001', 'Kevin Wijaya', '5A', [15, 17, 11, 13]],
        ['5002', 'Laras Anindya', '5A', [18, 18, 17, 18]],
        ['5003', 'Muhammad Rizki', '5A', [8, 9, 6, 8]],
        ['5004', 'Nadia Putri', '5B', [13, 14, 12, 14]],
        ['5005', 'Oka Mahendra', '5B', [11, 12, 10, 12]],
        ['6001', 'Putri Amelia', '6A', [16, 17, 15, 16]],
        ['6002', 'Rafi Akbar', '6A', [19, 19, 18, 18]],
        ['6003', 'Salsabila Nur', '6A', [11, 12, 10, 11]],
        ['6004', 'Tegar Firmansyah', '6B', [8, 7, 6, 8]],
        ['6005', 'Vania Kusuma', '6B', [14, 15, 13, 14]],
        ['5006', 'Wulan Sari', '5A', null],
        ['5007', 'Yusuf Ramadhan', '5A', null],
    ];

    public function run(TermService $terms, EnrollmentService $enrollments): void
    {
        $this->call(CefrReferenceSeeder::class);

        $year = AcademicYear::query()->firstOrCreate(['name' => '2026/2027']);
        $term = $year->terms()->where('semester', 1)->first()
            ?? $terms->createTerm($year, 1, '2026-12-18')[0];

        if (! $term->is_active) {
            $terms->activate($term);
        }

        foreach ([3, 4, 5, 6] as $grade) {
            foreach (['A', 'B'] as $letter) {
                Classroom::query()->firstOrCreate(
                    ['term_id' => $term->id, 'name' => "{$grade}{$letter}"],
                    [
                        'grade' => $grade,
                        'homeroom_teacher' => "Homeroom Teacher {$grade}{$letter}",
                        'english_teacher' => "English Teacher {$grade}",
                    ],
                );
            }
        }

        $classes = $term->classrooms()->get()->keyBy('name');

        foreach (self::STUDENTS as [$nis, $name, $class, $scores]) {
            $student = Student::query()->firstOrCreate(['nis' => $nis], ['name' => $name, 'is_active' => true]);
            $enrollment = Enrollment::query()->where(['term_id' => $term->id, 'student_id' => $student->id])->first()
                ?? $enrollments->assign($student, $classes[$class], log: false);

            if ($scores && ! $enrollment->score) {
                Score::query()->create([
                    'enrollment_id' => $enrollment->id,
                    'listening' => $scores[0],
                    'reading' => $scores[1],
                    'writing' => $scores[2],
                    'speaking' => $scores[3],
                    'version' => 1,
                ]);
            }
        }
    }
}
