<?php

use App\Models\ActivityLog;
use App\Support\PinSession;
use Inertia\Testing\AssertableInertia as Assert;

it('sends every page to /setup until a PIN exists', function (string $url) {
    $this->get($url)->assertRedirect('/setup');
})->with(['/', '/pin', '/pengaturan/sekolah', '/log']);

it('shows the setup wizard with the default school name', function () {
    $this->get('/setup')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/Index')
            ->where('defaults.school_name', 'SD Muhammadiyah Wonorejo'));
});

it('rejects weak PINs during setup', function (string $pin) {
    $this->post('/setup', [
        'school_name' => 'SD Muhammadiyah Wonorejo',
        'pin' => $pin,
        'pin_confirmation' => $pin,
    ])->assertSessionHasErrors(['pin' => 'PIN terlalu mudah ditebak. Pilih kombinasi lain.']);

    expect($this->settings()->isSetupComplete())->toBeFalse();
})->with(['1234', '0000', '2026', '2580']);

it('requires both PINs to match', function () {
    $this->post('/setup', ['school_name' => 'X', 'pin' => '2468', 'pin_confirmation' => '2469'])
        ->assertSessionHasErrors('pin');
});

it('completes setup, hashes the PIN and shows the recovery code once', function () {
    $response = $this->post('/setup', [
        'school_name' => 'SD Muhammadiyah Wonorejo',
        'program_name' => 'International Class Program',
        'partner_name' => 'Partner English Academy',
        'city' => 'Sidoarjo',
        'pin' => '2468',
        'pin_confirmation' => '2468',
    ]);

    $response->assertRedirect(route('recovery-code.show', ['context' => 'setup']));

    $hash = $this->settings()->get('pin_hash');
    expect($hash)->not->toBe('2468')->and(password_verify('2468', $hash))->toBeTrue()
        ->and($this->settings()->get('city'))->toBe('Sidoarjo');

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
    $this->post('/setup', ['school_name' => 'SD', 'pin' => '7351', 'pin_confirmation' => '7351']);

    $dump = ActivityLog::all()->toJson();
    expect($dump)->not->toContain('7351');
});
