<script setup lang="ts">
import type { ChartData, ChartOptions } from 'chart.js';
import { ArrowDown, ArrowUp, Check, Clock } from 'lucide-vue-next';
import { computed } from 'vue';
import { Doughnut } from 'vue-chartjs';
import { nf, STATUS_COLORS } from '@/components/charts/setup';

/** "Status terhadap harapan" — doughnut with an icon + label + count legend (PRD-06 §3.3). */
const props = defineProps<{
    status: Record<keyof typeof STATUS_COLORS, number>;
}>();

const keys = ['below', 'meeting', 'above', 'incomplete'] as const;
const icons = {
    below: ArrowDown,
    meeting: Check,
    above: ArrowUp,
    incomplete: Clock,
};
const total = computed(() => keys.reduce((n, k) => n + props.status[k], 0));
const assessed = computed(() => total.value - props.status.incomplete);

const data = computed<ChartData<'doughnut'>>(() => ({
    labels: keys.map((k) => STATUS_COLORS[k].label),
    datasets: [
        {
            data: keys.map((k) => props.status[k]),
            backgroundColor: keys.map((k) => STATUS_COLORS[k].bg),
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
        legend: { display: false }, // HTML legend below carries icon + label + count
        tooltip: {
            callbacks: { label: (ctx) => `${ctx.label}: ${ctx.raw} siswa` },
        },
    },
};
</script>

<template>
    <div class="flex flex-col items-center gap-4 sm:flex-row">
        <div class="relative size-44 shrink-0">
            <Doughnut
                :data="data"
                :options="options"
                aria-label="Status terhadap level harapan"
                role="img"
            />
            <div
                class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center"
            >
                <span class="text-2xl font-bold tabular-nums">{{
                    assessed
                }}</span>
                <span class="text-xs text-muted-foreground">siswa dinilai</span>
            </div>
        </div>
        <ul class="grid w-full gap-2 text-sm">
            <li v-for="k in keys" :key="k" class="flex items-center gap-2">
                <span
                    class="flex size-6 items-center justify-center rounded-full text-white"
                    :style="{ background: STATUS_COLORS[k].bg }"
                >
                    <component :is="icons[k]" class="size-3.5" />
                </span>
                <span class="flex-1">{{ STATUS_COLORS[k].label }}</span>
                <strong class="tabular-nums">{{ status[k] }}</strong>
                <span class="w-12 text-right text-muted-foreground tabular-nums"
                    >{{ total ? nf((status[k] / total) * 100, 0) : 0 }}%</span
                >
            </li>
        </ul>
    </div>
</template>
