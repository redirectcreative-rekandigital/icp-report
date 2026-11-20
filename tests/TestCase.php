<?php

namespace Tests;

use App\Services\PinService;
use App\Support\PinSession;
use App\Support\Settings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public const PIN = '2468';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); // tests must not depend on a fresh `npm run build`
    }

    /** Simulates a finished /setup with PIN 2468; returns the plain recovery code. */
    protected function completeSetup(string $pin = self::PIN): string
    {
        $pins = app(PinService::class);
        $pins->setPin($pin);

        return $pins->regenerateRecoveryCode();
    }

    /** A PIN-verified session. $confirmedAgo = seconds since the PIN was last entered. */
    protected function withPinSession(int $confirmedAgo = 0, int $verifiedAgo = 0, int $idleFor = 0): static
    {
        $now = now()->getTimestamp();

        return $this->withSession([
            PinSession::VERIFIED_AT => $now - $verifiedAgo,
            PinSession::LAST_ACTIVITY => $now - $idleFor,
            PinSession::CONFIRMED_AT => $now - $confirmedAgo,
            PinSession::TAG => 'test01',
        ]);
    }

    protected function settings(): Settings
    {
        return app(Settings::class);
    }
}
