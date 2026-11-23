<?php

namespace App\Support;

use App\Domain\Cefr\Level;
use App\Domain\Cefr\ScoringConfig;
use App\Models\Score;

/**
 * Compares file values with stored scores (PRD-03 §5.2–5.3):
 * blank = keep; "-" = clear (only with "Timpa"); existing different values are only replaced
 * with "Timpa"; numbers accept "12,5"/"12.5", must be 5–25 and whole or .5;
 * natgeo (grade 6 only) must be a National Geographic sub-level: Pre-A1, A1.1 … B2.3.
 */
class ScoreDiff
{
    public const FIELDS = ['listening', 'reading', 'writing', 'speaking', 'natgeo', 'comment'];

    private const COLUMNS = ['comment' => 'teacher_comment', 'natgeo' => 'natgeo_level'];

    private const LABELS = ['comment' => 'Komentar', 'natgeo' => 'NatGeo'];

    /**
     * @param  array<string, string>  $file  field => raw cell text
     * @return array{set: array<string, mixed>, errors: list<string>, warnings: list<string>, changes: list<string>, replaces: bool}
     */
    public static function compare(?Score $current, array $file, bool $natgeoAllowed, bool $overwrite, string $where): array
    {
        $out = ['set' => [], 'errors' => [], 'warnings' => [], 'changes' => [], 'replaces' => false];

        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $file)) {
                continue;
            }

            $text = trim((string) $file[$field]);
            $label = self::LABELS[$field] ?? ucfirst($field);
            $old = $current?->{self::COLUMNS[$field] ?? $field};

            if ($text === '') {
                continue; // blank cell: keep the stored value
            }

            if ($field === 'natgeo' && ! $natgeoAllowed) {
                $out['warnings'][] = 'Kolom NatGeo diabaikan (hanya untuk Kelas 6).';

                continue;
            }

            if ($text === '-') {
                if ($old === null) {
                    continue;
                }
                if (! $overwrite) {
                    $out['warnings'][] = "{$label}: tanda - diabaikan (aktifkan \"Timpa nilai yang sudah ada\" untuk mengosongkan).";

                    continue;
                }
                $out['set'][$field] = null;
                $out['changes'][] = "{$label} ".self::fmt($old).' → –';
                $out['replaces'] = true;

                continue;
            }

            if ($field === 'natgeo') {
                $norm = str_replace(' ', '-', $text);
                $new = collect(Level::subValues())->first(fn ($l) => strcasecmp($l, $norm) === 0);
                if ($new === null) {
                    $category = collect(Level::cases())->first(fn (Level $l) => strcasecmp($l->value, $norm) === 0);
                    $out['errors'][] = $category
                        ? "{$where}: NatGeo '{$text}' harus berupa anak level, mis. ".implode(', ', $category->subLevels()).'.'
                        : "{$where}: NatGeo '{$text}' harus salah satu dari ".implode(', ', Level::subValues()).'.';

                    continue;
                }
            } elseif ($field === 'comment') {
                if (mb_strlen($text) > 600) {
                    $out['errors'][] = "{$where}: Komentar lebih dari 600 karakter.";

                    continue;
                }
                $new = $text;
            } else {
                $norm = str_replace(',', '.', $text);
                [$min, $max] = [ScoringConfig::MIN, ScoringConfig::MAX];
                $shown = str_replace('.', ',', $text);

                if (! is_numeric($norm)) {
                    $out['errors'][] = "{$where}: {$label} '{$text}' bukan angka.";

                    continue;
                }
                $new = (float) $norm;
                if ($new < $min || $new > $max) {
                    $out['errors'][] = "{$where}: {$label} {$shown} harus antara 5 dan 25.";

                    continue;
                }
                if (fmod($new * 2, 1.0) !== 0.0) {
                    $out['errors'][] = "{$where}: {$label} {$shown} harus bulat atau kelipatan 0,5.";

                    continue;
                }
            }

            if ($old === $new || ($old !== null && ! isset(self::COLUMNS[$field]) && (float) $old === $new)) {
                continue;
            }

            if ($old !== null && ! $overwrite) {
                $out['warnings'][] = "{$label} tetap ".self::fmt($old).' (file: '.self::fmt($new).'); aktifkan "Timpa" untuk mengganti.';

                continue;
            }

            $out['set'][$field] = $new;
            $out['changes'][] = "{$label} ".self::fmt($old).' → '.self::fmt($new);
            $out['replaces'] = $out['replaces'] || $old !== null;
        }

        return $out;
    }

    /** Writes the changes; bumps the version so open grids get a 409 instead of silently overwriting. */
    public static function apply(int $enrollmentId, array $set): void
    {
        if (! $set) {
            return;
        }

        $score = Score::query()->firstOrNew(['enrollment_id' => $enrollmentId]);
        foreach ($set as $field => $value) {
            $score->{self::COLUMNS[$field] ?? $field} = $value;
        }
        $score->version = ($score->version ?? 0) + 1;
        $score->save();
    }

    public static function fmt(mixed $v): string
    {
        if ($v === null) {
            return '–';
        }

        if (is_float($v) || is_int($v)) {
            return rtrim(rtrim(number_format((float) $v, 1, ',', ''), '0'), ',');
        }

        return '"'.mb_strimwidth((string) $v, 0, 30, '…').'"';
    }
}
