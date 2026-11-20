<?php

use App\Models\ActivityLog;
use App\Services\PinService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(fn () => $this->completeSetup());

it('requires the PIN within the last 5 minutes for sensitive actions', function () {
    $this->withPinSession(confirmedAgo: 6 * 60)
        ->putJson('/pengaturan/keamanan/pin', ['current_pin' => self::PIN, 'pin' => '7351', 'pin_confirmation' => '7351'])
        ->assertStatus(428)
        ->assertJsonPath('code', 'PIN_CONFIRM_REQUIRED');

    expect(app(PinService::class)->check(self::PIN))->toBeTrue();
});

it('reports whether the PIN was confirmed recently', function () {
    $this->withPinSession(confirmedAgo: 6 * 60)->getJson('/pin/recent')->assertJson(['recent' => false]);
    $this->withPinSession(confirmedAgo: 60)->getJson('/pin/recent')->assertJson(['recent' => true]);
});

it('confirms the PIN and then allows the action', function () {
    $this->withPinSession(confirmedAgo: 6 * 60);

    $this->postJson('/pin/confirm', ['pin' => '1357'])->assertStatus(422)->assertJsonPath('code', 'PIN_INVALID');
    $this->postJson('/pin/confirm', ['pin' => self::PIN])->assertOk();

    $this->put('/pengaturan/keamanan/pin', ['current_pin' => self::PIN, 'pin' => '7351', 'pin_confirmation' => '7351'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    expect(app(PinService::class)->check('7351'))->toBeTrue()
        ->and(ActivityLog::where('action', 'pin.changed')->exists())->toBeTrue();
});

it('validates the PIN change', function (array $input, string $field) {
    $this->withPinSession()
        ->put('/pengaturan/keamanan/pin', $input)
        ->assertSessionHasErrors($field);
})->with([
    'wrong old PIN' => [['current_pin' => '1357', 'pin' => '7351', 'pin_confirmation' => '7351'], 'current_pin'],
    'weak new PIN' => [['current_pin' => '2468', 'pin' => '1234', 'pin_confirmation' => '1234'], 'pin'],
    'same as old' => [['current_pin' => '2468', 'pin' => '2468', 'pin_confirmation' => '2468'], 'pin'],
    'mismatch' => [['current_pin' => '2468', 'pin' => '7351', 'pin_confirmation' => '7352'], 'pin'],
]);

it('ends sessions on other devices when the PIN changes', function () {
    config(['session.driver' => 'database']);
    expect(Schema::hasTable('sessions'))->toBeTrue();

    DB::table('sessions')->insert([
        ['id' => 'other-device', 'payload' => '', 'last_activity' => time()],
        ['id' => 'another-one', 'payload' => '', 'last_activity' => time()],
    ]);

    app(PinService::class)->endOtherSessions('current-session');

    expect(DB::table('sessions')->count())->toBe(0);
});

it('regenerates the recovery code only with a recent PIN', function () {
    $old = $this->completeSetup();

    $this->withPinSession(confirmedAgo: 6 * 60)->postJson('/pengaturan/keamanan/kode-pemulihan')->assertStatus(428);

    $this->withPinSession()->post('/pengaturan/keamanan/kode-pemulihan')
        ->assertRedirect(route('recovery-code.show', ['context' => 'regenerate']));

    expect(app(PinService::class)->checkRecoveryCode($old))->toBeFalse();
});

it('validates the idle timeout range', function () {
    $this->withPinSession()->put('/pengaturan/keamanan/sesi', ['idle_minutes' => 10])->assertSessionHasErrors('idle_minutes');
    $this->withPinSession()->put('/pengaturan/keamanan/sesi', ['idle_minutes' => 90])->assertSessionHasNoErrors();

    expect($this->settings()->idleMinutes())->toBe(90);
});
