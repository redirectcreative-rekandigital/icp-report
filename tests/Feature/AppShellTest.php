<?php

use App\Models\ActivityLog;
use App\Models\Classroom;
use App\Models\Descriptor;
use App\Models\LevelMeta;
use App\Models\Score;
use App\Models\Student;
use App\Services\ActivityLogger;
use App\Services\Housekeeping;
use Database\Seeders\CefrReferenceSeeder;
use Database\Seeders\DemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->completeSetup());

it('sends security headers and noindex', function () {
    $this->get('/pin')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'same-origin')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('shares school and session info but never secrets', function () {
    $this->withPinSession()->get('/')
        ->assertInertia(fn (Assert $p) => $p
            ->where('school.name', 'SD Muhammadiyah Wonorejo')
            ->where('session.idleMinutes', 60)
            ->where('nav.classes', [])
            ->missing('school.pin_hash'));

    $this->withPinSession()->get('/')->assertDontSee('pin_hash')->assertDontSee('recovery_code_hash');
});

it('lists and filters the activity log, and exports CSV', function () {
    app(ActivityLogger::class)->log('settings.school_updated', 'pengaturan', 'Identitas sekolah diperbarui (city).');
    app(ActivityLogger::class)->log('pin.failed', 'akses', 'Percobaan PIN salah.');

    $this->withPinSession()->get('/log?category=pengaturan')
        ->assertInertia(fn (Assert $p) => $p
            ->component('ActivityLog/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.category', 'pengaturan'));

    $csv = $this->withPinSession()->get('/log/ekspor?q=PIN')->streamedContent();
    expect($csv)->toContain('Percobaan PIN salah.')->not->toContain('Identitas sekolah');
});

it('returns the uniform JSON error format with a reference code', function () {
    $this->withPinSession()->getJson('/tidak-ada')
        ->assertNotFound()
        ->assertJsonStructure(['message', 'code', 'ref'])
        ->assertJsonPath('message', 'Data tidak ditemukan.');
});

it('seeds level meta and the 20 descriptors from the Excel workbook', function () {
    $this->seed(CefrReferenceSeeder::class);

    expect(LevelMeta::count())->toBe(5)
        ->and(Descriptor::count())->toBe(20)
        ->and(LevelMeta::find('A2')->cambridge)->toBe('A2 Flyers / A2 Key')
        ->and(Descriptor::where(['level' => 'B1', 'skill' => 'speaking'])->value('can_do'))
        ->toBe('Can describe experiences, give opinions with reasons and keep a conversation going on familiar topics.');
});

it('prunes activity logs older than one year', function () {
    ActivityLog::create(['action' => 'x', 'category' => 'akses', 'description' => 'old', 'created_at' => now()->subDays(400)]);
    ActivityLog::create(['action' => 'x', 'category' => 'akses', 'description' => 'new', 'created_at' => now()->subDays(10)]);

    app(Housekeeping::class)->run();

    expect(ActivityLog::pluck('description')->all())->toBe(['new']);
});

it('seeds demo data from the Excel sample rows, idempotently', function () {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class);

    expect(Student::count())->toBe(22)
        ->and(Classroom::count())->toBe(8)
        ->and(Score::count())->toBe(20);
});
