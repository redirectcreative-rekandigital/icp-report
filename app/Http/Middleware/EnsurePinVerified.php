<?php

namespace App\Http\Middleware;

use App\Support\PinSession;
use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware `pin.verified`: requires a PIN-verified, non-expired session (PRD-02 §5).
 */
class EnsurePinVerified
{
    public function __construct(private Settings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! PinSession::isVerified($request)) {
            return $this->deny($request, 'Masukkan PIN untuk melanjutkan.', 'PIN_REQUIRED');
        }

        if ($reason = PinSession::expiryReason($request, $this->settings->idleMinutes())) {
            PinSession::end($request);

            return $this->deny($request, $reason, 'SESSION_EXPIRED');
        }

        PinSession::touch($request);

        return $next($request);
    }

    private function deny(Request $request, string $message, string $code): Response
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $message, 'code' => $code], 401);
        }

        if ($request->isMethod('GET')) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return redirect()->route('pin.show')->with('status', $code === 'PIN_REQUIRED' ? null : $message);
    }
}
