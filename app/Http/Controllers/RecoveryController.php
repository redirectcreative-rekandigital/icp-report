<?php

namespace App\Http\Controllers;

use App\Rules\StrongPin;
use App\Services\ActivityLogger;
use App\Services\PinService;
use App\Support\PinSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Forgot PIN (PRD-02 §8) and the one-time recovery-code display page. */
class RecoveryController extends Controller
{
    public function __construct(private PinService $pins, private ActivityLogger $logger) {}

    public function show(): Response
    {
        return Inertia::render('Pin/Recover');
    }

    public function store(Request $request): RedirectResponse
    {
        $key = 'pin-recover:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
            throw ValidationException::withMessages([
                'code' => "Terlalu banyak percobaan. Coba lagi dalam {$minutes} menit.",
            ]);
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'pin' => ['required', 'confirmed', new StrongPin],
        ], [
            'code.required' => 'Masukkan kode pemulihan.',
            'pin.required' => 'Buat PIN baru 4 digit.',
            'pin.confirmed' => 'Kedua PIN tidak sama. Ulangi PIN yang sama.',
        ]);

        if (! $this->pins->checkRecoveryCode($data['code'])) {
            RateLimiter::hit($key, 3600);
            $this->logger->log('pin.recover_failed', 'akses', 'Kode pemulihan salah.');

            throw ValidationException::withMessages([
                'code' => 'Kode pemulihan tidak cocok. Periksa kembali kode yang Anda simpan.',
            ]);
        }

        RateLimiter::clear($key);
        $this->pins->setPin($data['pin']);
        $newCode = $this->pins->regenerateRecoveryCode();
        $this->pins->endOtherSessions();

        PinSession::markVerified($request);
        PinSession::putPendingRecoveryCode($request, $newCode);
        $this->logger->log('pin.recovered', 'akses', 'PIN direset memakai kode pemulihan; kode pemulihan baru dibuat.');

        return redirect()->route('recovery-code.show', ['context' => 'recover']);
    }

    public function code(Request $request): Response|RedirectResponse
    {
        $code = PinSession::pendingRecoveryCode($request);

        if (! $code) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Pin/RecoveryCode', [
            'code' => $code,
            'context' => in_array($request->query('context'), ['setup', 'recover', 'regenerate'], true)
                ? $request->query('context') : 'regenerate',
        ]);
    }

    public function acknowledge(Request $request): RedirectResponse
    {
        $request->validate(['saved' => ['accepted']], [
            'saved.accepted' => 'Centang konfirmasi bahwa kode pemulihan sudah disimpan.',
        ]);

        PinSession::clearPendingRecoveryCode($request);

        return redirect()->route('dashboard')->with('success', 'Kode pemulihan tersimpan. Aplikasi siap dipakai.');
    }
}
