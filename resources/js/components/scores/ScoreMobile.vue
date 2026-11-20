<script setup lang="ts">
import LevelBadge from '@/components/app/LevelBadge.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import type { ResultMap } from '@/components/scores/types';
import type { ScoreRow } from '@/composables/useScoreAutosave';
import { formatScore, SKILLS } from '@/lib/cefr';
import type { ScoringConfig } from '@/lib/cefr';

/** Phone list replacing the table (PRD-08 §5.3); tapping a card opens the student form. */
defineProps<{
    rows: ScoreRow[];
    results: ResultMap;
    config: ScoringConfig;
    locked: boolean;
}>();
const emit = defineEmits<{ open: [row: ScoreRow] }>();
</script>

<template>
    <ul class="grid gap-2">
        <li v-for="row in rows" :key="row.enrollment_id">
            <button
                type="button"
                class="w-full rounded-xl border bg-card p-3 text-left active:bg-muted"
                :class="!row.is_active && 'opacity-60'"
                @click="emit('open', row)"
            >
                <div class="flex items-center gap-2">
                    <span
                        class="w-6 text-sm text-muted-foreground tabular-nums"
                        >{{ row.roll_no }}</span
                    >
                    <span class="min-w-0 flex-1 truncate font-semibold">{{
                        row.name
                    }}</span>
                    <StatusBadge
                        v-if="
                            results.get(row.enrollment_id)!.status ===
                            'incomplete'
                        "
                        status="incomplete"
                        size="sm"
                    />
                    <LevelBadge
                        v-else
                        :level="results.get(row.enrollment_id)!.overall_level"
                    />
                </div>
                <div
                    class="mt-1 flex items-center gap-3 pl-8 text-sm text-muted-foreground tabular-nums"
                >
                    <span v-for="s in SKILLS" :key="s">
                        {{ s.charAt(0).toUpperCase() }}
                        <strong class="text-foreground">{{
                            formatScore(row.scores[s]) || '–'
                        }}</strong>
                    </span>
                    <StatusBadge
                        v-if="
                            results.get(row.enrollment_id)!.status !==
                            'incomplete'
                        "
                        :status="results.get(row.enrollment_id)!.status"
                        size="sm"
                        class="ml-auto"
                    />
                </div>
            </button>
        </li>
        <li
            v-if="!rows.length"
            class="py-6 text-center text-sm text-muted-foreground"
        >
            Tidak ada siswa yang cocok.
        </li>
    </ul>
</template>
