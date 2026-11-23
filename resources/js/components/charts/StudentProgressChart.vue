<script setup lang="ts">
import type {
    ChartData,
    ChartOptions,
    ScriptableLineSegmentContext,
} from 'chart.js';
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import { BAND_COLORS, nf, SKILL_COLORS } from '@/components/charts/setup';
import type { ProgressPoint } from '@/components/charts/types';
import { SKILL_LABEL, SKILLS } from '@/lib/cefr';

/**
 * A student's progress (PRD-06 §4.2): total (20–100) per period with points coloured by band,
 * dashed where the value is still a Draft. One y-axis only — the level is shown on the
 * points and in the tooltip instead of a second axis.
 */
const props = defineProps<{
    points: ProgressPoint[];
    mode: 'overall' | 'skills';
}>();

const isDraft = (i: number) => props.points[i]?.source === 'draft';
const dashed = (ctx: ScriptableLineSegmentContext) =>
    isDraft(ctx.p1DataIndex) || isDraft(ctx.p0DataIndex) ? [5, 4] : undefined;

const data = computed<ChartData<'line'>>(() => ({
    labels: props.points.map((p) => [p.term, `Kelas ${p.classroom.name}`]),
    datasets:
        props.mode === 'overall'
            ? [
                  {
                      label: 'Total',
                      data: props.points.map((p) => p.total),
                      borderColor: '#1F3A68',
                      borderWidth: 2,
                      pointRadius: 7,
                      pointHoverRadius: 9,
                      pointBackgroundColor: props.points.map(
                          (p) => BAND_COLORS[p.overall_level].bg,
                      ),
                      pointBorderColor: '#FFFFFF',
                      pointBorderWidth: 2,
                      segment: { borderDash: dashed },
                  },
              ]
            : SKILLS.map((s) => ({
                  label: SKILL_LABEL[s],
                  data: props.points.map((p) => p.scores[s]),
                  borderColor: SKILL_COLORS[s],
                  backgroundColor: SKILL_COLORS[s],
                  borderWidth: 2,
                  pointRadius: 4,
                  pointBorderColor: '#FFFFFF',
                  pointBorderWidth: 2,
                  segment: { borderDash: dashed },
              })),
}));

const options = computed<ChartOptions<'line'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    scales: {
        x: { grid: { display: false } },
        y: {
            // Total 20–100, a single skill 5–25.
            min: props.mode === 'overall' ? 20 : 5,
            max: props.mode === 'overall' ? 100 : 25,
            grid: { color: '#EEF2F7' },
        },
    },
    plugins: {
        legend: { display: props.mode === 'skills', position: 'bottom' },
        tooltip: {
            callbacks: {
                label: (ctx) => {
                    const p = props.points[ctx.dataIndex];
                    const v = nf(Number(ctx.raw));

                    return props.mode === 'overall'
                        ? `Total ${v} • ${p.overall_level}`
                        : `${ctx.dataset.label}: ${v}`;
                },
                footer: (items) =>
                    isDraft(items[0]?.dataIndex ?? -1)
                        ? 'Draft — belum final'
                        : '',
            },
        },
    },
}));
</script>

<template>
    <div class="h-72">
        <Line
            :data="data"
            :options="options"
            aria-label="Grafik perkembangan siswa"
            role="img"
        />
    </div>
</template>
