<script setup lang="ts">
import { AlertCircle, Check, CloudOff, Loader2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { cn } from '@/lib/utils';

/** Global autosave status (PRD-04 §4.3). */
const props = defineProps<{
    saving: boolean;
    pending: number;
    failed: number;
    online: boolean;
    savedAt: number | null;
    compact?: boolean;
}>();
const emit = defineEmits<{ retry: [] }>();

// "Tersimpan ✓" fades after 2 s.
const showSaved = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;
watch(
    () => props.savedAt,
    () => {
        showSaved.value = true;
        clearTimeout(timer);
        timer = setTimeout(() => (showSaved.value = false), 2000);
    },
);

const state = computed(() => {
    if (props.failed) {
        return 'failed';
    }

    if (props.saving) {
        return 'saving';
    }

    if (props.pending) {
        return 'pending';
    }

    if (showSaved.value) {
        return 'saved';
    }

    return 'idle';
});
</script>

<template>
    <div aria-live="polite" class="flex min-w-0 items-center">
        <button
            v-if="state === 'failed'"
            type="button"
            :class="
                cn(
                    'flex items-center gap-1.5 rounded-full bg-status-below px-2.5 py-1 text-xs font-semibold text-status-below-fg',
                    compact && 'px-1.5',
                )
            "
            @click="emit('retry')"
        >
            <AlertCircle class="size-4" />
            <span :class="compact && 'sr-only'"
                >Gagal menyimpan — Coba lagi</span
            >
        </button>
        <span
            v-else-if="state === 'saving'"
            class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground"
            :class="compact && 'text-white'"
        >
            <Loader2 class="size-4 animate-spin" /><span
                :class="compact && 'sr-only'"
                >Menyimpan…</span
            >
        </span>
        <span
            v-else-if="state === 'pending'"
            class="flex items-center gap-1.5 text-xs font-semibold text-status-incomplete-fg"
            :class="compact && 'text-gold'"
        >
            <CloudOff class="size-4" /><span :class="compact && 'sr-only'"
                >{{ pending }} perubahan menunggu{{
                    online ? '' : ' (offline)'
                }}</span
            >
        </span>
        <Transition
            leave-active-class="transition-opacity duration-500"
            leave-to-class="opacity-0"
        >
            <span
                v-if="state === 'saved'"
                class="flex items-center gap-1.5 text-xs font-semibold text-status-meeting-fg"
                :class="compact && 'text-white'"
            >
                <Check class="size-4" /><span :class="compact && 'sr-only'"
                    >Tersimpan</span
                >
            </span>
        </Transition>
    </div>
</template>
