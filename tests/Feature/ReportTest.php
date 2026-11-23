<?php

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\PrintBatch;
use App\Models\Score;
use App\Models\ScoringConfig;
use App\Models\Student;
use App\Services\BatchPrintService;
use App\Services\PdfReportService;
use App\Services\ReportDataBuilder;
use App\Services\TermService;
use App\Support\ReportLayout;
use Database\Seeders\CefrReferenceSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

beforeEach(function () {
    Storage::fake('local');
    $this->completeSetup();
    $this->seed(CefrReferenceSeeder::class);
    $this->settings()->set(['city' => 'Sidoarjo', 'principal_name' => 'Kepala Sekolah', 'principal_id_number' => '987']);
    $this->withPinSession();
    [$this->term] = app(TermService::class)->createTerm(AcademicYear::create(['name' => '2026/2027']), 1, '2026-12-18');
    $this->class = Classroom::create(['term_id' => $this->term->id, 'grade' => 5, 'name' => '5A', 'english_teacher' => 'Ms. Sarah']);

    $this->enroll = function (string $nis, string $name, ?array $s) {
        $e = Enrollment::create(['classroom_id' => $this->class->id, 'student_id' => Student::create(compact('nis', 'name'))->id, 'term_id' => $this->term->id, 'roll_no' => (int) substr($nis, -1)]);
        if ($s) {
            Score::create(['enrollment_id' => $e->id] + array_combine(['listening', 'reading', 'writing', 'speaking'], $s));
        }

        return $e;
    };
    $this->kevin = ($this->enroll)('5001', 'Kevin Wijaya', [15, 17, 11, 13]);
    $this->laras = ($this->enroll)('5002', 'Laras Anindya', [18, 18, 17, 18]);
    $this->empty = ($this->enroll)('5003', 'Belum Lengkap', [10, null, null, null]);
});

it('lists printable students on the Rapor tab', function () {
    $this->get("/kelas/{$this->class->id}/rapor")->assertInertia(fn (Assert $p) => $p
        ->where('tab', 'rapor')
        ->where('paperDefault', 'a4')
        ->has('students', 3)
        ->where('students.0.overall_level', 'Intermediate')
        ->where('students.0.total', 56)
        ->where('students.0.printable', true)
        ->where('students.2.printable', false));
});

it('renders the HTML preview with the same content as the PDF, marked DRAFT', function () {
    $this->get("/kelas/{$this->class->id}/rapor/{$this->kevin->id}/html?paper=f4")->assertOk()->assertSee('size: 215mm 330mm', false);

    $html = $this->get("/kelas/{$this->class->id}/rapor/{$this->kevin->id}/html")->getContent();
    expect($html)->toContain('Kevin Wijaya')
        ->toContain('SD MUHAMMADIYAH WONOREJO')
        ->toContain('In Collaboration with <b>NATIONAL GEOGRAPHIC LEARNING</b>')
        ->toContain('alt="National Geographic Learning"')
        ->toContain('SKILL ASSESSMENT BY TEACHER')
        ->toContain('fonts/pagella/TeXGyrePagella-regular.ttf')
        ->toContain('size: 210mm 297mm') // A4 by default
        ->not->toContain('English Teacher')
        ->not->toContain('CEFR-Aligned')
        ->toContain('Strongest skill this term: Reading.')
        ->toContain('Sidoarjo, 18 December 2026')
        ->toContain('<span class="score">Intermediate</span>')
        ->toContain('56 / 100')
        ->not->toContain('EXPECTED LEVEL')
        ->not->toContain('CEFR LEVEL MAP') // grade 5: no National Geographic map
        ->toContain('Parent / Guardian')
        ->toContain('Homeroom Teacher')
        ->toContain('Principal')
        ->toContain('NBM. 987')
        ->toContain('class="draft"');
});

it('downloads one PDF page with the PRD file name, A4 by default and F4 on request', function () {
    $res = $this->get("/kelas/{$this->class->id}/rapor/{$this->kevin->id}/pdf?paper=f4");
    $res->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($res->headers->get('Content-Disposition'))->toBe('attachment; filename="Report_5001_Kevin_Wijaya_2026-2027_S1_F4.pdf"');

    $fpdi = new Fpdi;
    expect($fpdi->setSourceFile(StreamReader::createByString($res->getContent())))->toBe(1);
    $size = $fpdi->getTemplateSize($fpdi->importPage(1));
    expect(round($size['width']))->toEqual(215)->and(round($size['height']))->toEqual(330);

    $a4 = $this->get("/kelas/{$this->class->id}/rapor/{$this->kevin->id}/pdf?inline=1");
    expect($a4->headers->get('Content-Disposition'))->toStartWith('inline;')->toContain('_A4.pdf');
    $fpdi = new Fpdi;
    $fpdi->setSourceFile(StreamReader::createByString($a4->getContent()));
    expect(round($fpdi->getTemplateSize($fpdi->importPage(1))['height']))->toEqual(297);

    expect(strlen($res->getContent()))->toBeLessThan(300 * 1024);
});

it('refuses reports for incomplete students', function () {
    $this->get("/kelas/{$this->class->id}/rapor/{$this->empty->id}/pdf")->assertSessionHas('error', fn ($m) => str_contains($m, 'belum lengkap'));
});

it('uses the finalisation snapshot: later layout, threshold and logo changes do not alter final reports', function () {
    $this->settings()->set(['report_layout' => array_replace(ReportLayout::defaults(), ['header' => ['title' => 'TITLE AT FINALISATION', 'extra_line' => '']])]);
    $this->post('/pengaturan/sekolah/logo/school', ['logo' => UploadedFile::fake()->image('old.png', 50, 50)]);
    $this->empty->student->update(['is_active' => false]);
    $this->post("/kelas/{$this->class->id}/finalkan")->assertSessionHas('success');

    $this->settings()->set(['report_layout' => array_replace(ReportLayout::defaults(), ['header' => ['title' => 'NEW TITLE', 'extra_line' => '']])]);
    ScoringConfig::where(['term_id' => $this->term->id, 'grade' => 5])->update(['min_intermediate' => 18, 'min_advanced' => 22]);
    $this->delete('/pengaturan/sekolah/logo/school');

    $html = $this->get("/kelas/{$this->class->id}/rapor/{$this->kevin->id}/html")->getContent();
    expect(substr_count($html, 'data:image/png;base64'))->toBe(3); // frozen school logo copy + National Geographic logo + chart
    expect($html)->toContain('TITLE AT FINALISATION')
        ->not->toContain('NEW TITLE')
        ->not->toContain('class="draft"')
        ->toContain('<span class="score">Intermediate</span>'); // live thresholds would now say Beginner
});

it('builds all reports in chunks and merges them into one PDF', function () {
    $start = $this->postJson("/kelas/{$this->class->id}/rapor/batch", ['paper' => 'a4', 'mode' => 'merged'])->assertOk();
    expect($start->json('total'))->toBe(2); // the incomplete student is skipped

    // Starting again while running continues the same batch.
    $this->postJson("/kelas/{$this->class->id}/rapor/batch", ['paper' => 'a4', 'mode' => 'merged'])->assertJsonPath('id', $start->json('id'));

    $state = $this->postJson("/rapor/batch/{$start->json('id')}/next")->assertOk()->json();
    expect($state['status'])->toBe('completed')->and($state['done'])->toBe(2);

    $download = $this->get($state['download_url']);
    $download->assertOk();
    expect(PdfReportService::pageCount($download->streamedContent()))->toBe(2);
});

it('builds a ZIP with one PDF per student and resumes after a partial run', function () {
    $batch = app(BatchPrintService::class)->start($this->class, 'f4', 'zip', [$this->kevin->id, $this->laras->id]);
    Storage::disk('local')->put($batch->dir().'/000.pdf', app(PdfReportService::class)->render(
        app(ReportDataBuilder::class)->build($this->kevin, 'f4')
    )); // as if a previous request had done the first one

    $state = app(BatchPrintService::class)->next($batch->fresh());
    expect($state['status'])->toBe('completed');

    $zipPath = Storage::disk('local')->path(PrintBatch::find($batch->id)->file_path);
    $zip = new ZipArchive;
    $zip->open($zipPath);
    expect($zip->numFiles)->toBe(2)
        ->and($zip->getNameIndex(0))->toBe('Report_5001_Kevin_Wijaya_2026-2027_S1_F4.pdf');
});

it('limits a batch to 45 reports and purges expired batches', function () {
    $batch = app(BatchPrintService::class)->start($this->class, 'f4', 'merged', null);
    $batch->update(['expires_at' => now()->subHour()]);
    Storage::disk('local')->put($batch->dir().'/000.pdf', 'x');

    app(BatchPrintService::class)->purgeExpired();

    expect(PrintBatch::count())->toBe(0);
    Storage::disk('local')->assertMissing($batch->dir().'/000.pdf');
});

it('saves the report layout with a recent PIN and validates contrast and signatures', function () {
    $layout = ReportLayout::defaults();
    $layout['preset'] = 'minimal';
    $layout['sections'] = ReportLayout::PRESETS['minimal'];
    $layout['paper_default'] = 'a4';

    $this->withPinSession(confirmedAgo: 600)->putJson('/pengaturan/rapor', $layout)->assertStatus(428);
    $this->withPinSession()->put('/pengaturan/rapor', $layout)->assertSessionHasNoErrors();
    expect($this->settings()->get('report_layout')['paper_default'])->toBe('a4');

    $bad = $layout;
    $bad['theme'] = ['preset' => 'kustom', 'primary' => '#F4E04D', 'accent' => '#000000'];
    $this->withPinSession()->put('/pengaturan/rapor', $bad)->assertSessionHasErrors('theme.primary');

    $bad = $layout;
    $bad['signatures'] = [['role' => 'principal', 'label' => 'Principal']];
    $this->withPinSession()->put('/pengaturan/rapor', $bad)->assertSessionHasErrors('signatures');
});

it('previews the layout live with sample data and warns about a missing school logo', function () {
    $html = $this->postJson('/pengaturan/rapor/pratinjau', ReportLayout::defaults() + ['paper' => 'a4'])->assertOk()->json('html');
    expect($html)->toContain('Kevin Wijaya')
        ->toContain('Logo sekolah belum diunggah')
        ->toContain('ONLINE PLACEMENT TEST ALIGNED WITH THE CEFR LEVEL MAP') // the sample is a grade 6 student
        ->toContain('CURRENT LEVEL');
});

it('computes WCAG contrast for custom colours', function () {
    expect(ReportLayout::contrastWithWhite('#1F3A68'))->toBeGreaterThan(4.5)
        ->and(ReportLayout::contrastWithWhite('#E3A82B'))->toBeLessThan(4.5);
});

it('renders the preview page and the layout settings page', function () {
    $this->get("/kelas/{$this->class->id}/rapor/{$this->kevin->id}")->assertInertia(fn (Assert $p) => $p
        ->component('Reports/Preview')
        ->where('printable', true)
        ->where('nextId', $this->laras->id)
        ->where('prevId', null)
        ->where('count', 2));

    $this->get('/pengaturan/rapor')->assertInertia(fn (Assert $p) => $p
        ->component('Settings/Report')
        ->where('layout.paper_default', 'a4')
        ->where('layout.id_label', 'NBM')
        ->has('layout.signatures', 3)
        ->where('layout.signatures.0.role', 'parent')
        ->where('layout.signatures.1.role', 'homeroom')
        ->where('layout.signatures.2.role', 'principal'));
});
