<?php

namespace Database\Seeders;

use App\Models\Descriptor;
use App\Models\LevelMeta;
use Illuminate\Database\Seeder;

/**
 * level_meta (5 rows) and descriptors (20 rows), verbatim from the Excel "Descriptors" sheet.
 * Idempotent: re-running restores the original text.
 */
class CefrReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $content = require __DIR__.'/data/cefr_content.php';

        foreach ($content['levels'] as $level => $meta) {
            LevelMeta::query()->updateOrCreate(['level' => $level], $meta);
        }

        foreach ($content['can_do'] as $level => $skills) {
            foreach ($skills as $skill => $canDo) {
                Descriptor::query()->updateOrCreate(
                    ['level' => $level, 'skill' => $skill],
                    ['can_do' => $canDo, 'next_step' => $content['next_step'][$level][$skill]],
                );
            }
        }
    }
}
