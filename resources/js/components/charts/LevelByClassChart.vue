<script setup lang="ts">
import type { ChartData, ChartOptions } from 'chart.js';
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import { BAND_COLORS, nf, segmentLabels } from '@/components/charts/setup';
import type { ClassDist } from '@/components/charts/types';
import { BANDS } from '@/lib/cefr';
import type { Band } from '@/lib/cefr';

/**
 * "Sebaran level per kelas" — 100 % stacked horizontal bars (PRD-06 §3.3).
 * Clicking a segment opens the list of students at that level.
 */
const props = defineProps<{ classes: ClassDist[] }>();
const emit = defineEmits<{
    select: [payload: { class: string; level: Band; names: string[] }];
}>();

const totals = computed(() =>
    props.classes.map((c) => BANDS.reduce((n, l) => n + c.levels[l].length, 0)),
);

const data = computed<ChartData<'bar'>>(() => ({
    labels: props.classes.map((c) => (c.correcting ? `${c.class} *` : c.class)),
    datasets: BANDS.map((l) => ({
        label: l,
        data: props.classes.map((c, i) =>
            totals.value[i] ? (c.levels[l].length / totals.value[i]) * 100 : 0,
        ),
        backgroundColor: BAND_COLORS[l].bg,
        borderColor: '#FFFFFF',
        borderWidth: { left: 1, right: 1 }, // 2 px surface gap between segments
        borderSkipped: false,
        borderRadius: 4,
        barThickness: 22,
    })),
}));

const options = computed<ChartOptions<'bar'>>(() => ({
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        x: {
            stacked: true,
            max: 100,
            ticks: { callback: (v) => `${v}%` },
            grid: { color: '#EEF2F7' },
        },
        y: {
            stacked: true,
            grid: { display: false },
            ticks: { font: { weight: 'bold' } },
        },
    },
    plugins: {
        legend: { position: 'bottom' },
        tooltip: {
            callbacks: {
                label: (ctx) => {
                    const c = props.classes[ctx.dataIndex];
                    const n = c.levels[ctx.dataset.label as Band].length;

                    return `${ctx.dataset.label}: ${n} siswa (${nf(Number(ctx.raw), 0)}%)`;
                },
                footer: () => 'Klik untuk melihat daftar siswa',
            },
        },
    },
    onClick: (_e, elements) => {
        const el = elements[0];

        if (el) {
            const c = props.classes[el.index];
            const level = BANDS[el.datasetIndex];
            emit('select', { class: c.class, level, names: c.levels[level] });
        }
    },
    onHover: (e, elements) => {
        (e.native?.target as HTMLElement | undefined)?.style.setProperty(
            'cursor',
            elements.length ? 'pointer' : 'default',
        );
    },
}));

const plugins = [segmentLabels(BANDS.map((l) => BAND_COLORS[l].fg))];
const height = computed(() => Math.max(160, props.classes.length * 34 + 70));
</script>

<template>
    <div :style="{ height: `${height}px` }">
        <Bar
            :data="data"
            :options="options"
            :plugins="plugins"
            aria-label="Sebaran level per kelas"
            role="img"
        />
    </div>
    <p
        v-if="classes.some((c) => c.correcting)"
        class="mt-1 text-xs text-muted-foreground"
    >
        * sedang dikoreksi (memakai hasil final terakhir)
    </p>
</template>
