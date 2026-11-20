<script setup lang="ts">
import type { ChartData, ChartOptions } from 'chart.js';
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import { nf, SKILL_COLORS } from '@/components/charts/setup';
import { SKILL_LABEL, SKILLS } from '@/lib/cefr';
import type { Skill } from '@/lib/cefr';

/** "Rata-rata per skill" — grouped bars, 4 skills × class or grade (PRD-06 §3.3). */
const props = defineProps<{
    groups: { label: string; skills: Record<Skill, number | null> }[];
}>();

const data = computed<ChartData<'bar'>>(() => ({
    labels: props.groups.map((g) => g.label),
    datasets: SKILLS.map((s) => ({
        label: SKILL_LABEL[s],
        data: props.groups.map((g) => g.skills[s]),
        backgroundColor: SKILL_COLORS[s],
        borderColor: '#FFFFFF',
        borderWidth: { left: 1, right: 1 },
        borderSkipped: false,
        borderRadius: { topLeft: 4, topRight: 4 },
        maxBarThickness: 14,
    })),
}));

const options: ChartOptions<'bar'> = {
    responsive: true,
    maintainAspectRatio: false,
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
</script>

<template>
    <div class="h-72">
        <Bar
            :data="data"
            :options="options"
            aria-label="Rata-rata persentase per skill"
            role="img"
        />
    </div>
</template>
