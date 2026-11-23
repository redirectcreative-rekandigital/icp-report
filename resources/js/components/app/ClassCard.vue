<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { cn } from '@/lib/utils';

/** Class card: name, teacher, completion bar, status (PRD-04 §3, PRD-08 §4.3). */
const props = defineProps<{
    id: number;
    name: string;
    homeroomTeacher: string | null;
    status: 'draft' | 'final';
    complete: number;
    total: number;
}>();

const pct = computed(() =>
    props.total ? Math.round((props.complete / props.total) * 100) : 0,
);
const barColor = computed(() =>
    pct.value >= 100
        ? 'bg-secondary'
        : pct.value >= 50
          ? 'bg-gold'
          : 'bg-muted-foreground/40',
);
</script>

<template>
    <Link
        :href="`/kelas/${id}`"
        data-tour="kelas-card"
        class="group block min-h-16 rounded-xl border bg-card p-4 shadow-sm transition-all duration-150 hover:-translate-y-0.5 hover:shadow-md focus-visible:outline-2 focus-visible:outline-ring"
    >
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-2xl leading-none font-bold">{{ name }}</p>
                <p class="mt-1 truncate text-sm text-muted-foreground">
                    {{ homeroomTeacher || 'Wali kelas belum diisi' }}
                </p>
            </div>
            <StatusBadge :status="status" data-tour="kelas-status" />
        </div>
        <div class="mt-4" data-tour="kelas-progress">
            <div
                class="mb-1 flex justify-between text-xs text-muted-foreground"
            >
                <span>{{ complete }} / {{ total }} siswa lengkap</span>
                <span class="tabular-nums">{{ pct }}%</span>
            </div>
            <div
                class="h-2 overflow-hidden rounded-full bg-muted"
                role="progressbar"
                :aria-valuenow="pct"
                aria-valuemin="0"
                aria-valuemax="100"
            >
                <div
                    :class="
                        cn(
                            'h-full rounded-full transition-[width] duration-300',
                            barColor,
                        )
                    "
                    :style="{ width: `${pct}%` }"
                />
            </div>
        </div>
        <p
            class="mt-3 text-sm font-semibold text-primary group-hover:underline"
        >
            {{
                status === 'final'
                    ? 'Rapor'
                    : total
                      ? 'Isi Nilai'
                      : 'Lihat kelas'
            }}
            →
        </p>
    </Link>
</template>
