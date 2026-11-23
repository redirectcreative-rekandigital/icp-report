<script setup lang="ts">
import { Check } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

defineProps<{ current: 1 | 2 | 3 | 4 }>();

const steps = ['Sekolah', 'Periode & penilaian', 'Buat PIN', 'Kode pemulihan'];
</script>

<template>
    <ol class="flex items-center gap-2" aria-label="Langkah setup">
        <li
            v-for="(label, i) in steps"
            :key="label"
            class="flex flex-1 items-center gap-2"
            :aria-current="i + 1 === current ? 'step' : undefined"
        >
            <span
                :class="
                    cn(
                        'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-bold transition-colors',
                        i + 1 < current &&
                            'bg-secondary text-secondary-foreground',
                        i + 1 === current &&
                            'bg-primary text-primary-foreground',
                        i + 1 > current && 'bg-muted text-muted-foreground',
                    )
                "
            >
                <Check v-if="i + 1 < current" class="size-4" />
                <template v-else>{{ i + 1 }}</template>
            </span>
            <span
                :class="
                    cn(
                        'hidden text-xs font-medium sm:block',
                        i + 1 === current
                            ? 'text-foreground'
                            : 'text-muted-foreground',
                    )
                "
            >
                {{ label }}
            </span>
            <span
                v-if="i < steps.length - 1"
                class="h-px flex-1 bg-border"
                aria-hidden="true"
            />
        </li>
    </ol>
</template>
