<script setup lang="ts">
import { ref, watch } from 'vue';
import type { Band, Level } from '@/lib/cefr';
import { cn } from '@/lib/utils';

/** Band (Beginner/Intermediate/Advanced) or CEFR level pill, always with the name as text (PRD-07 §2.2, §5.2). */
const props = withDefaults(
    defineProps<{
        level: Band | Level | null;
        size?: 'sm' | 'md' | 'lg' | 'xl';
    }>(),
    {
        size: 'md',
    },
);

const COLORS: Record<Band | Level, string> = {
    Beginner: 'bg-[var(--band-beginner)] text-[var(--band-beginner-fg)]',
    Intermediate:
        'bg-[var(--band-intermediate)] text-[var(--band-intermediate-fg)]',
    Advanced: 'bg-[var(--band-advanced)] text-[var(--band-advanced-fg)]',
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
    </span>
</template>
