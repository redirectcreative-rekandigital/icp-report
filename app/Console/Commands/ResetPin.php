<?php

namespace App\Console\Commands;

use App\Services\PinService;
use Illuminate\Console\Command;

/** Emergency PIN reset for developers with server access (PRD-02 §8). */
class ResetPin extends Command
{
    protected $signature = 'pin:reset {--pin= : PIN baru 4 digit (jika kosong akan ditanyakan)}';

    protected $description = 'Reset PIN sekolah, buat kode pemulihan baru, dan akhiri semua sesi';

    public function handle(PinService $pins): int
    {
        $pin = $this->option('pin') ?? $this->secret('PIN baru (4 digit)');

        if (PinService::isWeak((string) $pin)) {
            $this->error('PIN harus 4 angka dan tidak mudah ditebak (mis. bukan 1234, 0000, atau tahun).');

            return self::FAILURE;
        }

        $pins->setPin($pin);
        $code = $pins->regenerateRecoveryCode();
        $pins->endOtherSessions();

        $this->info('PIN berhasil direset. Semua sesi diakhiri.');
        $this->line("Kode pemulihan baru: <comment>{$code}</comment> — simpan di tempat aman.");

        return self::SUCCESS;
    }
}
