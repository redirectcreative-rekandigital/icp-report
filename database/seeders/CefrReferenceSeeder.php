<?php

namespace Database\Seeders;

use App\Models\Descriptor;
use App\Models\LevelMeta;
use App\Support\Settings;
use Illuminate\Database\Seeder;

/**
 * level_meta (5 CEFR rows, for the grade 6 National Geographic map), band descriptors
 * (3 bands × 4 skills) and band comments. Texts are verbatim from the Excel "Descriptors"
 * sheet, mapped via `band_source`. Idempotent: re-running restores the original text.
 */
class CefrReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $content = require __DIR__.'/data/cefr_content.php';

        foreach ($content['levels'] as $level => $meta) {
            LevelMeta::query()->updateOrCreate(['level' => $level], collect($meta)->except('overall_comment')->all());
        }

        Descriptor::query()->whereNotIn('level', array_keys($content['band_source']))->delete();
        $comments = [];

        foreach ($content['band_source'] as $band => $level) {
            foreach ($content['can_do'][$level] as $skill => $canDo) {
                Descriptor::query()->updateOrCreate(
                    ['level' => $band, 'skill' => $skill],
                    ['can_do' => $canDo, 'next_step' => $content['next_step'][$level][$skill]],
                );
            }
            $comments[$band] = $content['levels'][$level]['overall_comment'];
        }

        app(Settings::class)->set(['band_comments' => $comments]);
    }
}
