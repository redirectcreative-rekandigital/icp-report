<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Cefr\Level;
use App\Domain\Cefr\Skill;
use App\Http\Controllers\Controller;
use App\Models\Descriptor;
use App\Models\LevelMeta;
use App\Services\ActivityLogger;
use Database\Seeders\CefrReferenceSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Pengaturan → Deskriptor: report texts (English), seeded from the Excel "Descriptors" sheet. */
class DescriptorSettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Settings/Descriptors', [
            'levels' => LevelMeta::query()->orderBy('sort')->get(['level', 'short_name', 'name', 'cambridge', 'overall_comment']),
            'descriptors' => Descriptor::query()->get(['level', 'skill', 'can_do', 'next_step'])
                ->groupBy('level')
                ->map(fn ($rows) => $rows->keyBy('skill')->map->only(['can_do', 'next_step'])),
        ]);
    }

    public function update(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $rules = [];
        foreach (Level::values() as $l) {
            $rules["levels.{$l}.short_name"] = ['required', 'string', 'max:40'];
            $rules["levels.{$l}.name"] = ['required', 'string', 'max:80'];
            $rules["levels.{$l}.cambridge"] = ['required', 'string', 'max:80'];
            $rules["levels.{$l}.overall_comment"] = ['required', 'string', 'max:400'];
            foreach (Skill::values() as $s) {
                $rules["descriptors.{$l}.{$s}.can_do"] = ['required', 'string', 'max:300'];
                $rules["descriptors.{$l}.{$s}.next_step"] = ['required', 'string', 'max:300'];
            }
        }

        $data = $request->validate($rules, [
            '*.required' => 'Teks wajib diisi.',
            'levels.*.*.required' => 'Teks wajib diisi.',
            'descriptors.*.*.*.required' => 'Teks wajib diisi.',
            'descriptors.*.*.*.max' => 'Maksimal :max karakter.',
            'levels.*.*.max' => 'Maksimal :max karakter.',
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['levels'] as $level => $meta) {
                LevelMeta::query()->whereKey($level)->update(array_map('trim', $meta));
            }
            foreach ($data['descriptors'] as $level => $skills) {
                foreach ($skills as $skill => $texts) {
                    Descriptor::query()->where(['level' => $level, 'skill' => $skill])->update(array_map('trim', $texts));
                }
            }
        });

        $logger->log('settings.descriptors_updated', 'pengaturan', 'Deskriptor & komentar otomatis rapor diperbarui.');

        return back()->with('success', 'Deskriptor tersimpan. Rapor kelas yang sudah final tidak berubah.');
    }

    public function reset(ActivityLogger $logger): RedirectResponse
    {
        app(CefrReferenceSeeder::class)->run();
        $logger->log('settings.descriptors_reset', 'pengaturan', 'Deskriptor dikembalikan ke teks bawaan Excel.');

        return back()->with('success', 'Deskriptor dikembalikan ke teks bawaan dari Excel.');
    }
}
