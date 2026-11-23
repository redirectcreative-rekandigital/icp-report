/**
 * Mirror of app/Domain/Cefr/CefrCalculator.php — for instant feedback only.
 * Stored and printed results always come from the server (PRD-01 §1.2).
 * Both implementations run tests/fixtures/cefr-parity.json.
 */

export const SKILLS = ['listening', 'reading', 'writing', 'speaking'] as const;
/** Internal teacher assessment bands (revisi 23-11-2026 item 6). */
export const BANDS = ['Beginner', 'Intermediate', 'Advanced'] as const;
/** CEFR levels of the grade 6 National Geographic placement test. */
export const LEVELS = ['Pre-A1', 'A1', 'A2', 'B1', 'B2'] as const;

export type Skill = (typeof SKILLS)[number];
export type Band = (typeof BANDS)[number];
export type Level = (typeof LEVELS)[number];
/** National Geographic sub-level a teacher picks: Pre-A1, A1.1 … B2.3 (category = part before the dot). */
export type NatgeoLevel = string;
/** Category → sub-levels, from Level::subLevelGroups(). */
export type NatgeoGroups = Record<Level, NatgeoLevel[]>;
export type Scores = Partial<Record<Skill, number | null>>;

export type ScoringConfig = {
    min: number;
    max: number;
    intermediate: number;
    advanced: number;
};

export type StudentResult = {
    level: Record<Skill, Band | null>;
    total: number | null;
    overall_level: Band | null;
    strongest: Skill | null;
};

export const SKILL_LABEL: Record<Skill, string> = {
    listening: 'Listening',
    reading: 'Reading',
    writing: 'Writing',
    speaking: 'Speaking',
};

export const SCORE_MIN = 5;
export const SCORE_MAX = 25;

export function levelFor(config: ScoringConfig, score: number): Band {
    if (score >= config.advanced) {
        return 'Advanced';
    }

    return score >= config.intermediate ? 'Intermediate' : 'Beginner';
}

export function calculate(
    config: ScoringConfig,
    scores: Scores,
): StudentResult {
    const level = {} as Record<Skill, Band | null>;
    let total = 0;
    let complete = true;

    for (const skill of SKILLS) {
        const raw = scores[skill];
        const has = raw !== null && raw !== undefined;
        level[skill] = has ? levelFor(config, raw) : null;
        complete = complete && has;
        total += has ? raw : 0;
    }

    if (!complete) {
        return { level, total: null, overall_level: null, strongest: null };
    }

    return {
        level,
        total,
        overall_level: levelFor(config, total / 4),
        strongest: strongest(scores as Record<Skill, number>),
    };
}

function strongest(scores: Record<Skill, number>): Skill {
    let best: Skill = SKILLS[0];

    for (const skill of SKILLS) {
        if (scores[skill] > scores[best]) {
            best = skill; // strict ">" keeps the first skill on ties
        }
    }

    return best;
}

/** Words kept capitalised when they open a teacher comment (mirrors CefrCalculator::KEEP_CAPITAL). */
const KEEP_CAPITAL = ['English', 'Indonesian', 'Mr', 'Mrs', 'Ms', 'Miss'];

const escapeRegExp = (s: string) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

/**
 * The teacher's own comment printed after the student's full name — mirrors
 * CefrCalculator::withName(): skipped when it already starts with the full or first name;
 * the first word is lower-cased unless it is "I", an acronym or always capitalised.
 */
export function withName(studentName: string, comment: string): string {
    const name = studentName.replace(/\s+/g, ' ').trim();

    if (name === '') {
        return comment;
    }

    for (const n of new Set([name, name.split(' ')[0]])) {
        if (new RegExp(`^${escapeRegExp(n)}(?!\\p{L})`, 'iu').test(comment)) {
            return comment;
        }
    }

    const word = /^(\p{Lu}\p{Ll}*)/u.exec(comment)?.[1];

    if (word && word.length > 1 && !KEEP_CAPITAL.includes(word)) {
        comment = comment.charAt(0).toLowerCase() + comment.slice(1);
    }

    return `${name} ${comment}`;
}

export function reportComment(
    result: StudentResult,
    studentName: string,
    teacherComment: string | null | undefined,
    overallComments: Partial<Record<Band, string>>,
    withStrongest = true,
): string | null {
    const own = (teacherComment ?? '').trim();

    if (own !== '') {
        return withName(studentName, own);
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

const RANGE_ERROR = `Nilai harus ${SCORE_MIN}–${SCORE_MAX} (kelipatan 0,5)`;

/**
 * Parses what a teacher typed into a score cell (PRD-04 §4.2):
 * "12,5" → 12.5, "015" → 15, "" → null; rejects text, < 5, > 25 and non-0.5 steps.
 */
export function parseScore(
    input: string,
): { ok: true; value: number | null } | { ok: false; error: string } {
    const text = input.trim().replace(',', '.');

    if (text === '' || text === '-') {
        return { ok: true, value: null };
    }

    if (!/^\d+(\.\d+)?$/.test(text)) {
        return { ok: false, error: RANGE_ERROR };
    }

    const value = Number(text);

    if (
        value < SCORE_MIN ||
        value > SCORE_MAX ||
        Math.round(value * 2) !== value * 2
    ) {
        return { ok: false, error: RANGE_ERROR };
    }

    return { ok: true, value };
}

/** Indonesian display of a raw score: 12.5 → "12,5". */
export function formatScore(value: number | null | undefined): string {
    return value === null || value === undefined
        ? ''
        : String(value).replace('.', ',');
}
