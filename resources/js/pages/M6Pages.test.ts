// @vitest-environment jsdom
import { flushPromises, mount } from '@vue/test-utils';
import { TooltipProvider } from 'reka-ui';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, ref } from 'vue';
import Documents from './Documents/Index.vue';
import Imports from './Imports/Index.vue';
import Backup from './Settings/Backup.vue';

const pageProps = {
    school: { name: 'SD Muhammadiyah Wonorejo', program: 'ICP', logoUrl: null },
    activeTerm: { id: 1, label: '2026/2027 • Semester 1', short: '2026/27 S1' },
    nav: { classes: [], showDocuments: true },
    flash: {},
    session: { idleMinutes: 60 },
    sidebarOpen: true,
    app: { name: 'ICP' },
};

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: pageProps, url: '/x' }),
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
    useForm: (data: Record<string, unknown>) => ({
        ...data,
        errors: {},
        processing: false,
        progress: null,
        defaults: vi.fn(),
        reset: vi.fn(),
        clearErrors: vi.fn(),
        transform: vi.fn(),
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

function mountPage(component: object, props: Record<string, unknown>) {
    const reactiveProps = ref(props);

    return mount(
        defineComponent(
            () => () =>
                h(TooltipProvider, null, () =>
                    h(component as never, reactiveProps.value as never),
                ),
        ),
        {
            attachTo: document.body,
        },
    );
}

beforeEach(() => {
    globalThis.fetch = vi.fn(
        async () =>
            new Response(JSON.stringify({ ref: 'ERR-TEST1' }), { status: 200 }),
    ) as never;
});

describe('Impor/Ekspor page', () => {
    const base = {
        term: { id: 1, label: '2026/2027 • Semester 1', classes: 2 },
        classes: [
            { id: 7, name: '5A', final: false },
            { id: 8, name: '3A', final: true },
        ],
        preview: null,
        result: null,
    };

    it('shows the three import cards and the exports', () => {
        const text = mountPage(Imports, base).text();

        expect(text).toContain('Impor siswa');
        expect(text).toContain('Impor nilai kelas');
        expect(text).toContain('Impor dari file Excel lama');
        expect(text).toContain('Rekap periode');
        expect(text).toContain('Timpa nilai yang sudah ada');
    });

    it('renders a score-import preview with sheet, changes and messages', () => {
        const w = mountPage(Imports, {
            ...base,
            preview: {
                type: 'excel-lama',
                filename: 'ICP_English_Progress_Report.xlsx',
                overwrite: true,
                class: null,
                summary: {
                    total: 2,
                    new: 1,
                    updated: 0,
                    unchanged: 0,
                    warning: 0,
                    error: 1,
                    importable: 1,
                },
                rows: [
                    {
                        line: 11,
                        sheet: 'Grade 3',
                        nipd: '8888',
                        name: 'Siswa Baru',
                        file_name: 'Siswa Baru',
                        class: '3B',
                        status: 'new',
                        messages: [],
                        changes: ['Siswa baru', 'Listening – → 11'],
                    },
                    {
                        line: 6,
                        sheet: 'Grade 5',
                        nipd: '5001',
                        name: 'Kevin',
                        file_name: 'Kevin',
                        class: '5C',
                        status: 'error',
                        messages: ["Baris 6 (Grade 5): Kelas '5C' tidak ada."],
                        changes: [],
                    },
                ],
            },
        });
        const text = w.text();

        expect(text).toContain('Pratinjau — Impor file Excel lama');
        expect(text).toContain('Timpa nilai aktif (perlu PIN)');
        expect(text).toContain('Grade 3');
        expect(text).toContain('Listening – → 11');
        expect(text).toContain("Kelas '5C' tidak ada.");
        expect(text).toContain('Impor 1 baris');
        expect(text).toContain('Unduh laporan galat');
    });
});

describe('Dokumen page', () => {
    it('groups documents per category with download links', () => {
        const text = mountPage(Documents, {
            documents: [
                {
                    id: 1,
                    title: 'Tes Kelas 5',
                    grade: 5,
                    category: 'tes',
                    original_name: 'tes5.pdf',
                    size: 204800,
                    extension: 'pdf',
                    updated_at: '2026-11-22T10:00:00+07:00',
                },
            ],
            categories: {
                tes: 'Instrumen Tes',
                pedoman: 'Pedoman Penskoran',
                lainnya: 'Lainnya',
            },
            filters: { grade: null },
        }).text();

        expect(text).toContain('Instrumen Tes');
        expect(text).toContain('Tes Kelas 5');
        expect(text).toContain('Kelas 5 • 200 KB');
        expect(text).toContain('Pedoman Penskoran');
    });

    it('shows an empty state', () => {
        expect(
            mountPage(Documents, {
                documents: [],
                categories: { tes: 'Instrumen Tes' },
                filters: { grade: null },
            }).text(),
        ).toContain('Belum ada dokumen');
    });
});

describe('Cadangan page', () => {
    it('renders download and restore sections', () => {
        const text = mountPage(Backup, {
            lastBackupAt: null,
            maxUploadMb: 50,
        }).text();

        expect(text).toContain('Unduh cadangan (.zip)');
        expect(text).toContain('Pulihkan dari cadangan');
        expect(text).toContain('PULIHKAN');
    });
});

describe('AppLayout error boundary', () => {
    it('shows ErrorState with a reference code instead of a blank page, and reports the error', async () => {
        const AppLayout = (await import('@/layouts/AppLayout.vue')).default;
        const Broken = defineComponent({
            name: 'BrokenWidget',
            setup() {
                throw new Error('boom');
            },
        });
        const w = mount(
            defineComponent(
                () => () => h(AppLayout, null, { default: () => h(Broken) }),
            ),
            { attachTo: document.body },
        );
        await flushPromises();
        await flushPromises();

        expect(w.text()).toContain('Terjadi kesalahan');
        expect(w.text()).toContain('ERR-TEST1');
        expect(globalThis.fetch).toHaveBeenCalledWith(
            '/client-errors',
            expect.objectContaining({ method: 'POST' }),
        );
    });
});
