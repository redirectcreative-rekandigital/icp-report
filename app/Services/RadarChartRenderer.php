<?php

namespace App\Services;

/**
 * Skill profile radar as a PNG data URI, drawn with GD (no headless browser on shared hosting).
 * Drawn at 3× and downsampled for smooth edges.
 */
class RadarChartRenderer
{
    private const SKILLS = ['Listening', 'Reading', 'Writing', 'Speaking'];

    /** @param list<float|null> $percent L, R, W, S */
    public function render(array $percent, string $primaryHex = '#1F3A68', int $size = 300): string
    {
        $scale = 3;
        $s = $size * $scale;
        $img = imagecreatetruecolor($s, $s);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127));
        imageantialias($img, true);

        [$pr, $pg, $pb] = sscanf($primaryHex, '#%02x%02x%02x');
        $grid = imagecolorallocate($img, 176, 188, 204);
        $text = imagecolorallocate($img, 75, 85, 99);
        // Opaque 25 % tint of the primary colour (alpha fills render inconsistently in PDFs).
        $fill = imagecolorallocate($img, (int) (255 - (255 - $pr) * .25), (int) (255 - (255 - $pg) * .25), (int) (255 - (255 - $pb) * .25));
        $line = imagecolorallocate($img, $pr, $pg, $pb);

        $c = $s / 2;
        $r = $s / 2 - 84 * $scale; // leave room for the axis labels
        $pt = function (int $i, float $pct) use ($c, $r) {
            $a = -M_PI / 2 + $i * M_PI / 2;
            $d = max(0, min(100, $pct)) / 100 * $r;

            return [$c + $d * cos($a), $c + $d * sin($a)];
        };

        imagesetthickness($img, 2 * $scale);
        foreach ([25, 50, 75, 100] as $ring) {
            $pts = [];
            foreach (range(0, 3) as $i) {
                array_push($pts, ...$pt($i, $ring));
            }
            imagepolygon($img, $pts, $grid);
        }
        foreach (range(0, 3) as $i) {
            [$x, $y] = $pt($i, 100);
            imageline($img, (int) $c, (int) $c, (int) $x, (int) $y, $grid);
        }

        $pts = [];
        foreach (range(0, 3) as $i) {
            array_push($pts, ...$pt($i, (float) ($percent[$i] ?? 0)));
        }
        imagefilledpolygon($img, $pts, $fill);
        imagesetthickness($img, 3 * $scale);
        imagepolygon($img, $pts, $line);
        foreach (range(0, 3) as $i) {
            [$x, $y] = $pt($i, (float) ($percent[$i] ?? 0));
            imagefilledellipse($img, (int) $x, (int) $y, 9 * $scale, 9 * $scale, $line);
        }

        // Axis labels with the bundled DejaVu font when available, else GD's built-in font.
        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
        foreach (self::SKILLS as $i => $label) {
            [$x, $y] = $pt($i, 100);
            $dx = [0, 1, 0, -1][$i];
            $dy = [-1, 0, 1, 0][$i];
            if (is_file($font) && function_exists('imagettftext')) {
                $fs = 9.5 * $scale;
                $box = imagettfbbox($fs, 0, $font, $label);
                $w = $box[2] - $box[0];
                $tx = $x - $w / 2 + $dx * ($w / 2 + 6 * $scale);
                $ty = $y + $dy * 16 * $scale + ($dy === 0 ? 5 * $scale : ($dy > 0 ? 12 * $scale : 0));
                imagettftext($img, $fs, 0, (int) $tx, (int) $ty, $text, $font, $label);
            } else {
                imagestring($img, 5, (int) ($x - 30), (int) $y, $label, $text);
            }
        }

        $out = imagecreatetruecolor($size, $size);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 255, 255, 255, 127));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $size, $size, $s, $s);

        ob_start();
        imagepng($out);
        $png = ob_get_clean();
        imagedestroy($img);
        imagedestroy($out);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
