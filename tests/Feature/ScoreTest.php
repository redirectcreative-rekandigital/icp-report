<?php

use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Score;
use App\Models\Student;
use App\Services\TermService;
use Database\Seeders\CefrReferenceSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->completeSetup();
    $this->seed(CefrReferenceSeeder::class);
    $this->withPinSession();
    [$this->term] = app(TermService::class)->createTerm(AcademicYear::create(['name' => '2026/2027']), 1, null);
    $this->class = Classroom::create(['term_id' => $this->term->id, 'grade' => 5, 'name' => '5A']);
    $this->kevin = Enrollment::create([
        'classroom_id' => $this->class->id,
        'student_id' => Student::create(['nis' => '5001', 'name' => 'Kevin Wijaya'])->id,
        'term_id' => $this->term->id,
        'roll_no' => 1,
    ]);
});

function patchScore($test, string $field, mixed $value, int $version)
{
    return $test->patchJson("/kelas/{$test->class->id}/skor/{$test->kevin->id}", compact('field', 'value', 'version'));
}

it('renders the score grid with config, levels and rows', function () {
    $this->get("/kelas/{$this->class->id}/nilai")->assertInertia(fn (Assert $p) => $p
        ->component('Classes/Show')
        ->where('config.thresholds.B1', 85)
        ->where('config.expected_max', 'A2')
        ->has('levels', 5)
        ->where('rows.0.name', 'Kevin Wijaya')
        ->where('rows.0.version', 0)
        ->where('rows.0.scores.listening', null));
});

it('autosaves a score and returns the server result, version and progress', function () {
    patchScore($this, 'listening', 15, 0)
        ->assertOk()
        ->assertJsonPath('scores.listening', 15)
        ->assertJsonPath('version', 1)
        ->assertJsonPath('result.level.listening', 'A2')
        ->assertJsonPath('result.status', 'incomplete')
        ->assertJsonPath('class_progress', ['complete' => 0, 'total' => 1]);

    patchScore($this, 'reading', '17', 1)->assertOk();
    patchScore($this, 'writing', 11, 2)->assertOk();
    patchScore($this, 'speaking', 13, 3)
        ->assertOk()
        ->assertJsonPath('result.overall_level', 'A2')
        ->assertJsonPath('result.status', 'meeting')
        ->assertJsonPath('result.strongest', 'reading')
        ->assertJsonPath('class_progress.complete', 1);

    expect((float) Score::first()->speaking)->toBe(13.0);
});

it('rejects invalid scores with clear messages', function (mixed $value, string $message) {
    patchScore($this, 'listening', $value, 0)->assertStatus(422)->assertJsonPath('errors.value.0', $message);
    expect(Score::count())->toBe(0);
})->with([
    [21, 'Nilai Listening harus antara 0 dan 20.'],
    [-1, 'Nilai Listening harus antara 0 dan 20.'],
    ['abc', "Listening 'abc' bukan angka."],
    [12.3, 'Nilai Listening harus bulat atau kelipatan 0,5.'],
]);

it('accepts 12.5 and clears with null', function () {
    patchScore($this, 'listening', 12.5, 0)->assertOk()->assertJsonPath('scores.listening', 12.5);
    patchScore($this, 'listening', null, 1)->assertOk()->assertJsonPath('scores.listening', null);
});

it('returns 409 with the latest values on a version conflict', function () {
    patchScore($this, 'listening', 15, 0)->assertOk();

    patchScore($this, 'listening', 16, 0)
        ->assertStatus(409)
        ->assertJsonPath('code', 'VERSION_CONFLICT')
        ->assertJsonPath('current.scores.listening', 15)
        ->assertJsonPath('current.version', 1);
});

it('returns 423 when the class is final', function () {
    $this->class->update(['status' => 'final']);
    patchScore($this, 'listening', 15, 0)->assertStatus(423)->assertJsonPath('code', 'CLASS_FINAL');
});

it('saves the teacher comment (max 600 characters)', function () {
    patchScore($this, 'comment', '  Great progress!  ', 0)->assertOk()->assertJsonPath('comment', 'Great progress!');
    patchScore($this, 'comment', str_repeat('a', 601), 1)->assertStatus(422);
});

it('merges consecutive edits of one cell within 60 seconds into one log entry', function () {
    patchScore($this, 'listening', 13, 0);
    patchScore($this, 'listening', 14, 1);
    patchScore($this, 'listening', 15, 2);
    patchScore($this, 'reading', 17, 3);

    $logs = ActivityLog::where('action', 'score.updated')->orderBy('id')->pluck('description')->all();
    expect($logs)->toBe([
        'Nilai Listening Kevin Wijaya (5A) diubah – → 15',
        'Nilai Reading Kevin Wijaya (5A) diubah – → 17',
    ]);

    $this->travel(61)->seconds();
    patchScore($this, 'listening', 16, 4);
    expect(ActivityLog::where('action', 'score.updated')->latest('id')->value('description'))
        ->toBe('Nilai Listening Kevin Wijaya (5A) diubah 15 → 16');
});

it('refuses an enrollment from another class', function () {
    $other = Classroom::create(['term_id' => $this->term->id, 'grade' => 5, 'name' => '5B']);
    $this->patchJson("/kelas/{$other->id}/skor/{$this->kevin->id}", ['field' => 'listening', 'value' => 10, 'version' => 0])->assertNotFound();
});
