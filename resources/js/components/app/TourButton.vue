<script setup lang="ts">
import { CircleHelp } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { startTour } from '@/composables/useTour';
import type { TourId } from '@/tours/types';

/** "Tutorial" button placed right of a page title (PRD-TUTOR §3.4). */
defineProps<{ tour: TourId }>();
</script>

<template>
    <Button
        type="button"
        variant="outline"
        size="sm"
        class="h-8 shrink-0 gap-1.5 rounded-full px-2 align-middle text-xs font-semibold sm:px-3"
        aria-label="Tutorial halaman ini"
        title="Tutorial halaman ini"
        data-tour-button
        @click="startTour(tour)"
    >
        <CircleHelp class="size-4" />
        <span class="hidden sm:inline">Tutorial</span>
    </Button>
</template>
