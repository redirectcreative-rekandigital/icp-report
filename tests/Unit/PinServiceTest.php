<?php

use App\Services\PinService;

it('rejects guessable PINs', function (string $pin) {
    expect(PinService::isWeak($pin))->toBeTrue();
})->with(['0000', '1111', '9999', '1234', '4321', '1212', '2580', '1999', '2026', '123', '12a4', '']);

it('accepts reasonable PINs', function (string $pin) {
    expect(PinService::isWeak($pin))->toBeFalse();
})->with(['2468', '7351', '0918', '1806', '3000']);
