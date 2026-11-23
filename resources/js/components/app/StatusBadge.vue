<script setup lang="ts">
import { Clock, Lock, Pencil } from 'lucide-vue-next';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

/** Belum lengkap / Draft / Final — always icon + text (PRD-07 §2.3). */
const props = defineProps<{
    status: 'incomplete' | 'draft' | 'final';
    size?: 'sm' | 'md';
}>();

const config = computed(
    () =>
        ({
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
