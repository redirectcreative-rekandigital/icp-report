<?php

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Score;
use App\Models\ScoringConfig;
use App\Models\Student;
use App\Models\Term;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->completeSetup();
    $this->withPinSession();
});

it('creates an academic year with YYYY/YYYY validation', function () {
    $this->post('/periode/tahun', ['name' => '2026/2027'])->assertSessionHasNoErrors();
    expect(AcademicYear::where('name', '2026/2027')->exists())->toBeTrue();

    $this->post('/periode/tahun', ['name' => '2026/2028'])->assertSessionHasErrors('name');
    $this->post('/periode/tahun', ['name' => '26/27'])->assertSessionHasErrors('name');
    $this->post('/periode/tahun', ['name' => '2026/2027'])->assertSessionHasErrors(['name' => 'Tahun ajaran ini sudah ada.']);
});

it('creates a semester with default scoring configs for grades 3–6 and activates the first period', function () {
    $year = AcademicYear::create(['name' => '2026/2027']);

    $this->post("/periode/tahun/{$year->id}/semester", ['semester' => 1, 'report_date' => '2026-12-18'])
        ->assertSessionHas('success', fn ($m) => str_contains($m, 'pengaturan penilaian bawaan') && str_contains($m, 'periode aktif'));

    $term = Term::first();
    expect($term->is_active)->toBeTrue()
        ->and($term->scoringConfigs()->count())->toBe(4);

    $g5 = ScoringConfig::where(['term_id' => $term->id, 'grade' => 5])->first();
    expect($g5->min_intermediate)->toBe(13.0)
        ->and($g5->min_advanced)->toBe(20.0);

    $this->post("/periode/tahun/{$year->id}/semester", ['semester' => 1])->assertSessionHasErrors('semester');
});

it('copies scoring configs from the latest period', function () {
    $year = AcademicYear::create(['name' => '2026/2027']);
    $this->post("/periode/tahun/{$year->id}/semester", ['semester' => 1]);
    ScoringConfig::where('grade', 4)->update(['min_intermediate' => 14.5, 'min_advanced' => 21]);

    $this->post("/periode/tahun/{$year->id}/semester", ['semester' => 2])
        ->assertSessionHas('success', fn ($m) => str_contains($m, 'disalin dari 2026/2027 • Semester 1'));

    $s2 = Term::where('semester', 2)->first();
    expect($s2->is_active)->toBeFalse()
        ->and(ScoringConfig::where(['term_id' => $s2->id, 'grade' => 4])->first()->min_intermediate)->toBe(14.5);
});

it('keeps only one active period', function () {
    $year = AcademicYear::create(['name' => '2026/2027']);
    $this->post("/periode/tahun/{$year->id}/semester", ['semester' => 1]);
    $this->post("/periode/tahun/{$year->id}/semester", ['semester' => 2]);
    $s2 = Term::where('semester', 2)->first();

    $this->post("/periode/{$s2->id}/aktifkan")->assertSessionHas('success');

    expect(Term::where('is_active', true)->pluck('id')->all())->toBe([$s2->id]);
    $this->get('/')->assertInertia(fn (Assert $p) => $p->where('activeTerm.label', '2026/2027 • Semester 2')->where('activeTerm.short', '2026/27 S2'));
});

it('deletes a period only without scores and with a recent PIN', function () {
    $year = AcademicYear::create(['name' => '2026/2027']);
    $this->post("/periode/tahun/{$year->id}/semester", ['semester' => 1]);
    $this->post("/periode/tahun/{$year->id}/semester", ['semester' => 2]);
    $s2 = Term::where('semester', 2)->first();
    $class = Classroom::create(['term_id' => $s2->id, 'grade' => 3, 'name' => '3A']);
    $student = Student::create(['nipd' => '3001', 'nisn' => '000003001', 'name' => 'Aditya Pratama']);
    $enrollment = Enrollment::create(['classroom_id' => $class->id, 'student_id' => $student->id, 'term_id' => $s2->id]);
    Score::create(['enrollment_id' => $enrollment->id, 'listening' => 15]);

    $this->withPinSession(confirmedAgo: 600)->deleteJson("/periode/{$s2->id}")->assertStatus(428);

    $this->withPinSession()->delete("/periode/{$s2->id}")
        ->assertSessionHas('error', 'Periode ini sudah memiliki nilai sehingga tidak dapat dihapus.');

    Score::query()->delete();
    $this->withPinSession()->delete("/periode/{$s2->id}")->assertSessionHas('success');
    expect(Term::find($s2->id))->toBeNull()->and(Classroom::count())->toBe(0)->and(Student::count())->toBe(1);

    // The active period cannot be deleted while another exists… but here it is the only one.
    $s1 = Term::first();
    $this->withPinSession()->delete("/periode/{$s1->id}")->assertSessionHas('success');
});

it('lists years and semesters with counts', function () {
    $year = AcademicYear::create(['name' => '2026/2027']);
    $this->post("/periode/tahun/{$year->id}/semester", ['semester' => 1]);
    $term = Term::first();
    Classroom::create(['term_id' => $term->id, 'grade' => 3, 'name' => '3A', 'status' => 'final']);
    Classroom::create(['term_id' => $term->id, 'grade' => 3, 'name' => '3B']);

    $this->get('/periode')->assertInertia(fn (Assert $p) => $p
        ->component('Periods/Index')
        ->where('years.0.name', '2026/2027')
        ->where('years.0.terms.0.classes', 2)
        ->where('years.0.terms.0.final', 1)
        ->where('years.0.terms.0.is_active', true));
});
