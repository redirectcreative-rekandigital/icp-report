<?php

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Descriptor;
use App\Models\Enrollment;
use App\Models\LevelMeta;
use App\Models\Score;
use App\Models\ScoringConfig;
use App\Models\Student;
use App\Services\TermService;
use Database\Seeders\CefrReferenceSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->completeSetup();
    $this->seed(CefrReferenceSeeder::class);
    $this->withPinSession();
    [$this->term] = app(TermService::class)->createTerm(AcademicYear::create(['name' => '2026/2027']), 1, null);
    $this->g4 = ScoringConfig::where(['term_id' => $this->term->id, 'grade' => 4])->first();
});

function g4Input(array $override = []): array
{
    return array_replace_recursive([
        'max' => ['listening' => 20, 'reading' => 20, 'writing' => 20, 'speaking' => 20],
        'weight' => ['listening' => 25, 'reading' => 25, 'writing' => 25, 'speaking' => 25],
        'thresholds' => ['Pre-A1' => 0, 'A1' => 50, 'A2' => 80, 'B1' => null, 'B2' => null],
        'expected_min' => 'A1',
        'expected_max' => 'A1',
    ], $override);
}

it('shows the configs of the active period', function () {
    $this->get('/pengaturan/penilaian')->assertInertia(fn (Assert $p) => $p
        ->component('Settings/Scoring')
        ->has('configs', 4)
        ->where('configs.1.grade', 4)
        ->where('configs.1.thresholds.A1', 50));
});

it('saves a valid config with a recent PIN', function () {
    $this->withPinSession(confirmedAgo: 600)->putJson("/pengaturan/penilaian/{$this->g4->id}", g4Input())->assertStatus(428);

    $this->withPinSession()->put("/pengaturan/penilaian/{$this->g4->id}", g4Input([
        'thresholds' => ['A1' => 45], 'weight' => ['listening' => 30, 'reading' => 30, 'writing' => 20, 'speaking' => 20],
    ]))->assertSessionHasNoErrors();

    $c = $this->g4->fresh();
    expect($c->thresholds['A1'])->toEqual(45)->and((float) $c->weight_listening)->toBe(30.0);
});

it('validates thresholds, weights and expected levels', function (array $override, string $field) {
    $this->put("/pengaturan/penilaian/{$this->g4->id}", g4Input($override))->assertSessionHasErrors($field);
})->with([
    'not increasing' => [['thresholds' => ['A1' => 80, 'A2' => 70]], 'thresholds.A2'],
    'over 100' => [['thresholds' => ['A2' => 120]], 'thresholds.A2'],
    'level after a gap' => [['thresholds' => ['A2' => null, 'B1' => 90]], 'thresholds.B1'],
    'zero weights' => [['weight' => ['listening' => 0, 'reading' => 0, 'writing' => 0, 'speaking' => 0]], 'weight'],
    'max 0' => [['max' => ['listening' => 0]], 'max.listening'],
    'min above max' => [['expected_min' => 'A2', 'expected_max' => 'A1'], 'expected_min'],
    'expected level not measured' => [['expected_max' => 'B1'], 'expected_max'],
]);

it('refuses a maximum below existing scores', function () {
    $class = Classroom::create(['term_id' => $this->term->id, 'grade' => 4, 'name' => '4A']);
    $e = Enrollment::create(['classroom_id' => $class->id, 'student_id' => Student::create(['nis' => '4001', 'name' => 'Budi'])->id, 'term_id' => $this->term->id]);
    Score::create(['enrollment_id' => $e->id, 'listening' => 18]);

    $this->put("/pengaturan/penilaian/{$this->g4->id}", g4Input(['max' => ['listening' => 15]]))
        ->assertSessionHasErrors(['max.listening' => 'Ada nilai Listening 18 yang melebihi maksimum baru.']);
});

it('previews which draft students change level', function () {
    $class = Classroom::create(['term_id' => $this->term->id, 'grade' => 4, 'name' => '4A']);
    $e = Enrollment::create(['classroom_id' => $class->id, 'student_id' => Student::create(['nis' => '4001', 'name' => 'Budi'])->id, 'term_id' => $this->term->id]);
    Score::create(['enrollment_id' => $e->id, 'listening' => 9, 'reading' => 9, 'writing' => 9, 'speaking' => 9]); // 45% → Pre-A1

    $this->postJson("/pengaturan/penilaian/{$this->g4->id}/dampak", g4Input(['thresholds' => ['A1' => 45]]))
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('students.0', ['name' => 'Budi', 'class' => '4A', 'from' => 'Pre-A1', 'to' => 'A1', 'status_from' => 'below', 'status_to' => 'meeting']);
});

it('edits and resets descriptors', function () {
    $payload = ['levels' => [], 'descriptors' => []];
    foreach (LevelMeta::all() as $m) {
        $payload['levels'][$m->level] = $m->only(['short_name', 'name', 'cambridge', 'overall_comment']);
    }
    foreach (Descriptor::all() as $d) {
        $payload['descriptors'][$d->level][$d->skill] = $d->only(['can_do', 'next_step']);
    }
    $payload['descriptors']['A1']['reading']['can_do'] = 'Can read short notices.';

    $this->put('/pengaturan/deskriptor', $payload)->assertSessionHasNoErrors();
    expect(Descriptor::where(['level' => 'A1', 'skill' => 'reading'])->value('can_do'))->toBe('Can read short notices.');

    $this->post('/pengaturan/deskriptor/bawaan');
    expect(Descriptor::where(['level' => 'A1', 'skill' => 'reading'])->value('can_do'))->toStartWith('Can understand short, simple texts');

    $payload['levels']['B2']['name'] = '';
    $this->put('/pengaturan/deskriptor', $payload)->assertSessionHasErrors('levels.B2.name');
});
