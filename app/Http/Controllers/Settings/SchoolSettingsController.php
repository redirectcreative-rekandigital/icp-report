<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Pengaturan → Sekolah: school identity & logos. */
class SchoolSettingsController extends Controller
{
    public const FIELDS = [
        'school_name', 'program_name', 'city',
        'principal_name', 'principal_id_label', 'principal_id_number', 'show_documents_menu',
    ];

    /** Logo slot → settings key. The National Geographic logo is bundled with the app. */
    public const LOGO_SLOTS = [
        'school' => 'school_logo_path',
    ];

    public function __construct(private Settings $settings, private ActivityLogger $logger) {}

    public function edit(): Response
    {
        $values = collect(self::FIELDS)->mapWithKeys(fn ($k) => [$k => $this->settings->get($k)])->all();

        $logos = collect(self::LOGO_SLOTS)->map(fn ($key, $slot) => $this->settings->get($key)
            ? route('media.logo', ['slot' => $slot, 'v' => substr(md5((string) $this->settings->get($key)), 0, 8)])
            : null);

        return Inertia::render('Settings/School', [
            'values' => $values,
            'logos' => $logos,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'program_name' => ['required', 'string', 'max:100'],
            ...self::identityRules(),
            'show_documents_menu' => ['boolean'],
        ], [
            'program_name.required' => 'Nama program wajib diisi.',
            ...self::IDENTITY_MESSAGES,
        ]);

        $data = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data);
        $before = collect(self::FIELDS)->mapWithKeys(fn ($k) => [$k => $this->settings->get($k)])->all();
        $this->settings->set($data);

        $changed = array_keys(array_diff_assoc(
            array_map('strval', $data),
            array_map('strval', array_intersect_key($before, $data)),
        ));

        if ($changed) {
            $this->logger->log('settings.school_updated', 'pengaturan', 'Identitas sekolah diperbarui ('.implode(', ', $changed).').');
        }

        return back()->with('success', 'Identitas sekolah tersimpan.');
    }

    public function uploadLogo(Request $request, string $slot): RedirectResponse
    {
        abort_unless(isset(self::LOGO_SLOTS[$slot]), 404);

        $request->validate([
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:1024'],
        ], [
            'logo.required' => 'Pilih file logo.',
            'logo.mimes' => 'Logo harus berformat PNG atau JPG.',
            'logo.max' => 'Ukuran logo maksimal 1 MB.',
        ]);

        $key = self::LOGO_SLOTS[$slot];
        $old = $this->settings->get($key);
        $path = $request->file('logo')->store('logos', 'local');
        $this->settings->set([$key => $path]);

        if ($old && $old !== $path) {
            Storage::disk('local')->delete($old);
        }

        $this->logger->log('settings.logo_uploaded', 'pengaturan', 'Logo diunggah: '.self::label($slot).'.');

        return back()->with('success', self::label($slot).' tersimpan.');
    }

    public static function label(string $slot): string
    {
        return 'Logo sekolah';
    }

    /** School identity required on the report (shared with the setup wizard). */
    public static function identityRules(): array
    {
        return [
            'school_name' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:60'],
            'principal_name' => ['required', 'string', 'max:100'],
            'principal_id_label' => ['required', Rule::in(['NBM', 'NIP'])],
            'principal_id_number' => ['required', 'string', 'max:30'],
        ];
    }

    public const IDENTITY_MESSAGES = [
        'school_name.required' => 'Nama sekolah wajib diisi.',
        'city.required' => 'Tempat wajib diisi (mis. Wonorejo).',
        'principal_name.required' => 'Nama kepala sekolah wajib diisi.',
        'principal_id_number.required' => 'NBM/NIP kepala sekolah wajib diisi.',
        '*.max' => 'Teks terlalu panjang (maksimal :max karakter).',
    ];
}
