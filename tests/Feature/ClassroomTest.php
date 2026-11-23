<?php

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Score;
use App\Models\Student;
use App\Services\ClassroomService;
use App\Services\TermService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->completeSetup();
    $this->withPinSession();
    [$this->term] = app(TermService::class)->createTerm(AcademicYear::create(['name' => '2026/2027']), 1, null);
});

function enroll(Classroom $c, string $nipd, string $name): Enrollment
{
    $s = Student::firstOrCreate(['nipd' => $nipd], ['nisn' => '0000'.$nipd, 'name' => $name]);

    return Enrollment::create(['classroom_id' => $c->id, 'student_id' => $s->id, 'term_id' => $c->term_id]);
}

it('creates a class with a unique name per period', function () {
    $this->post("/periode/{$this->term->id}/kelas", [
        'grade' => 5, 'name' => '5a', 'homeroom_teacher' => 'Bu Rina', 'homeroom_id_number' => '123',
    ])->assertSessionHasNoErrors();

    $class = Classroom::first();
    expect($class->name)->toBe('5A')->and($class->grade)->toBe(5)->and($class->status)->toBe('draft');

    $this->post("/periode/{$this->term->id}/kelas", ['grade' => 5, 'name' => '5A'])
        ->assertSessionHasErrors(['name' => 'Nama kelas ini sudah dipakai di periode yang sama.']);
    $this->post("/periode/{$this->term->id}/kelas", ['grade' => 7, 'name' => '7A'])->assertSessionHasErrors('grade');
    $this->post("/periode/{$this->term->id}/kelas", ['grade' => 3, 'name' => '3ABCDEFGHIJ'])->assertSessionHasErrors('name');
});

it('refuses changes to a final class', function () {
    $class = Classroom::create(['term_id' => $this->term->id, 'grade' => 5, 'name' => '5A', 'status' => 'final']);

    $this->put("/data-kelas/{$class->id}", ['grade' => 5, 'name' => '5A', 'homeroom_teacher' => 'Baru'])
        ->assertSessionHas('error', 'Kelas 5A sudah final. Batalkan finalisasi untuk mengubah data kelas.');
    $this->putJson("/data-kelas/{$class->id}", ['grade' => 5, 'name' => '5A'])->assertStatus(423)->assertJsonPath('code', 'CLASS_FINAL');

    expect($class->fresh()->homeroom_teacher)->toBeNull();
});

it('locks the grade once the class has scores', function () {
    $class = Classroom::create(['term_id' => $this->term->id, 'grade' => 5, 'name' => '5A']);
    Score::create(['enrollment_id' => enroll($class, '5001', 'Kevin Wijaya')->id, 'listening' => 15]);

    $this->put("/data-kelas/{$class->id}", ['grade' => 6, 'name' => '5A'])->assertSessionHas('error');
    expect($class->fresh()->grade)->toBe(5);

    $this->put("/data-kelas/{$class->id}", ['grade' => 5, 'name' => '5A', 'homeroom_teacher' => 'Bu Rina'])->assertSessionHas('success');
    expect($class->fresh()->homeroom_teacher)->toBe('Bu Rina');
});

it('deletes a class only with a recent PIN and without scores', function () {
    $class = Classroom::create(['term_id' => $this->term->id, 'grade' => 3, 'name' => '3A']);
    enroll($class, '3001', 'Aditya Pratama');

    $this->withPinSession(confirmedAgo: 600)->deleteJson("/data-kelas/{$class->id}")->assertStatus(428);
    $this->withPinSession()->delete("/data-kelas/{$class->id}")->assertSessionHas('success', 'Kelas 3A dihapus.');

    expect(Classroom::count())->toBe(0)->and(Enrollment::count())->toBe(0)->and(Student::count())->toBe(1);
});

it('copies classes with promotion and students into a new year', function () {
    $c3 = Classroom::create(['term_id' => $this->term->id, 'grade' => 3, 'name' => '3A', 'homeroom_teacher' => 'Bu Rina']);
    $c6 = Classroom::create(['term_id' => $this->term->id, 'grade' => 6, 'name' => '6A']);
    enroll($c3, '3001', 'Aditya Pratama');
    enroll($c3, '3002', 'Bunga Lestari')->student->update(['is_active' => false]);
    enroll($c6, '6001', 'Lulus');

    [$next] = app(TermService::class)->createTerm(AcademicYear::create(['name' => '2027/2028']), 1, null);

    $plan = app(ClassroomService::class)->copyPlan($next, $this->term, [$c3->id, $c6->id], promote: true, withStudents: true);
    expect(collect($plan)->firstWhere('source_name', '3A'))->toMatchArray(['name' => '4A', 'grade' => 4, 'students' => 1, 'skip' => null])
        ->and(collect($plan)->firstWhere('source_name', '6A')['skip'])->toBe('Kelas 6 tidak ikut dinaikkan.');

    $this->postJson("/periode/{$next->id}/salin-kelas/pratinjau", ['source_term_id' => $this->term->id, 'all' => true, 'promote' => true])
        ->assertOk()->assertJsonCount(2, 'plan');

    $this->post("/periode/{$next->id}/salin-kelas", [
        'source_term_id' => $this->term->id, 'class_ids' => [$c3->id, $c6->id], 'promote' => true, 'with_students' => true,
    ])->assertSessionHas('success', '1 kelas disalin beserta 1 siswa.');

    $new = Classroom::where('term_id', $next->id)->first();
    expect($new->name)->toBe('4A')
        ->and($new->homeroom_teacher)->toBe('Bu Rina')
        ->and($new->enrollments()->with('student')->get()->pluck('student.nipd')->all())->toBe(['3001']);

    // Second copy: the class already exists → skipped.
    $plan = app(ClassroomService::class)->copyPlan($next, $this->term, [$c3->id], true, false);
    expect($plan[0]['skip'])->toBe('Kelas 4A sudah ada di periode tujuan.');
});

it('shows class cards per grade with progress, and redirects to the right tab', function () {
    $class = Classroom::create(['term_id' => $this->term->id, 'grade' => 5, 'name' => '5A']);
    $e1 = enroll($class, '5001', 'Kevin Wijaya');
    enroll($class, '5002', 'Laras');
    Score::create(['enrollment_id' => $e1->id, 'listening' => 15, 'reading' => 17, 'writing' => 11, 'speaking' => 13]);

    $this->get('/kelas')->assertInertia(fn (Assert $p) => $p
        ->component('Classes/Index')
        ->where('classes.0.total', 2)
        ->where('classes.0.complete', 1)
        ->where('nav.classes.0.name', '5A')
        ->where('nav.classes.0.complete', 1));

    $this->get("/kelas/{$class->id}")->assertRedirect("/kelas/{$class->id}/nilai");
    $class->update(['status' => 'final']);
    $this->get("/kelas/{$class->id}")->assertRedirect("/kelas/{$class->id}/rapor");

    $this->get("/kelas/{$class->id}/nilai")->assertInertia(fn (Assert $p) => $p
        ->component('Classes/Show')
        ->where('tab', 'nilai')
        ->has('rows', 2)
        ->where('rows.0.name', 'Kevin Wijaya'));
});

it('promotes class names', function (string $name, int $grade, string $expected) {
    expect(ClassroomService::promotedName($name, $grade))->toBe($expected);
})->with([['3A', 3, '4A'], ['5B', 5, '6B'], ['Melati', 4, 'Melati']]);
