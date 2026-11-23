import {
    ArcElement,
    BarElement,
    CategoryScale,
    Chart,
    Filler,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';
import type { Plugin } from 'chart.js';
import type { Band, Level, Skill } from '@/lib/cefr';

/**
 * Chart.js setup shared by every chart (PRD-06 §3.3, dataviz rules):
 * recessive grid/axes, 2 px surface gaps between fills, Indonesian tooltips,
 * level codes drawn inside stacked segments so identity never relies on colour alone.
 */
Chart.register(
    ArcElement,
    BarElement,
    CategoryScale,
    Filler,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Tooltip,
);

Chart.defaults.font.family =
    "'Plus Jakarta Sans Variable', Inter, system-ui, sans-serif";
Chart.defaults.font.size = 12;
Chart.defaults.color = '#4B5563';
Chart.defaults.borderColor = '#E2E8F0';
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.plugins.legend.labels.boxWidth = 8;
Chart.defaults.plugins.legend.labels.boxHeight = 8;
Chart.defaults.plugins.tooltip.backgroundColor = '#111827';
Chart.defaults.plugins.tooltip.padding = 10;
Chart.defaults.plugins.tooltip.cornerRadius = 8;
Chart.defaults.plugins.tooltip.titleFont = { weight: 'bold' };

// Only tweak the duration: replacing the whole `animation` object drops Chart.js's own
// defaults (easing, interpolators) and an interrupted animation — e.g. switching a card to
// "Lihat tabel" and back — then throws "this._fn is not a function" and stops ALL charts.
if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
    Chart.defaults.animation = false;
} else {
    Chart.defaults.set('animation', { duration: 400 }); // merges into the existing defaults
}

/** PRD-07 §2.2 — validated with labels as secondary encoding (see M5 notes). */
export const LEVEL_COLORS: Record<Level, { bg: string; fg: string }> = {
    'Pre-A1': { bg: '#F4A261', fg: '#3B2A12' },
    A1: { bg: '#E9C46A', fg: '#3B2A12' },
    A2: { bg: '#1F7A70', fg: '#FFFFFF' },
    B1: { bg: '#457B9D', fg: '#FFFFFF' },
    B2: { bg: '#6A4C93', fg: '#FFFFFF' },
};

/** PRD-07 §2.4. */
export const SKILL_COLORS: Record<Skill, string> = {
    listening: '#2563EB',
    reading: '#059669',
    writing: '#D97706',
    speaking: '#DB2777',
};

/** Grades 3–6: existing PRD colours in an order that passes the CVD validator. */
export const GRADE_COLORS: Record<number, string> = {
    3: '#6A4C93',
    4: '#D97706',
    5: '#2563EB',
    6: '#059669',
};

/** Internal assessment bands (revisi 23-11-2026): red / yellow / green, labels drawn in-segment. */
export const BAND_COLORS: Record<Band, { bg: string; fg: string }> = {
    Beginner: { bg: '#D64545', fg: '#FFFFFF' },
    Intermediate: { bg: '#F2C230', fg: '#3B2A12' },
    Advanced: { bg: '#2E8B57', fg: '#FFFFFF' },
};

export const nf = (v: number, digits = 1) =>
    v.toLocaleString('id-ID', { maximumFractionDigits: digits });

/**
 * Draws the dataset label (e.g. "A2") centred in stacked bar segments that are large enough,
 * in the segment's text colour. `textColors` maps dataset index → colour.
 */
export function segmentLabels(
    textColors: string[],
    minSize = 22,
): Plugin<'bar'> {
    return {
        id: 'segmentLabels',
        afterDatasetsDraw(chart) {
            const { ctx } = chart;
            const horizontal = chart.options.indexAxis === 'y';
            ctx.save();
            ctx.font = `600 11px ${Chart.defaults.font.family}`;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';

            chart.data.datasets.forEach((ds, di) => {
                const meta = chart.getDatasetMeta(di);

                if (meta.hidden) {
                    return;
                }

                meta.data.forEach((el, i) => {
                    const value = Number(ds.data[i] ?? 0);

                    if (!value) {
                        return;
                    }

                    const bar = el as unknown as {
                        x: number;
                        y: number;
                        base: number;
                        width: number;
                        height: number;
                    };
                    const size = horizontal
                        ? Math.abs(bar.x - bar.base)
                        : Math.abs(bar.base - bar.y);

                    if (size < minSize) {
                        return;
                    }

                    const x = horizontal ? (bar.x + bar.base) / 2 : bar.x;
                    const y = horizontal ? bar.y : (bar.y + bar.base) / 2;
                    ctx.fillStyle = textColors[di] ?? '#111827';
                    ctx.fillText(String(ds.label ?? ''), x, y);
                });
            });

            ctx.restore();
        },
    };
}

export { Chart };
