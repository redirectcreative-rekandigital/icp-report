<?php

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Descriptor;
use App\Models\Enrollment;
use App\Models\LevelMeta;
use App\Models\Score;
use App\Models\ScoringConfig;
use App\Models\Student;
use App\Services\ScoreService;
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

it('shows the band thresholds of the active period (default 13 / 20)', function () {
    $this->get('/pengaturan/penilaian')->assertInertia(fn (Assert $p) => $p
        ->component('Settings/Scoring')
        ->has('configs', 4)
        ->where('configs.1.grade', 4)
        ->where('configs.1.min', 5)
        ->where('configs.1.max', 25)
        ->where('configs.1.intermediate', 13)
        ->where('configs.1.advanced', 20));
});

it('saves valid thresholds with a recent PIN', function () {
    $this->withPinSession(confirmedAgo: 600)->putJson("/pengaturan/penilaian/{$this->g4->id}", ['intermediate' => 14, 'advanced' => 21])->assertStatus(428);

    $this->withPinSession()->put("/pengaturan/penilaian/{$this->g4->id}", ['intermediate' => 14.5, 'advanced' => 21])
        ->assertSessionHasNoErrors();

    $c = $this->g4->fresh();
    expect($c->min_intermediate)->toBe(14.5)->and($c->min_advanced)->toBe(21.0);
});

it('validates the thresholds', function (array $input, string $field) {
    $this->put("/pengaturan/penilaian/{$this->g4->id}", $input)->assertSessionHasErrors($field);
})->with([
    'advanced not above intermediate' => [['intermediate' => 15, 'advanced' => 15], 'advanced'],
    'over 25' => [['intermediate' => 13, 'advanced' => 26], 'advanced'],
    'no room for Beginner' => [['intermediate' => 5, 'advanced' => 20], 'intermediate'],
    'not a .5 step' => [['intermediate' => 13.3, 'advanced' => 20], 'intermediate'],
    'missing' => [['advanced' => 20], 'intermediate'],
]);

it('previews which draft students change band', function () {
    $class = Classroom::create(['term_id' => $this->term->id, 'grade' => 4, 'name' => '4A']);
    $e = Enrollment::create(['classroom_id' => $class->id, 'student_id' => Student::create(['nis' => '4001', 'name' => 'Budi'])->id, 'term_id' => $this->term->id]);
    Score::create(['enrollment_id' => $e->id, 'listening' => 12, 'reading' => 12, 'writing' => 12, 'speaking' => 12]); // total 48 → Beginner

    $this->postJson("/pengaturan/penilaian/{$this->g4->id}/dampak", ['intermediate' => 12, 'advanced' => 20])
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('students.0', ['name' => 'Budi', 'class' => '4A', 'from' => 'Beginner', 'to' => 'Intermediate']);
});

it('edits and resets band descriptors, comments and CEFR map names', function () {
    $payload = ['comments' => ScoreService::overallComments(), 'descriptors' => [], 'levels' => []];
    foreach (Descriptor::all() as $d) {
        $payload['descriptors'][$d->level][$d->skill] = $d->only(['can_do', 'next_step']);
    }
    foreach (LevelMeta::all() as $m) {
        $payload['levels'][$m->level] = $m->only(['short_name', 'name', 'cambridge']);
    }
    $payload['descriptors']['Beginner']['reading']['can_do'] = 'Can read short notices.';
    $payload['comments']['Advanced'] = 'is a confident English user.';

    $this->put('/pengaturan/deskriptor', $payload)->assertSessionHasNoErrors();
    expect(Descriptor::where(['level' => 'Beginner', 'skill' => 'reading'])->value('can_do'))->toBe('Can read short notices.')
        ->and(ScoreService::overallComments()['Advanced'])->toBe('is a confident English user.');

    $this->post('/pengaturan/deskriptor/bawaan');
    expect(Descriptor::where(['level' => 'Beginner', 'skill' => 'reading'])->value('can_do'))->toStartWith('Can understand short, simple texts')
        ->and(ScoreService::overallComments()['Advanced'])->toStartWith('uses English confidently');

    $payload['levels']['B2']['name'] = '';
    $payload['comments']['Beginner'] = '';
    $this->put('/pengaturan/deskriptor', $payload)->assertSessionHasErrors(['levels.B2.name', 'comments.Beginner']);
});
