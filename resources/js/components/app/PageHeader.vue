<script setup lang="ts">
import TourButton from '@/components/app/TourButton.vue';
import { usePageTour } from '@/composables/useTour';
import type { TourId } from '@/tours/types';

const props = defineProps<{
    title: string;
    description?: string;
    tour?: TourId;
    tourWaitFor?: string;
}>();

usePageTour(() => props.tour, { waitFor: props.tourWaitFor });
</script>

<template>
    <div
        class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
    >
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <h1 class="text-2xl font-bold tracking-tight md:text-[30px]">
                    {{ title }}
                </h1>
                <TourButton v-if="tour" :tour="tour" />
            </div>
            <p
                v-if="description"
                class="mt-1 text-sm text-muted-foreground md:text-base"
            >
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.default" class="flex flex-wrap gap-2">
            <slot />
        </div>
    </div>
</template>
