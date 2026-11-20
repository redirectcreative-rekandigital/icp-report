<script setup lang="ts">
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';

/** Side form: right sheet on ≥ 640 px, full screen on phones (PRD-08 §3). */
defineProps<{ title: string; description?: string }>();
const open = defineModel<boolean>('open', { required: true });
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent class="w-full gap-0 p-0 sm:max-w-md">
            <SheetHeader class="border-b px-5 py-4">
                <SheetTitle>{{ title }}</SheetTitle>
                <SheetDescription v-if="description">{{
                    description
                }}</SheetDescription>
            </SheetHeader>
            <div class="flex-1 overflow-y-auto px-5 py-4">
                <slot />
            </div>
            <SheetFooter
                v-if="$slots.footer"
                class="flex-row justify-end gap-2 border-t px-5 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]"
            >
                <slot name="footer" />
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
