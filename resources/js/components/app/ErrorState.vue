<script setup lang="ts">
import { useClipboard } from '@vueuse/core';
import { Copy, RotateCw, TriangleAlert } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

/** Error message + retry + reference code (PRD-07 §5.2, §8.2). */
withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        errorRef?: string | null;
    }>(),
    {
        title: 'Terjadi kesalahan',
        description:
            'Bagian ini gagal ditampilkan. Muat ulang halaman; bila berulang, laporkan kode di bawah.',
        errorRef: null,
    },
);
const emit = defineEmits<{ retry: [] }>();
const { copy, copied } = useClipboard({ copiedDuring: 2000 });
</script>

<template>
    <div
        role="alert"
        class="flex flex-col items-center justify-center rounded-xl border bg-card px-6 py-12 text-center"
    >
        <div
            class="mb-4 flex size-16 items-center justify-center rounded-full bg-status-below text-status-below-fg"
        >
            <TriangleAlert class="size-8" />
        </div>
        <h2 class="text-lg font-semibold">{{ title }}</h2>
        <p class="mt-1 max-w-md text-sm text-muted-foreground">
            {{ description }}
        </p>
        <button
            v-if="errorRef"
            type="button"
            class="mt-3 flex items-center gap-2 rounded-md bg-muted px-3 py-1.5 font-mono text-sm"
            @click="copy(errorRef)"
        >
            {{ errorRef }} <Copy class="size-3.5" /><span
                class="font-sans text-xs text-muted-foreground"
                >{{ copied ? 'tersalin' : 'salin' }}</span
            >
        </button>
        <Button class="mt-5" @click="emit('retry')"
            ><RotateCw /> Muat ulang halaman</Button
        >
    </div>
</template>
