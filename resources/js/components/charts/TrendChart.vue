<script setup lang="ts">
import type { ChartData, ChartOptions, Plugin } from 'chart.js';
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import { GRADE_COLORS, nf } from '@/components/charts/setup';

/**
 * "Perbandingan periode" — average overall % per grade over the last ≤ 6 final periods.
 * Lines carry a direct label at their last point plus a legend (≤ 4 series).
 */
const props = defineProps<{
    labels: string[];
    series: { grade: number; values: (number | null)[] }[];
}>();

const data = computed<ChartData<'line'>>(() => ({
    labels: props.labels,
    datasets: props.series.map((s) => ({
        label: `Kelas ${s.grade}`,
        data: s.values,
        borderColor: GRADE_COLORS[s.grade],
        backgroundColor: GRADE_COLORS[s.grade],
        borderWidth: 2,
        pointRadius: 4,
        pointHoverRadius: 6,
        pointBorderColor: '#FFFFFF',
        pointBorderWidth: 2,
        spanGaps: true,
        tension: 0,
    })),
}));

const options: ChartOptions<'line'> = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    layout: { padding: { right: 56 } },
    scales: {
        x: { grid: { display: false } },
        y: {
            min: 0,
            max: 100,
            ticks: { callback: (v) => `${v}%` },
            grid: { color: '#EEF2F7' },
        },
    },
    plugins: {
        legend: { position: 'bottom' },
        tooltip: {
            callbacks: {
                label: (ctx) =>
                    `${ctx.dataset.label}: ${ctx.raw === null ? '–' : `${nf(Number(ctx.raw))}%`}`,
            },
        },
    },
};

const endLabels: Plugin<'line'> = {
    id: 'endLabels',
    afterDatasetsDraw(chart) {
        const { ctx } = chart;
        ctx.save();
        ctx.font = '600 11px sans-serif';
        ctx.fillStyle = '#374151';
        ctx.textBaseline = 'middle';

        chart.data.datasets.forEach((ds, i) => {
            const meta = chart.getDatasetMeta(i);
            const idx = (ds.data as (number | null)[])
                .map((v, j) => (v === null ? -1 : j))
                .filter((j) => j >= 0)
                .pop();

            if (!meta.hidden && idx !== undefined) {
                const pt = meta.data[idx];
                ctx.fillText(String(ds.label), pt.x + 8, pt.y);
            }
        });

        ctx.restore();
    },
};
</script>

<template>
    <div class="h-72">
        <Line
            :data="data"
            :options="options"
            :plugins="[endLabels]"
            aria-label="Rata-rata overall per jenjang antar periode"
            role="img"
        />
    </div>
</template>
