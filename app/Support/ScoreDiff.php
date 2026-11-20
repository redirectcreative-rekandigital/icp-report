<?php

namespace App\Support;

use App\Domain\Cefr\ScoringConfig;
use App\Models\Score;

/**
 * Compares file values with stored scores (PRD-03 §5.2–5.3):
 * blank = keep; "-" = clear (only with "Timpa"); existing different values are only replaced
 * with "Timpa"; numbers accept "12,5"/"12.5", must be 0–max and whole or .5.
 */
class ScoreDiff
{
    public const FIELDS = ['listening', 'reading', 'writing', 'speaking', 'comment'];

    /**
     * @param  array<string, string>  $file  field => raw cell text
     * @return array{set: array<string, mixed>, errors: list<string>, warnings: list<string>, changes: list<string>, replaces: bool}
     */
    public static function compare(?Score $current, array $file, ScoringConfig $config, bool $overwrite, string $where): array
    {
        $out = ['set' => [], 'errors' => [], 'warnings' => [], 'changes' => [], 'replaces' => false];

        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $file)) {
                continue;
            }

            $text = trim((string) $file[$field]);
            $label = $field === 'comment' ? 'Komentar' : ucfirst($field);
            $old = $field === 'comment' ? $current?->teacher_comment : $current?->{$field};

            if ($text === '') {
                continue; // blank cell: keep the stored value
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

            if ($field === 'comment') {
                if (mb_strlen($text) > 600) {
                    $out['errors'][] = "{$where}: Komentar lebih dari 600 karakter.";

                    continue;
                }
                $new = $text;
            } else {
                $norm = str_replace(',', '.', $text);
                $max = $config->max[$field];
                $maxText = self::fmt($max);
                $shown = str_replace('.', ',', $text);

                if (! is_numeric($norm)) {
                    $out['errors'][] = "{$where}: {$label} '{$text}' bukan angka.";

                    continue;
                }
                $new = (float) $norm;
                if ($new < 0) {
                    $out['errors'][] = "{$where}: {$label} {$shown} tidak boleh negatif.";

                    continue;
                }
                if ($new > $max) {
                    $out['errors'][] = "{$where}: {$label} {$shown} melebihi nilai maksimum {$maxText}.";

                    continue;
                }
                if (fmod($new * 2, 1.0) !== 0.0) {
                    $out['errors'][] = "{$where}: {$label} {$shown} harus bulat atau kelipatan 0,5.";

                    continue;
                }
            }

            if ($old === $new || ($old !== null && $field !== 'comment' && (float) $old === $new)) {
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
            $score->{$field === 'comment' ? 'teacher_comment' : $field} = $value;
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
