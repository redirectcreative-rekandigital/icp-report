<?php

namespace App\Domain\Cefr;

/**
 * The CEFR engine — the single source of truth (PRD-01 §1.2, PRD-04 §5.2).
 * Mirrors the Excel "Grade 3–6" sheet formulas exactly:
 *   pct      = IF(score="", "", score/Max*100)
 *   level    = INDEX(Levels, MATCH(pct, Thr, 1))        (999 = level not measured)
 *   overall  = IF(COUNT(scores)<4, "", Σ(pct×w)/Σw)
 *   status   = Below / Meeting / Above vs. the expected range, or "Incomplete scores"
 *   strongest= INDEX(SkillNames, MATCH(MAX(pcts), pcts, 0))  → first max wins (L, R, W, S)
 * resources/js/lib/cefr.ts is a line-by-line mirror; both run tests/fixtures/cefr-parity.json.
 */
final class CefrCalculator
{
    /** Excel compares with ~15 significant digits; rounding avoids 59.99999999 vs 60 surprises. */
    private const PRECISION = 9;

    /** @param array<string, float|int|null> $scores skill => raw score */
    public static function calculate(ScoringConfig $config, array $scores): StudentResult
    {
        $percent = [];
        $level = [];

        foreach (Skill::values() as $skill) {
            $raw = $scores[$skill] ?? null;
            $max = $config->max[$skill] ?? 0.0;
            $pct = ($raw === null || $max <= 0) ? null : round($raw / $max * 100, self::PRECISION);
            $percent[$skill] = $pct;
            $level[$skill] = $pct === null ? null : self::levelFor($config, $pct);
        }

        $complete = ! in_array(null, $percent, true);
        $weightSum = array_sum(array_map(fn ($s) => $config->weight[$s] ?? 0.0, Skill::values()));

        $overall = null;
        if ($complete && $weightSum > 0) {
            $sum = 0.0;
            foreach (Skill::values() as $skill) {
                $sum += $percent[$skill] * ($config->weight[$skill] ?? 0.0);
            }
            $overall = round($sum / $weightSum, self::PRECISION);
        }

        $overallLevel = $overall === null ? null : self::levelFor($config, $overall);

        return new StudentResult(
            $percent,
            $level,
            $overall,
            $overallLevel,
            self::status($config, $overallLevel),
            $overall === null ? null : self::strongest($percent),
        );
    }

    /** Highest level whose threshold is set and ≤ pct (Pre-A1 always qualifies at 0). */
    public static function levelFor(ScoringConfig $config, float $pct): string
    {
        $result = Level::PreA1->value;

        foreach ($config->thresholds as $level => $threshold) {
            if ($threshold !== null && $pct >= $threshold) {
                $result = $level;
            }
        }

        return $result;
    }

    private static function status(ScoringConfig $config, ?string $overallLevel): string
    {
        if ($overallLevel === null) {
            return 'incomplete';
        }

        $idx = Level::from($overallLevel)->index();

        return match (true) {
            $idx < Level::from($config->expectedMin)->index() => 'below',
            $idx > Level::from($config->expectedMax)->index() => 'above',
            default => 'meeting',
        };
    }

    /** @param array<string, float> $percent */
    private static function strongest(array $percent): string
    {
        $best = null;

        foreach (Skill::values() as $skill) {
            if ($best === null || $percent[$skill] > $percent[$best]) {
                $best = $skill; // strict ">" keeps the first skill on ties
            }
        }

        return $best;
    }

    /**
     * Report comment (PRD-04 §5.2 rule 7): the teacher's comment if given, otherwise
     * "{Name} {overall comment} Strongest skill this term: {Skill}."
     *
     * @param  array<string, string>  $overallComments  level => sentence (from level_meta)
     */
    public static function reportComment(
        StudentResult $result,
        string $studentName,
        ?string $teacherComment,
        array $overallComments,
        bool $withStrongest = true,
    ): ?string {
        $teacherComment = trim((string) $teacherComment);

        if ($teacherComment !== '') {
            return $teacherComment;
        }

        if ($result->overallLevel === null) {
            return null;
        }

        $text = trim($studentName).' '.($overallComments[$result->overallLevel] ?? '');

        if ($withStrongest && $result->strongest) {
            $text .= ' Strongest skill this term: '.Skill::from($result->strongest)->label().'.';
        }

        return $text;
    }
}
