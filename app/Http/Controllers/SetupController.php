<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Settings\SchoolSettingsController;
use App\Http\Controllers\Settings\ScoringSettingsController;
use App\Models\AcademicYear;
use App\Rules\StrongPin;
use App\Services\ActivityLogger;
use App\Services\PinService;
use App\Services\TermService;
use App\Support\PinSession;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * First-run wizard (PRD-02 §3, revisi 23-11-2026): everything the report needs — school identity,
 * Tempat, logo, principal, the first period and the band thresholds — is filled in before the
 * dashboard opens. Only reachable while no PIN exists.
 */
class SetupController extends Controller
{
    public function show(Settings $settings): Response
    {
        $y = (int) now()->format('Y') - (now()->month < 7 ? 1 : 0); // school year starts in July

        return Inertia::render('Setup/Index', [
            'defaults' => [
                'school_name' => $settings->get('school_name'),
                'city' => $settings->get('city'),
                'year' => $y.'/'.($y + 1),
                'semester' => now()->month >= 7 ? 1 : 2,
            ],
        ]);
    }

    public function store(Request $request, Settings $settings, PinService $pins, TermService $terms, ActivityLogger $logger): RedirectResponse
    {
        $data = $request->validate([
            ...SchoolSettingsController::identityRules(),
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:1024'],
            'year' => [
                'required', 'regex:/^\d{4}\/\d{4}$/',
                function ($attr, $value, $fail) {
                    [$a, $b] = array_map('intval', explode('/', $value) + [1 => 0]);
                    if ($b !== $a + 1) {
                        $fail('Tahun kedua harus satu tahun setelah tahun pertama (mis. 2026/2027).');
                    }
                },
            ],
            'semester' => ['required', 'in:1,2'],
            'report_date' => ['required', 'date'],
            'pin' => ['required', 'confirmed', new StrongPin],
        ], [
            ...SchoolSettingsController::IDENTITY_MESSAGES,
            'logo.required' => 'Unggah logo sekolah.',
            'logo.mimes' => 'Logo harus berformat PNG atau JPG.',
            'logo.max' => 'Ukuran logo maksimal 1 MB.',
            'year.required' => 'Tahun ajaran wajib diisi.',
            'year.regex' => 'Format tahun ajaran harus YYYY/YYYY, mis. 2026/2027.',
            'semester.required' => 'Pilih semester 1 atau 2.',
            'report_date.required' => 'Tanggal rapor wajib diisi.',
            'pin.required' => 'Buat PIN 4 digit.',
            'pin.confirmed' => 'Kedua PIN tidak sama. Ulangi PIN yang sama.',
        ]);
        $thresholds = ScoringSettingsController::validatedThresholds($request);

        DB::transaction(function () use ($data, $thresholds, $request, $settings, $terms) {
            $settings->set([
                'school_name' => trim($data['school_name']),
                'city' => trim($data['city']),
                'principal_name' => trim($data['principal_name']),
                'principal_id_label' => $data['principal_id_label'],
                'principal_id_number' => trim($data['principal_id_number']),
                'school_logo_path' => $request->file('logo')->store('logos', 'local'),
            ]);

            // Reuse the period if it already exists (e.g. demo data seeded before setup).
            $year = AcademicYear::query()->firstOrCreate(['name' => $data['year']]);
            $term = $year->terms()->where('semester', (int) $data['semester'])->first();
            if ($term) {
                $term->update(['report_date' => $data['report_date']]);
            } else {
                [$term] = $terms->createTerm($year, (int) $data['semester'], $data['report_date']);
            }
            if (! $term->is_active) {
                $terms->activate($term);
            }
            $term->scoringConfigs()->update($thresholds); // same thresholds for every grade; adjustable per grade later
        });

        $settings->set(['setup_completed_at' => now()->toIso8601String()]);
        $pins->setPin($data['pin']);
        $code = $pins->regenerateRecoveryCode();

        PinSession::markVerified($request);
        PinSession::putPendingRecoveryCode($request, $code);
        $logger->log('setup.completed', 'pengaturan', 'Setup awal selesai: identitas sekolah, periode, batas level, PIN dan kode pemulihan dibuat.');

        return redirect()->route('recovery-code.show', ['context' => 'setup']);
    }
}
