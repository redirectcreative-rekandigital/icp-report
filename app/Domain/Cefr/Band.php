<?php

namespace App\Domain\Cefr;

/** Internal teacher assessment band (revisi 23-11-2026 item 6). */
enum Band: string
{
    case Beginner = 'Beginner';
    case Intermediate = 'Intermediate';
    case Advanced = 'Advanced';

    public const BG = ['Beginner' => '#D64545', 'Intermediate' => '#F2C230', 'Advanced' => '#2E8B57'];

    public const FG = ['Beginner' => '#FFFFFF', 'Intermediate' => '#3B2A12', 'Advanced' => '#FFFFFF'];

    /** 0-based order Beginner → Advanced. */
    public function index(): int
    {
        return array_search($this, self::cases(), true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $b) => $b->value, self::cases());
    }
}
