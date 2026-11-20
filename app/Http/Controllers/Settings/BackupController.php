<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\ActivityLogger;
use App\Services\BackupService;
use App\Services\PinService;
use App\Support\PinSession;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Pengaturan → Cadangan (both actions need the PIN within 5 minutes, PRD-02 §6). */
class BackupController extends Controller
{
    public function edit(Settings $settings): Response
    {
        $last = ActivityLog::query()->where('action', 'backup.downloaded')->latest('id')->first();

        return Inertia::render('Settings/Backup', [
            'lastBackupAt' => $last?->created_at?->toIso8601String(),
            'maxUploadMb' => (int) min(50, $this->iniMb('upload_max_filesize'), $this->iniMb('post_max_size')),
        ]);
    }

    public function download(BackupService $backups, ActivityLogger $logger, Settings $settings): BinaryFileResponse
    {
        $path = $backups->create();
        $logger->log('backup.downloaded', 'pengaturan', 'Cadangan data diunduh.');
        $school = preg_replace('/[^A-Za-z0-9]+/', '_', (string) $settings->get('school_name'));

        return response()->download($path, "Cadangan_ICP_{$school}_".now()->format('Y-m-d_His').'.zip', [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend();
    }

    public function restore(Request $request, BackupService $backups, PinService $pins, ActivityLogger $logger): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:zip', 'max:51200'],
            'confirm' => ['required', 'in:PULIHKAN'],
        ], [
            'file.required' => 'Pilih file cadangan (.zip).',
            'file.mimes' => 'File cadangan harus berformat .zip.',
            'file.max' => 'File cadangan maksimal 50 MB.',
            'confirm.in' => 'Ketik PULIHKAN untuk mengonfirmasi.',
            'confirm.required' => 'Ketik PULIHKAN untuk mengonfirmasi.',
        ]);

        $result = $backups->restore($request->file('file')->getRealPath());

        // The restored data brings its own PIN: every session (including this one) must sign in again.
        $pins->endOtherSessions();
        $logger->log('backup.restored', 'pengaturan', 'Data dipulihkan dari cadangan '.$result['created_at'].'.');
        PinSession::end($request);

        return redirect()->route('pin.show')->with(
            'status',
            'Data berhasil dipulihkan dari cadangan. Masukkan PIN yang berlaku saat cadangan dibuat.',
        );
    }

    private function iniMb(string $key): float
    {
        $v = trim((string) ini_get($key));
        $n = (float) $v;

        return match (strtolower(substr($v, -1))) {
            'g' => $n * 1024,
            'm' => $n,
            'k' => $n / 1024,
            default => $n / 1048576,
        } ?: 50;
    }
}
