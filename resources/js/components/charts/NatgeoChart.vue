<script setup lang="ts">
import type { ChartData, ChartOptions } from 'chart.js';
import { computed } from 'vue';
import { Doughnut } from 'vue-chartjs';
import { LEVEL_COLORS, nf } from '@/components/charts/setup';
import { LEVELS } from '@/lib/cefr';
import type { Level } from '@/lib/cefr';

/**
 * Grade 6 National Geographic placement levels (Pre-A1–B2) — doughnut with a
 * level + count legend; "Belum diisi" = students without a level yet.
 */
const props = defineProps<{
    natgeo: Record<Level, number> & { empty: number };
}>();

const EMPTY = { bg: '#CBD5E1', label: 'Belum diisi' };
const filled = computed(() => LEVELS.reduce((n, l) => n + props.natgeo[l], 0));
const total = computed(() => filled.value + props.natgeo.empty);

const data = computed<ChartData<'doughnut'>>(() => ({
    labels: [...LEVELS, EMPTY.label],
    datasets: [
        {
            data: [...LEVELS.map((l) => props.natgeo[l]), props.natgeo.empty],
            backgroundColor: [
                ...LEVELS.map((l) => LEVEL_COLORS[l].bg),
                EMPTY.bg,
            ],
            borderColor: '#FFFFFF',
            borderWidth: 2,
            spacing: 1,
        },
    ],
}));

const options: ChartOptions<'doughnut'> = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '68%',
    plugins: {
        legend: { display: false }, // HTML legend below carries label + count
        tooltip: {
            callbacks: { label: (ctx) => `${ctx.label}: ${ctx.raw} siswa` },
        },
    },
};

const rows = computed(() => [
    ...LEVELS.map((l) => ({
        key: l,
        label: l,
        n: props.natgeo[l],
        ...LEVEL_COLORS[l],
    })),
    {
        key: 'empty',
        label: EMPTY.label,
        n: props.natgeo.empty,
        bg: EMPTY.bg,
        fg: '#111827',
    },
]);
</script>

<template>
    <div class="flex flex-col items-center gap-4 sm:flex-row">
        <div class="relative size-44 shrink-0">
            <Doughnut
                :data="data"
                :options="options"
                aria-label="Sebaran level National Geographic Kelas 6"
                role="img"
            />
            <div
                class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center"
            >
                <span class="text-2xl font-bold tabular-nums">{{
                    filled
                }}</span>
                <span class="text-xs text-muted-foreground">siswa Kelas 6</span>
            </div>
        </div>
        <ul class="grid w-full gap-1.5 text-sm">
            <li v-for="r in rows" :key="r.key" class="flex items-center gap-2">
                <span
                    class="inline-flex h-5 min-w-12 items-center justify-center rounded-full px-1.5 text-[11px] font-bold"
                    :style="{ background: r.bg, color: r.fg }"
                    >{{ r.key === 'empty' ? '–' : r.label }}</span
                >
                <span class="flex-1">{{
                    r.key === 'empty' ? r.label : ''
                }}</span>
                <strong class="tabular-nums">{{ r.n }}</strong>
                <span class="w-12 text-right text-muted-foreground tabular-nums"
                    >{{ total ? nf((r.n / total) * 100, 0) : 0 }}%</span
                >
            </li>
        </ul>
    </div>
</template>
