<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\ClientErrorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\PinController;
use App\Http\Controllers\RecoveryController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScoreController;
use App\Http\Controllers\Settings\BackupController;
use App\Http\Controllers\Settings\DescriptorSettingsController;
use App\Http\Controllers\Settings\ReportSettingsController;
use App\Http\Controllers\Settings\SchoolSettingsController;
use App\Http\Controllers\Settings\ScoringSettingsController;
use App\Http\Controllers\Settings\SecuritySettingsController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TermClassroomController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

foreach (['term', 'year', 'classroom', 'student', 'enrollment', 'config', 'document'] as $param) {
    Route::pattern($param, '[0-9]+');
}

/*
| Public (no PIN). EnsureSetupComplete runs on every web request.
*/
Route::get('/setup', [SetupController::class, 'show'])->name('setup.show');
Route::post('/setup', [SetupController::class, 'store'])->name('setup.store');

Route::get('/pin', [PinController::class, 'show'])->name('pin.show');
Route::post('/pin', [PinController::class, 'verify'])->name('pin.verify');
Route::get('/pin/recover', [RecoveryController::class, 'show'])->name('pin.recover');
Route::post('/pin/recover', [RecoveryController::class, 'store'])->name('pin.recover.store');

Route::get('/media/logo/{slot}', [MediaController::class, 'logo'])
    ->whereIn('slot', ['school', 'second', 'partner'])
    ->name('media.logo');

/*
| PIN-protected.
*/
Route::middleware('pin.verified')->group(function () {
    Route::post('/pin/lock', [PinController::class, 'lock'])->name('pin.lock');
    Route::post('/pin/confirm', [PinController::class, 'confirm'])->name('pin.confirm');
    Route::get('/pin/recent', [PinController::class, 'recent'])->name('pin.recent');
    Route::post('/ping', [PinController::class, 'ping'])->name('ping');

    Route::get('/kode-pemulihan', [RecoveryController::class, 'code'])->name('recovery-code.show');
    Route::post('/kode-pemulihan', [RecoveryController::class, 'acknowledge'])->name('recovery-code.ack');

    Route::get('/', DashboardController::class)->name('dashboard');

    // Pengaturan
    Route::redirect('/pengaturan', '/pengaturan/sekolah')->name('settings');
    Route::get('/pengaturan/sekolah', [SchoolSettingsController::class, 'edit'])->name('settings.school');
    Route::put('/pengaturan/sekolah', [SchoolSettingsController::class, 'update'])->name('settings.school.update');
    Route::post('/pengaturan/sekolah/logo/{slot}', [SchoolSettingsController::class, 'uploadLogo'])->name('settings.logo.upload');
    Route::delete('/pengaturan/sekolah/logo/{slot}', [SchoolSettingsController::class, 'deleteLogo'])->name('settings.logo.delete');

    Route::get('/pengaturan/keamanan', [SecuritySettingsController::class, 'edit'])->name('settings.security');
    Route::put('/pengaturan/keamanan/sesi', [SecuritySettingsController::class, 'updateSession'])->name('settings.security.session');
    Route::middleware('pin.recent')->group(function () {
        Route::put('/pengaturan/keamanan/pin', [SecuritySettingsController::class, 'updatePin'])->name('settings.security.pin');
        Route::post('/pengaturan/keamanan/kode-pemulihan', [SecuritySettingsController::class, 'regenerateRecoveryCode'])->name('settings.security.recovery');
    });

    // Log aktivitas
    Route::get('/log', [ActivityLogController::class, 'index'])->name('log.index');
    Route::get('/log/ekspor', [ActivityLogController::class, 'export'])->name('log.export');

    // Periode & Kelas (PRD-03 §3)
    Route::get('/periode', [PeriodController::class, 'index'])->name('periods.index');
    Route::post('/periode/tahun', [PeriodController::class, 'storeYear'])->name('periods.years.store');
    Route::post('/periode/tahun/{year}/semester', [PeriodController::class, 'storeTerm'])->name('periods.terms.store');
    Route::patch('/periode/{term}', [PeriodController::class, 'updateTerm'])->name('periods.terms.update');
    Route::post('/periode/{term}/aktifkan', [PeriodController::class, 'activate'])->name('periods.terms.activate');
    Route::get('/periode/{term}', [TermClassroomController::class, 'show'])->name('periods.show');
    Route::post('/periode/{term}/kelas', [TermClassroomController::class, 'store'])->name('periods.classes.store');
    Route::post('/periode/{term}/salin-kelas/pratinjau', [TermClassroomController::class, 'copyPreview'])->name('periods.classes.copy.preview');
    Route::post('/periode/{term}/salin-kelas', [TermClassroomController::class, 'copy'])->name('periods.classes.copy');
    Route::put('/data-kelas/{classroom}', [TermClassroomController::class, 'update'])->name('periods.classes.update');

    // Siswa (PRD-03 §4)
    Route::get('/siswa', [StudentController::class, 'index'])->name('students.index');
    Route::post('/siswa', [StudentController::class, 'store'])->name('students.store');
    Route::post('/siswa/massal', [StudentController::class, 'bulk'])->name('students.bulk');
    Route::get('/siswa/{student}', [StudentController::class, 'show'])->name('students.show');
    Route::put('/siswa/{student}', [StudentController::class, 'update'])->name('students.update');

    // Hapus data memerlukan PIN ≤ 5 menit (PRD-02 §6).
    Route::middleware('pin.recent')->group(function () {
        Route::delete('/periode/{term}', [PeriodController::class, 'destroyTerm'])->name('periods.terms.destroy');
        Route::delete('/periode/tahun/{year}', [PeriodController::class, 'destroyYear'])->name('periods.years.destroy');
        Route::delete('/data-kelas/{classroom}', [TermClassroomController::class, 'destroy'])->name('periods.classes.destroy');
        Route::delete('/siswa/{student}', [StudentController::class, 'destroy'])->name('students.destroy');

        // Finalisasi & pembatalan (PRD-04 §6)
        Route::post('/kelas/{classroom}/finalkan', [ScoreController::class, 'finalize'])->name('classes.finalize');
        Route::post('/kelas/{classroom}/batalkan-final', [ScoreController::class, 'unfinalize'])->name('classes.unfinalize');

        // Pengaturan penilaian (PRD-04 §5.3)
        Route::put('/pengaturan/penilaian/{config}', [ScoringSettingsController::class, 'update'])->name('settings.scoring.update');

        // Layout rapor (PRD-05 §5)
        Route::put('/pengaturan/rapor', [ReportSettingsController::class, 'update'])->name('settings.report.update');
        Route::post('/pengaturan/rapor/bawaan', [ReportSettingsController::class, 'reset'])->name('settings.report.reset');
    });

    // Autosave satu sel (PRD-04 §4.3)
    Route::patch('/kelas/{classroom}/skor/{enrollment}', [ScoreController::class, 'update'])
        ->middleware('throttle:240,1')
        ->name('scores.update');
    Route::get('/kelas/{classroom}/kesiapan-final', [ScoreController::class, 'readiness'])->name('classes.readiness');

    Route::get('/pengaturan/penilaian', [ScoringSettingsController::class, 'edit'])->name('settings.scoring');
    Route::post('/pengaturan/penilaian/{config}/dampak', [ScoringSettingsController::class, 'impact'])->name('settings.scoring.impact');
    Route::get('/pengaturan/deskriptor', [DescriptorSettingsController::class, 'edit'])->name('settings.descriptors');
    Route::put('/pengaturan/deskriptor', [DescriptorSettingsController::class, 'update'])->name('settings.descriptors.update');
    Route::post('/pengaturan/deskriptor/bawaan', [DescriptorSettingsController::class, 'reset'])->name('settings.descriptors.reset');

    // Menu Kelas (PRD-04 §3; isi tab di M3–M4)
    Route::get('/kelas', [ClassController::class, 'index'])->name('classes.index');
    Route::get('/kelas/{classroom}', [ClassController::class, 'show'])->name('classes.show');
    Route::get('/kelas/{classroom}/nilai', [ClassController::class, 'tab'])->name('classes.scores');
    Route::get('/kelas/{classroom}/rapor', [ClassController::class, 'tab'])->name('classes.report');
    Route::get('/kelas/{classroom}/riwayat', [ClassController::class, 'tab'])->name('classes.history');

    // Rapor & Cetak (PRD-05 §3–4, §7)
    Route::get('/kelas/{classroom}/rapor/{enrollment}', [ReportController::class, 'preview'])->name('reports.preview');
    Route::get('/kelas/{classroom}/rapor/{enrollment}/html', [ReportController::class, 'html'])->name('reports.html');
    Route::get('/kelas/{classroom}/rapor/{enrollment}/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
    Route::post('/kelas/{classroom}/rapor/batch', [ReportController::class, 'startBatch'])->name('reports.batch.start');
    Route::post('/rapor/batch/{batch}/next', [ReportController::class, 'nextBatch'])->name('reports.batch.next');
    Route::post('/rapor/batch/{batch}/ulang', [ReportController::class, 'retryFailed'])->name('reports.batch.retry');
    Route::get('/rapor/batch/{batch}/download', [ReportController::class, 'downloadBatch'])->name('reports.batch.download');
    Route::delete('/rapor/batch/{batch}', [ReportController::class, 'cancelBatch'])->name('reports.batch.cancel');

    Route::get('/pengaturan/rapor', [ReportSettingsController::class, 'edit'])->name('settings.report');
    Route::post('/pengaturan/rapor/pratinjau', [ReportSettingsController::class, 'preview'])->name('settings.report.preview');

    // Impor/Ekspor (PRD-03 §5–6)
    Route::get('/impor', [ImportController::class, 'index'])->name('imports.index');
    Route::get('/impor/template/siswa', [ImportController::class, 'studentTemplate'])->name('imports.template.students');
    Route::get('/impor/template/nilai/{classroom}', [ImportController::class, 'scoreTemplate'])->name('imports.template.scores');
    Route::post('/impor/siswa/pratinjau', [ImportController::class, 'previewStudents'])->name('imports.students.preview');
    Route::post('/impor/nilai/pratinjau', [ImportController::class, 'previewScores'])->name('imports.scores.preview');
    Route::post('/impor/excel-lama/pratinjau', [ImportController::class, 'previewLegacy'])->name('imports.legacy.preview');
    Route::post('/impor/simpan', [ImportController::class, 'commit'])->name('imports.commit');
    Route::delete('/impor', [ImportController::class, 'cancel'])->name('imports.cancel');
    Route::get('/impor/laporan-galat', [ImportController::class, 'errorReport'])->name('imports.errors');
    Route::get('/ekspor/siswa/{term}', [ExportController::class, 'students'])->name('exports.students');
    Route::get('/ekspor/kelas/{classroom}', [ExportController::class, 'classRecap'])->name('exports.class');
    Route::get('/ekspor/periode/{term}', [ExportController::class, 'termRecap'])->name('exports.term');

    // Dokumen (PRD-03 §7)
    Route::get('/dokumen', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('/dokumen', [DocumentController::class, 'store'])->name('documents.store');
    Route::post('/dokumen/{document}', [DocumentController::class, 'update'])->name('documents.update'); // POST: multipart file replace
    Route::delete('/dokumen/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    Route::get('/dokumen/{document}/unduh', [DocumentController::class, 'download'])->name('documents.download');

    // Cadangan (PRD-02 §6: unduh & pulihkan memerlukan PIN ≤ 5 menit)
    Route::get('/pengaturan/cadangan', [BackupController::class, 'edit'])->name('settings.backup');
    Route::middleware('pin.recent')->group(function () {
        Route::get('/pengaturan/cadangan/unduh', [BackupController::class, 'download'])->name('settings.backup.download');
        Route::post('/pengaturan/cadangan/pulihkan', [BackupController::class, 'restore'])->name('settings.backup.restore');
    });

    // Laporan error JavaScript (PRD-07 §8.2)
    Route::post('/client-errors', ClientErrorController::class)->middleware('throttle:10,1')->name('client-errors');

    // Galeri komponen, hanya di lingkungan lokal (PRD-07 §5.2)
    if (app()->isLocal()) {
        Route::get('/_ui', fn () => Inertia::render('UiGallery'))->name('ui.gallery');
    }
});
