<?php

namespace App\Support;

use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * PRD-02 §4.2: 5 wrong PINs / 15 min per IP; 20 wrong PINs / 60 min across all IPs → 30 min global lock.
 */
class PinThrottle
{
    public const PER_IP = 5;

    public const PER_IP_DECAY = 15 * 60;

    public const GLOBAL = 20;

    public const GLOBAL_DECAY = 60 * 60;

    public const GLOBAL_LOCK = 30 * 60;

    private const GLOBAL_LOCK_KEY = 'pin:global_lock_until';

    public function __construct(private ActivityLogger $logger) {}

    private function ipKey(Request $request): string
    {
        return 'pin:'.$request->ip();
    }

    /** Seconds until another attempt is allowed (0 = allowed now). */
    public function lockoutSeconds(Request $request): int
    {
        $global = (int) Cache::get(self::GLOBAL_LOCK_KEY, 0) - now()->getTimestamp();

        $ip = RateLimiter::tooManyAttempts($this->ipKey($request), self::PER_IP)
            ? RateLimiter::availableIn($this->ipKey($request))
            : 0;

        return max(0, $global, $ip);
    }

    public function isGloballyLocked(): bool
    {
        return (int) Cache::get(self::GLOBAL_LOCK_KEY, 0) > now()->getTimestamp();
    }

    /** Records a failure, returns the attempts left for this IP. */
    public function registerFailure(Request $request): int
    {
        RateLimiter::hit($this->ipKey($request), self::PER_IP_DECAY);
        RateLimiter::hit('pin:global', self::GLOBAL_DECAY);

        if (RateLimiter::attempts('pin:global') >= self::GLOBAL && ! $this->isGloballyLocked()) {
            Cache::put(self::GLOBAL_LOCK_KEY, now()->getTimestamp() + self::GLOBAL_LOCK, self::GLOBAL_LOCK);
            RateLimiter::clear('pin:global');
            $this->logger->log('pin.global_lock', 'akses', 'Penguncian global 30 menit karena terlalu banyak PIN salah.');
        }

        return max(0, self::PER_IP - RateLimiter::attempts($this->ipKey($request)));
    }

    public function clear(Request $request): void
    {
        RateLimiter::clear($this->ipKey($request));
    }
}
