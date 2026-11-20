<?php

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\InstallController;
use App\Http\Middleware\EnsurePinVerified;
use App\Http\Middleware\EnsureSetupComplete;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireRecentPin;
use App\Http\Middleware\SecurityHeaders;
use App\Services\ScoreConflictException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Installer: outside the "web" group because the sessions table may not exist yet.
        then: function () {
            Route::middleware(SecurityHeaders::class)->group(function () {
                Route::get('/install', [InstallController::class, 'show'])->name('install.show');
                // No throttle here: the rate limiter needs the cache table, which does not exist yet.
                Route::post('/install', [InstallController::class, 'install'])->name('install.run');
                Route::post('/install/migrate', [InstallController::class, 'migrate'])->middleware('throttle:10,1')->name('install.migrate');
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['sidebar_state']);

        $middleware->web(append: [
            EnsureSetupComplete::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'pin.verified' => EnsurePinVerified::class,
            'pin.recent' => RequireRecentPin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every reported error gets a short reference code users can quote (PRD-07 §8.1).
        $exceptions->context(fn () => ['ref' => app('error.ref')]);

        // Business rules are expected outcomes, not bugs: never logged as errors.
        $exceptions->dontReport(BusinessRuleException::class);
        $exceptions->render(function (BusinessRuleException $e, Request $request) {
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['message' => $e->getMessage(), 'code' => $e->errorCode], $e->status);
            }

            return back()->with('error', $e->getMessage());
        });

        // Fresh hosting before /install: the sessions/cache tables do not exist yet.
        $exceptions->render(function (QueryException $e, Request $request) {
            if (preg_match('/no such table|Base table or view not found|doesn\'t exist/i', $e->getMessage()) && ! Schema::hasTable('settings')) {
                return response()->view('not-installed', [], 503)->header('X-App-State', 'not-installed');
            }

            return null;
        });

        $exceptions->dontReport(ScoreConflictException::class);
        $exceptions->render(fn (ScoreConflictException $e) => response()->json([
            'message' => $e->getMessage(),
            'code' => 'VERSION_CONFLICT',
            'current' => $e->current,
        ], 409));

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->expectsJson() || $request->header('X-Inertia') || $e instanceof ValidationException) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            $messages = [
                403 => 'Aksi ini tidak diizinkan.',
                404 => 'Data tidak ditemukan.',
                413 => 'File terlalu besar.',
                419 => 'Sesi halaman kedaluwarsa. Muat ulang halaman.',
                423 => 'Data terkunci karena kelas sudah final.',
                429 => 'Terlalu banyak permintaan. Coba lagi sebentar lagi.',
                503 => 'Aplikasi sedang dalam pemeliharaan.',
            ];

            return response()->json([
                'message' => $status >= 500 && $status !== 503
                    ? 'Terjadi kesalahan di server. Coba lagi. Bila berulang, laporkan kode '.app('error.ref').'.'
                    : ($e instanceof HttpExceptionInterface && $e->getMessage() !== '' && $status !== 404 ? $e->getMessage() : ($messages[$status] ?? 'Terjadi kesalahan.')),
                'code' => 'HTTP_'.$status,
                'ref' => app('error.ref'),
            ], $status);
        });

        // Friendly Inertia error pages outside local debugging.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if ($response->headers->has('X-App-State')) {
                return $response; // the "belum dipasang" page must not be replaced
            }

            if (! app()->hasDebugModeEnabled() && in_array($status, [403, 404, 500, 503], true) && ! $request->expectsJson()) {
                return Inertia::render('Error', ['status' => $status, 'errorRef' => app('error.ref')])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            if ($status === 419 && $request->header('X-Inertia')) {
                return back()->with('error', 'Halaman kedaluwarsa. Silakan ulangi tindakan Anda.');
            }

            return $response;
        });
    })->create();
