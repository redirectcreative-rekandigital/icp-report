<?php

namespace App\Domain\Cefr;

enum Level: string
{
    case PreA1 = 'Pre-A1';
    case A1 = 'A1';
    case A2 = 'A2';
    case B1 = 'B1';
    case B2 = 'B2';

    /** 0-based order Pre-A1 → B2. */
    public function index(): int
    {
        return array_search($this, self::cases(), true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $l) => $l->value, self::cases());
    }
}
