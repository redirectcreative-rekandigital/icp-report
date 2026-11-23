// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { TooltipProvider } from 'reka-ui';
import { describe, expect, it, vi } from 'vitest';
import { defineComponent, h, ref } from 'vue';
import fixture from '../../../../tests/fixtures/cefr-parity.json';
import ScoresTab from './ScoresTab.vue';

// Minimal Inertia stand-ins: the real page props are deep reactive proxies (the bug this guards).
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: { nav: { classes: [] }, flash: {} } }),
    router: {
        on: () => () => undefined,
        reload: vi.fn(),
        visit: vi.fn(),
        post: vi.fn(),
    },
    Link: defineComponent({
        props: ['href'],
        setup:
            (_, { slots }) =>
            () =>
                h('a', slots.default?.()),
    }),
    useForm: (data: object) => ({
        ...data,
        errors: {},
        processing: false,
        reset: vi.fn(),
        clearErrors: vi.fn(),
        post: vi.fn(),
    }),
}));

Object.defineProperty(window, 'matchMedia', {
    value: (q: string) => ({
        matches: q.includes('min-width'),
        media: q,
        addEventListener() {},
        removeEventListener() {},
        addListener() {},
        removeListener() {},
    }),
});
globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
} as never;

const row = (
    id: number,
    name: string,
    s: [number | null, number | null, number | null, number | null],
    comment: string | null = null,
) => ({
    enrollment_id: id,
    roll_no: id,
    student_id: id,
    nipd: `500${id}`,
    name,
    is_active: true,
    scores: { listening: s[0], reading: s[1], writing: s[2], speaking: s[3] },
    natgeo: null as string | null,
    comment,
    version: 1,
});

function mountGrid(status: 'draft' | 'final' = 'draft', grade = 5) {
    // Wrap the props in a reactive ref exactly like Inertia does.
    const page = ref({
        classroom: {
            id: 7,
            grade,
            name: `${grade}A`,
            homeroom_teacher: null,
            status,
            term: '2026/2027 • Semester 1',
            term_id: 1,
            finalized_at: null,
            last_event: null,
            complete: 1,
            total: 2,
        },
        config: { min: 5, max: 25, ...fixture.defaults },
        comments: {},
        natgeoLevels: grade === 6 ? ['Pre-A1', 'A1', 'A2', 'B1', 'B2'] : null,
        rows: [
            row(1, 'Kevin Wijaya', [15, 17, 11, 13]),
            row(2, 'Wulan Sari', [null, null, null, null]),
        ],
    });

    // In the app, AppLayout (SidebarProvider) supplies the TooltipProvider.
    const Wrapper = defineComponent(
        () => () =>
            h(TooltipProvider, null, () => h(ScoresTab, page.value as never)),
    );

    return mount(Wrapper, { attachTo: document.body });
}

describe('ScoresTab (Input Nilai)', () => {
    it('renders the student table from reactive Inertia props', () => {
        const w = mountGrid();
        const text = w.text();

        expect(text).toContain('Kevin Wijaya');
        expect(text).toContain('Wulan Sari');
        expect(text).toContain('1/2'); // summary: 1 of 2 complete
        expect(w.findAll('tbody tr')).toHaveLength(2);
        expect(w.findAll('input[data-cell]')).toHaveLength(8); // 2 students × 4 skills
        expect(
            (w.find('input[data-cell="0:0"]').element as HTMLInputElement)
                .value,
        ).toBe('15');
    });

    it('computes bands instantly in the browser (Kevin → total 56, Intermediate)', () => {
        const w = mountGrid();
        const firstRow = w.findAll('tbody tr')[0].text();

        expect(firstRow).toContain('Intermediate'); // Reading 17 ≥ 13, total 56/4 = 14 ≥ 13
        expect(firstRow).toContain('Beginner'); // Writing 11 < 13
        expect(firstRow).toContain('56');
        expect(firstRow).not.toContain('%');
        expect(w.text()).not.toContain('NatGeo');
    });

    it('grade 6: shows the NatGeo column and counts it for completeness', () => {
        const w = mountGrid('draft', 6);

        expect(w.text()).toContain('NatGeo');
        expect(w.findAll('tbody select')).toHaveLength(2);
        expect(w.text()).toContain('0/2'); // Kevin has 4 scores but no NatGeo level yet
    });

    it('makes the cells read-only when the class is final', () => {
        const w = mountGrid('final');

        expect(w.text()).toContain('Nilai terkunci');
        expect(
            w.find('input[data-cell="0:0"]').attributes('readonly'),
        ).toBeDefined();
    });
});
