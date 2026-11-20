<?php

namespace App\Services;

use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PinService
{
    /** Alphabet without look-alike characters (0/O, 1/I/L). */
    private const RECOVERY_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function __construct(private Settings $settings) {}

    /** PRD-02 §3: reject guessable PINs. */
    public static function isWeak(string $pin): bool
    {
        if (! preg_match('/^\d{4}$/', $pin)) {
            return true;
        }

        if (count(array_unique(str_split($pin))) === 1) {
            return true; // 0000, 1111 … 9999
        }

        if (in_array($pin, ['1234', '4321', '1212', '2580'], true)) {
            return true;
        }

        return (bool) preg_match('/^(19|20)\d{2}$/', $pin); // years 19xx / 20xx
    }

    public function check(string $pin): bool
    {
        $hash = $this->settings->get('pin_hash');

        return $hash !== null && Hash::check($pin, $hash);
    }

    public function setPin(string $pin): void
    {
        $this->settings->set([
            'pin_hash' => Hash::make($pin),
            'pin_changed_at' => now()->toIso8601String(),
        ]);
    }

    /** Creates a new recovery code, stores only its hash, returns the plain code once. */
    public function regenerateRecoveryCode(): string
    {
        $raw = '';
        for ($i = 0; $i < 12; $i++) {
            $raw .= self::RECOVERY_ALPHABET[random_int(0, strlen(self::RECOVERY_ALPHABET) - 1)];
        }

        $this->settings->set(['recovery_code_hash' => Hash::make($raw)]);

        return implode('-', str_split($raw, 4));
    }

    public function checkRecoveryCode(string $code): bool
    {
        $hash = $this->settings->get('recovery_code_hash');
        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));

        return $hash !== null && strlen($normalized) === 12 && Hash::check($normalized, $hash);
    }

    /** Ends every session except (optionally) the current one. */
    public function endOtherSessions(?string $keepSessionId = null): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->when($keepSessionId, fn ($q) => $q->where('id', '!=', $keepSessionId))
            ->delete();
    }
}
