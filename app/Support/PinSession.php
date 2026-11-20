<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Session state for the shared-PIN access model (PRD-02 §4–6).
 */
class PinSession
{
    public const VERIFIED_AT = 'pin.verified_at';

    public const LAST_ACTIVITY = 'pin.last_activity_at';

    public const CONFIRMED_AT = 'pin.confirmed_at';

    public const PENDING_RECOVERY = 'pin.pending_recovery_code';

    /** Short random tag per PIN login; shown in the activity log as "kode sesi". */
    public const TAG = 'pin.tag';

    public const ABSOLUTE_HOURS = 12;

    public const RECENT_MINUTES = 5;

    public static function markVerified(Request $request): void
    {
        $request->session()->regenerate();
        $now = now()->getTimestamp();
        $request->session()->put([
            self::VERIFIED_AT => $now,
            self::LAST_ACTIVITY => $now,
            self::CONFIRMED_AT => $now,
            self::TAG => strtolower(Str::random(6)),
        ]);
    }

    public static function tag(Request $request): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        return $request->session()->get(self::TAG) ?? substr(hash('sha256', $request->session()->getId()), 0, 6);
    }

    public static function markConfirmed(Request $request): void
    {
        $request->session()->put(self::CONFIRMED_AT, now()->getTimestamp());
    }

    public static function isVerified(Request $request): bool
    {
        return $request->hasSession() && $request->session()->has(self::VERIFIED_AT);
    }

    /** Returns the reason the session expired, or null when still valid. */
    public static function expiryReason(Request $request, int $idleMinutes): ?string
    {
        $now = now()->getTimestamp();
        $verifiedAt = (int) $request->session()->get(self::VERIFIED_AT);
        $last = (int) $request->session()->get(self::LAST_ACTIVITY, $verifiedAt);

        if ($now - $verifiedAt > self::ABSOLUTE_HOURS * 3600) {
            return 'Sesi berakhir karena sudah lebih dari 12 jam. Masukkan PIN kembali.';
        }

        if ($now - $last > $idleMinutes * 60) {
            return 'Sesi berakhir karena tidak ada aktivitas.';
        }

        return null;
    }

    public static function touch(Request $request): void
    {
        $request->session()->put(self::LAST_ACTIVITY, now()->getTimestamp());
    }

    public static function recentlyConfirmed(Request $request): bool
    {
        $at = (int) $request->session()->get(self::CONFIRMED_AT, 0);

        return now()->getTimestamp() - $at <= self::RECENT_MINUTES * 60;
    }

    public static function end(Request $request): void
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /** Plain recovery code is kept encrypted in the session until the user acknowledges it. */
    public static function putPendingRecoveryCode(Request $request, string $code): void
    {
        $request->session()->put(self::PENDING_RECOVERY, Crypt::encryptString($code));
    }

    public static function pendingRecoveryCode(Request $request): ?string
    {
        $value = $request->session()->get(self::PENDING_RECOVERY);

        return $value ? Crypt::decryptString($value) : null;
    }

    public static function clearPendingRecoveryCode(Request $request): void
    {
        $request->session()->forget(self::PENDING_RECOVERY);
    }
}
