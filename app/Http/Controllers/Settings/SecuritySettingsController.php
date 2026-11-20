<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Rules\StrongPin;
use App\Services\ActivityLogger;
use App\Services\PinService;
use App\Support\PinSession;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Pengaturan → Keamanan (PRD-02 §5, §7). */
class SecuritySettingsController extends Controller
{
    public function __construct(
        private Settings $settings,
        private PinService $pins,
        private ActivityLogger $logger,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('Settings/Security', [
            'idleMinutes' => $this->settings->idleMinutes(),
            'pinChangedAt' => $this->settings->get('pin_changed_at'),
        ]);
    }

    public function updatePin(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_pin' => ['required', 'digits:4'],
            'pin' => ['required', 'confirmed', 'different:current_pin', new StrongPin],
        ], [
            'current_pin.required' => 'Masukkan PIN lama.',
            'current_pin.digits' => 'PIN lama terdiri dari 4 angka.',
            'pin.required' => 'Masukkan PIN baru.',
            'pin.confirmed' => 'Kedua PIN baru tidak sama.',
            'pin.different' => 'PIN baru harus berbeda dengan PIN lama.',
        ]);

        if (! $this->pins->check($data['current_pin'])) {
            $this->logger->log('pin.failed', 'akses', 'PIN lama salah saat mengganti PIN.');
            throw ValidationException::withMessages(['current_pin' => 'PIN lama salah.']);
        }

        $this->pins->setPin($data['pin']);
        $this->pins->endOtherSessions($request->session()->getId());
        PinSession::markConfirmed($request);
        $this->logger->log('pin.changed', 'pengaturan', 'PIN sekolah diganti; sesi di perangkat lain diakhiri.');

        return back()->with('success', 'PIN berhasil diganti. Perangkat lain perlu memasukkan PIN baru.');
    }

    public function regenerateRecoveryCode(Request $request): RedirectResponse
    {
        $code = $this->pins->regenerateRecoveryCode();
        PinSession::putPendingRecoveryCode($request, $code);
        $this->logger->log('pin.recovery_regenerated', 'pengaturan', 'Kode pemulihan baru dibuat; kode lama tidak berlaku.');

        return redirect()->route('recovery-code.show', ['context' => 'regenerate']);
    }

    public function updateSession(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'idle_minutes' => ['required', 'integer', 'min:15', 'max:240'],
        ], [
            'idle_minutes.*' => 'Batas waktu sesi harus antara 15 dan 240 menit.',
        ]);

        $this->settings->set(['session_idle_minutes' => (int) $data['idle_minutes']]);
        $this->logger->log('settings.session_updated', 'pengaturan', "Batas waktu sesi diubah menjadi {$data['idle_minutes']} menit.");

        return back()->with('success', 'Batas waktu sesi tersimpan.');
    }
}
