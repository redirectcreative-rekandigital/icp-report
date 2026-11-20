<?php

namespace App\Http\Controllers;

use App\Services\PinService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

/**
 * One-time web installer for hosting without SSH (PRD-01 §8.2):
 *  - /install          : only while the database is empty AND INSTALL_TOKEN (.env) matches;
 *                        runs migrations + seeders, then is disabled (settings table exists).
 *  - /install/migrate  : updates after a new version — needs the token AND the school PIN.
 * Runs without the "web" middleware group: there may be no sessions table yet.
 */
class InstallController extends Controller
{
    public function show(Request $request): View
    {
        $this->guardToken($request);
        $installed = $this->installed();

        return view('install', [
            'installed' => $installed,
            'token' => $request->query('token'),
            'checks' => $this->checks(),
            'output' => null,
            'error' => null,
        ]);
    }

    public function install(Request $request): View
    {
        $this->guardToken($request);
        abort_if($this->installed(), 404);

        return $this->run($request, fn () => [
            Artisan::call('migrate', ['--force' => true]) === 0 ? Artisan::output() : throw new \RuntimeException(Artisan::output()),
            Artisan::call('db:seed', ['--force' => true]) === 0 ? Artisan::output() : throw new \RuntimeException(Artisan::output()),
        ], done: 'Instalasi selesai. Buka halaman utama untuk Setup awal (buat PIN).');
    }

    public function migrate(Request $request, PinService $pins): View
    {
        $this->guardToken($request);
        abort_unless($this->installed(), 404);

        if (! $pins->check((string) $request->input('pin'))) {
            return $this->page($request, error: 'PIN salah.');
        }

        return $this->run($request, fn () => [
            Artisan::call('migrate', ['--force' => true]) === 0 ? Artisan::output() : throw new \RuntimeException(Artisan::output()),
            Artisan::call('optimize:clear') === 0 ? Artisan::output() : '',
        ], done: 'Pembaruan database selesai.');
    }

    private function run(Request $request, callable $steps, string $done): View
    {
        @set_time_limit(120);

        try {
            $output = implode("\n", array_filter($steps()));

            return $this->page($request, output: $output, done: $done);
        } catch (Throwable $e) {
            report($e);

            return $this->page($request, error: 'Gagal: '.mb_strimwidth($e->getMessage(), 0, 600, '…'));
        }
    }

    private function page(Request $request, ?string $output = null, ?string $error = null, ?string $done = null): View
    {
        return view('install', [
            'installed' => $this->installed(),
            'token' => $request->input('token'),
            'checks' => $this->checks(),
            'output' => $output,
            'error' => $error,
            'done' => $done,
        ]);
    }

    /** No token configured or a wrong token → the installer simply does not exist (404). */
    private function guardToken(Request $request): void
    {
        $expected = (string) config('app.install_token');
        $given = (string) ($request->input('token') ?? $request->query('token'));

        abort_if($expected === '' || ! hash_equals($expected, $given), 404);
    }

    private function installed(): bool
    {
        try {
            return Schema::hasTable('settings');
        } catch (Throwable) {
            return false;
        }
    }

    /** Server requirements (PRD-01 §8.2 step 5). */
    private function checks(): array
    {
        $ext = ['pdo_mysql', 'mbstring', 'gd', 'zip', 'xml', 'fileinfo', 'intl', 'bcmath'];
        $checks = ['PHP ≥ 8.2 (sekarang '.PHP_VERSION.')' => version_compare(PHP_VERSION, '8.2.0', '>=')];
        foreach ($ext as $e) {
            $checks["Ekstensi {$e}"] = extension_loaded($e);
        }
        $checks['Folder storage dapat ditulis'] = is_writable(storage_path());
        $checks['Folder bootstrap/cache dapat ditulis'] = is_writable(base_path('bootstrap/cache'));

        try {
            DB::connection()->getPdo();
            $checks['Koneksi database'] = true;
        } catch (Throwable) {
            $checks['Koneksi database'] = false;
        }

        return $checks;
    }
}
