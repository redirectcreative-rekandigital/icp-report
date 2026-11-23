<?php

use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Finalization;
use App\Models\FinalResult;
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
    [$this->term] = app(TermService::class)->createTerm(AcademicYear::create(['name' => '2026/2027']), 1, '2026-12-18');
    $this->class = Classroom::create(['term_id' => $this->term->id, 'grade' => 5, 'name' => '5A', 'homeroom_teacher' => 'Bu Rina']);

    $add = function (string $nipd, string $name, array $s, ?string $comment = null) {
        $e = Enrollment::create(['classroom_id' => $this->class->id, 'student_id' => Student::create(compact('nipd', 'name') + ['nisn' => '0000'.$nipd])->id, 'term_id' => $this->term->id]);
        Score::create(['enrollment_id' => $e->id, 'teacher_comment' => $comment] + $s);

        return $e;
    };
    $this->kevin = $add('5001', 'Kevin Wijaya', ['listening' => 15, 'reading' => 17, 'writing' => 11, 'speaking' => 13]);
    $this->citra = $add('5002', 'Citra Ayu', ['listening' => 10, 'reading' => 12, 'writing' => null, 'speaking' => null], 'Keep it up!');
});

it('lists incomplete students and refuses to finalize', function () {
    $this->getJson("/kelas/{$this->class->id}/kesiapan-final")
        ->assertJsonPath('incomplete.0.name', 'Citra Ayu')
        ->assertJsonPath('incomplete.0.missing', ['Writing', 'Speaking']);

    $this->post("/kelas/{$this->class->id}/finalkan")->assertSessionHas('error', '1 siswa belum lengkap. Lengkapi nilai sebelum memfinalkan.');
    expect($this->class->fresh()->status)->toBe('draft');
});

it('needs a recent PIN to finalize', function () {
    $this->withPinSession(confirmedAgo: 600)->postJson("/kelas/{$this->class->id}/finalkan")->assertStatus(428);
});

it('finalizes: snapshot, final results, lock, log', function () {
    Score::where('enrollment_id', $this->citra->id)->update(['writing' => 9, 'speaking' => 12]);

    $this->post("/kelas/{$this->class->id}/finalkan")
        ->assertRedirect("/kelas/{$this->class->id}/rapor")
        ->assertSessionHas('success', 'Kelas 5A berhasil difinalkan. Rapor siap dicetak.');

    $class = $this->class->fresh();
    expect($class->status)->toBe('final')->and($class->current_finalization_id)->not->toBeNull();

    $kevin = FinalResult::where('enrollment_id', $this->kevin->id)->first();
    expect($kevin->overall_level)->toBe('Intermediate')
        ->and($kevin->total)->toBe(56.0)
        ->and($kevin->reading_level)->toBe('Intermediate')
        ->and($kevin->writing_level)->toBe('Beginner')
        ->and($kevin->natgeo_level)->toBeNull()
        ->and($kevin->strongest_skill)->toBe('reading')
        ->and($kevin->comment_final)->toStartWith('Kevin Wijaya can understand and use English in everyday situations')
        ->and($kevin->comment_final)->toEndWith('Strongest skill this term: Reading.')
        ->and(FinalResult::where('enrollment_id', $this->citra->id)->value('comment_final'))->toBe('Citra Ayu keep it up!'); // teacher comment gets the name in front

    $snapshot = Finalization::find($class->current_finalization_id)->config_snapshot;
    expect($snapshot['scoring']['intermediate'])->toEqual(13)
        ->and($snapshot['band_comments'])->toHaveKeys(['Beginner', 'Intermediate', 'Advanced'])
        ->and($snapshot['classroom']['homeroom_teacher'])->toBe('Bu Rina')
        ->and($snapshot['school']['name'])->toBe('SD Muhammadiyah Wonorejo')
        ->and($snapshot['term']['report_date'])->toBe('2026-12-18')
        ->and($snapshot['descriptors'])->toHaveCount(12);

    expect(ActivityLog::where('action', 'class.finalized')->exists())->toBeTrue();

    // Locked: edits refused, the grid is read-only.
    $this->patchJson("/kelas/{$this->class->id}/skor/{$this->kevin->id}", ['field' => 'listening', 'value' => 20, 'version' => 0])->assertStatus(423);
});

it('keeps final results when thresholds change afterwards', function () {
    Score::where('enrollment_id', $this->citra->id)->update(['writing' => 9, 'speaking' => 12]);
    $this->post("/kelas/{$this->class->id}/finalkan");

    ScoringConfig::where(['term_id' => $this->term->id, 'grade' => 5])->update(['min_intermediate' => 18, 'min_advanced' => 22]);

    expect(FinalResult::where('enrollment_id', $this->kevin->id)->value('overall_level'))->toBe('Intermediate');
});

it('requires the National Geographic level before finalizing grade 6', function () {
    $g6 = Classroom::create(['term_id' => $this->term->id, 'grade' => 6, 'name' => '6A']);
    $e = Enrollment::create(['classroom_id' => $g6->id, 'student_id' => Student::create(['nipd' => '6001', 'nisn' => '000006001', 'name' => 'Putri'])->id, 'term_id' => $this->term->id]);
    $score = Score::create(['enrollment_id' => $e->id, 'listening' => 16, 'reading' => 17, 'writing' => 15, 'speaking' => 16]);

    $this->getJson("/kelas/{$g6->id}/kesiapan-final")->assertJsonPath('incomplete.0.missing', ['NatGeo']);
    $this->post("/kelas/{$g6->id}/finalkan")->assertSessionHas('error');

    $score->update(['natgeo_level' => 'A2.2']);
    $this->post("/kelas/{$g6->id}/finalkan")->assertSessionHas('success');
    expect(FinalResult::where('enrollment_id', $e->id)->value('natgeo_level'))->toBe('A2.2');
});

it('unfinalizes with a reason and PIN, keeps the last snapshot, and can finalize again', function () {
    Score::where('enrollment_id', $this->citra->id)->update(['writing' => 9, 'speaking' => 12]);
    $this->post("/kelas/{$this->class->id}/finalkan");

    $this->post("/kelas/{$this->class->id}/batalkan-final", ['reason' => 'pendek'])->assertSessionHasErrors('reason');
    $this->withPinSession(confirmedAgo: 600)->postJson("/kelas/{$this->class->id}/batalkan-final", ['reason' => 'Koreksi nilai Speaking Kevin'])->assertStatus(428);

    $this->withPinSession()->post("/kelas/{$this->class->id}/batalkan-final", ['reason' => 'Koreksi nilai Speaking Kevin'])
        ->assertRedirect("/kelas/{$this->class->id}/nilai");

    expect($this->class->fresh()->status)->toBe('draft')
        ->and(FinalResult::where('is_current', true)->count())->toBe(2)
        ->and(ActivityLog::where('action', 'class.unfinalized')->value('description'))->toContain('Koreksi nilai Speaking Kevin');

    $this->patchJson("/kelas/{$this->class->id}/skor/{$this->kevin->id}", ['field' => 'speaking', 'value' => 14, 'version' => 0])->assertOk();
    $this->post("/kelas/{$this->class->id}/finalkan");

    expect(FinalResult::count())->toBe(4)
        ->and(FinalResult::where('is_current', true)->count())->toBe(2)
        ->and(FinalResult::where(['enrollment_id' => $this->kevin->id, 'is_current' => true])->value('speaking'))->toBe(14.0);

    $this->get("/kelas/{$this->class->id}/riwayat")->assertInertia(fn (Assert $p) => $p
        ->has('history', 3)
        ->where('history.0.action', 'finalize')
        ->where('history.1.action', 'unfinalize')
        ->where('history.1.reason', 'Koreksi nilai Speaking Kevin'));
});

it('excludes inactive students from finalisation', function () {
    $this->citra->student->update(['is_active' => false]);

    $this->post("/kelas/{$this->class->id}/finalkan")->assertSessionHas('success');
    expect(FinalResult::count())->toBe(1);
});
