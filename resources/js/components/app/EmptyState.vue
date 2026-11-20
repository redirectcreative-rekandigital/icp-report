<script setup lang="ts">
import type { LucideIcon } from 'lucide-vue-next';

/** Lucide icon in a pastel circle + title + description + actions (PRD-07 §9). */
defineProps<{
    icon: LucideIcon;
    title: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center px-6 py-12 text-center"
    >
        <div
            class="mb-4 flex size-16 items-center justify-center rounded-full bg-kpi-icon text-primary"
        >
            <component :is="icon" class="size-8" />
        </div>
        <h2 class="text-lg font-semibold text-foreground">{{ title }}</h2>
        <p
            v-if="description"
            class="mt-1 max-w-md text-sm text-muted-foreground"
        >
            {{ description }}
        </p>
        <div
            v-if="$slots.default"
            class="mt-5 flex flex-wrap justify-center gap-2"
        >
            <slot />
        </div>
    </div>
</template>
