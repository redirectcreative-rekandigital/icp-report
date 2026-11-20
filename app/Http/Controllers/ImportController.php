<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Models\Classroom;
use App\Models\Term;
use App\Services\LegacyExcelImportService;
use App\Services\RecapExportService;
use App\Services\ScoreImportService;
use App\Services\SpreadsheetExportService;
use App\Services\StudentImportService;
use App\Support\PinSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * /impor — two-step imports (PRD-03 §5.2): upload → preview (nothing saved) → commit.
 * The parsed rows live in the cache for 30 minutes under a random token kept in the session;
 * commit re-validates them against the current database.
 *   siswa      : student list → classes of the active period
 *   nilai      : template_nilai_{kelas}.xlsx → scores of one class
 *   excel-lama : the old ICP_English_Progress_Report.xlsx (sheets Grade 3–6)
 */
class ImportController extends Controller
{
    private const SESSION_KEY = 'import.pending.token';

    private const TTL = 1800;

    public function __construct(
        private StudentImportService $students,
        private ScoreImportService $scores,
        private LegacyExcelImportService $legacy,
        private SpreadsheetExportService $sheets,
    ) {}

    public function index(Request $request): Response
    {
        $term = Term::active();
        $pending = $this->pending($request);

        return Inertia::render('Imports/Index', [
            'term' => $term ? ['id' => $term->id, 'label' => $term->fullLabel(), 'classes' => $term->classrooms()->count()] : null,
            'classes' => $term ? $term->classrooms()->orderBy('grade')->orderBy('name')->get()
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'final' => $c->isFinal()]) : [],
            'preview' => $pending && $term ? $this->previewPayload($pending, $term) : null,
            'result' => session('import_result'),
        ]);
    }

    /* ---------------- templates ---------------- */

    public function studentTemplate(): StreamedResponse
    {
        return $this->sheets->studentTemplate(Term::active()?->load('academicYear'));
    }

    public function scoreTemplate(Classroom $classroom, RecapExportService $recaps): StreamedResponse
    {
        return $recaps->scoreTemplate($classroom);
    }

    /* ---------------- previews ---------------- */

    public function previewStudents(Request $request): RedirectResponse
    {
        $file = $this->validatedFile($request);
        $this->activeTerm();
        $rows = $this->students->read($file->getRealPath(), $file->getClientOriginalExtension());

        return $this->store($request, ['type' => 'siswa', 'filename' => $file->getClientOriginalName(), 'rows' => $rows]);
    }

    public function previewScores(Request $request): RedirectResponse
    {
        $file = $this->validatedFile($request, ['classroom_id' => ['required', 'integer', 'exists:classrooms,id'], 'overwrite' => ['boolean']]);
        $classroom = Classroom::query()->findOrFail($request->integer('classroom_id'));

        if ($classroom->isFinal()) {
            throw new BusinessRuleException("Kelas {$classroom->name} sudah final. Batalkan finalisasi untuk mengimpor nilai.", 'CLASS_FINAL', 423);
        }

        return $this->store($request, [
            'type' => 'nilai',
            'filename' => $file->getClientOriginalName(),
            'rows' => $this->scores->read($file->getRealPath(), $file->getClientOriginalExtension()),
            'classroom_id' => $classroom->id,
            'overwrite' => $request->boolean('overwrite'),
        ]);
    }

    public function previewLegacy(Request $request): RedirectResponse
    {
        $file = $this->validatedFile($request, ['overwrite' => ['boolean']], ['xlsx', 'xls'], 5120);
        $this->activeTerm();

        return $this->store($request, [
            'type' => 'excel-lama',
            'filename' => $file->getClientOriginalName(),
            'rows' => $this->legacy->read($file->getRealPath()),
            'overwrite' => $request->boolean('overwrite'),
        ]);
    }

    /* ---------------- commit / cancel / report ---------------- */

    public function commit(Request $request): RedirectResponse|JsonResponse
    {
        $p = $this->pending($request) ?? throw new BusinessRuleException('Pratinjau impor sudah kedaluwarsa. Unggah ulang file.');
        $term = $this->activeTerm();

        // Replacing existing scores is a sensitive action (PRD-02 §6).
        if (($p['overwrite'] ?? false) && ! PinSession::recentlyConfirmed($request)) {
            return $request->header('X-Inertia')
                ? back()->with('pin_confirm_required', true)->withErrors(['pin' => 'Masukkan PIN untuk menimpa nilai.'])
                : response()->json(['message' => 'Masukkan PIN untuk menimpa nilai.', 'code' => 'PIN_CONFIRM_REQUIRED'], 428);
        }

        if ($p['type'] === 'siswa') {
            $r = $this->students->commit($p['rows'], $term);
            $message = "Impor selesai: {$r['created']} siswa baru, {$r['updated']} diperbarui.";
        } elseif ($p['type'] === 'nilai') {
            $classroom = Classroom::query()->findOrFail($p['classroom_id']);
            $r = $this->scores->commit($p['rows'], $classroom, $p['overwrite']) + ['class_id' => $classroom->id];
            $message = "Impor nilai {$classroom->name} selesai: {$r['values']} nilai pada {$r['students']} siswa.";
        } else {
            $r = $this->legacy->commit($p['rows'], $term, $p['overwrite']);
            $message = "Impor Excel lama selesai: {$r['students']} siswa ({$r['created']} baru), {$r['values']} nilai.";
        }

        $this->forget($request);

        return redirect()->route('imports.index')
            ->with('success', $message)
            ->with('import_result', ['type' => $p['type'], 'message' => $message, 'term' => $term->fullLabel()] + $r);
    }

    public function cancel(Request $request): RedirectResponse
    {
        $this->forget($request);

        return redirect()->route('imports.index');
    }

    public function errorReport(Request $request): StreamedResponse
    {
        $p = $this->pending($request) ?? abort(404);
        $payload = $this->previewPayload($p, $this->activeTerm());
        $name = pathinfo($p['filename'], PATHINFO_FILENAME);

        return $this->sheets->importErrorReport($payload['rows'], "Laporan_Galat_{$name}.xlsx");
    }

    /* ---------------- helpers ---------------- */

    private function previewPayload(array $p, Term $term): array
    {
        $analysis = match ($p['type']) {
            'siswa' => $this->students->analyse($p['rows'], $term),
            'nilai' => $this->scores->analyse($p['rows'], Classroom::query()->findOrFail($p['classroom_id']), $p['overwrite']),
            'excel-lama' => $this->legacy->analyse($p['rows'], $term, $p['overwrite']),
        };

        return [
            'type' => $p['type'],
            'filename' => $p['filename'],
            'overwrite' => (bool) ($p['overwrite'] ?? false),
            'class' => isset($p['classroom_id']) ? Classroom::query()->find($p['classroom_id'])?->name : null,
            // Rows are only displayed: drop the internal write payload.
            'rows' => array_map(fn ($r) => array_diff_key($r, ['set' => 1, 'create' => 1, 'enrollment_id' => 1]), $analysis['rows']),
            'summary' => $analysis['summary'],
        ];
    }

    private function validatedFile(Request $request, array $extra = [], array $mimes = ['xlsx', 'xls', 'csv', 'txt'], int $maxKb = 2048)
    {
        $shown = implode(', ', array_map(fn ($m) => ".{$m}", array_diff($mimes, ['txt'])));

        $request->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', $mimes), "max:{$maxKb}"],
            ...$extra,
        ], [
            'file.required' => 'Pilih file Excel atau CSV.',
            'file.mimes' => "Format file harus {$shown}.",
            'file.max' => 'Ukuran file melebihi batas '.($maxKb / 1024).' MB.',
            'classroom_id.required' => 'Pilih kelas.',
        ]);

        return $request->file('file');
    }

    private function activeTerm(): Term
    {
        return Term::active() ?? throw new BusinessRuleException('Belum ada periode aktif. Buat periode dan kelas terlebih dahulu.');
    }

    private function store(Request $request, array $payload): RedirectResponse
    {
        if ($payload['rows'] === []) {
            throw new BusinessRuleException('File tidak berisi data di bawah baris judul kolom.');
        }

        $this->forget($request);
        $token = Str::random(32);
        Cache::put("import:{$token}", $payload, self::TTL);
        $request->session()->put(self::SESSION_KEY, $token);

        return redirect()->route('imports.index');
    }

    private function pending(Request $request): ?array
    {
        $token = $request->session()->get(self::SESSION_KEY);

        return $token ? Cache::get("import:{$token}") : null;
    }

    private function forget(Request $request): void
    {
        if ($token = $request->session()->pull(self::SESSION_KEY)) {
            Cache::forget("import:{$token}");
        }
    }
}
