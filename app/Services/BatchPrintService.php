<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\PrintBatch;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Throwable;
use ZipArchive;

/**
 * "Unduh/Cetak semua" without a queue worker (PRD-05 §7.2): the browser calls next()
 * repeatedly; every call renders at most CHUNK reports or stops after TIME_BUDGET seconds,
 * so no request comes near a 30 s shared-hosting limit. next() is idempotent (files on disk
 * are the progress), so a closed tab can resume later.
 */
class BatchPrintService
{
    public const CHUNK = 5;

    public const TIME_BUDGET = 20.0;

    public const MAX_REPORTS = 45;

    public const MAX_ATTEMPTS = 3;

    public const TTL_HOURS = 24;

    public function __construct(
        private ReportDataBuilder $builder,
        private PdfReportService $pdf,
        private ActivityLogger $logger,
    ) {}

    /** Enrollments whose report can be produced (complete; active students only), in roll order. */
    public function printable(Classroom $classroom): array
    {
        return $classroom->enrollments()->with(['student', 'score'])->get()
            ->filter(fn (Enrollment $e) => $e->student->is_active && $e->score
                && $e->score->listening !== null && $e->score->reading !== null
                && $e->score->writing !== null && $e->score->speaking !== null)
            ->pluck('id')->values()->all();
    }

    /** @param list<int>|null $ids */
    public function start(Classroom $classroom, string $paper, string $mode, ?array $ids): PrintBatch
    {
        $running = PrintBatch::query()
            ->where('classroom_id', $classroom->id)
            ->where('status', 'running')
            ->where('expires_at', '>', now())
            ->first();

        if ($running) {
            return $running; // one running batch per class: continue it
        }

        $printable = $this->printable($classroom);
        $ids = $ids === null ? $printable : array_values(array_intersect($printable, array_map('intval', $ids)));

        if ($ids === []) {
            throw new BusinessRuleException('Tidak ada rapor yang bisa dibuat: nilai siswa belum lengkap.');
        }

        if (count($ids) > self::MAX_REPORTS) {
            throw new BusinessRuleException('Maksimal '.self::MAX_REPORTS.' rapor dalam satu proses. Pilih sebagian siswa.');
        }

        $batch = PrintBatch::query()->create([
            'classroom_id' => $classroom->id,
            'paper' => $paper,
            'mode' => $mode,
            'enrollment_ids' => $ids,
            'total' => count($ids),
            'expires_at' => now()->addHours(self::TTL_HOURS),
        ]);

        $this->logger->log('report.batch_started', 'data', "Membuat {$batch->total} rapor PDF kelas {$classroom->name} (".strtoupper($paper).').', $classroom);

        return $batch;
    }

    /** @return array<string, mixed> progress state */
    public function next(PrintBatch $batch): array
    {
        if ($batch->status !== 'running') {
            return $batch->state();
        }

        $disk = Storage::disk('local');
        $started = microtime(true);
        $rendered = 0;
        $current = null;
        $failed = $batch->failed ?? [];
        $enrollments = Enrollment::query()->with('student')->whereIn('id', $batch->enrollment_ids)->get()->keyBy('id');

        foreach ($batch->enrollment_ids as $i => $id) {
            $file = $batch->dir().'/'.str_pad((string) $i, 3, '0', STR_PAD_LEFT).'.pdf';

            if ($disk->exists($file) || ($failed[$id]['attempts'] ?? 0) >= self::MAX_ATTEMPTS) {
                continue;
            }

            if ($rendered >= self::CHUNK || microtime(true) - $started > self::TIME_BUDGET) {
                break;
            }

            $e = $enrollments[$id] ?? null;
            $current = $e?->student->name;

            try {
                $data = $this->builder->build($e, $batch->paper);
                $disk->put($file, $this->pdf->render($data));
                unset($failed[$id]);
            } catch (Throwable $ex) {
                report($ex);
                $failed[$id] = [
                    'name' => $e?->student->name ?? "#{$id}",
                    'attempts' => ($failed[$id]['attempts'] ?? 0) + 1,
                    'error' => $ex instanceof BusinessRuleException ? $ex->getMessage() : 'Gagal membuat PDF (kode '.app('error.ref').').',
                ];
            }

            $rendered++;
        }

        $done = count($disk->files($batch->dir()));
        $gaveUp = collect($failed)->filter(fn ($f) => $f['attempts'] >= self::MAX_ATTEMPTS)->count();
        $batch->update(['done' => $done, 'failed' => $failed ?: null]);

        if ($done + $gaveUp >= $batch->total) {
            $this->assemble($batch);
        }

        return $batch->fresh()->state($current);
    }

    /** Merge into one PDF (FPDI) or zip one PDF per student. */
    private function assemble(PrintBatch $batch): void
    {
        $disk = Storage::disk('local');
        $files = collect($disk->files($batch->dir()))->filter(fn ($f) => str_ends_with($f, '.pdf'))->sort()->values();

        if ($files->isEmpty()) {
            $batch->update(['status' => 'failed']);

            return;
        }

        $classroom = $batch->classroom()->with('term.academicYear')->first();
        $slug = $classroom->name.'_'.$classroom->term->fileSlug().'_'.strtoupper($batch->paper);
        $out = "batches/{$batch->id}-result";

        if ($batch->mode === 'zip') {
            $zip = new ZipArchive;
            $path = $disk->path("{$out}.zip");
            $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $names = Enrollment::query()->with(['student', 'classroom.term.academicYear'])->whereIn('id', $batch->enrollment_ids)->get()->keyBy('id');

            foreach ($files as $f) {
                $i = (int) basename($f, '.pdf');
                $e = $names[$batch->enrollment_ids[$i]];
                $zip->addFile($disk->path($f), ReportDataBuilder::filename([
                    'student' => ['nis' => $e->student->nis, 'name' => $e->student->name],
                    'term' => ['year' => $e->classroom->term->academicYear->name, 'label' => $e->classroom->term->label],
                    'paper' => $batch->paper,
                ]));
            }

            $zip->close();
            $batch->update(['status' => 'completed', 'file_path' => "{$out}.zip", 'filename' => "Rapor_{$slug}.zip"]);
        } else {
            $fpdi = new Fpdi;
            foreach ($files as $f) {
                $pages = $fpdi->setSourceFile($disk->path($f));
                for ($p = 1; $p <= $pages; $p++) {
                    $tpl = $fpdi->importPage($p);
                    $size = $fpdi->getTemplateSize($tpl);
                    $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $fpdi->useTemplate($tpl);
                }
            }
            $disk->put("{$out}.pdf", $fpdi->Output('S'));
            $batch->update(['status' => 'completed', 'file_path' => "{$out}.pdf", 'filename' => "Rapor_{$slug}.pdf"]);
        }

        $disk->deleteDirectory($batch->dir());
    }

    public function cancel(PrintBatch $batch): void
    {
        $batch->update(['status' => 'cancelled']);
        Storage::disk('local')->deleteDirectory($batch->dir());
    }

    /** Removes expired batches and their files (Housekeeping). */
    public function purgeExpired(): void
    {
        $disk = Storage::disk('local');

        PrintBatch::query()->where('expires_at', '<', now())->get()->each(function (PrintBatch $b) use ($disk) {
            $disk->deleteDirectory($b->dir());
            if ($b->file_path) {
                $disk->delete($b->file_path);
            }
            $b->delete();
        });
    }
}
