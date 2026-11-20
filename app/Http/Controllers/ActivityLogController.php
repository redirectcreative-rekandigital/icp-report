<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** /log — activity log (PRD-02 §9). Class filter arrives with classrooms in M2. */
class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $logs = $this->query($filters)
            ->paginate(50)
            ->withQueryString()
            ->through(fn (ActivityLog $log) => [
                'id' => $log->id,
                'created_at' => $log->created_at->toIso8601String(),
                'action' => $log->action,
                'category' => $log->category,
                'description' => $log->description,
                'device' => $log->device,
                'ip' => $log->ip,
                'session_short' => $log->session_short,
            ]);

        return Inertia::render('ActivityLog/Index', [
            'logs' => $logs,
            'filters' => $filters,
            'categories' => ActivityLog::CATEGORIES,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $filename = 'Log_Aktivitas_'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($filters) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows characters correctly
            fputcsv($out, ['Waktu', 'Kategori', 'Aksi', 'Deskripsi', 'Perangkat', 'IP', 'Sesi'], ';');

            $this->query($filters)->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $log) {
                    fputcsv($out, [
                        $log->created_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
                        ActivityLog::CATEGORIES[$log->category] ?? $log->category,
                        $log->action,
                        $log->description,
                        $log->device,
                        $log->ip,
                        $log->session_short,
                    ], ';');
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{from: ?string, to: ?string, category: ?string, q: ?string} */
    private function filters(Request $request): array
    {
        $valid = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'category' => ['nullable', Rule::in(array_keys(ActivityLog::CATEGORIES))],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return [
            'from' => $valid['from'] ?? null,
            'to' => $valid['to'] ?? null,
            'category' => $valid['category'] ?? null,
            'q' => $valid['q'] ?? null,
        ];
    }

    private function query(array $f): Builder
    {
        return ActivityLog::query()
            ->when($f['from'], fn ($q, $d) => $q->where('created_at', '>=', $d.' 00:00:00'))
            ->when($f['to'], fn ($q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'))
            ->when($f['category'], fn ($q, $c) => $q->where('category', $c))
            ->when($f['q'], fn ($q, $s) => $q->where('description', 'like', '%'.addcslashes($s, '%_\\').'%'))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
