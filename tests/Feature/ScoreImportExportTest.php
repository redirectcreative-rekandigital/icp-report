<?php

use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Score;
use App\Models\Student;
use App\Models\Term;
use App\Services\FinalizationService;
use Database\Seeders\DemoSeeder;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    $this->completeSetup();
    $this->withPinSession();
    $this->seed(DemoSeeder::class);
    $this->term = Term::active();
    $this->c5a = Classroom::where(['term_id' => $this->term->id, 'name' => '5A'])->first();
});

function sheetUpload(array $rows, string $name = 'nilai.xlsx', ?callable $tweak = null): UploadedFile
{
    $book = new Spreadsheet;
    $book->getActiveSheet()->fromArray($rows, null, 'A1', true);
    $tweak && $tweak($book);
    $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    (new Xlsx($book))->save($path);

    return new UploadedFile($path, $name, null, null, true);
}

function readXlsx(string $content): Spreadsheet
{
    $path = tempnam(sys_get_temp_dir(), 'exp').'.xlsx';
    file_put_contents($path, $content);

    return IOFactory::load($path);
}

function scoreOf(string $nis): ?Score
{
    return Enrollment::whereHas('student', fn ($q) => $q->where('nis', $nis))->first()?->score()->first();
}

it('downloads a score template listing the class students with validation', function () {
    $res = $this->get("/impor/template/nilai/{$this->c5a->id}")->assertOk()->assertDownload('template_nilai_5A.xlsx');
    $sheet = readXlsx($res->streamedContent())->getActiveSheet();

    expect($sheet->getCell('B3')->getValue())->toBe('NIS*')
        ->and($sheet->getCell('B4')->getValue())->toBe('5001')
        ->and($sheet->getCell('D4')->getValue())->toEqual(15)
        ->and($sheet->getCell('D4')->getDataValidation()->getFormula1())->toBe('5')
        ->and($sheet->getCell('D4')->getDataValidation()->getFormula2())->toBe('25')
        ->and($sheet->getCell('I3')->getValue())->toBeNull(); // no NatGeo column below grade 6
});

it('imports the National Geographic level for grade 6 only', function () {
    $c6a = Classroom::where(['term_id' => $this->term->id, 'name' => '6A'])->first();
    $sheet = readXlsx($this->get("/impor/template/nilai/{$c6a->id}")->streamedContent())->getActiveSheet();
    expect($sheet->getCell('I3')->getValue())->toBe('NatGeo')
        ->and($sheet->getCell('I4')->getValue())->toBe('A2');

    $rows = [['NIS', 'NatGeo'], ['6001', 'b1'], ['6002', 'C1']];
    $this->post('/impor/nilai/pratinjau', ['file' => sheetUpload($rows), 'classroom_id' => $c6a->id, 'overwrite' => true]);
    $this->get('/impor')->assertInertia(fn (Assert $p) => $p
        ->where('preview.rows.0.changes', ['NatGeo "A2" → "B1"'])
        ->where('preview.rows.1.messages.0', "Baris 3: NatGeo 'C1' harus Pre-A1, A1, A2, B1 atau B2."));
    $this->post('/impor/simpan')->assertSessionHas('success');
    expect(scoreOf('6001')->natgeo_level)->toBe('B1');

    $this->post('/impor/nilai/pratinjau', ['file' => sheetUpload([['NIS', 'NatGeo'], ['5001', 'A2']]), 'classroom_id' => $this->c5a->id]);
    $this->get('/impor')->assertInertia(fn (Assert $p) => $p
        ->where('preview.rows.0.messages.0', 'Kolom NatGeo diabaikan (hanya untuk Kelas 6).'));
});

it('imports new scores, keeps existing ones without "Timpa", and reports errors', function () {
    $rows = [
        ['TEMPLATE'], ['petunjuk'],
        ['No', 'NIS*', 'Nama', 'Listening', 'Reading', 'Writing', 'Speaking', 'Komentar'],
        [1, '5006', 'Wulan Sari', '12,5', 14, 13, 15, 'Good job'],   // new scores
        [2, '5001', 'Kevin Wijaya', 16, '', '', '', ''],               // differs from 15 → kept (warning)
        [3, '5002', 'Laras', 'lima belas', '', '', '', ''],           // not a number
        [4, '5007', 'Yusuf Ramadhan', 26, '', 12.3, '', ''],                    // > 25, not .5
        [5, '6010', 'Nobody', 10, 10, 10, 10, ''],                     // not in class
    ];
    $this->post('/impor/nilai/pratinjau', ['file' => sheetUpload($rows), 'classroom_id' => $this->c5a->id])->assertRedirect('/impor');

    $this->get('/impor')->assertInertia(fn (Assert $p) => $p
        ->where('preview.type', 'nilai')
        ->where('preview.class', '5A')
        ->where('preview.rows.0.status', 'new')
        ->where('preview.rows.0.changes', ['Listening – → 12,5', 'Reading – → 14', 'Writing – → 13', 'Speaking – → 15', 'Komentar – → "Good job"'])
        ->where('preview.rows.1.status', 'warning')
        ->where('preview.rows.2.messages.0', "Baris 6: Listening 'lima belas' bukan angka.")
        ->where('preview.rows.3.messages', ['Baris 7: Listening 26 harus antara 5 dan 25.', 'Baris 7: Writing 12,3 harus bulat atau kelipatan 0,5.'])
        ->where('preview.rows.4.messages.0', 'Baris 8: NIS 6010 tidak terdaftar di kelas 5A.')
        ->where('preview.summary.importable', 1)
        ->missing('preview.rows.0.set'));

    $this->post('/impor/simpan')->assertSessionHas('success', 'Impor nilai 5A selesai: 5 nilai pada 1 siswa.');

    expect(scoreOf('5006')->listening)->toBe(12.5)
        ->and(scoreOf('5006')->teacher_comment)->toBe('Good job')
        ->and(scoreOf('5001')->listening)->toBe(15.0); // not overwritten
});

it('overwrites existing scores and clears with "-" only with a recent PIN', function () {
    $rows = [['NIS', 'Nama', 'Listening', 'Reading', 'Writing', 'Speaking'], ['5001', 'Kevin Wijaya', 16, '-', '', '']];

    $this->post('/impor/nilai/pratinjau', ['file' => sheetUpload($rows), 'classroom_id' => $this->c5a->id, 'overwrite' => true]);
    $this->get('/impor')->assertInertia(fn (Assert $p) => $p
        ->where('preview.overwrite', true)
        ->where('preview.rows.0.status', 'updated')
        ->where('preview.rows.0.changes', ['Listening 15 → 16', 'Reading 17 → –']));

    $this->withPinSession(confirmedAgo: 600)->postJson('/impor/simpan')->assertStatus(428);
    expect(scoreOf('5001')->listening)->toBe(15.0);

    $this->withPinSession()->post('/impor/simpan')->assertSessionHas('success');
    expect(scoreOf('5001')->listening)->toBe(16.0)->and(scoreOf('5001')->reading)->toBeNull();
});

it('refuses score imports into a final class', function () {
    $c3a = Classroom::where(['term_id' => $this->term->id, 'name' => '3A'])->first();
    app(FinalizationService::class)->finalize($c3a);

    $this->post('/impor/nilai/pratinjau', ['file' => sheetUpload([['NIS', 'Listening'], ['3001', 10]]), 'classroom_id' => $c3a->id])
        ->assertSessionHas('error', 'Kelas 3A sudah final. Batalkan finalisasi untuk mengimpor nilai.');
});

it('imports the old Excel workbook (sheets Grade 3–6)', function () {
    // A copy of the real workbook shipped with the project, with one extra student added.
    $source = base_path('docs/source/ICP_English_Progress_Report.xlsx');
    $book = IOFactory::load($source);
    $g3 = $book->getSheetByName('Grade 3');
    $g3->fromArray(['8888', 'Siswa Baru Excel', '3B', 11, 12, 13, 14], null, 'B11');
    $g5 = $book->getSheetByName('Grade 5');
    $g5->setCellValue('D6', '5C'); // class that does not exist
    $path = tempnam(sys_get_temp_dir(), 'old').'.xlsx';
    $writer = new Xlsx($book);
    $writer->setPreCalculateFormulas(false); // the Report Card sheet formulas are not needed here
    $writer->save($path);

    $this->post('/impor/excel-lama/pratinjau', ['file' => new UploadedFile($path, 'ICP_English_Progress_Report.xlsx', null, null, true)])
        ->assertRedirect('/impor');

    $this->get('/impor')->assertInertia(fn (Assert $p) => $p
        ->where('preview.type', 'excel-lama')
        ->where('preview.summary.total', 21)        // 20 sample rows + 1 added
        ->where('preview.summary.new', 1)
        ->where('preview.summary.error', 1)         // 5C does not exist
        ->where('preview.rows.5.sheet', 'Grade 3')
        ->where('preview.rows.5.nis', '8888'));

    $this->post('/impor/simpan')->assertSessionHas('success', fn ($m) => str_contains($m, '1 baru'));

    $new = Student::where('nis', '8888')->first();
    expect($new->name)->toBe('Siswa Baru Excel')
        ->and($new->enrollments()->first()->classroom->name)->toBe('3B')
        ->and((float) $new->enrollments()->first()->score->speaking)->toBe(14.0);
});

it('exports the class recap like the Excel Grade sheet, marked DRAFT', function () {
    $res = $this->get("/ekspor/kelas/{$this->c5a->id}")->assertOk()->assertDownload('Rekap_5A_2026-2027_S1.xlsx');
    $sheet = readXlsx($res->streamedContent())->getActiveSheet();

    expect($sheet->getTitle())->toBe('5A (DRAFT)')
        ->and($sheet->getCell('C5')->getValue())->toBe('Kevin Wijaya')
        ->and($sheet->getCell('K5')->getValue())->toBe('Intermediate') // Reading 17
        ->and($sheet->getCell('L5')->getValue())->toBe('Beginner')     // Writing 11
        ->and($sheet->getCell('N5')->getValue())->toEqual(56)          // total
        ->and($sheet->getCell('O5')->getValue())->toBe('Intermediate')
        ->and($sheet->getCell('P5')->getValue())->toBeNull()           // NatGeo: grade 6 only
        ->and($sheet->getCell('Q5')->getValue())->toBe('Reading')
        ->and($sheet->getStyle('O5')->getFill()->getStartColor()->getRGB())->toBe('F2C230');
});

it('exports the period recap with a summary sheet and one sheet per class', function () {
    $res = $this->get("/ekspor/periode/{$this->term->id}")->assertOk()->assertDownload('Rekap_Periode_2026-2027_S1.xlsx');
    $book = readXlsx($res->streamedContent());

    expect($book->getSheetCount())->toBe(9)
        ->and($book->getSheet(0)->getTitle())->toBe('Ringkasan')
        ->and($book->getSheet(0)->getCell('A4')->getValue())->toBe('3A (DRAFT)');
});
