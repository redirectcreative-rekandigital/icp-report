<?php

use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\ScoringConfig;
use App\Models\Term;
use App\Services\TermService;
use App\Support\PinSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Storage::fake('local'));

/** Everything the wizard requires (revisi 23-11-2026). */
function setupPayload(array $override = []): array
{
    return array_replace([
        'school_name' => 'SD Muhammadiyah Wonorejo',
        'city' => 'Wonorejo',
        'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
        'principal_name' => 'Nama Kepala Sekolah, S.Pd.',
        'principal_id_label' => 'NBM',
        'principal_id_number' => '1234567',
        'year' => '2026/2027',
        'semester' => 1,
        'report_date' => '2026-12-18',
        'intermediate' => 13,
        'advanced' => 20,
        'pin' => '2468',
        'pin_confirmation' => '2468',
    ], $override);
}

it('sends every page to /setup until a PIN exists', function (string $url) {
    $this->get($url)->assertRedirect('/setup');
})->with(['/', '/pin', '/pengaturan/sekolah', '/log']);

it('shows the setup wizard with defaults', function () {
    $this->get('/setup')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/Index')
            ->where('defaults.school_name', 'SD Muhammadiyah Wonorejo')
            ->where('defaults.city', 'Wonorejo')
            ->where('defaults.year', fn ($y) => (bool) preg_match('/^\d{4}\/\d{4}$/', $y)));
});

it('rejects weak PINs during setup', function (string $pin) {
    $this->post('/setup', setupPayload(['pin' => $pin, 'pin_confirmation' => $pin]))
        ->assertSessionHasErrors(['pin' => 'PIN terlalu mudah ditebak. Pilih kombinasi lain.']);

    expect($this->settings()->isSetupComplete())->toBeFalse();
})->with(['1234', '0000', '2026', '2580']);

it('requires both PINs to match', function () {
    $this->post('/setup', setupPayload(['pin_confirmation' => '2469']))->assertSessionHasErrors('pin');
});

it('requires every report field before the dashboard opens', function () {
    $this->post('/setup', setupPayload([
        'city' => '', 'logo' => null, 'principal_name' => '', 'principal_id_number' => '',
        'year' => '2026/2028', 'report_date' => '',
    ]))->assertSessionHasErrors(['city', 'logo', 'principal_name', 'principal_id_number', 'year', 'report_date']);

    $this->post('/setup', setupPayload(['intermediate' => 15, 'advanced' => 15]))->assertSessionHasErrors('advanced');

    expect($this->settings()->isSetupComplete())->toBeFalse()->and(Term::count())->toBe(0);
});

it('completes setup: identity, logo, active period, thresholds, PIN and the recovery code once', function () {
    $response = $this->post('/setup', setupPayload(['city' => 'Sidoarjo', 'intermediate' => 12.5, 'advanced' => 19]));

    $response->assertRedirect(route('recovery-code.show', ['context' => 'setup']));

    $hash = $this->settings()->get('pin_hash');
    expect($hash)->not->toBe('2468')->and(password_verify('2468', $hash))->toBeTrue()
        ->and($this->settings()->get('city'))->toBe('Sidoarjo')
        ->and($this->settings()->get('principal_name'))->toBe('Nama Kepala Sekolah, S.Pd.')
        ->and($this->settings()->get('principal_id_number'))->toBe('1234567');
    Storage::disk('local')->assertExists($this->settings()->get('school_logo_path'));

    $term = Term::active();
    expect($term->fullLabel())->toBe('2026/2027 • Semester 1')
        ->and($term->report_date->toDateString())->toBe('2026-12-18')
        ->and(ScoringConfig::where('term_id', $term->id)->pluck('min_intermediate')->unique()->all())->toBe([12.5])
        ->and(ScoringConfig::where('term_id', $term->id)->pluck('min_advanced')->unique()->all())->toBe([19.0]);

    // Recovery code page shows a 12-character code in XXXX-XXXX-XXXX format.
    $this->get(route('recovery-code.show', ['context' => 'setup']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pin/RecoveryCode')
            ->where('code', fn ($code) => (bool) preg_match('/^[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}$/', $code)));

    // Plain code is stored encrypted in the session, never in clear text.
    expect(session(PinSession::PENDING_RECOVERY))->not->toMatch('/^[A-Z2-9]{4}-/');

    $this->post('/kode-pemulihan', ['saved' => true])->assertRedirect('/');
    $this->get('/kode-pemulihan')->assertRedirect('/');

    // Setup is now closed.
    $this->get('/setup')->assertNotFound();
});

it('never writes the PIN into the activity log', function () {
    $this->post('/setup', setupPayload(['pin' => '7351', 'pin_confirmation' => '7351']));

    $dump = ActivityLog::all()->toJson();
    expect($dump)->not->toContain('7351');
});

it('reuses a period that already exists (demo data seeded before setup)', function () {
    $year = AcademicYear::create(['name' => '2026/2027']);
    [$existing] = app(TermService::class)->createTerm($year, 1, null);

    $this->post('/setup', setupPayload())->assertSessionHasNoErrors();

    expect(Term::count())->toBe(1)
        ->and(Term::active()->id)->toBe($existing->id)
        ->and(Term::active()->report_date->toDateString())->toBe('2026-12-18');
});
