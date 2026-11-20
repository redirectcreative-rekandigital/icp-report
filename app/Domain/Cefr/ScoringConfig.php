<?php

namespace App\Domain\Cefr;

use App\Models\ScoringConfig as ScoringConfigModel;

/**
 * Immutable scoring rules for one grade in one period (PRD-04 §5.1).
 * Thresholds are percentages per level in Pre-A1 → B2 order; null = level not measured.
 */
final class ScoringConfig
{
    /**
     * @param  array<string, float>  $max  skill => maximum raw score
     * @param  array<string, float>  $weight  skill => weight
     * @param  array<string, float|null>  $thresholds  level => minimum percentage
     */
    public function __construct(
        public readonly array $max,
        public readonly array $weight,
        public readonly array $thresholds,
        public readonly string $expectedMin,
        public readonly string $expectedMax,
    ) {}

    public static function fromModel(ScoringConfigModel $m): self
    {
        return self::fromArray([
            'max' => array_combine(Skill::values(), array_map(fn ($s) => (float) $m->{"max_{$s}"}, Skill::values())),
            'weight' => array_combine(Skill::values(), array_map(fn ($s) => (float) $m->{"weight_{$s}"}, Skill::values())),
            'thresholds' => $m->thresholds,
            'expected_min' => $m->expected_min,
            'expected_max' => $m->expected_max,
        ]);
    }

    /** @param array{max: array, weight: array, thresholds: array, expected_min: string, expected_max: string} $a */
    public static function fromArray(array $a): self
    {
        $thresholds = [];
        foreach (Level::values() as $level) {
            $v = $a['thresholds'][$level] ?? null;
            $thresholds[$level] = $v === null ? null : (float) $v;
        }

        return new self(
            array_map('floatval', $a['max']),
            array_map('floatval', $a['weight']),
            $thresholds,
            $a['expected_min'],
            $a['expected_max'],
        );
    }

    /** Same shape as fromArray(); sent to the browser for instant feedback. */
    public function toArray(): array
    {
        return [
            'max' => $this->max,
            'weight' => $this->weight,
            'thresholds' => $this->thresholds,
            'expected_min' => $this->expectedMin,
            'expected_max' => $this->expectedMax,
        ];
    }
}
