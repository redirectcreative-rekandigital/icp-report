<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { onKeyStroke, useDebounceFn } from '@vueuse/core';
import {
    ChevronLeft,
    ChevronRight,
    Copy,
    FileText,
    Star,
} from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import LevelBadge from '@/components/app/LevelBadge.vue';
import ScoreCell from '@/components/app/ScoreCell.vue';
import SkillRadar from '@/components/app/SkillRadar.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import TourIconButton from '@/components/app/TourIconButton.vue';
import type { ClassInfo, LevelMetaRow } from '@/components/scores/types';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import type { Field, ScoreRow } from '@/composables/useScoreAutosave';
import { isTourEvent, useDialogTour } from '@/composables/useTour';
import { reportComment, SKILL_LABEL, SKILLS } from '@/lib/cefr';
import type { ScoringConfig, StudentResult } from '@/lib/cefr';
import { formatPercent } from '@/lib/format';

const props = defineProps<{
    row: ScoreRow | null;
    result: StudentResult | null;
    config: ScoringConfig;
    levels: LevelMetaRow[];
    classroom: ClassInfo;
    locked: boolean;
    cells: Record<string, { status: string; message?: string }>;
    position: number;
    count: number;
    phone: boolean;
    /** Flow tutorial: "?" next to the title + auto-start once (PRD-TUTOR D5). */
    tour?: 'flow-panel-siswa';
}>();

const emit = defineEmits<{
    close: [];
    step: [delta: number];
    set: [field: Field, value: number | string | null];
}>();

const open = computed({
    get: () => !!props.row,
    set: (v) => !v && emit('close'),
});

useDialogTour(
    () => props.tour,
    () => open.value,
);

/** Clicks on the tutorial popover must not close the sheet (PRD-TUTOR §7.1). */
function onOutside(e: Event): void {
    if (isTourEvent(e)) {
        e.preventDefault();
    }
}
const comments = computed(() =>
    Object.fromEntries(props.levels.map((l) => [l.level, l.overall_comment])),
);
const auto = computed(() =>
    props.row && props.result
        ? reportComment(props.result, props.row.name, '', comments.value)
        : null,
);

const comment = ref('');
watch(
    () => props.row?.enrollment_id,
    () => {
        comment.value = props.row?.comment ?? '';
    },
    { immediate: true },
);

const saveComment = useDebounceFn(
    (value: string) =>
        emit('set', 'comment', value.trim() === '' ? null : value),
    800,
);

function onComment(value: string | number): void {
    comment.value = String(value).slice(0, 600);
    void saveComment(comment.value);
}

function useAuto(): void {
    if (auto.value) {
        onComment(auto.value);
    }
}

const inputs = ref<InstanceType<typeof ScoreCell>[]>([]);

function nextField(i: number): void {
    if (i < SKILLS.length - 1) {
        inputs.value[i + 1]?.focus();
    } else if (props.position < props.count) {
        // After Speaking: next student, first field (PRD-08 §5.3).
        emit('step', 1);
        void nextTick(() => inputs.value[0]?.focus());
    }
}

// [ and ] move between students (PRD-07 §12).
onKeyStroke(['[', ']'], (e) => {
    const t = e.target as HTMLElement;

    if (!props.row || ['INPUT', 'TEXTAREA'].includes(t.tagName)) {
        return;
    }

    e.preventDefault();
    emit('step', e.key === ']' ? 1 : -1);
});

const cellState = (field: Field) =>
    props.row && props.cells[`${props.row.enrollment_id}:${field}`];
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent
            :side="phone ? 'bottom' : 'right'"
            class="flex h-full w-full flex-col gap-0 p-0 sm:max-w-md"
            :class="phone && 'h-dvh'"
            @interact-outside="onOutside"
        >
            <template v-if="row && result">
                <SheetHeader class="border-b px-5 py-4 pr-12">
                    <div class="flex items-center gap-1">
                        <SheetTitle class="truncate">{{ row.name }}</SheetTitle>
                        <TourIconButton
                            v-if="tour"
                            :tour="tour"
                            class="-my-1"
                        />
                    </div>
                    <SheetDescription
                        >NIS {{ row.nis }} • Kelas {{ classroom.name }} •
                        {{ position }}/{{ count }}</SheetDescription
                    >
                </SheetHeader>

                <div class="flex-1 space-y-5 overflow-y-auto px-5 py-4">
                    <!-- Scores (large inputs) -->
                    <div
                        class="grid gap-2 landscape:max-md:grid-cols-2"
                        data-tour="panel-scores"
                    >
                        <div
                            v-for="(s, i) in SKILLS"
                            :key="s"
                            class="flex items-center gap-3"
                        >
                            <Label class="w-20 shrink-0">{{
                                SKILL_LABEL[s]
                            }}</Label>
                            <div class="w-24">
                                <ScoreCell
                                    ref="inputs"
                                    large
                                    :model-value="row.scores[s]"
                                    :max="config.max[s]"
                                    :locked="locked"
                                    :saving="cellState(s)?.status === 'saving'"
                                    :server-error="
                                        cellState(s)?.status === 'error' ||
                                        cellState(s)?.status === 'failed'
                                            ? cellState(s)?.message
                                            : null
                                    "
                                    :label="`${SKILL_LABEL[s]}, ${row.name}`"
                                    @commit="(v) => emit('set', s, v)"
                                    @navigate="
                                        (d) => d === 'down' && nextField(i)
                                    "
                                />
                            </div>
                            <span class="w-10 text-sm text-muted-foreground"
                                >/{{ config.max[s] }}</span
                            >
                            <LevelBadge
                                :level="result.level[s]"
                                :percent="result.percent[s]"
                            />
                        </div>
                    </div>

                    <!-- Summary -->
                    <div
                        class="flex items-center gap-4 rounded-xl border bg-muted/40 p-3"
                        data-tour="panel-summary"
                    >
                        <SkillRadar :percent="result.percent" :size="140" />
                        <div class="min-w-0 space-y-2 text-sm">
                            <div class="flex items-center gap-2">
                                <span class="text-muted-foreground"
                                    >Overall</span
                                >
                                <LevelBadge
                                    :level="result.overall_level"
                                    size="lg"
                                />
                            </div>
                            <p class="font-semibold tabular-nums">
                                {{ formatPercent(result.overall_percent) }}
                            </p>
                            <StatusBadge :status="result.status" />
                            <p
                                v-if="result.strongest"
                                class="flex items-center gap-1 text-muted-foreground"
                            >
                                <Star class="size-3.5 text-gold" /> Terkuat:
                                {{ SKILL_LABEL[result.strongest] }}
                            </p>
                        </div>
                    </div>

                    <!-- Comment -->
                    <div class="space-y-2">
                        <Label for="teacher-comment"
                            >Komentar guru (opsional, bahasa Inggris)</Label
                        >
                        <div
                            class="rounded-lg border border-dashed bg-muted/30 p-3 text-sm"
                            data-tour="panel-auto"
                        >
                            <p
                                class="mb-1 text-xs font-semibold text-muted-foreground uppercase"
                            >
                                Komentar otomatis
                            </p>
                            <p v-if="auto" class="italic">{{ auto }}</p>
                            <p v-else class="text-muted-foreground">
                                Muncul setelah keempat nilai terisi.
                            </p>
                        </div>
                        <Textarea
                            id="teacher-comment"
                            :model-value="comment"
                            rows="5"
                            maxlength="600"
                            :readonly="locked"
                            placeholder="Kosongkan untuk memakai komentar otomatis."
                            data-tour="panel-comment"
                            @update:model-value="onComment"
                        />
                        <div class="flex items-center justify-between gap-2">
                            <Button
                                v-if="!locked"
                                size="sm"
                                variant="outline"
                                :disabled="!auto"
                                @click="useAuto"
                            >
                                <Copy /> Gunakan komentar otomatis sebagai dasar
                            </Button>
                            <span
                                class="ml-auto text-xs text-muted-foreground tabular-nums"
                                >{{ comment.length }}/600</span
                            >
                        </div>
                        <p
                            v-if="cellState('comment')?.message"
                            class="text-sm text-destructive"
                        >
                            {{ cellState('comment')?.message }}
                        </p>
                    </div>

                    <Button variant="link" class="px-0" as-child>
                        <Link :href="`/kelas/${classroom.id}/rapor`"
                            ><FileText /> Pratinjau rapor</Link
                        >
                    </Button>
                </div>

                <div
                    class="flex gap-2 border-t px-5 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]"
                    data-tour="panel-nav"
                >
                    <Button
                        variant="outline"
                        class="flex-1"
                        :disabled="position <= 1"
                        @click="emit('step', -1)"
                    >
                        <ChevronLeft /> Sebelumnya
                    </Button>
                    <Button
                        class="flex-1"
                        :disabled="position >= count"
                        @click="emit('step', 1)"
                    >
                        Berikutnya <ChevronRight />
                    </Button>
                </div>
            </template>
        </SheetContent>
    </Sheet>
</template>
