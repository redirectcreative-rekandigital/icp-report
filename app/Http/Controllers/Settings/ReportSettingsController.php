<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Term;
use App\Services\ActivityLogger;
use App\Services\PdfReportService;
use App\Services\ReportDataBuilder;
use App\Support\ReportLayout;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Pengaturan → Rapor: layout editor with live preview (PRD-05 §5). */
class ReportSettingsController extends Controller
{
    public function __construct(private Settings $settings) {}

    public function edit(): Response
    {
        return Inertia::render('Settings/Report', [
            'layout' => ReportLayout::normalize($this->settings->get('report_layout')),
            'presets' => ReportLayout::PRESETS,
            'themes' => ReportLayout::THEMES,
            'roles' => ReportLayout::ROLES,
            'logos' => [
                'school' => (bool) $this->settings->get('school_logo_path'),
                'second' => (bool) $this->settings->get('second_logo_path'),
                'partner' => (bool) $this->settings->get('partner_logo_path'),
            ],
            'sampleStudents' => $this->sampleStudents(),
        ]);
    }

    public function update(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $layout = $this->validated($request);
        $this->settings->set(['report_layout' => $layout]);
        $logger->log('settings.report_layout', 'pengaturan', 'Pengaturan layout rapor diubah (preset '.$layout['preset'].', kertas '.strtoupper($layout['paper_default']).').');

        return back()->with('success', 'Pengaturan rapor tersimpan. Rapor kelas yang sudah final tetap memakai layout saat finalisasi.');
    }

    public function reset(ActivityLogger $logger): RedirectResponse
    {
        $this->settings->set(['report_layout' => ReportLayout::defaults()]);
        $logger->log('settings.report_layout_reset', 'pengaturan', 'Layout rapor dikembalikan ke bawaan.');

        return back()->with('success', 'Layout rapor dikembalikan ke bawaan.');
    }

    /** Live preview: HTML of the report with the unsaved layout. */
    public function preview(Request $request, ReportDataBuilder $builder, PdfReportService $pdf): JsonResponse
    {
        $layout = $this->validated($request);
        $paper = $request->input('paper') === 'a4' ? 'a4' : 'f4';
        $enrollment = $request->integer('enrollment_id') ? Enrollment::query()->find($request->integer('enrollment_id')) : null;

        $data = $enrollment && ! $enrollment->classroom->isFinal()
            ? $builder->build($enrollment, $paper, forPreview: true, layoutOverride: $layout)
            : $builder->sample($layout, $paper);

        return response()->json(['html' => $pdf->html($data)]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'preset' => ['required', Rule::in(['lengkap', 'ringkas', 'minimal', 'kustom'])],
            'paper_default' => ['required', Rule::in(['f4', 'a4'])],
            'margin_mm' => ['required', 'integer', 'min:8', 'max:20'],
            'theme.preset' => ['required', Rule::in(['navy', 'hijau', 'kustom'])],
            'theme.primary' => ['required_if:theme.preset,kustom', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.accent' => ['required_if:theme.preset,kustom', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'header.extra_line' => ['nullable', 'string', 'max:80'],
            'header.show_muhammadiyah_logo' => ['boolean'],
            'header.title' => ['required', 'string', 'max:60'],
            'sections' => ['required', 'array'],
            'sections.*' => ['boolean'],
            'signatures' => ['required', 'array', 'min:2', 'max:4'],
            'signatures.*.role' => ['required', 'distinct', Rule::in(array_keys(ReportLayout::ROLES))],
            'signatures.*.label' => ['required', 'string', 'max:40'],
            'id_label' => ['required', Rule::in(['NBM', 'NIP', 'none'])],
        ], [
            'header.title.required' => 'Judul rapor wajib diisi.',
            'header.title.max' => 'Judul rapor maksimal 60 karakter.',
            'header.extra_line.max' => 'Baris kop tambahan maksimal 80 karakter.',
            'signatures.min' => 'Pilih minimal 2 penanda tangan.',
            'signatures.max' => 'Maksimal 4 penanda tangan.',
            'signatures.*.role.distinct' => 'Penanda tangan tidak boleh ganda.',
            'signatures.*.label.required' => 'Label jabatan wajib diisi.',
            'theme.primary.regex' => 'Warna harus berformat #RRGGBB.',
            'theme.accent.regex' => 'Warna harus berformat #RRGGBB.',
        ]);

        if (($data['theme']['preset'] ?? null) === 'kustom' && ReportLayout::contrastWithWhite($data['theme']['primary']) < 4.5) {
            throw ValidationException::withMessages([
                'theme.primary' => 'Kontras warna utama dengan teks putih kurang dari 4,5 : 1. Pilih warna yang lebih gelap.',
            ]);
        }

        return ReportLayout::normalize($data);
    }

    /** A few complete students of the active period to preview with real data. */
    private function sampleStudents(): array
    {
        $term = Term::active();

        if (! $term) {
            return [];
        }

        return Enrollment::query()
            ->with(['student', 'classroom'])
            ->where('term_id', $term->id)
            ->whereHas('classroom', fn ($q) => $q->where('status', 'draft'))
            ->whereHas('score', fn ($q) => $q->complete())
            ->limit(30)->get()
            ->map(fn ($e) => ['id' => $e->id, 'label' => "{$e->student->name} ({$e->classroom->name})"])
            ->all();
    }
}
