<?php

use App\Models\ActivityLog;
use App\Support\PinThrottle;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->completeSetup());

it('redirects protected pages to /pin and remembers the intended URL', function () {
    $this->get('/pengaturan/sekolah')->assertRedirect('/pin');

    $this->post('/pin', ['pin' => self::PIN])->assertRedirect('/pengaturan/sekolah');
});

it('opens the session with the correct PIN', function () {
    $this->post('/pin', ['pin' => self::PIN])->assertRedirect('/');

    $this->get('/')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Dashboard'));
    expect(ActivityLog::where('action', 'pin.success')->count())->toBe(1);
});

it('reports remaining attempts on a wrong PIN', function () {
    $this->from('/pin')->post('/pin', ['pin' => '1357'])
        ->assertRedirect('/pin')
        ->assertSessionHasErrors(['pin' => 'PIN salah. Sisa percobaan: 4']);

    expect(ActivityLog::where('action', 'pin.failed')->count())->toBe(1);
});

it('locks an IP after 5 wrong PINs within 15 minutes', function () {
    foreach (range(1, 5) as $_) {
        $this->post('/pin', ['pin' => '1357']);
    }

    // 6th attempt — even with the right PIN — is refused with a countdown.
    $this->postJson('/pin', ['pin' => self::PIN])
        ->assertStatus(429)
        ->assertJsonPath('code', 'PIN_LOCKED')
        ->assertJson(fn ($json) => $json->where('lockout_seconds', fn ($s) => $s > 0 && $s <= 900)->etc());

    $this->get('/pin')->assertInertia(fn (Assert $p) => $p->where('lockoutSeconds', fn ($s) => $s > 0));

    // Unlocks after 15 minutes.
    $this->travel(16)->minutes();
    $this->post('/pin', ['pin' => self::PIN])->assertRedirect('/');
});

it('locks globally after 20 wrong PINs from any IP', function () {
    foreach (range(1, 20) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])->post('/pin', ['pin' => '1357']);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])
        ->postJson('/pin', ['pin' => self::PIN])
        ->assertStatus(429);

    expect(ActivityLog::where('action', 'pin.global_lock')->count())->toBe(1);

    $this->travel(PinThrottle::GLOBAL_LOCK + 60)->seconds();
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])
        ->post('/pin', ['pin' => self::PIN])->assertRedirect('/');
});

it('expires an idle session', function () {
    $this->withPinSession(idleFor: 61 * 60)
        ->get('/')
        ->assertRedirect('/pin')
        ->assertSessionHas('status', 'Sesi berakhir karena tidak ada aktivitas.');
});

it('respects the configured idle timeout', function () {
    $this->settings()->set(['session_idle_minutes' => 120]);

    $this->withPinSession(idleFor: 90 * 60)->get('/')->assertOk();
});

it('ends any session after 12 hours', function () {
    $this->withPinSession(verifiedAgo: 12 * 3600 + 1)->get('/')->assertRedirect('/pin');
});

it('returns 401 JSON for API calls without a session', function () {
    $this->postJson('/ping')->assertStatus(401)->assertJsonPath('code', 'PIN_REQUIRED');
});

it('locks the screen', function () {
    $this->withPinSession()->post('/pin/lock')->assertRedirect('/pin');
    $this->get('/')->assertRedirect('/pin');
});
