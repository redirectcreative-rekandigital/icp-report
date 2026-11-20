<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\Housekeeping;
use App\Services\PinService;
use App\Support\PinSession;
use App\Support\PinThrottle;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PinController extends Controller
{
    public function __construct(
        private PinService $pins,
        private PinThrottle $throttle,
        private ActivityLogger $logger,
        private Settings $settings,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        if (PinSession::isVerified($request)) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Pin/Enter', [
            'lockoutSeconds' => $this->throttle->lockoutSeconds($request),
            'status' => session('status'),
        ]);
    }

    public function verify(Request $request): HttpResponse
    {
        if ($seconds = $this->throttle->lockoutSeconds($request)) {
            return $this->lockedResponse($request, $seconds);
        }

        $pin = (string) $request->input('pin', '');

        if ($this->pins->check($pin)) {
            $this->throttle->clear($request);
            PinSession::markVerified($request);
            $this->logger->log('pin.success', 'akses', 'PIN benar, sesi dibuka.');
            app(Housekeeping::class)->runIfDue();

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['ok' => true]); // re-login from the in-page PIN dialog
            }

            return redirect()->intended(route('dashboard'));
        }

        $left = $this->throttle->registerFailure($request);
        $this->logger->log('pin.failed', 'akses', 'Percobaan PIN salah.');

        if ($seconds = $this->throttle->lockoutSeconds($request)) {
            return $this->lockedResponse($request, $seconds);
        }

        return $this->failResponse($request, "PIN salah. Sisa percobaan: {$left}", 422);
    }

    /** Re-confirm the PIN for a sensitive action (JSON, used by PinConfirmDialog). */
    public function confirm(Request $request): JsonResponse
    {
        if ($seconds = $this->throttle->lockoutSeconds($request)) {
            return response()->json([
                'message' => 'Terlalu banyak percobaan. Coba lagi nanti.',
                'code' => 'PIN_LOCKED',
                'lockout_seconds' => $seconds,
            ], 429);
        }

        Validator::make($request->all(), ['pin' => ['required', 'digits:4']], [
            'pin.required' => 'Masukkan PIN 4 digit.',
            'pin.digits' => 'PIN terdiri dari 4 angka.',
        ])->validate();

        if (! $this->pins->check($request->string('pin'))) {
            $left = $this->throttle->registerFailure($request);
            $this->logger->log('pin.failed', 'akses', 'PIN konfirmasi salah.');

            return response()->json([
                'message' => "PIN salah. Sisa percobaan: {$left}",
                'code' => 'PIN_INVALID',
                'errors' => ['pin' => ["PIN salah. Sisa percobaan: {$left}"]],
                'lockout_seconds' => $this->throttle->lockoutSeconds($request),
            ], 422);
        }

        $this->throttle->clear($request);
        PinSession::markConfirmed($request);

        return response()->json(['ok' => true]);
    }

    public function recent(Request $request): JsonResponse
    {
        return response()->json(['recent' => PinSession::recentlyConfirmed($request)]);
    }

    /** Lightweight keep-alive for the session timeout dialog. */
    public function ping(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'idle_minutes' => $this->settings->idleMinutes(),
        ]);
    }

    public function lock(Request $request): RedirectResponse
    {
        $this->logger->log('pin.locked', 'akses', 'Layar dikunci.');
        PinSession::end($request);

        return redirect()->route('pin.show');
    }

    private function lockedResponse(Request $request, int $seconds): HttpResponse
    {
        $minutes = (int) ceil($seconds / 60);
        $message = $this->throttle->isGloballyLocked()
            ? "Akses dikunci sementara karena terlalu banyak PIN salah. Coba lagi dalam {$minutes} menit."
            : 'Terlalu banyak percobaan. Coba lagi setelah hitung mundur selesai.';

        return $this->failResponse($request, $message, 429, $seconds);
    }

    private function failResponse(Request $request, string $message, int $status, int $lockout = 0): HttpResponse
    {
        if ($request->header('X-Inertia') || ! $request->expectsJson()) {
            // Inertia flow: errors in the session; the page re-reads lockoutSeconds on render.
            return back()->withErrors(['pin' => $message]);
        }

        return response()->json([
            'message' => $message,
            'code' => $status === 429 ? 'PIN_LOCKED' : 'PIN_INVALID',
            'lockout_seconds' => $lockout,
        ], $status);
    }
}
