<script setup lang="ts">
import type { ChartData, ChartOptions } from 'chart.js';
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import { BAND_COLORS, segmentLabels } from '@/components/charts/setup';
import { BANDS } from '@/lib/cefr';
import type { Band } from '@/lib/cefr';

/** Band distribution as stacked vertical bars, student counts — per grade or per skill (PRD-06 §3.3). */
const props = defineProps<{
    groups: { label: string; levels: Record<Band, number> }[];
    chartLabel: string;
}>();

const data = computed<ChartData<'bar'>>(() => ({
    labels: props.groups.map((g) => g.label),
    datasets: BANDS.map((l) => ({
        label: l,
        data: props.groups.map((g) => g.levels[l]),
        backgroundColor: BAND_COLORS[l].bg,
        borderColor: '#FFFFFF',
        borderWidth: { top: 1, bottom: 1 },
        borderSkipped: false,
        borderRadius: 4,
        maxBarThickness: 48,
    })),
}));

const options: ChartOptions<'bar'> = {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        x: { stacked: true, grid: { display: false } },
        y: {
            stacked: true,
            beginAtZero: true,
            ticks: { precision: 0 },
            grid: { color: '#EEF2F7' },
            title: { display: true, text: 'Jumlah siswa' },
        },
    },
    plugins: {
        legend: { position: 'bottom' },
        tooltip: {
            callbacks: {
                label: (ctx) => `${ctx.dataset.label}: ${ctx.raw} siswa`,
            },
        },
    },
};

const plugins = [
    segmentLabels(
        BANDS.map((l) => BAND_COLORS[l].fg),
        16,
    ),
];
</script>

<template>
    <div class="h-72">
        <Bar
            :data="data"
            :options="options"
            :plugins="plugins"
            :aria-label="chartLabel"
            role="img"
        />
    </div>
</template>
