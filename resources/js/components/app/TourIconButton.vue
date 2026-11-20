<script setup lang="ts">
import { CircleHelp } from 'lucide-vue-next';
import { startTour } from '@/composables/useTour';
import type { TourId } from '@/tours/types';

/** Small "?" next to a dialog/panel title (PRD-TUTOR D5). */
defineProps<{ tour: TourId }>();
</script>

<template>
    <button
        type="button"
        class="inline-flex size-8 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        aria-label="Tutorial"
        title="Tutorial"
        data-tour-button
        @click="startTour(tour)"
    >
        <CircleHelp class="size-4" />
    </button>
</template>
