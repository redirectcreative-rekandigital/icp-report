<?php

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\TermService;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    $this->completeSetup();
    $this->withPinSession();
    [$this->term] = app(TermService::class)->createTerm(AcademicYear::create(['name' => '2026/2027']), 1, null);
    foreach (['3A' => 3, '3B' => 3, '5A' => 5] as $name => $grade) {
        Classroom::create(['term_id' => $this->term->id, 'grade' => $grade, 'name' => $name]);
    }
});

/** Builds an .xlsx upload from rows (first row = header). */
function xlsxUpload(array $rows, array $preamble = []): UploadedFile
{
    $book = new Spreadsheet;
    $book->getActiveSheet()->fromArray([...$preamble, ...$rows], null, 'A1', true);
    $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    (new Xlsx($book))->save($path);

    return new UploadedFile($path, 'siswa.xlsx', null, null, true);
}

it('previews 40 rows with 3 errors, imports 37, and offers an error report', function () {
    $rows = [['NIS*', 'Nama*', 'L/P', 'Kelas*']];
    foreach (range(1, 37) as $i) {
        $rows[] = [(string) (3000 + $i), 'Siswa '.chr(65 + intdiv($i, 26)).chr(65 + $i % 26), $i % 2 ? 'L' : 'P', $i <= 20 ? '3A' : '3b'];
    }
    $rows[] = ['', 'Tanpa NIS', 'L', '3A'];          // error: NIS empty
    $rows[] = ['3001', 'Duplikat', 'L', '3A'];       // error: duplicate NIS
    $rows[] = ['4999', 'Kelas Salah', 'P', '5C'];    // error: class not found

    $this->post('/impor/siswa/pratinjau', ['file' => xlsxUpload($rows, [['TEMPLATE IMPOR SISWA'], ['petunjuk']])])
        ->assertRedirect('/impor');

    $this->get('/impor')->assertInertia(fn (Assert $p) => $p
        ->component('Imports/Index')
        ->where('preview.summary.total', 40)
        ->where('preview.summary.new', 37)
        ->where('preview.summary.error', 3)
        ->where('preview.summary.importable', 37)
        ->where('preview.rows.37.messages.0', 'Baris 41: NIS wajib diisi.')
        ->where('preview.rows.38.messages.0', 'Baris 42: NIS 3001 muncul lebih dari sekali (baris 4 dan 42).')
        ->where('preview.rows.39.messages.0', "Baris 43: Kelas '5C' tidak ada di periode 2026/2027 • Semester 1."));

    expect(Student::count())->toBe(0); // preview writes nothing

    $report = $this->get('/impor/laporan-galat');
    $report->assertOk();
    $path = tempnam(sys_get_temp_dir(), 'rep').'.xlsx';
    file_put_contents($path, $report->streamedContent());
    expect(IOFactory::load($path)->getActiveSheet()->getHighestRow())->toBe(4); // header + 3 errors

    $this->post('/impor/simpan')->assertRedirect('/impor')->assertSessionHas('success', 'Impor selesai: 37 siswa baru, 0 diperbarui.');

    expect(Student::count())->toBe(37)
        ->and(Classroom::where('name', '3B')->first()->enrollments()->count())->toBe(17)
        ->and(Classroom::where('name', '3A')->first()->enrollments()->orderBy('roll_no')->first()->roll_no)->toBe(1);

    $this->get('/impor')->assertInertia(fn (Assert $p) => $p->where('preview', null)->where('result.created', 37));
});

it('marks updates and name warnings for existing students', function () {
    $budi = Student::create(['nis' => '3001', 'name' => 'Budi Santoso', 'gender' => null]);
    $cici = Student::create(['nis' => '3002', 'name' => 'Cici']);
    Enrollment::create(['classroom_id' => Classroom::where('name', '3A')->value('id'), 'student_id' => $cici->id, 'term_id' => $this->term->id]);

    $this->post('/impor/siswa/pratinjau', ['file' => xlsxUpload([
        ['NIS', 'Nama', 'L/P', 'Kelas'],
        ['3001', 'Budi S.', 'Laki-laki', '3A'],  // name differs → warning, gender + class updated
        ['3002', 'Cici', '', '3B'],              // moved 3A → 3B
        ['3003', 'Nama 123', 'L', '3A'],         // invalid name
        ['3004', 'Dewi', 'X', '3A'],             // invalid gender
    ])]);

    $this->get('/impor')->assertInertia(fn (Assert $p) => $p
        ->where('preview.rows.0.status', 'warning')
        ->where('preview.rows.0.name', 'Budi Santoso')
        ->where('preview.rows.0.changes', ['L/P – → L', 'Masuk kelas 3A'])
        ->where('preview.rows.1.status', 'updated')
        ->where('preview.rows.1.changes', ['Kelas 3A → 3B'])
        ->where('preview.rows.2.status', 'error')
        ->where('preview.rows.3.status', 'error'));

    $this->post('/impor/simpan')->assertSessionHas('success', 'Impor selesai: 0 siswa baru, 2 diperbarui.');

    expect($budi->fresh()->name)->toBe('Budi Santoso')->and($budi->fresh()->gender)->toBe('L')
        ->and($cici->enrollments()->first()->classroom->name)->toBe('3B');
});

it('rejects files with missing headers, too many rows, or a final class', function () {
    $this->post('/impor/siswa/pratinjau', ['file' => xlsxUpload([['Nomor', 'Siswa'], ['1', 'A']])])
        ->assertSessionHas('error', 'Kolom wajib tidak ditemukan: NIS, Nama, Kelas. Gunakan template terbaru.');

    Classroom::where('name', '5A')->update(['status' => 'final']);
    $this->post('/impor/siswa/pratinjau', ['file' => xlsxUpload([['NIS', 'Nama', 'Kelas'], ['5001', 'Kevin', '5A']])]);
    $this->get('/impor')->assertInertia(fn (Assert $p) => $p
        ->where('preview.rows.0.status', 'error')
        ->where('preview.rows.0.messages.0', 'Baris 2: Kelas 5A sudah final. Batalkan finalisasi untuk menambah siswa.'));

    $this->post('/impor/siswa/pratinjau', ['file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('file');
    $this->post('/impor/siswa/pratinjau', ['file' => UploadedFile::fake()->create('big.xlsx', 3000)])
        ->assertSessionHasErrors(['file' => 'Ukuran file melebihi batas 2 MB.']);
});

it('reads CSV with semicolons and comma decimals-free NIS', function () {
    $path = tempnam(sys_get_temp_dir(), 'csv').'.csv';
    file_put_contents($path, "NIS;Nama;L/P;Kelas\n0301;Ani;P;3A\n");

    $this->post('/impor/siswa/pratinjau', ['file' => new UploadedFile($path, 'siswa.csv', 'text/csv', null, true)]);
    $this->get('/impor')->assertInertia(fn (Assert $p) => $p->where('preview.rows.0.nis', '0301')->where('preview.rows.0.status', 'new'));
});

it('downloads the template and the student list', function () {
    $this->get('/impor/template/siswa')->assertOk()->assertDownload('template_siswa.xlsx');
    $this->get("/ekspor/siswa/{$this->term->id}")->assertOk()->assertDownload('Daftar_Siswa_2026-2027_S1.xlsx');
});
