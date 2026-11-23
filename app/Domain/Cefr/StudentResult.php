<?php

namespace App\Domain\Cefr;

/** Result of CefrCalculator for one student. */
final class StudentResult
{
    /**
     * @param  array<string, string|null>  $level  skill => band
     */
    public function __construct(
        public readonly array $level,
        public readonly ?float $total, // sum of the four skills, null while incomplete
        public readonly ?string $overallLevel, // band of the total
        public readonly ?string $strongest, // skill key, null when incomplete
    ) {}

    public function isComplete(): bool
    {
        return $this->total !== null;
    }

    public function toArray(): array
    {
        return [
            'level' => $this->level,
            'total' => $this->total,
            'overall_level' => $this->overallLevel,
            'strongest' => $this->strongest,
        ];
    }
}
