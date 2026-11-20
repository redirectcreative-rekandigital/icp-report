// @vitest-environment jsdom
import { mount, flushPromises } from '@vue/test-utils';
import { TooltipProvider } from 'reka-ui';
import { describe, expect, it, vi } from 'vitest';
import { defineComponent, h, ref } from 'vue';
import Dashboard from './Dashboard.vue';
import StudentShow from './Students/Show.vue';

// Inertia stand-ins. Page props are wrapped in a reactive ref like the real adapter does.
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            school: { name: 'SD Muhammadiyah Wonorejo', logoUrl: null },
            activeTerm: {
                id: 1,
                label: '2026/2027 • Semester 1',
                short: '2026/27 S1',
            },
            nav: { classes: [] },
            flash: {},
            session: { idleMinutes: 60 },
            sidebarOpen: true,
            app: { name: 'ICP' },
        },
        url: '/',
    }),
    router: {
        on: () => () => undefined,
        get: vi.fn(),
        visit: vi.fn(),
        post: vi.fn(),
        reload: vi.fn(),
        delete: vi.fn(),
    },
    Head: defineComponent(() => () => null),
    Link: defineComponent({
        props: ['href'],
        setup:
            (_, { slots }) =>
            () =>
                h('a', slots.default?.()),
    }),
    Deferred: defineComponent({
        props: ['data'],
        setup:
            (_, { slots }) =>
            () =>
                slots.default?.(),
    }),
    useForm: (data: object) => ({
        ...data,
        errors: {},
        processing: false,
        defaults: vi.fn(),
        reset: vi.fn(),
        clearErrors: vi.fn(),
        transform: () => ({ put: vi.fn() }),
    }),
}));

// AppLayout is covered elsewhere; render the page body only. Charts need a canvas jsdom lacks.
vi.mock('@/layouts/AppLayout.vue', () => ({
    default: defineComponent(
        (_, { slots }) =>
            () =>
                h('main', slots.default?.()),
    ),
}));
// Only the canvas renderer is stubbed: the real chart components still build data, colours and labels.
vi.mock('vue-chartjs', () => {
    const stub = (type: string) =>
        defineComponent({
            props: ['data', 'options', 'plugins'],
            setup: (p) => () =>
                h('div', {
                    'data-chart': type,
                    'data-series': (
                        p.data as { datasets: { label?: string }[] }
                    ).datasets
                        .map((d) => d.label)
                        .join(','),
                }),
        });

    return { Bar: stub('bar'), Line: stub('line'), Doughnut: stub('doughnut') };
});

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

function mountPage(component: object, props: Record<string, unknown>) {
    const reactiveProps = ref(props);
    const Wrapper = defineComponent(
        () => () =>
            h(TooltipProvider, null, () =>
                h(component as never, reactiveProps.value as never),
            ),
    );

    return mount(Wrapper, { attachTo: document.body });
}

const levels = {
    'Pre-A1': [],
    A1: ['Aditya Pratama'],
    A2: ['Kevin Wijaya'],
    B1: [],
    B2: [],
};

describe('Dashboard page', () => {
    it('renders KPIs, highlights, chart cards and class progress', async () => {
        const w = mountPage(Dashboard, {
            hasActiveTerm: true,
            classCount: 2,
            studentCount: 3,
            terms: [
                { id: 1, label: '2026/2027 • Semester 1', is_active: true },
            ],
            filters: {
                term: 1,
                grade: null,
                final_only: false,
                tab: 'ringkasan',
                cohort: 'same',
            },
            term: { id: 1, label: '2026/2027 • Semester 1' },
            kpis: {
                assessed: 2,
                students: 3,
                final: 1,
                classes: 2,
                not_final: ['3B'],
                average: 68.4,
                previous_average: 65,
                delta: 3.4,
                meeting_or_above: 50,
            },
            progress: [
                {
                    id: 1,
                    name: '3A',
                    grade: 3,
                    teacher: 'Ms. A',
                    status: 'final',
                    total: 2,
                    complete: 2,
                },
                {
                    id: 2,
                    name: '5A',
                    grade: 5,
                    teacher: null,
                    status: 'draft',
                    total: 1,
                    complete: 0,
                },
            ],
            charts: {
                byClass: [
                    {
                        id: 1,
                        class: '3A',
                        grade: 3,
                        correcting: false,
                        levels,
                        skills: {
                            listening: 80,
                            reading: 75,
                            writing: 70,
                            speaking: 75,
                        },
                        average: 75,
                    },
                ],
                byGrade: [
                    {
                        grade: 3,
                        levels: { 'Pre-A1': 0, A1: 1, A2: 1, B1: 0, B2: 0 },
                        skills: {
                            listening: 80,
                            reading: 75,
                            writing: 70,
                            speaking: 75,
                        },
                    },
                ],
                status: { below: 1, meeting: 1, above: 0, incomplete: 1 },
                trend: {
                    labels: ['2025/26 S2', '2026/27 S1'],
                    series: [{ grade: 3, values: [60, 68.4] }],
                },
                insights: [
                    {
                        tone: 'warning',
                        text: 'Kelas 5A belum diisi sama sekali.',
                        href: '/kelas/2/nilai',
                    },
                ],
            },
        });
        await vi.dynamicImportSettled();
        await flushPromises();
        const text = w.text();

        expect(text).toContain('Siswa dinilai');
        expect(text).toContain('Kelas final');
        expect(text).toContain('Kelas 5A belum diisi sama sekali.');
        expect(text).toContain('Sebaran level per kelas');
        expect(text).toContain('Perbandingan periode');
        expect(text).toContain('Progres kelas');
        expect(text).toContain('3,4 dari periode lalu');
        // Real chart components rendered with level / skill series.
        expect(
            w
                .find('[data-chart="bar"][data-series="Pre-A1,A1,A2,B1,B2"]')
                .exists(),
        ).toBe(true);
        expect(
            w
                .find('[data-series="Listening,Reading,Writing,Speaking"]')
                .exists(),
        ).toBe(true);
        expect(
            w.find('[data-chart="line"][data-series="Kelas 3"]').exists(),
        ).toBe(true);
        expect(text).toContain('Below Expected');
    });

    it('shows onboarding when the period has no classes', () => {
        const w = mountPage(Dashboard, {
            hasActiveTerm: false,
            classCount: 0,
            studentCount: 0,
            terms: [],
            filters: {
                term: null,
                grade: null,
                final_only: false,
                tab: 'ringkasan',
                cohort: 'same',
            },
            term: null,
        });

        expect(w.text()).toContain('Langkah persiapan');
    });

    it('renders the comparison tab', () => {
        const summary = {
            n: 2,
            average: 60,
            levels: { 'Pre-A1': 0, A1: 50, A2: 50, B1: 0, B2: 0 },
        };
        const w = mountPage(Dashboard, {
            hasActiveTerm: true,
            classCount: 2,
            studentCount: 3,
            terms: [
                { id: 1, label: 'A', is_active: true },
                { id: 2, label: 'B', is_active: false },
            ],
            filters: {
                term: 1,
                grade: null,
                final_only: false,
                tab: 'perbandingan',
                cohort: 'same',
            },
            term: { id: 1, label: 'A' },
            comparison: {
                a_id: 2,
                b_id: 1,
                a: { label: 'B', ...summary },
                b: { label: 'A', ...summary, average: 70 },
                grades: [{ grade: 4, a: summary, b: summary }],
                moves: { up: 2, same: 0, down: 0 },
            },
        });

        expect(w.text()).toContain('Naik level');
        expect(w.text()).toContain('Selisih (B − A)');
        expect(w.text()).toContain('+10');
    });
});

describe('Student profile page', () => {
    it('renders the progress summary, narrative and history table', async () => {
        const w = mountPage(StudentShow, {
            student: {
                id: 1,
                nis: '5001',
                name: 'Kevin Wijaya',
                gender: 'L',
                is_active: true,
                notes: null,
                has_scores: true,
                classroom_id: 3,
            },
            history: [],
            points: [
                {
                    enrollment_id: 1,
                    term: '2025/26 S2',
                    term_full: '',
                    classroom: { id: 1, name: '4A', grade: 4 },
                    pct: {
                        listening: 60,
                        reading: 65,
                        writing: 50,
                        speaking: 60,
                    },
                    levels: {
                        listening: 'A1',
                        reading: 'A1',
                        writing: 'A1',
                        speaking: 'A1',
                    },
                    overall_pct: 58.8,
                    overall_level: 'A1',
                    status: 'meeting',
                    source: 'final',
                    pdf_url: '/x.pdf',
                    change: null,
                    previous_level: null,
                },
                {
                    enrollment_id: 2,
                    term: '2026/27 S1',
                    term_full: '',
                    classroom: { id: 3, name: '5A', grade: 5 },
                    pct: {
                        listening: 75,
                        reading: 85,
                        writing: 55,
                        speaking: 65,
                    },
                    levels: {
                        listening: 'A2',
                        reading: 'B1',
                        writing: 'A2',
                        speaking: 'A2',
                    },
                    overall_pct: 70,
                    overall_level: 'A2',
                    status: 'meeting',
                    source: 'draft',
                    pdf_url: null,
                    change: 'up',
                    previous_level: 'A1',
                },
            ],
            latestLevel: 'A1',
            narrative: 'Kevin naik dari A1 ke A2.',
            classes: [],
        });
        await vi.dynamicImportSettled();
        await flushPromises();
        const text = w.text();

        expect(text).toContain('Perkembangan level');
        expect(text).toContain('Kevin naik dari A1 ke A2.');
        expect(text).toContain('Riwayat level per periode');
        expect(text).toContain('A1 → A2');
        expect(text).toContain('Draft');
    });
});
