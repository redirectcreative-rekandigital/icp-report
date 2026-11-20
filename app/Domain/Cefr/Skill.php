<?php

namespace App\Domain\Cefr;

enum Skill: string
{
    // Order matters: tie-break for "strongest skill" follows this order (Excel MATCH).
    case Listening = 'listening';
    case Reading = 'reading';
    case Writing = 'writing';
    case Speaking = 'speaking';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
