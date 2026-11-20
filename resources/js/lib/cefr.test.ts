import { describe, expect, it } from 'vitest';
import fixture from '../../../tests/fixtures/cefr-parity.json';
import type { ScoringConfig, Scores } from './cefr';
import { calculate, parseScore, reportComment } from './cefr';

type Case = {
    name: string;
    grade: number;
    override?: Partial<ScoringConfig>;
    scores: Scores;
    expected: ReturnType<typeof calculate>;
};

const defaults = fixture.defaults as unknown as Record<string, ScoringConfig>;

describe('cefr parity (tests/fixtures/cefr-parity.json)', () => {
    for (const c of fixture.cases as unknown as Case[]) {
        it(c.name, () => {
            const config = {
                ...defaults[String(c.grade)],
                ...(c.override ?? {}),
            } as ScoringConfig;
            const r = calculate(config, c.scores);

            for (const [skill, pct] of Object.entries(c.expected.percent)) {
                const got = r.percent[skill as keyof typeof r.percent];

                if (pct === null) {
                    expect(got).toBeNull();
                } else {
                    expect(got).toBeCloseTo(pct, 9);
                }
            }

            expect(r.level).toEqual(c.expected.level);
            expect(r.overall_level).toBe(c.expected.overall_level);
            expect(r.status).toBe(c.expected.status);
            expect(r.strongest).toBe(c.expected.strongest);

            if (c.expected.overall_percent === null) {
                expect(r.overall_percent).toBeNull();
            } else {
                expect(r.overall_percent).toBeCloseTo(
                    c.expected.overall_percent,
                    9,
                );
            }
        });
    }
});

describe('parseScore', () => {
    it.each([
        ['15', 15],
        ['12,5', 12.5],
        ['12.5', 12.5],
        ['015', 15],
        [' 0 ', 0],
        ['20', 20],
        ['', null],
    ])('accepts %s', (input, value) => {
        expect(parseScore(input, 20)).toEqual({ ok: true, value });
    });

    it.each(['21', '-1', 'abc', '12,3', '12.25', '1e1'])(
        'rejects %s',
        (input) => {
            expect(parseScore(input, 20).ok).toBe(false);
        },
    );
});

describe('reportComment', () => {
    it('falls back to the automatic comment', () => {
        const config = defaults['5'];
        const r = calculate(config, {
            listening: 15,
            reading: 17,
            writing: 11,
            speaking: 13,
        });

        expect(
            reportComment(r, 'Kevin Wijaya', '', { A2: 'can understand.' }),
        ).toBe(
            'Kevin Wijaya can understand. Strongest skill this term: Reading.',
        );
        expect(reportComment(r, 'Kevin', 'Great job!', {})).toBe('Great job!');
    });
});
