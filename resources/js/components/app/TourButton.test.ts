// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PageHeader from '@/components/app/PageHeader.vue';
import TourButton from '@/components/app/TourButton.vue';
import TourIconButton from '@/components/app/TourIconButton.vue';

describe('tutorial buttons (PRD-TUTOR §3.4)', () => {
    it('PageHeader without `tour` renders no Tutorial button', () => {
        const w = mount(PageHeader, { props: { title: 'Siswa' } });
        expect(w.find('[data-tour-button]').exists()).toBe(false);
    });

    it('PageHeader with `tour` renders the Tutorial button next to the title', () => {
        const w = mount(PageHeader, {
            props: { title: 'Siswa', tour: 'siswa-index' },
        });
        const btn = w.find('h1 + [data-tour-button]');
        expect(btn.exists()).toBe(true);
        expect(btn.attributes('aria-label')).toBe('Tutorial halaman ini');
        expect(btn.text()).toContain('Tutorial');
    });

    it('TourButton labels the page tour and is hidden text on phones', () => {
        const w = mount(TourButton, { props: { tour: 'log' } });
        expect(w.attributes('aria-label')).toBe('Tutorial halaman ini');
        expect(w.find('span').classes()).toContain('hidden');
        expect(w.find('span').text()).toBe('Tutorial');
    });

    it('TourIconButton is a compact "?" for dialogs', () => {
        const w = mount(TourIconButton, { props: { tour: 'flow-finalkan' } });
        expect(w.attributes('aria-label')).toBe('Tutorial');
        expect(w.attributes('data-tour-button')).toBeDefined();
    });
});
