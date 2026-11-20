<?php

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Score;
use App\Models\Student;
use App\Services\PdfReportService;
use App\Services\ReportDataBuilder;
use App\Services\TermService;
use App\Support\ReportLayout;
use Database\Seeders\CefrReferenceSeeder;

beforeEach(function () {
    $this->completeSetup();
    $this->seed(CefrReferenceSeeder::class);
    $this->settings()->set([
        'school_address' => 'Jl. Raya Wonorejo No. 1, Kecamatan Wonorejo, Kabupaten Contoh, Jawa Timur 61234',
        'partner_name' => 'Partner English Academy',
        'city' => 'Sidoarjo',
        'principal_name' => 'Nama Kepala Sekolah, S.Pd., M.Pd.',
        'principal_id_number' => '1234567',
    ]);
    [$this->term] = app(TermService::class)->createTerm(AcademicYear::create(['name' => '2026/2027']), 1, '2026-12-18');
    $this->class = Classroom::create(['term_id' => $this->term->id, 'grade' => 6, 'name' => '6A', 'homeroom_teacher' => 'Ibu Rina Wulandari, S.Pd.', 'english_teacher' => 'Ms. Sarah Johnson']);
    $this->e = Enrollment::create([
        'classroom_id' => $this->class->id,
        'student_id' => Student::create(['nis' => '6001', 'name' => 'Muhammad Rizki Ramadhan Putra Pratama Wijaya'])->id, // > 32 chars
        'term_id' => $this->term->id,
        'roll_no' => 1,
    ]);
    Score::create(['enrollment_id' => $this->e->id, 'listening' => 19, 'reading' => 15, 'writing' => 12.5, 'speaking' => 16, 'teacher_comment' => str_repeat('Excellent effort and steady growth this semester. ', 12)]);
});

it('renders exactly one page for every preset, paper and the longest comment', function (string $preset, string $paper) {
    $layout = ReportLayout::defaults();
    $layout['preset'] = $preset;
    $layout['sections'] = ReportLayout::PRESETS[$preset];
    $this->settings()->set(['report_layout' => $layout]);

    $data = app(ReportDataBuilder::class)->build($this->e, $paper);
    $pdf = app(PdfReportService::class)->render($data);

    expect(PdfReportService::pageCount($pdf))->toBe(1);
})->with(['lengkap', 'ringkas', 'minimal'])->with(['f4', 'a4']);

it('stays on one page with every section off and with every section on at 20 mm margins', function (bool $on) {
    $layout = ReportLayout::defaults();
    $layout['sections'] = array_fill_keys(ReportLayout::SECTIONS, $on);
    $layout['margin_mm'] = $on ? 20 : 8;
    $this->settings()->set(['report_layout' => $layout]);

    foreach (['f4', 'a4'] as $paper) {
        $pdf = app(PdfReportService::class)->render(app(ReportDataBuilder::class)->build($this->e, $paper));
        expect(PdfReportService::pageCount($pdf))->toBe(1);
    }
})->with([true, false]);
