<?php

namespace App\Http\Controllers;

use App\Domain\Cefr\CefrCalculator;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\FinalResult;
use App\Models\PrintBatch;
use App\Services\ActivityLogger;
use App\Services\BatchPrintService;
use App\Services\PdfReportService;
use App\Services\ReportDataBuilder;
use App\Services\ScoreService;
use App\Support\ReportLayout;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Tab "Rapor & Cetak", preview, PDF per student and batch PDFs (PRD-05 §3–4, §7). */
class ReportController extends Controller
{
    public function __construct(
        private ReportDataBuilder $builder,
        private PdfReportService $pdf,
        private Settings $settings,
    ) {}

    /** Extra props for the Rapor tab (merged into ClassController::tab). */
    public static function tabProps(Classroom $classroom): array
    {
        $scores = app(ScoreService::class);
        $config = $scores->config($classroom);
        $finals = $classroom->isFinal()
            ? FinalResult::query()->where('classroom_id', $classroom->id)->where('is_current', true)->get()->keyBy('enrollment_id')
            : collect();

        $students = $classroom->enrollments()->with(['student', 'score'])->get()->map(function (Enrollment $e) use ($config, $scores, $finals) {
            $final = $finals->get($e->id);
            $r = $final ? null : CefrCalculator::calculate($config, $scores->scorePayload($e->score)['scores']);

            return [
                'enrollment_id' => $e->id,
                'roll_no' => $e->roll_no,
                'name' => $e->student->name,
                'nis' => $e->student->nis,
                'is_active' => $e->student->is_active,
                'overall_level' => $final?->overall_level ?? $r?->overallLevel,
                'total' => $final ? $final->total : $r?->total,
                'printable' => $e->student->is_active && ($final !== null || ($r && $r->isComplete())),
            ];
        });

        $layout = ReportLayout::normalize(app(Settings::class)->get('report_layout'));
        $running = PrintBatch::query()
            ->where('classroom_id', $classroom->id)->where('status', 'running')->where('expires_at', '>', now())
            ->latest('created_at')->first();

        return [
            'students' => $students,
            'paperDefault' => $layout['paper_default'],
            'runningBatch' => $running?->state(),
        ];
    }

    public function preview(Request $request, Classroom $classroom, Enrollment $enrollment): InertiaResponse
    {
        abort_unless($enrollment->classroom_id === $classroom->id, 404);
        $classroom->load('term.academicYear');

        $printable = collect(self::tabProps($classroom)['students'])->where('printable', true)->values();
        $idx = $printable->search(fn ($s) => $s['enrollment_id'] === $enrollment->id);
        $snapshotAt = $classroom->isFinal()
            ? FinalResult::query()->where('enrollment_id', $enrollment->id)->where('is_current', true)->first()?->finalization?->created_at
            : null;

        return Inertia::render('Reports/Preview', [
            'classroom' => ['id' => $classroom->id, 'name' => $classroom->name, 'status' => $classroom->status, 'term' => $classroom->term->fullLabel()],
            'student' => ['enrollment_id' => $enrollment->id, 'name' => $enrollment->student->name, 'nis' => $enrollment->student->nis],
            'printable' => $idx !== false,
            'prevId' => $idx !== false && $idx > 0 ? $printable[$idx - 1]['enrollment_id'] : null,
            'nextId' => $idx !== false && $idx < $printable->count() - 1 ? $printable[$idx + 1]['enrollment_id'] : null,
            'position' => $idx !== false ? $idx + 1 : null,
            'count' => $printable->count(),
            'paperDefault' => ReportLayout::normalize($this->settings->get('report_layout'))['paper_default'],
            'snapshotAt' => $snapshotAt?->toIso8601String(),
        ]);
    }

    /** The report as HTML (same Blade as the PDF), shown in the preview iframe. */
    public function html(Request $request, Classroom $classroom, Enrollment $enrollment): Response
    {
        abort_unless($enrollment->classroom_id === $classroom->id, 404);
        $data = $this->builder->build($enrollment, $this->paper($request), forPreview: true);

        return response($this->pdf->html($data));
    }

    public function pdf(Request $request, Classroom $classroom, Enrollment $enrollment, ActivityLogger $logger): Response
    {
        abort_unless($enrollment->classroom_id === $classroom->id, 404);
        $data = $this->builder->build($enrollment, $this->paper($request));
        $filename = ReportDataBuilder::filename($data);
        $inline = $request->boolean('inline');

        $logger->log('report.pdf', 'data', "Rapor PDF {$enrollment->student->name} ({$classroom->name}) ".($inline ? 'dibuka untuk cetak' : 'diunduh').'.', $enrollment);

        return response($this->pdf->render($data), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function startBatch(Request $request, Classroom $classroom, BatchPrintService $batches): JsonResponse
    {
        $data = $request->validate([
            'paper' => ['required', Rule::in(['f4', 'a4'])],
            'mode' => ['required', Rule::in(['merged', 'zip'])],
            'enrollment_ids' => ['nullable', 'array'],
            'enrollment_ids.*' => ['integer'],
        ]);

        $batch = $batches->start($classroom, $data['paper'], $data['mode'], $data['enrollment_ids'] ?? null);

        return response()->json($batch->state());
    }

    public function nextBatch(PrintBatch $batch, BatchPrintService $batches): JsonResponse
    {
        abort_if($batch->expires_at?->isPast(), 410, 'Proses sudah kedaluwarsa. Mulai ulang.');
        @set_time_limit(60);

        return response()->json($batches->next($batch));
    }

    public function retryFailed(PrintBatch $batch): JsonResponse
    {
        // Give students that failed 3× another 3 attempts.
        $batch->update(['failed' => null, 'status' => $batch->status === 'completed' ? 'completed' : 'running']);

        return response()->json($batch->state());
    }

    public function downloadBatch(Request $request, PrintBatch $batch): StreamedResponse
    {
        abort_unless($batch->status === 'completed' && $batch->file_path && $batch->expires_at?->isFuture(), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($batch->file_path), 404);

        $inline = $request->boolean('inline') && str_ends_with($batch->file_path, '.pdf');

        return $disk->response($batch->file_path, $batch->filename, [
            'Content-Type' => str_ends_with($batch->file_path, '.zip') ? 'application/zip' : 'application/pdf',
            'Cache-Control' => 'private, no-store',
        ], $inline ? 'inline' : 'attachment');
    }

    public function cancelBatch(PrintBatch $batch, BatchPrintService $batches): JsonResponse
    {
        $batches->cancel($batch);

        return response()->json(['ok' => true]);
    }

    private function paper(Request $request): string
    {
        $paper = $request->query('paper', ReportLayout::normalize($this->settings->get('report_layout'))['paper_default']);

        return in_array($paper, ['f4', 'a4'], true) ? $paper : 'a4';
    }
}
