<?php

namespace App\Http\Controllers;

use App\Rules\StrongPin;
use App\Services\ActivityLogger;
use App\Services\PinService;
use App\Support\PinSession;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** First-run wizard (PRD-02 §3). Only reachable while no PIN exists. */
class SetupController extends Controller
{
    public function show(Settings $settings): Response
    {
        return Inertia::render('Setup/Index', [
            'defaults' => [
                'school_name' => $settings->get('school_name'),
                'program_name' => $settings->get('program_name'),
                'partner_name' => $settings->get('partner_name'),
                'city' => $settings->get('city'),
            ],
        ]);
    }

    public function store(Request $request, Settings $settings, PinService $pins, ActivityLogger $logger): RedirectResponse
    {
        $data = $request->validate([
            'school_name' => ['required', 'string', 'max:100'],
            'program_name' => ['nullable', 'string', 'max:100'],
            'partner_name' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:60'],
            'pin' => ['required', 'confirmed', new StrongPin],
        ], [
            'school_name.required' => 'Nama sekolah wajib diisi.',
            'pin.required' => 'Buat PIN 4 digit.',
            'pin.confirmed' => 'Kedua PIN tidak sama. Ulangi PIN yang sama.',
        ]);

        $settings->set([
            'school_name' => trim($data['school_name']),
            'program_name' => trim($data['program_name'] ?? '') ?: 'International Class Program',
            'partner_name' => trim($data['partner_name'] ?? ''),
            'city' => trim($data['city'] ?? ''),
            'setup_completed_at' => now()->toIso8601String(),
        ]);
        $pins->setPin($data['pin']);
        $code = $pins->regenerateRecoveryCode();

        PinSession::markVerified($request);
        PinSession::putPendingRecoveryCode($request, $code);
        $logger->log('setup.completed', 'pengaturan', 'Setup awal selesai: PIN dan kode pemulihan dibuat.');

        return redirect()->route('recovery-code.show', ['context' => 'setup']);
    }
}
