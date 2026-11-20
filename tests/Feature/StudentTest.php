<?php

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Score;
use App\Models\Student;
use App\Services\TermService;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->completeSetup();
    $this->withPinSession();
    [$this->term] = app(TermService::class)->createTerm(AcademicYear::create(['name' => '2026/2027']), 1, null);
    $this->c4a = Classroom::create(['term_id' => $this->term->id, 'grade' => 4, 'name' => '4A']);
    $this->c4b = Classroom::create(['term_id' => $this->term->id, 'grade' => 4, 'name' => '4B']);
    $this->c5a = Classroom::create(['term_id' => $this->term->id, 'grade' => 5, 'name' => '5A']);
});

it('creates a student and enrolls them with roll numbers ordered by name', function () {
    $this->post('/siswa', ['nis' => '4002', 'name' => 'Zahra', 'gender' => 'P', 'classroom_id' => $this->c4a->id])->assertSessionHasNoErrors();
    $this->post('/siswa', ['nis' => '4001', 'name' => "Ahmad D'Arcy", 'gender' => 'L', 'classroom_id' => $this->c4a->id])->assertSessionHasNoErrors();

    $roll = $this->c4a->enrollments()->with('student')->get()->mapWithKeys(fn ($e) => [$e->student->name => $e->roll_no])->all();
    expect($roll)->toBe(["Ahmad D'Arcy" => 1, 'Zahra' => 2]);
});

it('validates the student form', function (array $input, string $field) {
    Student::create(['nis' => '9999', 'name' => 'Sudah Ada']);
    $this->post('/siswa', $input + ['nis' => '4001', 'name' => 'Budi'])->assertSessionHasErrors($field);
})->with([
    'NIS too short' => [['nis' => '12'], 'nis'],
    'NIS with space' => [['nis' => '40 01'], 'nis'],
    'NIS taken' => [['nis' => '9999'], 'nis'],
    'name with digits' => [['name' => 'Budi 2'], 'name'],
    'gender' => [['gender' => 'X'], 'gender'],
    'class of another period' => [['classroom_id' => 999], 'classroom_id'],
]);

it('allows one class per student per period', function () {
    $this->post('/siswa', ['nis' => '4001', 'name' => 'Budi', 'classroom_id' => $this->c4a->id]);
    $budi = Student::where('nis', '4001')->first();

    // Moving through the edit form is allowed (same grade, no scores).
    $this->put("/siswa/{$budi->id}", ['nis' => '4001', 'name' => 'Budi', 'classroom_id' => $this->c4b->id])->assertSessionHasNoErrors();
    expect(Enrollment::where('student_id', $budi->id)->pluck('classroom_id')->all())->toBe([$this->c4b->id]);

    // The DB itself refuses a second enrollment in the same period.
    expect(fn () => Enrollment::create(['classroom_id' => $this->c4a->id, 'student_id' => $budi->id, 'term_id' => $this->term->id]))
        ->toThrow(QueryException::class);
});

it('keeps scores when moving within a grade but blocks moving to another grade', function () {
    $this->post('/siswa', ['nis' => '4001', 'name' => 'Budi', 'classroom_id' => $this->c4a->id]);
    $budi = Student::where('nis', '4001')->first();
    Score::create(['enrollment_id' => $budi->enrollments()->first()->id, 'listening' => 12]);

    $this->post('/siswa/massal', ['action' => 'move', 'ids' => [$budi->id], 'classroom_id' => $this->c5a->id])
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'hanya bisa dipindah ke kelas dengan jenjang yang sama'));

    $this->post('/siswa/massal', ['action' => 'move', 'ids' => [$budi->id], 'classroom_id' => $this->c4b->id])
        ->assertSessionHas('success', '1 siswa dipindahkan.');
    expect($budi->enrollments()->first()->score->listening)->toBe(12.0);

    $this->post('/siswa/massal', ['action' => 'remove', 'ids' => [$budi->id], 'term_id' => $this->term->id])
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'sudah memiliki nilai'));
});

it('refuses to remove students from a final class', function () {
    $this->post('/siswa', ['nis' => '4001', 'name' => 'Budi', 'classroom_id' => $this->c4a->id]);
    $this->c4a->update(['status' => 'final']);
    $budi = Student::where('nis', '4001')->first();

    $this->post('/siswa/massal', ['action' => 'remove', 'ids' => [$budi->id], 'term_id' => $this->term->id])
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'sudah final'));
    $this->post('/siswa', ['nis' => '4002', 'name' => 'Cici', 'classroom_id' => $this->c4a->id])->assertSessionHas('error');
    expect(Student::where('nis', '4002')->exists())->toBeFalse(); // rolled back
});

it('deactivates in bulk and refuses deleting a student with scores', function () {
    $this->post('/siswa', ['nis' => '4001', 'name' => 'Budi', 'classroom_id' => $this->c4a->id]);
    $this->post('/siswa', ['nis' => '4002', 'name' => 'Cici']);
    $ids = Student::pluck('id')->all();

    $this->post('/siswa/massal', ['action' => 'deactivate', 'ids' => $ids])->assertSessionHas('success', '2 siswa dinonaktifkan.');
    expect(Student::where('is_active', true)->count())->toBe(0);

    $budi = Student::where('nis', '4001')->first();
    Score::create(['enrollment_id' => $budi->enrollments()->first()->id, 'reading' => 10]);

    $this->withPinSession()->delete("/siswa/{$budi->id}")->assertSessionHas('error', fn ($m) => str_contains($m, 'Pilih Nonaktifkan'));
    $cici = Student::where('nis', '4002')->first();
    $this->withPinSession(confirmedAgo: 600)->deleteJson("/siswa/{$cici->id}")->assertStatus(428);
    $this->withPinSession()->delete("/siswa/{$cici->id}")->assertRedirect('/siswa');
    expect(Student::count())->toBe(1);
});

it('searches and filters the student list', function () {
    $this->post('/siswa', ['nis' => '4001', 'name' => 'Budi Santoso', 'classroom_id' => $this->c4a->id]);
    $this->post('/siswa', ['nis' => '5001', 'name' => 'Kevin Wijaya', 'classroom_id' => $this->c5a->id]);
    $this->post('/siswa', ['nis' => '7777', 'name' => 'Tanpa Kelas']);

    $this->get('/siswa?q=kevin')->assertInertia(fn (Assert $p) => $p->has('students.data', 1)->where('students.data.0.classroom.name', '5A'));
    $this->get('/siswa?q=4001')->assertInertia(fn (Assert $p) => $p->where('students.data.0.name', 'Budi Santoso'));
    $this->get("/siswa?class={$this->c4a->id}")->assertInertia(fn (Assert $p) => $p->has('students.data', 1));
    $this->get('/siswa?class=none')->assertInertia(fn (Assert $p) => $p->has('students.data', 1)->where('students.data.0.nis', '7777'));
    $this->get('/siswa?status=inactive')->assertInertia(fn (Assert $p) => $p->has('students.data', 0));
});

it('shows the student profile with class history', function () {
    $this->post('/siswa', ['nis' => '4001', 'name' => 'Budi', 'classroom_id' => $this->c4a->id]);
    $budi = Student::first();

    $this->get("/siswa/{$budi->id}")->assertInertia(fn (Assert $p) => $p
        ->component('Students/Show')
        ->where('student.classroom_id', $this->c4a->id)
        ->where('history.0.classroom.name', '4A')
        ->where('history.0.term', '2026/2027 • Semester 1'));
});
