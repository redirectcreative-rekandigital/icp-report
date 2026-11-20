<?php

namespace App\Domain\Cefr;

/** Result of CefrCalculator for one student. Percentages are unrounded. */
final class StudentResult
{
    /**
     * @param  array<string, float|null>  $percent  skill => %
     * @param  array<string, string|null>  $level  skill => level
     */
    public function __construct(
        public readonly array $percent,
        public readonly array $level,
        public readonly ?float $overallPercent,
        public readonly ?string $overallLevel,
        public readonly string $status, // incomplete | below | meeting | above
        public readonly ?string $strongest, // skill key, null when incomplete
    ) {}

    public function isComplete(): bool
    {
        return $this->status !== 'incomplete';
    }

    public function toArray(): array
    {
        return [
            'percent' => $this->percent,
            'level' => $this->level,
            'overall_percent' => $this->overallPercent,
            'overall_level' => $this->overallLevel,
            'status' => $this->status,
            'strongest' => $this->strongest,
        ];
    }
}
