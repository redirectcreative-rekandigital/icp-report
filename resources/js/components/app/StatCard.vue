<script setup lang="ts">
import { TransitionPresets, useTransition } from '@vueuse/core';
import type { LucideIcon } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/** KPI tile with a 600 ms count-up (PRD-06 §3.2, PRD-07 §6). */
const props = defineProps<{
    icon: LucideIcon;
    label: string;
    value: number | null;
    suffix?: string;
    digits?: number;
    of?: number | null;
    ring?: number | null; // 0–100: completion ring
}>();

const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
const source = ref(0);
const shown = useTransition(source, {
    duration: reduced ? 0 : 600,
    transition: TransitionPresets.easeOutCubic,
});
watch(
    () => props.value,
    (v) => (source.value = v ?? 0),
    { immediate: true },
);

const text = computed(() =>
    props.value === null
        ? '–'
        : shown.value.toLocaleString('id-ID', {
              minimumFractionDigits: props.digits ?? 0,
              maximumFractionDigits: props.digits ?? 0,
          }),
);

const C = 2 * Math.PI * 18;
</script>

<template>
    <div
        class="flex min-w-0 items-start gap-3 rounded-xl border bg-card p-4 shadow-sm"
    >
        <div
            class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-kpi-icon text-primary"
        >
            <component :is="icon" class="size-5" />
        </div>
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm text-muted-foreground">{{ label }}</p>
            <p class="text-2xl font-bold tabular-nums md:text-[28px]">
                {{ text
                }}<span v-if="value !== null && suffix" class="text-lg">{{
                    suffix
                }}</span>
                <span
                    v-if="of !== undefined && of !== null"
                    class="text-base font-semibold text-muted-foreground"
                >
                    / {{ of }}</span
                >
            </p>
            <div class="text-xs text-muted-foreground"><slot /></div>
        </div>
        <svg
            v-if="ring !== undefined && ring !== null"
            class="size-11 shrink-0 -rotate-90"
            viewBox="0 0 44 44"
            aria-hidden="true"
        >
            <circle
                cx="22"
                cy="22"
                r="18"
                fill="none"
                stroke="var(--muted)"
                stroke-width="5"
            />
            <circle
                cx="22"
                cy="22"
                r="18"
                fill="none"
                stroke="var(--secondary)"
                stroke-width="5"
                stroke-linecap="round"
                :stroke-dasharray="C"
                :stroke-dashoffset="C * (1 - Math.min(100, ring) / 100)"
                class="transition-[stroke-dashoffset] duration-500"
            />
        </svg>
    </div>
</template>
