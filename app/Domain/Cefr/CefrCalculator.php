<?php

namespace App\Domain\Cefr;

/**
 * The scoring engine — the single source of truth.
 *   level    = Advanced if score ≥ advanced, Intermediate if ≥ intermediate, else Beginner
 *   total    = Σ scores, only when all four are filled in
 *   overall  = band of total/4 (same thresholds ×4)
 *   strongest= highest score, first skill wins ties (L, R, W, S)
 * resources/js/lib/cefr.ts is a line-by-line mirror; both run tests/fixtures/cefr-parity.json.
 */
final class CefrCalculator
{
    /** @param array<string, float|int|null> $scores skill => raw score */
    public static function calculate(ScoringConfig $config, array $scores): StudentResult
    {
        $level = [];
        $total = 0.0;
        $complete = true;

        foreach (Skill::values() as $skill) {
            $raw = $scores[$skill] ?? null;
            $level[$skill] = $raw === null ? null : self::levelFor($config, (float) $raw);
            $complete = $complete && $raw !== null;
            $total += (float) $raw;
        }

        if (! $complete) {
            return new StudentResult($level, null, null, null);
        }

        return new StudentResult($level, $total, self::levelFor($config, $total / 4), self::strongest($scores));
    }

    public static function levelFor(ScoringConfig $config, float $score): string
    {
        return match (true) {
            $score >= $config->advanced => Band::Advanced->value,
            $score >= $config->intermediate => Band::Intermediate->value,
            default => Band::Beginner->value,
        };
    }

    /** @param array<string, float|int> $scores */
    private static function strongest(array $scores): string
    {
        $best = null;

        foreach (Skill::values() as $skill) {
            if ($best === null || $scores[$skill] > $scores[$best]) {
                $best = $skill; // strict ">" keeps the first skill on ties
            }
        }

        return $best;
    }

    /**
     * Report comment: the teacher's comment if given, otherwise
     * "{Name} {band comment} Strongest skill this term: {Skill}."
     *
     * @param  array<string, string>  $overallComments  band => sentence
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
            return self::withName($studentName, $teacherComment);
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

    /** Words kept capitalised when they open a teacher comment. */
    public const KEEP_CAPITAL = ['English', 'Indonesian', 'Mr', 'Mrs', 'Ms', 'Miss'];

    /**
     * The teacher's own comment is printed after the student's full name (revisi 23-11-2026):
     * "shows great effort." → "Kevin Wijaya shows great effort.". Left as is when it already
     * starts with the full or first name; the first word is lower-cased unless it is "I",
     * an acronym (ICP) or a word that is always capitalised (English).
     */
    public static function withName(string $studentName, string $comment): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $studentName));
        if ($name === '') {
            return $comment;
        }

        foreach (array_unique([$name, explode(' ', $name)[0]]) as $n) {
            if (preg_match('/^'.preg_quote($n, '/').'(?!\p{L})/iu', $comment)) {
                return $comment;
            }
        }

        if (preg_match('/^(\p{Lu}\p{Ll}*)/u', $comment, $m) && mb_strlen($m[1]) > 1 && ! in_array($m[1], self::KEEP_CAPITAL, true)) {
            $comment = mb_strtolower(mb_substr($comment, 0, 1)).mb_substr($comment, 1);
        }

        return "{$name} {$comment}";
    }
}
