<script setup lang="ts">
import LevelBadge from '@/components/app/LevelBadge.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import CommentSnippet from '@/components/scores/CommentSnippet.vue';
import type { ResultMap } from '@/components/scores/types';
import type { ScoreRow } from '@/composables/useScoreAutosave';
import { formatScore, reportComment, SCORE_MAX, SKILLS } from '@/lib/cefr';
import type { Band } from '@/lib/cefr';

/**
 * Phone list replacing the table (PRD-08 §5.3); tapping a card opens the student form.
 * Three short lines: name + total/level, scores, then the comment preview.
 */
const props = defineProps<{
    rows: ScoreRow[];
    results: ResultMap;
    /** Grade 6: show the National Geographic level. */
    natgeo: boolean;
    locked: boolean;
    comments: Partial<Record<Band, string>>;
}>();
const emit = defineEmits<{ open: [row: ScoreRow] }>();

/** Same rule as the table and finalisation: four scores, plus NatGeo in grade 6. */
const incomplete = (row: ScoreRow) =>
    props.results.get(row.enrollment_id)!.total === null ||
    (props.natgeo && row.natgeo === null);

const autoComment = (row: ScoreRow) =>
    reportComment(
        props.results.get(row.enrollment_id)!,
        row.name,
        '',
        props.comments,
    );

const MISSING = 'rounded border border-dashed border-gold px-1';
</script>

<template>
    <ul class="grid gap-1.5">
        <li v-for="(row, i) in rows" :key="row.enrollment_id">
            <button
                type="button"
                class="w-full rounded-xl border px-3 py-2 text-left active:bg-muted"
                :class="[
                    i % 2 ? 'bg-zebra' : 'bg-card',
                    !row.is_active && 'opacity-60',
                ]"
                @click="emit('open', row)"
            >
                <div class="flex items-center gap-2">
                    <span
                        class="w-6 shrink-0 text-sm text-muted-foreground tabular-nums"
                        >{{ row.roll_no }}</span
                    >
                    <span class="min-w-0 flex-1 truncate font-semibold">{{
                        row.name
                    }}</span>
                    <span
                        v-if="results.get(row.enrollment_id)!.total !== null"
                        class="shrink-0 font-bold tabular-nums"
                        >{{ formatScore(results.get(row.enrollment_id)!.total)
                        }}<span
                            class="text-xs font-normal text-muted-foreground"
                            >/{{ SCORE_MAX * 4 }}</span
                        ></span
                    >
                    <StatusBadge
                        v-if="incomplete(row) && !locked"
                        status="incomplete"
                        size="sm"
                    />
                    <LevelBadge
                        v-else-if="
                            results.get(row.enrollment_id)!.overall_level
                        "
                        :level="results.get(row.enrollment_id)!.overall_level"
                        size="sm"
                    />
                </div>
                <div
                    class="mt-1 flex items-center gap-2 pl-8 text-sm text-muted-foreground tabular-nums"
                >
                    <span
                        v-if="natgeo"
                        :class="row.natgeo === null && !locked && MISSING"
                    >
                        NG<strong class="ml-0.5 text-foreground">{{
                            row.natgeo ?? '–'
                        }}</strong>
                    </span>
                    <span
                        v-for="s in SKILLS"
                        :key="s"
                        :class="row.scores[s] === null && !locked && MISSING"
                    >
                        {{ s.charAt(0).toUpperCase()
                        }}<strong class="ml-0.5 text-foreground">{{
                            formatScore(row.scores[s]) || '–'
                        }}</strong>
                    </span>
                </div>
                <CommentSnippet
                    class="mt-1 pl-8 text-sm"
                    :comment="row.comment"
                    :auto="autoComment(row)"
                />
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
