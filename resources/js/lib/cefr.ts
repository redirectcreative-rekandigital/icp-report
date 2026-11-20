/**
 * Mirror of app/Domain/Cefr/CefrCalculator.php — for instant feedback only.
 * Stored and printed results always come from the server (PRD-01 §1.2).
 * Both implementations run tests/fixtures/cefr-parity.json.
 */

export const SKILLS = ['listening', 'reading', 'writing', 'speaking'] as const;
export const LEVELS = ['Pre-A1', 'A1', 'A2', 'B1', 'B2'] as const;

export type Skill = (typeof SKILLS)[number];
export type Level = (typeof LEVELS)[number];
export type Status = 'incomplete' | 'below' | 'meeting' | 'above';
export type Scores = Partial<Record<Skill, number | null>>;

export type ScoringConfig = {
    max: Record<Skill, number>;
    weight: Record<Skill, number>;
    thresholds: Record<Level, number | null>;
    expected_min: Level;
    expected_max: Level;
};

export type StudentResult = {
    percent: Record<Skill, number | null>;
    level: Record<Skill, Level | null>;
    overall_percent: number | null;
    overall_level: Level | null;
    status: Status;
    strongest: Skill | null;
};

export const SKILL_LABEL: Record<Skill, string> = {
    listening: 'Listening',
    reading: 'Reading',
    writing: 'Writing',
    speaking: 'Speaking',
};

const PRECISION = 1e9;

/** Same as PHP round($x, 9). */
function round9(x: number): number {
    return Math.round(x * PRECISION) / PRECISION;
}

export function levelFor(config: ScoringConfig, pct: number): Level {
    let result: Level = 'Pre-A1';

    for (const level of LEVELS) {
        const t = config.thresholds[level];

        if (t !== null && t !== undefined && pct >= t) {
            result = level;
        }
    }

    return result;
}

export function calculate(
    config: ScoringConfig,
    scores: Scores,
): StudentResult {
    const percent = {} as Record<Skill, number | null>;
    const level = {} as Record<Skill, Level | null>;

    for (const skill of SKILLS) {
        const raw = scores[skill];
        const max = config.max[skill] ?? 0;
        const pct =
            raw === null || raw === undefined || max <= 0
                ? null
                : round9((raw / max) * 100);
        percent[skill] = pct;
        level[skill] = pct === null ? null : levelFor(config, pct);
    }

    const complete = SKILLS.every((s) => percent[s] !== null);
    const weightSum = SKILLS.reduce(
        (sum, s) => sum + (config.weight[s] ?? 0),
        0,
    );

    let overall: number | null = null;

    if (complete && weightSum > 0) {
        const sum = SKILLS.reduce(
            (acc, s) => acc + (percent[s] as number) * (config.weight[s] ?? 0),
            0,
        );
        overall = round9(sum / weightSum);
    }

    const overallLevel = overall === null ? null : levelFor(config, overall);

    return {
        percent,
        level,
        overall_percent: overall,
        overall_level: overallLevel,
        status: status(config, overallLevel),
        strongest:
            overall === null
                ? null
                : strongest(percent as Record<Skill, number>),
    };
}

function status(config: ScoringConfig, overallLevel: Level | null): Status {
    if (overallLevel === null) {
        return 'incomplete';
    }

    const idx = LEVELS.indexOf(overallLevel);

    if (idx < LEVELS.indexOf(config.expected_min)) {
        return 'below';
    }

    if (idx > LEVELS.indexOf(config.expected_max)) {
        return 'above';
    }

    return 'meeting';
}

function strongest(percent: Record<Skill, number>): Skill {
    let best: Skill = SKILLS[0];

    for (const skill of SKILLS) {
        if (percent[skill] > percent[best]) {
            best = skill; // strict ">" keeps the first skill on ties (Excel MATCH)
        }
    }

    return best;
}

export function reportComment(
    result: StudentResult,
    studentName: string,
    teacherComment: string | null | undefined,
    overallComments: Partial<Record<Level, string>>,
    withStrongest = true,
): string | null {
    const own = (teacherComment ?? '').trim();

    if (own !== '') {
        return own;
    }

    if (result.overall_level === null) {
        return null;
    }

    let text = `${studentName.trim()} ${overallComments[result.overall_level] ?? ''}`;

    if (withStrongest && result.strongest) {
        text += ` Strongest skill this term: ${SKILL_LABEL[result.strongest]}.`;
    }

    return text;
}

/**
 * Parses what a teacher typed into a score cell (PRD-04 §4.2):
 * "12,5" → 12.5, "015" → 15, "" → null; rejects text, < 0, > max and non-0.5 steps.
 */
export function parseScore(
    input: string,
    max: number,
): { ok: true; value: number | null } | { ok: false; error: string } {
    const text = input.trim().replace(',', '.');

    if (text === '' || text === '-') {
        return { ok: true, value: null };
    }

    if (!/^\d+(\.\d+)?$/.test(text)) {
        return {
            ok: false,
            error: `Nilai harus 0–${formatMax(max)} (kelipatan 0,5)`,
        };
    }

    const value = Number(text);

    if (value < 0 || value > max || Math.round(value * 2) !== value * 2) {
        return {
            ok: false,
            error: `Nilai harus 0–${formatMax(max)} (kelipatan 0,5)`,
        };
    }

    return { ok: true, value };
}

function formatMax(max: number): string {
    return String(max).replace('.', ',');
}

/** Indonesian display of a raw score: 12.5 → "12,5". */
export function formatScore(value: number | null | undefined): string {
    return value === null || value === undefined
        ? ''
        : String(value).replace('.', ',');
}
