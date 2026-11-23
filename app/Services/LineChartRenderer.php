<?php

namespace App\Services;

use App\Domain\Cefr\ScoringConfig;

/**
 * Skill profile line chart as a PNG data URI, drawn with GD (no headless browser on shared hosting).
 * Y axis 5–25, X axis the four skills; each point is coloured by its band. Drawn at 3× and downsampled.
 */
class LineChartRenderer
{
    private const SKILLS = ['Listening', 'Reading', 'Writing', 'Speaking'];

    /**
     * @param  list<float|null>  $scores  L, R, W, S (5–25)
     * @param  list<string>  $pointHex  one colour per point (band colour)
     */
    public function render(array $scores, array $pointHex, string $lineHex = '#1F3A68', int $w = 420, int $h = 300): string
    {
        $k = 3;
        $W = $w * $k;
        $H = $h * $k;
        $img = imagecreatetruecolor($W, $H);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));

        $rgb = fn (string $hex) => imagecolorallocate($img, ...sscanf($hex, '#%02x%02x%02x'));
        $grid = imagecolorallocate($img, 214, 220, 228);
        $axis = imagecolorallocate($img, 120, 130, 145);
        $text = imagecolorallocate($img, 55, 65, 81);
        $line = $rgb($lineHex);

        $font = public_path('fonts/pagella/TeXGyrePagella-regular.ttf');
        $bold = public_path('fonts/pagella/TeXGyrePagella-bold.ttf');
        $label = function (string $s, float $size, float $cx, float $y, $color, bool $isBold = false, bool $right = false) use ($img, $font, $bold, $k) {
            $f = $isBold ? $bold : $font;
            $box = imagettfbbox($size * $k, 0, $f, $s);
            $tw = $box[2] - $box[0];
            imagettftext($img, $size * $k, 0, (int) ($right ? $cx - $tw : $cx - $tw / 2), (int) $y, $color, $f, $s);
        };

        // Plot area
        $left = 36 * $k;
        $right = $W - 14 * $k;
        $top = 28 * $k; // room for the value label above a 25
        $bottom = $H - 30 * $k;
        $min = ScoringConfig::MIN;
        $max = ScoringConfig::MAX;
        $y = fn (float $v) => $bottom - (max($min, min($max, $v)) - $min) / ($max - $min) * ($bottom - $top);
        $x = fn (int $i) => $left + ($i + .5) * ($right - $left) / 4;

        imagesetthickness($img, 1 * $k);
        foreach ([5, 10, 15, 20, 25] as $tick) {
            imageline($img, (int) $left, (int) $y($tick), (int) $right, (int) $y($tick), $tick === 5 ? $axis : $grid);
            $label((string) $tick, 11.5, $left - 7 * $k, $y($tick) + 4 * $k, $text, false, true);
        }
        imageline($img, (int) $left, (int) $top, (int) $left, (int) $bottom, $axis);
        foreach (self::SKILLS as $i => $s) {
            $label($s, 12, $x($i), $bottom + 20 * $k, $text, true);
        }

        $pts = [];
        foreach ($scores as $i => $v) {
            if ($v !== null) {
                $pts[$i] = [$x($i), $y((float) $v), (float) $v];
            }
        }
        imagesetthickness($img, 3 * $k);
        $prev = null;
        foreach ($pts as $p) {
            if ($prev) {
                imageline($img, (int) $prev[0], (int) $prev[1], (int) $p[0], (int) $p[1], $line);
            }
            $prev = $p;
        }
        foreach ($pts as $i => [$px, $py, $v]) {
            imagefilledellipse($img, (int) $px, (int) $py, 15 * $k, 15 * $k, $line);
            imagefilledellipse($img, (int) $px, (int) $py, 10 * $k, 10 * $k, $rgb($pointHex[$i] ?? $lineHex));
            $txt = rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
            $ty = $py - 11 * $k;
            $label($txt, 12.5, $px, $ty, $text, true);
        }

        $out = imagecreatetruecolor($w, $h);
        imagecopyresampled($out, $img, 0, 0, 0, 0, $w, $h, $W, $H);
        ob_start();
        imagepng($out);
        $png = ob_get_clean();
        imagedestroy($img);
        imagedestroy($out);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
