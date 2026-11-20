<script setup lang="ts">
import { useEventListener, useIntervalFn } from '@vueuse/core';
import { Check, Delete, Lock } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { formatCountdown } from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * 4-digit PIN keypad (PRD-02 §4.1, PRD-08 §5.1). Auto-submits on the 4th digit.
 * States: idle, error (shake), locked (countdown), success.
 */
const props = withDefaults(
    defineProps<{
        error?: string | null;
        lockoutSeconds?: number;
        processing?: boolean;
        success?: boolean;
        captureKeyboard?: boolean;
        compact?: boolean;
    }>(),
    {
        error: null,
        lockoutSeconds: 0,
        processing: false,
        success: false,
        captureKeyboard: true,
        compact: false,
    },
);

const emit = defineEmits<{ submit: [pin: string] }>();

const digits = ref('');
const shaking = ref(false);
const remaining = ref(props.lockoutSeconds);
const hiddenInput = ref<HTMLInputElement | null>(null);

const locked = computed(() => remaining.value > 0);
const disabled = computed(
    () => locked.value || props.processing || props.success,
);

const { pause, resume } = useIntervalFn(
    () => {
        remaining.value = Math.max(0, remaining.value - 1);

        if (remaining.value === 0) {
            pause();
        }
    },
    1000,
    { immediate: props.lockoutSeconds > 0 },
);

watch(
    () => props.lockoutSeconds,
    (v) => {
        remaining.value = v;

        if (v > 0) {
            resume();
        }
    },
);

watch(
    () => props.error,
    (e) => {
        if (e) {
            digits.value = '';
            shaking.value = true;
            setTimeout(() => (shaking.value = false), 320);
        }
    },
);

function press(d: string): void {
    if (disabled.value || digits.value.length >= 4) {
        return;
    }

    digits.value += d;

    if (digits.value.length === 4) {
        emit('submit', digits.value);
    }
}

function backspace(): void {
    if (!disabled.value) {
        digits.value = digits.value.slice(0, -1);
    }
}

function clear(): void {
    digits.value = '';
}

function onHiddenInput(e: Event): void {
    const el = e.target as HTMLInputElement;
    const value = el.value.replace(/\D/g, '').slice(0, 4);
    el.value = '';

    for (const d of value.slice(digits.value.length)) {
        press(d);
    }
}

useEventListener(window, 'keydown', (e: KeyboardEvent) => {
    if (
        !props.captureKeyboard ||
        e.target === hiddenInput.value ||
        e.altKey ||
        e.ctrlKey ||
        e.metaKey
    ) {
        return;
    }

    if (/^\d$/.test(e.key)) {
        e.preventDefault();
        press(e.key);
    } else if (e.key === 'Backspace') {
        e.preventDefault();
        backspace();
    }
});

defineExpose({ clear });

const keys = ['1', '2', '3', '4', '5', '6', '7', '8', '9'];
</script>

<template>
    <div class="flex flex-col items-center gap-5">
        <!-- Dots (tap to open the phone's numeric keypad) -->
        <div class="relative">
            <div
                :class="cn('flex gap-4', shaking && 'animate-shake')"
                aria-hidden="true"
            >
                <span
                    v-for="i in 4"
                    :key="i"
                    :class="
                        cn(
                            'size-4 rounded-full border-2 transition-colors duration-150',
                            success
                                ? 'border-status-meeting-fg bg-status-meeting-fg'
                                : error && !digits.length
                                  ? 'border-destructive'
                                  : i <= digits.length
                                    ? 'border-primary bg-primary'
                                    : 'border-muted-foreground/40',
                        )
                    "
                />
            </div>
            <input
                ref="hiddenInput"
                type="text"
                inputmode="numeric"
                autocomplete="off"
                maxlength="4"
                aria-label="PIN 4 digit"
                class="absolute inset-0 cursor-pointer opacity-0"
                :disabled="disabled"
                @input="onHiddenInput"
            />
        </div>

        <div class="min-h-10 text-center" aria-live="assertive">
            <p
                v-if="success"
                class="flex items-center gap-1.5 text-sm font-semibold text-status-meeting-fg"
            >
                <Check class="size-4" /> PIN benar
            </p>
            <template v-else-if="locked">
                <p
                    class="flex items-center justify-center gap-1.5 text-sm font-medium text-destructive"
                >
                    <Lock class="size-4" /> Coba lagi dalam
                </p>
                <p class="text-2xl font-bold text-destructive tabular-nums">
                    {{ formatCountdown(remaining) }}
                </p>
            </template>
            <p
                v-else-if="error"
                class="max-w-64 text-sm font-medium text-destructive"
            >
                {{ error }}
            </p>
        </div>

        <!-- Keypad 3×4 -->
        <div
            :class="cn('grid grid-cols-3', compact ? 'gap-2.5' : 'gap-3')"
            role="group"
            aria-label="Keypad PIN"
        >
            <button
                v-for="k in keys"
                :key="k"
                type="button"
                :disabled="disabled"
                :class="
                    cn(
                        'rounded-full bg-muted text-xl font-semibold text-foreground transition-colors duration-150 hover:bg-accent active:bg-primary active:text-primary-foreground disabled:opacity-40',
                        compact ? 'size-14' : 'size-14 sm:size-16',
                    )
                "
                @click="press(k)"
            >
                {{ k }}
            </button>
            <button
                type="button"
                :disabled="disabled || !digits.length"
                aria-label="Hapus digit"
                :class="
                    cn(
                        'flex items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-accent disabled:opacity-40',
                        compact ? 'size-14' : 'size-14 sm:size-16',
                    )
                "
                @click="backspace"
            >
                <Delete class="size-6" />
            </button>
            <button
                type="button"
                :disabled="disabled"
                :class="
                    cn(
                        'rounded-full bg-muted text-xl font-semibold text-foreground transition-colors duration-150 hover:bg-accent active:bg-primary active:text-primary-foreground disabled:opacity-40',
                        compact ? 'size-14' : 'size-14 sm:size-16',
                    )
                "
                @click="press('0')"
            >
                0
            </button>
            <button
                type="button"
                :disabled="disabled || digits.length !== 4"
                aria-label="Masuk"
                :class="
                    cn(
                        'flex items-center justify-center rounded-full bg-primary text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-40',
                        compact ? 'size-14' : 'size-14 sm:size-16',
                    )
                "
                @click="emit('submit', digits)"
            >
                <Check class="size-6" />
            </button>
        </div>
    </div>
</template>
