<?php

use App\Models\Classroom;
use App\Models\Document;
use App\Models\FinalResult;
use App\Models\Score;
use App\Models\Student;
use App\Services\FinalizationService;
use App\Services\PinService;
use Database\Seeders\DemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    $this->completeSetup();
    $this->withPinSession();
});

/* ---------------- Dokumen ---------------- */

it('uploads, lists, replaces, downloads and deletes documents', function () {
    $this->post('/dokumen', ['title' => 'Tes Kelas 5', 'grade' => 5, 'category' => 'tes', 'file' => UploadedFile::fake()->create('tes5.pdf', 200, 'application/pdf')])
        ->assertSessionHas('success');
    $doc = Document::first();
    Storage::disk('local')->assertExists($doc->file_path);

    $this->get('/dokumen?grade=5')->assertInertia(fn (Assert $p) => $p->component('Documents/Index')->has('documents', 1)->where('documents.0.extension', 'pdf'));
    $this->get('/dokumen?grade=3')->assertInertia(fn (Assert $p) => $p->has('documents', 0));

    $old = $doc->file_path;
    $this->post("/dokumen/{$doc->id}", ['title' => 'Tes Kelas 5 (revisi)', 'grade' => 5, 'category' => 'tes', 'file' => UploadedFile::fake()->create('tes5b.docx', 50)])
        ->assertSessionHas('success');
    Storage::disk('local')->assertMissing($old);
    expect($doc->fresh()->original_name)->toBe('tes5b.docx');

    $this->get("/dokumen/{$doc->id}/unduh")->assertOk()->assertDownload('tes5b.docx');

    $this->delete("/dokumen/{$doc->id}")->assertSessionHas('success');
    expect(Document::count())->toBe(0);
});

it('validates document uploads', function () {
    $this->post('/dokumen', ['title' => '', 'category' => 'x', 'file' => UploadedFile::fake()->create('a.exe', 10)])
        ->assertSessionHasErrors(['title', 'category', 'file']);
    $this->post('/dokumen', ['title' => 'Besar', 'category' => 'lainnya', 'file' => UploadedFile::fake()->create('big.pdf', 11000, 'application/pdf')])
        ->assertSessionHasErrors(['file' => 'Ukuran file maksimal 10 MB.']);
});

/* ---------------- Cadangan ---------------- */

it('backs up and restores all data and files, then requires the PIN again', function () {
    $this->seed(DemoSeeder::class);
    app(FinalizationService::class)->finalize(Classroom::where('name', '3A')->first());
    $this->post('/pengaturan/sekolah/logo/school', ['logo' => UploadedFile::fake()->image('logo.png', 40, 40)]);
    $this->post('/dokumen', ['title' => 'Pedoman', 'category' => 'pedoman', 'file' => UploadedFile::fake()->create('pedoman.pdf', 20, 'application/pdf')]);

    $this->withPinSession(confirmedAgo: 600)->get('/pengaturan/cadangan/unduh', ['Accept' => 'application/json'])->assertStatus(428);

    $res = $this->withPinSession()->get('/pengaturan/cadangan/unduh');
    $res->assertOk();
    $zip = $res->getFile()->getPathname();
    $copy = tempnam(sys_get_temp_dir(), 'bk').'.zip';
    copy($zip, $copy);

    // Damage the data after the backup.
    Score::query()->delete();
    Student::where('nis', '3001')->update(['name' => 'Rusak']);
    Storage::disk('local')->deleteDirectory('logos');
    app(PinService::class)->setPin('7351');

    $this->withPinSession()->post('/pengaturan/cadangan/pulihkan', ['file' => new UploadedFile($copy, 'cadangan.zip', 'application/zip', null, true), 'confirm' => 'salah'])
        ->assertSessionHasErrors('confirm');

    $this->withPinSession()->post('/pengaturan/cadangan/pulihkan', ['file' => new UploadedFile($copy, 'cadangan.zip', 'application/zip', null, true), 'confirm' => 'PULIHKAN'])
        ->assertRedirect('/pin');

    expect(Score::count())->toBe(20)
        ->and(Student::where('nis', '3001')->value('name'))->toBe('Aditya Pratama')
        ->and(FinalResult::count())->toBe(3)
        ->and(Classroom::where('name', '3A')->first()->current_finalization_id)->not->toBeNull()
        ->and(app(PinService::class)->check(self::PIN))->toBeTrue(); // PIN from the backup

    Storage::disk('local')->assertExists($this->settings()->get('school_logo_path'));
    Storage::disk('local')->assertExists(Document::first()->file_path);
    $this->get('/')->assertRedirect('/pin');
});

it('rejects a ZIP that is not an ICP backup', function () {
    $path = tempnam(sys_get_temp_dir(), 'x').'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('manifest.json', '{"app":"other"}');
    $zip->close();

    $this->withPinSession()->post('/pengaturan/cadangan/pulihkan', ['file' => new UploadedFile($path, 'x.zip', 'application/zip', null, true), 'confirm' => 'PULIHKAN'])
        ->assertSessionHas('error', 'File ini bukan cadangan ICP CEFR Report.');
});

/* ---------------- Installer ---------------- */

it('hides the installer without the right token and once installed', function () {
    config(['app.install_token' => 'secret-token']);

    $this->get('/install')->assertNotFound();
    $this->get('/install?token=wrong')->assertNotFound();

    // Already installed (settings table exists) → only the update form is shown.
    $this->get('/install?token=secret-token')->assertOk()->assertSee('Perbarui database');
    $this->post('/install', ['token' => 'secret-token'])->assertNotFound();

    config(['app.install_token' => '']);
    $this->get('/install?token=')->assertNotFound();
});

it('runs migrations from /install/migrate only with the token and the school PIN', function () {
    config(['app.install_token' => 'secret-token']);

    $this->post('/install/migrate', ['token' => 'secret-token', 'pin' => '1357'])->assertOk()->assertSee('PIN salah.');
    $this->post('/install/migrate', ['token' => 'secret-token', 'pin' => self::PIN])->assertOk()->assertSee('Pembaruan database selesai.');
});

/* ---------------- Client errors ---------------- */

it('logs client JavaScript errors with a reference code, rate limited', function () {
    $this->postJson('/client-errors', ['message' => 'TypeError: x is undefined', 'url' => '/kelas/1/nilai'])
        ->assertOk()->assertJson(fn ($j) => $j->where('ref', fn ($r) => str_starts_with($r, 'ERR-')));

    foreach (range(1, 10) as $_) {
        $this->postJson('/client-errors', ['message' => 'spam']);
    }
    $this->postJson('/client-errors', ['message' => 'spam'])->assertStatus(429);
});

it('serves the backup page', function () {
    $this->get('/pengaturan/cadangan')->assertInertia(fn (Assert $p) => $p->component('Settings/Backup')->where('lastBackupAt', null));
});
