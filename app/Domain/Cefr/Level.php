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

    /**
     * National Geographic placement sub-levels, the values teachers pick (revisi 23-11-2026):
     * Pre-A1 has none of its own, A1–B2 are split in .1–.3.
     *
     * @return list<string>
     */
    public function subLevels(): array
    {
        return $this === self::PreA1 ? [$this->value] : array_map(fn ($i) => "{$this->value}.{$i}", [1, 2, 3]);
    }

    /** @return array<string, list<string>> category → sub-levels */
    public static function subLevelGroups(): array
    {
        return array_combine(self::values(), array_map(fn (self $l) => $l->subLevels(), self::cases()));
    }

    /** @return list<string> all 13 sub-levels in order */
    public static function subValues(): array
    {
        return array_merge(...array_values(self::subLevelGroups()));
    }

    /** Category of a stored sub-level ("A2.2" → A2), null for anything else. */
    public static function ofSub(?string $sub): ?self
    {
        return in_array($sub, self::subValues(), true) ? self::from(explode('.', $sub)[0]) : null;
    }
}
