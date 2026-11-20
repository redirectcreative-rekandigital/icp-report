<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

/** Native <select> styled like the inputs: reliable keyboard & phone pickers. */
defineOptions({ inheritAttrs: false });
const model = defineModel<string | number | null>();
defineProps<{ class?: string; invalid?: boolean }>();
</script>

<template>
    <div :class="cn('relative', $props.class)">
        <select
            v-model="model"
            :aria-invalid="invalid || undefined"
            class="h-9 w-full appearance-none rounded-md border border-input bg-card pr-8 pl-3 text-sm shadow-xs transition-colors focus-visible:outline-2 focus-visible:outline-ring disabled:opacity-50 aria-invalid:border-destructive"
            v-bind="$attrs"
        >
            <slot />
        </select>
        <ChevronDown
            class="pointer-events-none absolute top-1/2 right-2.5 size-4 -translate-y-1/2 text-muted-foreground"
        />
    </div>
</template>
