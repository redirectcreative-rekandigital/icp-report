<?php

namespace App\Http\Middleware;

use App\Support\PinSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware `pin.recent`: sensitive actions need the PIN entered within the last 5 minutes
 * (PRD-02 §6). The client asks GET /pin/recent first and shows PinConfirmDialog; this is the
 * server-side guarantee.
 */
class RequireRecentPin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (PinSession::recentlyConfirmed($request)) {
            return $next($request);
        }

        $payload = [
            'message' => 'Masukkan PIN untuk mengonfirmasi tindakan ini.',
            'code' => 'PIN_CONFIRM_REQUIRED',
        ];

        if ($request->header('X-Inertia')) {
            return back()->with('pin_confirm_required', true)->withErrors(['pin' => $payload['message']]);
        }

        return response()->json($payload, 428);
    }
}
