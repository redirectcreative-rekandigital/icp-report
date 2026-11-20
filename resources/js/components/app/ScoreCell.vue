<script setup lang="ts">
import { useDebounceFn } from '@vueuse/core';
import { AlertTriangle, Lock } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatScore, parseScore } from '@/lib/cefr';
import { cn } from '@/lib/utils';

/**
 * Spreadsheet-like score input (PRD-04 §4.2). States: idle, focus, typing, invalid,
 * saving, saved, error/failed, locked. Commits on blur or 800 ms after typing stops.
 */
const props = withDefaults(
    defineProps<{
        modelValue: number | null;
        max: number;
        locked?: boolean;
        saving?: boolean;
        serverError?: string | null;
        label: string;
        cell?: string; // "row:col" for keyboard navigation
        large?: boolean;
    }>(),
    {
        locked: false,
        saving: false,
        serverError: null,
        cell: undefined,
        large: false,
    },
);

const emit = defineEmits<{
    commit: [value: number | null];
    navigate: [direction: 'up' | 'down' | 'next'];
    paste: [text: string];
}>();

const draft = ref(formatScore(props.modelValue));
const focused = ref(false);
const input = ref<HTMLInputElement | null>(null);

watch(
    () => props.modelValue,
    (v) => {
        if (!focused.value) {
            draft.value = formatScore(v);
        }
    },
);

const parsed = computed(() => parseScore(draft.value, props.max));
const invalid = computed(() => !parsed.value.ok);
const message = computed(() =>
    parsed.value.ok ? props.serverError : parsed.value.error,
);

function commit(): void {
    const p = parsed.value;

    if (p.ok && p.value !== props.modelValue) {
        emit('commit', p.value);
    }
}

const debounced = useDebounceFn(commit, 800);

function onInput(e: Event): void {
    const el = e.target as HTMLInputElement;
    const cleaned = el.value.replace(/[^0-9.,-]/g, '').slice(0, 4);

    if (cleaned !== el.value) {
        el.value = cleaned;
    }

    draft.value = cleaned;
    void debounced();
}

function onKeydown(e: KeyboardEvent): void {
    if (e.key === 'Enter' || (e.key === 'ArrowDown' && !e.altKey)) {
        e.preventDefault();
        commit();
        emit('navigate', e.shiftKey && e.key === 'Enter' ? 'up' : 'down');
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        commit();
        emit('navigate', 'up');
    } else if (e.key === 'Escape') {
        draft.value = formatScore(props.modelValue);
        input.value?.select();
    } else if (
        e.key === 'Delete' &&
        input.value?.selectionStart === 0 &&
        input.value.selectionEnd === draft.value.length
    ) {
        e.preventDefault();
        draft.value = '';
        commit();
    }
}

function onPaste(e: ClipboardEvent): void {
    const text = e.clipboardData?.getData('text') ?? '';

    // A block copied from Excel (tabs/newlines) is handled by the grid.
    if (/[\t\n]/.test(text.trim())) {
        e.preventDefault();
        emit('paste', text);
    }
}

function onFocus(): void {
    focused.value = true;
    requestAnimationFrame(() => input.value?.select());
}

function onBlur(): void {
    focused.value = false;
    commit();

    if (parsed.value.ok) {
        draft.value = formatScore(parsed.value.value);
    }
}

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
    <Tooltip :open="!!message && focused ? true : undefined">
        <TooltipTrigger as-child>
            <div class="relative">
                <input
                    ref="input"
                    :value="draft"
                    type="text"
                    inputmode="decimal"
                    autocomplete="off"
                    maxlength="4"
                    :readonly="locked"
                    :data-cell="cell"
                    :aria-label="label"
                    :aria-invalid="invalid || !!serverError || undefined"
                    :class="
                        cn(
                            'w-full rounded-md border text-center font-semibold tabular-nums transition-colors duration-150 outline-none',
                            large ? 'h-12 text-lg' : 'h-9 text-sm',
                            locked
                                ? 'cursor-default border-transparent bg-input-computed text-foreground'
                                : 'border-transparent bg-input-editable hover:border-gold/60 focus:border-primary focus:bg-card focus:ring-2 focus:ring-primary/30',
                            (invalid || serverError) &&
                                'border-destructive bg-status-below focus:border-destructive focus:ring-destructive/30',
                        )
                    "
                    @input="onInput"
                    @keydown="onKeydown"
                    @paste="onPaste"
                    @focus="onFocus"
                    @blur="onBlur"
                />
                <span
                    v-if="saving"
                    class="absolute top-1 right-1 size-1.5 animate-pulse-dot rounded-full bg-primary"
                    aria-hidden="true"
                />
                <AlertTriangle
                    v-else-if="invalid || serverError"
                    class="absolute top-1/2 right-1 size-3.5 -translate-y-1/2 text-destructive"
                    aria-hidden="true"
                />
                <Lock
                    v-else-if="locked && large"
                    class="absolute top-1/2 right-2 size-3.5 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
            </div>
        </TooltipTrigger>
        <TooltipContent
            v-if="message"
            side="top"
            class="bg-destructive text-destructive-foreground"
            >{{ message }}</TooltipContent
        >
    </Tooltip>
</template>
