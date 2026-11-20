import { describe, expect, it } from 'vitest';
import { isReactive, ref } from 'vue';
import { clone } from './clone';

describe('clone', () => {
    it('copies reactive Inertia-style props (structuredClone would throw)', () => {
        const page = ref({
            props: {
                rows: [
                    {
                        enrollment_id: 1,
                        scores: { listening: 15, reading: null },
                    },
                ],
            },
        });
        const rows = page.value.props.rows;

        expect(() => structuredClone(rows)).toThrow();

        const copy = clone(rows);
        expect(copy).toEqual([
            { enrollment_id: 1, scores: { listening: 15, reading: null } },
        ]);
        expect(isReactive(copy)).toBe(false);

        copy[0].scores.listening = 20;
        expect(rows[0].scores.listening).toBe(15);
    });
});
