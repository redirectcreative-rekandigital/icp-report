<?php

use App\Models\ActivityLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->completeSetup();
    Storage::fake('local');
});

it('shows the school settings with NBM as the default ID label', function () {
    $this->withPinSession()->get('/pengaturan/sekolah')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p
            ->component('Settings/School')
            ->where('values.school_name', 'SD Muhammadiyah Wonorejo')
            ->where('values.principal_id_label', 'NBM'));
});

it('updates the school identity and logs the change', function () {
    $this->withPinSession()->put('/pengaturan/sekolah', [
        'school_name' => 'SD Muhammadiyah Wonorejo',
        'school_address' => 'Jl. Raya Wonorejo No. 1',
        'program_name' => 'International Class Program',
        'partner_name' => 'Partner English Academy',
        'city' => 'Sidoarjo',
        'principal_name' => 'Nama Kepala Sekolah, S.Pd.',
        'principal_id_label' => 'NBM',
        'principal_id_number' => '1234567',
        'show_documents_menu' => false,
    ])->assertSessionHasNoErrors()->assertSessionHas('success', 'Identitas sekolah tersimpan.');

    expect($this->settings()->get('city'))->toBe('Sidoarjo')
        ->and($this->settings()->get('principal_id_number'))->toBe('1234567')
        ->and($this->settings()->get('show_documents_menu'))->toBeFalse()
        ->and(ActivityLog::where('action', 'settings.school_updated')->exists())->toBeTrue();
});

it('validates the school identity', function () {
    $this->withPinSession()->put('/pengaturan/sekolah', [
        'school_name' => '',
        'program_name' => 'ICP',
        'principal_id_label' => 'KTP',
    ])->assertSessionHasErrors(['school_name', 'principal_id_label']);
});

it('stores logos privately and serves them through the app', function () {
    $this->withPinSession()
        ->post('/pengaturan/sekolah/logo/second', ['logo' => UploadedFile::fake()->image('muhammadiyah.png', 300, 300)])
        ->assertSessionHasNoErrors();

    $path = $this->settings()->get('second_logo_path');
    Storage::disk('local')->assertExists($path);
    expect($path)->toStartWith('logos/');

    $this->get('/media/logo/second')->assertOk();

    $this->withPinSession()->delete('/pengaturan/sekolah/logo/second');
    Storage::disk('local')->assertMissing($path);
    $this->get('/media/logo/second')->assertNotFound();
});

it('rejects logos that are not PNG/JPG or larger than 1 MB', function () {
    $this->withPinSession()
        ->post('/pengaturan/sekolah/logo/school', ['logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors(['logo' => 'Logo harus berformat PNG atau JPG.']);

    $this->withPinSession()
        ->post('/pengaturan/sekolah/logo/school', ['logo' => UploadedFile::fake()->image('big.png')->size(1500)])
        ->assertSessionHasErrors(['logo' => 'Ukuran logo maksimal 1 MB.']);
});
