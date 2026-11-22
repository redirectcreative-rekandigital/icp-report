<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';
import TourIconButton from '@/components/app/TourIconButton.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Drawer,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import {
    isTourActive,
    isTourEvent,
    useDialogTour,
} from '@/composables/useTour';
import type { TourId } from '@/tours/types';

/** Dialog on ≥ 768 px, bottom sheet below (PRD-08 §3). */
const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        dismissible?: boolean;
        /** Flow tutorial: "?" next to the title + auto-start once (PRD-TUTOR D5). */
        tour?: TourId;
        /** False while the dialog content is still loading: the tutorial waits for it. */
        tourReady?: boolean;
    }>(),
    { tourReady: true },
);

const open = defineModel<boolean>('open', { required: true });
const isDesktop = useMediaQuery('(min-width: 768px)');

useDialogTour(
    () => props.tour,
    () => open.value && props.tourReady,
);

/** Clicks on the tutorial popover must not close the dialog; nor may Esc meant for the tour. */
function onOutside(e: Event) {
    if (props.dismissible === false || isTourEvent(e)) {
        e.preventDefault();
    }
}

function onEscape(e: Event) {
    if (props.dismissible === false || isTourActive()) {
        e.preventDefault();
    }
}
</script>

<template>
    <Dialog v-if="isDesktop" v-model:open="open">
        <DialogContent
            class="sm:max-w-md"
            @interact-outside="onOutside"
            @escape-key-down="onEscape"
        >
            <DialogHeader>
                <div class="flex items-center gap-1 pr-6">
                    <DialogTitle>{{ title }}</DialogTitle>
                    <TourIconButton v-if="tour" :tour="tour" class="-my-1" />
                </div>
                <DialogDescription v-if="description">{{
                    description
                }}</DialogDescription>
            </DialogHeader>
            <slot />
            <DialogFooter v-if="$slots.footer">
                <slot name="footer" />
            </DialogFooter>
        </DialogContent>
    </Dialog>
    <Drawer v-else v-model:open="open" :dismissible="dismissible !== false">
        <DrawerContent
            class="pb-[env(safe-area-inset-bottom)]"
            @interact-outside="onOutside"
            @escape-key-down="onEscape"
        >
            <DrawerHeader class="text-left">
                <div class="flex items-center gap-1">
                    <DrawerTitle>{{ title }}</DrawerTitle>
                    <TourIconButton v-if="tour" :tour="tour" class="-my-1" />
                </div>
                <DrawerDescription v-if="description">{{
                    description
                }}</DrawerDescription>
            </DrawerHeader>
            <div class="px-4">
                <slot />
            </div>
            <DrawerFooter v-if="$slots.footer">
                <slot name="footer" />
            </DrawerFooter>
        </DrawerContent>
    </Drawer>
</template>
