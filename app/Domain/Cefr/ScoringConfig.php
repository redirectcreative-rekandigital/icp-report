<?php

namespace App\Domain\Cefr;

use App\Models\ScoringConfig as ScoringConfigModel;

/**
 * Band thresholds for one grade in one period. Each skill is scored MIN–MAX;
 * a skill score ≥ intermediate is Intermediate, ≥ advanced is Advanced. The total
 * (4 skills) uses the same thresholds ×4.
 */
final class ScoringConfig
{
    public const MIN = 5.0;

    public const MAX = 25.0;

    public function __construct(
        public readonly float $intermediate,
        public readonly float $advanced,
    ) {}

    public static function fromModel(ScoringConfigModel $m): self
    {
        return new self((float) $m->min_intermediate, (float) $m->min_advanced);
    }

    /** @param array{intermediate: float|int|string, advanced: float|int|string} $a */
    public static function fromArray(array $a): self
    {
        return new self((float) $a['intermediate'], (float) $a['advanced']);
    }

    /** Sent to the browser for instant feedback and frozen in the finalisation snapshot. */
    public function toArray(): array
    {
        return ['min' => self::MIN, 'max' => self::MAX, 'intermediate' => $this->intermediate, 'advanced' => $this->advanced];
    }
}
