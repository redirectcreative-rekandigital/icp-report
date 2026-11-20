<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** /dokumen — test files stored privately, downloaded through the app (PRD-03 §7, PRD-01 §6). */
class DocumentController extends Controller
{
    private const MIMES = 'pdf,doc,docx,xls,xlsx';

    public function __construct(private ActivityLogger $logger) {}

    public function index(Request $request): Response
    {
        $grade = $request->integer('grade') ?: null;

        return Inertia::render('Documents/Index', [
            'documents' => Document::query()
                ->when($grade, fn ($q) => $q->where(fn ($w) => $w->where('grade', $grade)->orWhereNull('grade')))
                ->orderBy('category')->orderBy('grade')->orderBy('title')
                ->get()
                ->map(fn (Document $d) => [
                    'id' => $d->id,
                    'title' => $d->title,
                    'grade' => $d->grade,
                    'category' => $d->category,
                    'original_name' => $d->original_name,
                    'size' => $d->size,
                    'extension' => strtolower(pathinfo($d->original_name, PATHINFO_EXTENSION)),
                    'updated_at' => $d->updated_at->toIso8601String(),
                ]),
            'categories' => Document::CATEGORIES,
            'filters' => ['grade' => $grade],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, fileRequired: true);
        $file = $request->file('file');

        $doc = Document::query()->create([
            ...$data,
            'file_path' => $file->store('documents', 'local'),
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime' => $file->getMimeType() ?? 'application/octet-stream',
        ]);
        $this->logger->log('document.uploaded', 'data', "Dokumen \"{$doc->title}\" diunggah.", $doc);

        return back()->with('success', "Dokumen \"{$doc->title}\" diunggah.");
    }

    public function update(Request $request, Document $document): RedirectResponse
    {
        $data = $this->validated($request, fileRequired: false);

        if ($file = $request->file('file')) {
            Storage::disk('local')->delete($document->file_path);
            $data += [
                'file_path' => $file->store('documents', 'local'),
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime' => $file->getMimeType() ?? 'application/octet-stream',
            ];
        }

        $document->update($data);
        $this->logger->log('document.updated', 'data', "Dokumen \"{$document->title}\" diperbarui".($file ? ' (file diganti).' : '.'), $document);

        return back()->with('success', 'Dokumen tersimpan.');
    }

    public function destroy(Document $document): RedirectResponse
    {
        Storage::disk('local')->delete($document->file_path);
        $document->delete();
        $this->logger->log('document.deleted', 'data', "Dokumen \"{$document->title}\" dihapus.");

        return back()->with('success', "Dokumen \"{$document->title}\" dihapus.");
    }

    public function download(Document $document): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->original_name, ['Cache-Control' => 'private, no-store']);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $fileRequired): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'grade' => ['nullable', Rule::in([3, 4, 5, 6])],
            'category' => ['required', Rule::in(array_keys(Document::CATEGORIES))],
            'file' => [$fileRequired ? 'required' : 'nullable', 'file', 'mimes:'.self::MIMES, 'max:10240'],
        ], [
            'title.required' => 'Judul wajib diisi.',
            'file.required' => 'Pilih file.',
            'file.mimes' => 'Format file harus PDF, Word (.doc/.docx), atau Excel (.xls/.xlsx).',
            'file.max' => 'Ukuran file maksimal 10 MB.',
        ]);
    }
}
