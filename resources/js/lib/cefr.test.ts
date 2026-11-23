import { describe, expect, it } from 'vitest';
import fixture from '../../../tests/fixtures/cefr-parity.json';
import type { ScoringConfig, Scores } from './cefr';
import { calculate, parseScore, reportComment } from './cefr';

type Case = {
    name: string;
    override?: Partial<ScoringConfig>;
    scores: Scores;
    expected: ReturnType<typeof calculate>;
};

const defaults = { min: 5, max: 25, ...fixture.defaults } as ScoringConfig;

describe('band parity (tests/fixtures/cefr-parity.json)', () => {
    for (const c of fixture.cases as unknown as Case[]) {
        it(c.name, () => {
            const r = calculate(
                { ...defaults, ...(c.override ?? {}) },
                c.scores,
            );

            expect(r.level).toEqual(c.expected.level);
            expect(r.overall_level).toBe(c.expected.overall_level);
            expect(r.strongest).toBe(c.expected.strongest);
            expect(r.total).toBe(c.expected.total);
        });
    }
});

describe('parseScore', () => {
    it.each([
        ['15', 15],
        ['12,5', 12.5],
        ['12.5', 12.5],
        ['015', 15],
        [' 5 ', 5],
        ['25', 25],
        ['', null],
    ])('accepts %s', (input, value) => {
        expect(parseScore(input)).toEqual({ ok: true, value });
    });

    it.each(['26', '4.5', '0', '-1', 'abc', '12,3', '12.25', '1e1'])(
        'rejects %s',
        (input) => {
            expect(parseScore(input).ok).toBe(false);
        },
    );
});

describe('reportComment', () => {
    it('falls back to the automatic comment', () => {
        const r = calculate(defaults, {
            listening: 15,
            reading: 17,
            writing: 11,
            speaking: 13,
        });

        expect(
            reportComment(r, 'Kevin Wijaya', '', {
                Intermediate: 'can understand.',
            }),
        ).toBe(
            'Kevin Wijaya can understand. Strongest skill this term: Reading.',
        );
        expect(reportComment(r, 'Kevin', 'Great job!', {})).toBe('Great job!');
    });
});
