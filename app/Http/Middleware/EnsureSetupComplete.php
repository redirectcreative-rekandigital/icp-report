<?php

namespace App\Http\Middleware;

use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Before a PIN exists every page goes to /setup; afterwards /setup is a 404 (PRD-02 §3).
 */
class EnsureSetupComplete
{
    public function __construct(private Settings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $isSetupRoute = $request->routeIs('setup.*');

        if (! $this->settings->isSetupComplete()) {
            if (! $isSetupRoute) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Aplikasi belum disiapkan.', 'code' => 'SETUP_REQUIRED'], 409)
                    : redirect()->route('setup.show');
            }

            return $next($request);
        }

        if ($isSetupRoute) {
            abort(404);
        }

        return $next($request);
    }
}
