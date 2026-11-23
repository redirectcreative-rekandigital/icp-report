<?php

namespace App\Support;

/**
 * Report layout settings (PRD-05 §5): presets, section toggles, header, colours, signatures, paper.
 * Stored in settings.report_layout and copied into the finalisation snapshot.
 */
class ReportLayout
{
    /** level_map applies to grade 6 only; 'radar' is the skill profile line chart (key kept from the old radar). */
    public const SECTIONS = ['level_map', 'cambridge', 'radar', 'can_do', 'strongest_skill', 'parent_signature'];

    public const PRESETS = [
        'lengkap' => ['level_map' => true, 'cambridge' => true, 'radar' => true, 'can_do' => true, 'strongest_skill' => true, 'parent_signature' => true],
        'ringkas' => ['level_map' => true, 'cambridge' => true, 'radar' => true, 'can_do' => false, 'strongest_skill' => true, 'parent_signature' => true],
        'minimal' => ['level_map' => false, 'cambridge' => false, 'radar' => false, 'can_do' => false, 'strongest_skill' => true, 'parent_signature' => true],
    ];

    public const THEMES = [
        'navy' => ['primary' => '#1F3A68', 'accent' => '#E3A82B'],
        'hijau' => ['primary' => '#00613F', 'accent' => '#F2C230'], // hijau Muhammadiyah + kuning
    ];

    public const ROLES = [
        'parent' => 'Parent / Guardian',
        'homeroom' => 'Homeroom Teacher',
        'principal' => 'Principal',
    ];

    public const DEFAULT_TITLE = 'ENGLISH PROFICIENCY PROGRESS REPORT';

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'preset' => 'lengkap',
            'paper_default' => 'a4',
            'margin_mm' => 12,
            'theme' => ['preset' => 'navy', ...self::THEMES['navy']],
            'header' => ['extra_line' => '', 'title' => self::DEFAULT_TITLE],
            'sections' => self::PRESETS['lengkap'],
            'signatures' => array_map(fn ($role) => ['role' => $role, 'label' => self::ROLES[$role]], array_keys(self::ROLES)),
            'id_label' => 'NBM',
        ];
    }

    /** Merge stored/partial values over the defaults and clamp everything to valid values. */
    public static function normalize(?array $layout): array
    {
        $d = self::defaults();
        $l = array_replace_recursive($d, $layout ?? []);

        $l['preset'] = in_array($l['preset'], ['lengkap', 'ringkas', 'minimal', 'kustom'], true) ? $l['preset'] : 'lengkap';
        $l['paper_default'] = in_array($l['paper_default'], ['f4', 'a4'], true) ? $l['paper_default'] : 'a4';
        $l['margin_mm'] = max(8, min(20, (int) $l['margin_mm']));
        $l['sections'] = array_combine(self::SECTIONS, array_map(fn ($s) => (bool) ($l['sections'][$s] ?? true), self::SECTIONS));
        $l['header']['title'] = trim((string) $l['header']['title']) ?: self::DEFAULT_TITLE;
        $l['header']['extra_line'] = trim((string) $l['header']['extra_line']);
        $l['header'] = ['extra_line' => $l['header']['extra_line'], 'title' => $l['header']['title']];
        $l['id_label'] = in_array($l['id_label'], ['NBM', 'NIP', 'none'], true) ? $l['id_label'] : 'NBM';

        $preset = $l['theme']['preset'] ?? 'navy';
        $l['theme'] = isset(self::THEMES[$preset])
            ? ['preset' => $preset, ...self::THEMES[$preset]]
            : ['preset' => 'kustom', 'primary' => self::hex($l['theme']['primary'] ?? null, '#1F3A68'), 'accent' => self::hex($l['theme']['accent'] ?? null, '#E3A82B')];

        // 2–4 distinct roles; unknown roles dropped; labels default to English role names.
        $seen = [];
        $signatures = [];
        foreach ((array) ($layout['signatures'] ?? $d['signatures']) as $s) {
            $role = $s['role'] ?? null;
            if (isset(self::ROLES[$role]) && ! isset($seen[$role])) {
                $seen[$role] = true;
                $signatures[] = ['role' => $role, 'label' => trim((string) ($s['label'] ?? '')) ?: self::ROLES[$role]];
            }
        }
        $l['signatures'] = count($signatures) >= 2 ? array_slice($signatures, 0, 4) : $d['signatures'];

        return $l;
    }

    /** Signature columns actually printed (parent column follows the "parent_signature" switch). */
    public static function printedSignatures(array $layout): array
    {
        return array_values(array_filter(
            $layout['signatures'],
            fn ($s) => $s['role'] !== 'parent' || $layout['sections']['parent_signature'],
        ));
    }

    /** WCAG contrast ratio of white text on $hex. */
    public static function contrastWithWhite(string $hex): float
    {
        [$r, $g, $b] = array_map(fn ($c) => hexdec($c) / 255, str_split(ltrim($hex, '#'), 2));
        $lin = fn ($c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $lum = 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);

        return 1.05 / ($lum + 0.05);
    }

    private static function hex(?string $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) ? strtoupper($value) : $fallback;
    }
}
