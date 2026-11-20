<script setup lang="ts">
import { computed } from 'vue';
import { SKILL_LABEL, SKILLS } from '@/lib/cefr';
import type { Skill } from '@/lib/cefr';

/** Lightweight 4-axis radar in SVG (no chart library needed for the side panel). */
const props = withDefaults(
    defineProps<{ percent: Record<Skill, number | null>; size?: number }>(),
    { size: 180 },
);

const c = computed(() => props.size / 2);
const r = computed(() => props.size / 2 - 28);
// Listening top, Reading right, Writing bottom, Speaking left.
const angle = (i: number) => -Math.PI / 2 + (i * Math.PI) / 2;
const point = (i: number, pct: number) => {
    const d = (Math.max(0, Math.min(100, pct)) / 100) * r.value;

    return [c.value + d * Math.cos(angle(i)), c.value + d * Math.sin(angle(i))];
};

const polygon = computed(() =>
    SKILLS.map((s, i) => point(i, props.percent[s] ?? 0).join(',')).join(' '),
);
const rings = [25, 50, 75, 100];
const labelPos = (i: number) => {
    const d = r.value + 16;

    return {
        x: c.value + d * Math.cos(angle(i)),
        y: c.value + d * Math.sin(angle(i)) + 4,
    };
};
</script>

<template>
    <svg
        :width="size"
        :height="size"
        :viewBox="`0 0 ${size} ${size}`"
        role="img"
        aria-label="Profil skill"
    >
        <polygon
            v-for="ring in rings"
            :key="ring"
            :points="SKILLS.map((_, i) => point(i, ring).join(',')).join(' ')"
            fill="none"
            stroke="var(--border)"
        />
        <line
            v-for="(_, i) in SKILLS"
            :key="i"
            :x1="c"
            :y1="c"
            :x2="point(i, 100)[0]"
            :y2="point(i, 100)[1]"
            stroke="var(--border)"
        />
        <polygon
            :points="polygon"
            fill="var(--primary)"
            fill-opacity="0.18"
            stroke="var(--primary)"
            stroke-width="2"
        />
        <circle
            v-for="(s, i) in SKILLS"
            :key="s"
            :cx="point(i, percent[s] ?? 0)[0]"
            :cy="point(i, percent[s] ?? 0)[1]"
            r="3.5"
            :fill="`var(--skill-${s})`"
        />
        <text
            v-for="(s, i) in SKILLS"
            :key="`l-${s}`"
            :x="labelPos(i).x"
            :y="labelPos(i).y"
            text-anchor="middle"
            font-size="10"
            font-weight="600"
            fill="var(--muted-foreground)"
        >
            {{ SKILL_LABEL[s].charAt(0) }}
        </text>
    </svg>
</template>
