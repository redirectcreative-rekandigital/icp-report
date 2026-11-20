<?php

use App\Services\PinService;
use Inertia\Testing\AssertableInertia as Assert;

it('resets the PIN with the recovery code and issues a new code', function () {
    $code = $this->completeSetup();

    $this->post('/pin/recover', [
        'code' => strtolower($code), // case and dashes do not matter
        'pin' => '7351',
        'pin_confirmation' => '7351',
    ])->assertRedirect(route('recovery-code.show', ['context' => 'recover']));

    $pins = app(PinService::class);
    expect($pins->check('7351'))->toBeTrue()
        ->and($pins->check(self::PIN))->toBeFalse()
        ->and($pins->checkRecoveryCode($code))->toBeFalse(); // old code no longer valid

    $this->get(route('recovery-code.show', ['context' => 'recover']))
        ->assertInertia(fn (Assert $p) => $p->component('Pin/RecoveryCode')->where('code', fn ($c) => $c !== $code));
});

it('rejects a wrong recovery code', function () {
    $this->completeSetup();

    $this->post('/pin/recover', ['code' => 'AAAA-BBBB-CCCC', 'pin' => '7351', 'pin_confirmation' => '7351'])
        ->assertSessionHasErrors('code');

    expect(app(PinService::class)->check(self::PIN))->toBeTrue();
});

it('rate limits recovery attempts to 5 per hour', function () {
    $code = $this->completeSetup();

    foreach (range(1, 5) as $_) {
        $this->post('/pin/recover', ['code' => 'AAAA-BBBB-CCCC', 'pin' => '7351', 'pin_confirmation' => '7351']);
    }

    $this->post('/pin/recover', ['code' => $code, 'pin' => '7351', 'pin_confirmation' => '7351'])
        ->assertSessionHasErrors(['code' => 'Terlalu banyak percobaan. Coba lagi dalam 60 menit.']);
});
