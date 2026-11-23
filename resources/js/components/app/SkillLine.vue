<script setup lang="ts">
import { computed } from 'vue';
import { SCORE_MAX, SCORE_MIN, SKILL_LABEL, SKILLS } from '@/lib/cefr';
import type { Skill } from '@/lib/cefr';

/**
 * Skill profile as a line chart (revisi 23-11-2026 item 5): y axis 5–25, x axis the four
 * skills, one dot per score joined by a line. Plain SVG, no chart library.
 */
const props = withDefaults(
    defineProps<{
        scores: Record<Skill, number | null>;
        width?: number;
        height?: number;
    }>(),
    { width: 220, height: 150 },
);

const pad = { left: 24, right: 10, top: 14, bottom: 22 };
const ticks = [5, 10, 15, 20, 25];

const x = (i: number) =>
    pad.left +
    ((i + 0.5) * (props.width - pad.left - pad.right)) / SKILLS.length;
const y = (v: number) =>
    pad.top +
    ((SCORE_MAX - v) * (props.height - pad.top - pad.bottom)) /
        (SCORE_MAX - SCORE_MIN);

const points = computed(() =>
    SKILLS.flatMap((s, i) => {
        const v = props.scores[s];

        return v === null ? [] : [{ s, v, cx: x(i), cy: y(v) }];
    }),
);
</script>

<template>
    <svg
        :width="width"
        :height="height"
        :viewBox="`0 0 ${width} ${height}`"
        role="img"
        aria-label="Profil skill"
    >
        <g v-for="t in ticks" :key="t">
            <line
                :x1="pad.left"
                :x2="width - pad.right"
                :y1="y(t)"
                :y2="y(t)"
                stroke="var(--border)"
            />
            <text
                :x="pad.left - 5"
                :y="y(t) + 3"
                text-anchor="end"
                font-size="9"
                fill="var(--muted-foreground)"
            >
                {{ t }}
            </text>
        </g>
        <text
            v-for="(s, i) in SKILLS"
            :key="`l-${s}`"
            :x="x(i)"
            :y="height - 6"
            text-anchor="middle"
            font-size="9"
            font-weight="600"
            fill="var(--muted-foreground)"
        >
            {{ SKILL_LABEL[s] }}
        </text>
        <polyline
            v-if="points.length > 1"
            :points="points.map((p) => `${p.cx},${p.cy}`).join(' ')"
            fill="none"
            stroke="var(--primary)"
            stroke-width="2"
        />
        <g v-for="p in points" :key="p.s">
            <circle
                :cx="p.cx"
                :cy="p.cy"
                r="3.5"
                :fill="`var(--skill-${p.s})`"
            />
            <text
                :x="p.cx"
                :y="p.cy - 6"
                text-anchor="middle"
                font-size="9"
                font-weight="700"
                fill="var(--foreground)"
            >
                {{ String(p.v).replace('.', ',') }}
            </text>
        </g>
    </svg>
</template>
