<script setup lang="ts">
import { FileSpreadsheet, UploadCloud } from 'lucide-vue-next';
import { ref } from 'vue';
import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';

/** Drag & drop / pick a .xlsx/.xls/.csv file; size & type checked before upload (PRD-03 §8). */
const props = withDefaults(
    defineProps<{
        maxMb?: number;
        progress?: number | null;
        disabled?: boolean;
    }>(),
    {
        maxMb: 2,
        progress: null,
        disabled: false,
    },
);
const emit = defineEmits<{ file: [file: File] }>();

const over = ref(false);
const error = ref<string | null>(null);
const input = ref<HTMLInputElement | null>(null);

function check(file: File): void {
    error.value = null;
    const ext = file.name.split('.').pop()?.toLowerCase() ?? '';

    if (!['xlsx', 'xls', 'csv'].includes(ext)) {
        error.value = 'Format file harus .xlsx, .xls, atau .csv.';

        return;
    }

    if (file.size > props.maxMb * 1024 * 1024) {
        const mb = (file.size / 1024 / 1024).toLocaleString('id-ID', {
            maximumFractionDigits: 1,
        });
        error.value = `Ukuran file ${mb} MB melebihi batas ${props.maxMb} MB.`;

        return;
    }

    emit('file', file);
}

function onDrop(e: DragEvent): void {
    over.value = false;
    const file = e.dataTransfer?.files?.[0];

    if (file && !props.disabled) {
        check(file);
    }
}

function onPick(e: Event): void {
    const el = e.target as HTMLInputElement;
    const file = el.files?.[0];
    el.value = '';

    if (file) {
        check(file);
    }
}
</script>

<template>
    <div>
        <button
            type="button"
            :disabled="disabled"
            :class="
                cn(
                    'flex w-full flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-4 py-8 text-center transition-colors',
                    over
                        ? 'border-primary bg-accent'
                        : 'border-border bg-muted/40 hover:border-primary/60',
                    disabled && 'cursor-wait opacity-70',
                )
            "
            @click="input?.click()"
            @dragover.prevent="over = true"
            @dragleave.prevent="over = false"
            @drop.prevent="onDrop"
        >
            <UploadCloud v-if="progress === null" class="size-9 text-primary" />
            <FileSpreadsheet v-else class="size-9 animate-pulse text-primary" />
            <span class="font-semibold">
                {{
                    progress === null
                        ? 'Seret file ke sini atau klik untuk memilih'
                        : progress < 100
                          ? `Mengunggah… ${progress}%`
                          : 'Membaca file…'
                }}
            </span>
            <span class="text-sm text-muted-foreground"
                >.xlsx, .xls, .csv • maks. {{ maxMb }} MB • maks. 1.000
                baris</span
            >
            <Progress
                v-if="progress !== null"
                :model-value="progress"
                class="mt-2 h-2 w-full max-w-xs"
            />
        </button>
        <input
            ref="input"
            type="file"
            accept=".xlsx,.xls,.csv"
            class="sr-only"
            @change="onPick"
        />
        <p
            v-if="error"
            class="mt-2 text-sm font-medium text-destructive"
            role="alert"
        >
            {{ error }}
        </p>
    </div>
</template>
