<script setup lang="ts">
import { ref, watch } from 'vue';
import type { Level } from '@/lib/cefr';
import { cn } from '@/lib/utils';

/** CEFR level pill, always with the level code as text (PRD-07 §2.2, §5.2). */
const props = withDefaults(
    defineProps<{
        level: Level | null;
        size?: 'sm' | 'md' | 'lg' | 'xl';
        percent?: number | null;
    }>(),
    {
        size: 'md',
        percent: undefined,
    },
);

const COLORS: Record<Level, string> = {
    'Pre-A1': 'bg-[var(--level-pre-a1)] text-[var(--level-pre-a1-fg)]',
    A1: 'bg-[var(--level-a1)] text-[var(--level-a1-fg)]',
    A2: 'bg-[var(--level-a2)] text-[var(--level-a2-fg)]',
    B1: 'bg-[var(--level-b1)] text-[var(--level-b1-fg)]',
    B2: 'bg-[var(--level-b2)] text-[var(--level-b2-fg)]',
};

// "Pop" when the level changes (PRD-07 §6); disabled by prefers-reduced-motion in app.css.
const pop = ref(false);
watch(
    () => props.level,
    (now, before) => {
        if (now && before !== undefined && now !== before) {
            pop.value = false;
            requestAnimationFrame(() => (pop.value = true));
            setTimeout(() => (pop.value = false), 260);
        }
    },
);
</script>

<template>
    <span class="inline-flex flex-col items-center leading-none">
        <span
            :class="
                cn(
                    'inline-flex items-center justify-center rounded-full font-bold whitespace-nowrap transition-colors duration-200',
                    size === 'sm' && 'h-5 min-w-10 px-1.5 text-[11px]',
                    size === 'md' && 'h-6 min-w-12 px-2 text-xs',
                    size === 'lg' && 'h-8 min-w-16 px-3 text-sm',
                    size === 'xl' && 'h-14 min-w-24 px-4 text-2xl',
                    level
                        ? COLORS[level]
                        : 'border border-dashed border-muted-foreground/40 text-muted-foreground',
                    pop && 'animate-level-pop',
                )
            "
        >
            {{ level ?? '–' }}
        </span>
        <span
            v-if="percent !== undefined && percent !== null"
            class="mt-0.5 text-[10px] text-muted-foreground tabular-nums"
        >
            {{ percent.toLocaleString('id-ID', { maximumFractionDigits: 1 }) }}%
        </span>
    </span>
</template>
