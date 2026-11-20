<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
    Check,
    Clock,
    Lock,
    Pencil,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

/** Below/Meeting/Above/Belum lengkap/Draft/Final — always icon + text (PRD-07 §2.3). */
const props = defineProps<{
    status: 'below' | 'meeting' | 'above' | 'incomplete' | 'draft' | 'final';
    size?: 'sm' | 'md';
}>();

const config = computed(
    () =>
        ({
            below: {
                label: 'Below',
                icon: ArrowDown,
                cls: 'bg-status-below text-status-below-fg',
            },
            meeting: {
                label: 'Meeting',
                icon: Check,
                cls: 'bg-status-meeting text-status-meeting-fg',
            },
            above: {
                label: 'Above',
                icon: ArrowUp,
                cls: 'bg-status-above text-status-above-fg',
            },
            incomplete: {
                label: 'Belum lengkap',
                icon: Clock,
                cls: 'bg-status-incomplete text-status-incomplete-fg',
            },
            draft: {
                label: 'Draft',
                icon: Pencil,
                cls: 'bg-status-draft text-status-draft-fg',
            },
            final: {
                label: 'Final',
                icon: Lock,
                cls: 'bg-status-meeting text-status-meeting-fg',
            },
        })[props.status],
);
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex items-center gap-1 rounded-full font-semibold whitespace-nowrap',
                size === 'sm'
                    ? 'px-2 py-0.5 text-[11px]'
                    : 'px-2.5 py-0.5 text-xs',
                config.cls,
            )
        "
    >
        <component :is="config.icon" class="size-3" />
        {{ config.label }}
    </span>
</template>
