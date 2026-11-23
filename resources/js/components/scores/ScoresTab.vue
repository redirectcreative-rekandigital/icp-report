<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import {
    CheckCircle2,
    Download,
    FileUp,
    Info,
    Lock,
    MessageSquare,
    Search,
    Undo2,
    Users,
} from 'lucide-vue-next';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import EmptyState from '@/components/app/EmptyState.vue';
import LevelBadge from '@/components/app/LevelBadge.vue';
import NativeSelect from '@/components/app/NativeSelect.vue';
import ResponsiveDialog from '@/components/app/ResponsiveDialog.vue';
import SaveIndicator from '@/components/app/SaveIndicator.vue';
import ScoreCell from '@/components/app/ScoreCell.vue';
import FinalizeDialog from '@/components/scores/FinalizeDialog.vue';
import ScoreMobile from '@/components/scores/ScoreMobile.vue';
import StudentPanel from '@/components/scores/StudentPanel.vue';
import type { GridProps } from '@/components/scores/types';
import UnfinalizeDialog from '@/components/scores/UnfinalizeDialog.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import type { Field, ScoreRow } from '@/composables/useScoreAutosave';
import {
    assignField,
    fieldValue,
    useScoreAutosave,
} from '@/composables/useScoreAutosave';
import {
    BANDS,
    calculate,
    formatScore,
    parseScore,
    SCORE_MAX,
    SKILL_LABEL,
    SKILLS,
} from '@/lib/cefr';
import type { Band, Level, Skill } from '@/lib/cefr';
import { clone } from '@/lib/clone';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';

const props = defineProps<GridProps & { rows: ScoreRow[] }>();

const page = usePage();
const isPhone = useMediaQuery('(max-width: 767px)');
const locked = computed(() => props.classroom.status === 'final');

const rows = ref<ScoreRow[]>(clone(props.rows));
const results = computed(
    () =>
        new Map(
            rows.value.map((r) => [
                r.enrollment_id,
                calculate(props.config, r.scores),
            ]),
        ),
);

/* ---------- autosave ---------- */
const autosave = useScoreAutosave({
    classroomId: props.classroom.id,
    rows,
    onProgress: (p) => {
        // Keep the sidebar "18/25" in sync without reloading (PRD-08 §2.2).
        const nav = page.props.nav?.classes.find(
            (c) => c.id === props.classroom.id,
        );

        if (nav) {
            nav.complete = p.complete;
            nav.total = p.total;
        }
    },
    onLocked: () => {
        toast.error(
            'Kelas ini sudah difinalkan di perangkat lain. Halaman dimuat ulang dalam mode baca.',
        );
        router.reload();
    },
});

watch(
    autosave.backOnline,
    (n) =>
        n &&
        setTimeout(
            () =>
                !autosave.pendingCount.value &&
                toast.success(`${n} perubahan berhasil disimpan.`),
            1500,
        ),
);

function setScore(
    row: ScoreRow,
    field: Field,
    value: number | string | null,
): void {
    if (locked.value || fieldValue(row, field) === value) {
        return;
    }

    assignField(row, field, value);
    autosave.enqueue(row, field, value);
}

/** Complete = four scores, plus the NatGeo level in grade 6 (same rule as finalisation). */
function isComplete(row: ScoreRow): boolean {
    return (
        results.value.get(row.enrollment_id)!.total !== null &&
        (!props.natgeoLevels || row.natgeo !== null)
    );
}

/* ---------- filters & summary ---------- */
const q = ref('');
const show = ref<'all' | 'incomplete' | 'complete'>('all');

const visible = computed(() => {
    const term = q.value.trim().toLowerCase();

    return rows.value.filter((r) => {
        const complete = isComplete(r);

        return (
            (!term ||
                r.name.toLowerCase().includes(term) ||
                r.nis.includes(term)) &&
            (show.value === 'all' || (show.value === 'complete') === complete)
        );
    });
});

const summary = computed(() => {
    const all = [...results.value.values()];
    const scored = all.filter((r) => r.total !== null);
    const dist = Object.fromEntries(
        BANDS.map((b) => [
            b,
            scored.filter((r) => r.overall_level === b).length,
        ]),
    ) as Record<Band, number>;
    const avg = scored.length
        ? scored.reduce((s, r) => s + (r.total ?? 0), 0) / scored.length
        : null;

    return {
        complete: rows.value.filter(isComplete).length,
        total: all.length,
        avg,
        dist,
    };
});

const thresholds = computed(
    () =>
        `Intermediate ≥ ${formatScore(props.config.intermediate)} • Advanced ≥ ${formatScore(props.config.advanced)}`,
);

/* ---------- keyboard navigation & paste (PRD-04 §4.2) ---------- */
function focusCell(rowIdx: number, colIdx: number): void {
    const el = document.querySelector<HTMLInputElement>(
        `[data-cell="${rowIdx}:${colIdx}"]`,
    );
    el?.focus();
}

function navigate(
    rowIdx: number,
    colIdx: number,
    dir: 'up' | 'down' | 'next',
): void {
    const next = dir === 'up' ? rowIdx - 1 : rowIdx + 1;

    if (next >= 0 && next < visible.value.length) {
        focusCell(next, colIdx);
    }
}

type PasteCell = { row: ScoreRow; field: Skill; value: number | null };
const pastePlan = ref<{
    cells: PasteCell[];
    overwrite: number;
    invalid: number;
} | null>(null);
const pasteOpen = computed({
    get: () => !!pastePlan.value,
    set: (v) => !v && (pastePlan.value = null),
});

function onPaste(rowIdx: number, colIdx: number, text: string): void {
    if (locked.value) {
        return;
    }

    const matrix = text
        .replace(/\r/g, '')
        .replace(/\n$/, '')
        .split('\n')
        .map((line) => line.split('\t'));
    const cells: PasteCell[] = [];
    let invalid = 0;

    matrix.forEach((cols, i) => {
        const row = visible.value[rowIdx + i];

        cols.forEach((raw, j) => {
            const field = SKILLS[colIdx + j];

            if (!row || !field) {
                return;
            }

            const p = parseScore(raw);

            if (p.ok) {
                cells.push({ row, field, value: p.value });
            } else {
                invalid++;
            }
        });
    });

    const overwrite = cells.filter(
        (c) =>
            c.row.scores[c.field] !== null && c.row.scores[c.field] !== c.value,
    ).length;
    const plan = { cells, overwrite, invalid };

    if (overwrite > 1) {
        pastePlan.value = plan;
    } else {
        applyPaste(plan);
    }
}

function applyPaste(plan: { cells: PasteCell[]; invalid: number }): void {
    plan.cells.forEach((c) => setScore(c.row, c.field, c.value));
    pastePlan.value = null;
    toast.success(
        `${plan.cells.length} nilai ditempel.` +
            (plan.invalid
                ? ` ${plan.invalid} nilai tidak valid dilewati.`
                : ''),
    );
}

/* ---------- panel, finalisation ---------- */
const panelRow = ref<ScoreRow | null>(null);
const finalizeOpen = ref(false);
const unfinalizeOpen = ref(false);

function openPanel(row: ScoreRow): void {
    panelRow.value = row;
}

function stepPanel(delta: number): void {
    if (!panelRow.value) {
        return;
    }

    const list = isPhone.value ? rows.value : visible.value;
    const i = list.indexOf(panelRow.value) + delta;

    if (i >= 0 && i < list.length) {
        panelRow.value = list[i];
    }
}

function jumpTo(enrollmentId: number): void {
    finalizeOpen.value = false;
    q.value = '';
    show.value = 'all';
    void nextTick(() => {
        const idx = visible.value.findIndex(
            (r) => r.enrollment_id === enrollmentId,
        );
        const row = visible.value[idx];
        const firstEmpty = row
            ? Math.max(
                  0,
                  SKILLS.findIndex((s) => row.scores[s] === null),
              )
            : 0;

        if (isPhone.value) {
            panelRow.value = row ?? null;
        } else {
            focusCell(idx, firstEmpty);
        }
    });
}

/* ---------- leaving with unsaved changes (PRD-04 §4.6) ---------- */
const leaveUrl = ref<string | null>(null);
const leaveOpen = computed({
    get: () => !!leaveUrl.value,
    set: (v) => !v && (leaveUrl.value = null),
});
let bypass = false;

const offBefore = router.on('before', (event) => {
    const visit = event.detail.visit;

    if (
        !bypass &&
        autosave.pendingCount.value > 0 &&
        visit.method === 'get' &&
        !visit.only?.length
    ) {
        event.preventDefault();
        leaveUrl.value = visit.url.href;
    }
});
onUnmounted(offBefore);

function leave(): void {
    bypass = true;
    const url = leaveUrl.value!;
    leaveUrl.value = null;
    router.visit(url);
}

const lastEvent = computed(() => props.classroom.last_event);
defineExpose({ pendingCount: autosave.pendingCount });
</script>

<template>
    <!-- Header actions -->
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <!-- Tour anchor on the wrapper: SaveIndicator is empty (0×0) while idle. -->
        <div
            class="flex flex-wrap items-center gap-2"
            data-tour="save-indicator"
        >
            <span
                class="rounded-full bg-kpi-icon px-3 py-1 text-sm font-semibold text-primary"
                :title="`Batas level per skill (nilai 5–${SCORE_MAX})`"
                >{{ thresholds }}</span
            >
            <SaveIndicator
                :saving="autosave.saving.value"
                :pending="autosave.pendingCount.value"
                :failed="autosave.failedCount.value"
                :online="autosave.online.value"
                :saved-at="autosave.savedAt.value"
                @retry="autosave.retryFailed"
            />
        </div>
        <div class="ml-auto flex flex-wrap gap-2">
            <Button
                v-if="!locked"
                variant="outline"
                size="sm"
                as="a"
                data-tour="score-import"
                :href="`/impor?kelas=${classroom.id}`"
            >
                <FileUp /> Impor Nilai
            </Button>
            <Button
                v-if="!locked"
                variant="outline"
                size="sm"
                as="a"
                :href="`/impor/template/nilai/${classroom.id}`"
            >
                <Download /> Unduh Template
            </Button>
            <Button
                variant="outline"
                size="sm"
                as="a"
                :href="`/ekspor/kelas/${classroom.id}`"
            >
                <Download /> Ekspor
            </Button>
            <Button
                v-if="!locked"
                size="sm"
                data-tour="finalize-btn"
                :disabled="!rows.length"
                @click="finalizeOpen = true"
                ><Lock /> Finalkan</Button
            >
            <Button
                v-else
                size="sm"
                variant="outline"
                class="border-destructive/40 text-destructive"
                @click="unfinalizeOpen = true"
            >
                <Undo2 /> Batalkan Finalisasi
            </Button>
        </div>
    </div>

    <!-- Banners -->
    <div
        v-if="locked"
        class="mb-4 flex items-start gap-2 rounded-lg border border-status-meeting-fg/30 bg-status-meeting px-4 py-3 text-sm text-status-meeting-fg"
    >
        <CheckCircle2 class="mt-0.5 size-4 shrink-0" />
        Kelas ini sudah final pada {{ formatDateTime(classroom.finalized_at) }}.
        Nilai terkunci.
    </div>
    <div
        v-else-if="lastEvent?.action === 'unfinalize'"
        class="mb-4 flex items-start gap-2 rounded-lg border border-status-incomplete-fg/30 bg-status-incomplete px-4 py-3 text-sm text-status-incomplete-fg"
    >
        <Info class="mt-0.5 size-4 shrink-0" />
        Finalisasi dibatalkan pada {{ formatDateTime(lastEvent.at) }}. Alasan:
        {{ lastEvent.reason }}. Finalkan kembali setelah koreksi.
    </div>
    <div
        v-if="autosave.restorableCount.value"
        class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-gold/50 bg-tag px-4 py-3 text-sm text-tag-foreground"
    >
        <Info class="size-4 shrink-0" />
        Ada {{ autosave.restorableCount.value }} perubahan yang belum tersimpan
        dari sesi sebelumnya.
        <span class="ml-auto flex gap-2">
            <Button size="sm" :disabled="locked" @click="autosave.restore"
                >Simpan sekarang</Button
            >
            <Button
                size="sm"
                variant="ghost"
                @click="autosave.discardRestorable"
                >Buang</Button
            >
        </span>
    </div>

    <Card v-if="!rows.length">
        <EmptyState
            :icon="Users"
            :title="`Kelas ${classroom.name} belum memiliki siswa`"
            description="Tambahkan siswa atau impor dari Excel."
        />
    </Card>

    <template v-else>
        <!-- Summary bar (sticky) -->
        <div
            class="sticky top-14 z-20 -mx-4 mb-3 flex flex-wrap items-center gap-x-4 gap-y-2 border-b bg-background/95 px-4 py-2 text-sm backdrop-blur md:top-16 md:mx-0 md:rounded-lg md:border md:bg-card md:px-4"
        >
            <span
                ><strong class="tabular-nums"
                    >{{ summary.complete }}/{{ summary.total }}</strong
                >
                lengkap</span
            >
            <span class="text-muted-foreground"
                >Belum lengkap {{ summary.total - summary.complete }}</span
            >
            <span
                >Rata-rata total
                <strong>{{
                    summary.avg === null
                        ? '–'
                        : formatScore(Math.round(summary.avg * 10) / 10)
                }}</strong></span
            >
            <span class="flex flex-wrap gap-1">
                <span
                    v-for="l in BANDS"
                    v-show="summary.dist[l]"
                    :key="l"
                    class="inline-flex items-center gap-1"
                >
                    <LevelBadge :level="l" size="sm" /><span
                        class="text-xs tabular-nums"
                        >{{ summary.dist[l] }}</span
                    >
                </span>
            </span>
        </div>

        <!-- Tools -->
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <div class="relative min-w-48 flex-1 md:max-w-xs">
                <Search
                    class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="q"
                    type="search"
                    placeholder="Cari siswa…"
                    class="h-9 bg-card pl-9"
                />
            </div>
            <NativeSelect v-model="show" class="w-40" aria-label="Tampilkan">
                <option value="all">Semua</option>
                <option value="incomplete">Belum lengkap</option>
                <option value="complete">Lengkap</option>
            </NativeSelect>
        </div>

        <ScoreMobile
            v-if="isPhone"
            data-tour="score-list"
            :rows="visible"
            :results="results"
            :natgeo="!!natgeoLevels"
            :locked="locked"
            @open="openPanel"
        />

        <!-- Desktop / tablet grid -->
        <Card v-else data-tour="score-grid" class="overflow-hidden p-0">
            <div class="max-h-[calc(100dvh-16rem)] overflow-auto">
                <table class="w-full border-separate border-spacing-0 text-sm">
                    <thead
                        class="sticky top-0 z-10 bg-muted text-xs font-semibold text-muted-foreground"
                    >
                        <tr>
                            <th
                                class="sticky left-0 z-20 w-10 bg-muted px-2 py-2 text-left"
                            >
                                No
                            </th>
                            <th
                                class="sticky left-10 z-20 min-w-48 border-r bg-muted px-2 py-2 text-left"
                            >
                                Nama (NIS)
                            </th>
                            <th
                                v-for="s in SKILLS"
                                :key="s"
                                class="w-20 px-1 py-2"
                            >
                                <span class="inline-flex items-center gap-1">
                                    <Lock v-if="locked" class="size-3" />{{
                                        SKILL_LABEL[s]
                                    }}
                                </span>
                                <span class="block font-normal"
                                    >5–{{ SCORE_MAX }}</span
                                >
                            </th>
                            <th
                                v-for="s in SKILLS"
                                :key="`l-${s}`"
                                class="w-14 bg-input-computed px-1 py-2"
                                :title="SKILL_LABEL[s]"
                            >
                                {{ SKILL_LABEL[s].charAt(0) }}
                            </th>
                            <th
                                data-tour="score-levels"
                                class="w-24 bg-input-computed px-2 py-2"
                            >
                                Total
                            </th>
                            <th
                                v-if="natgeoLevels"
                                class="w-28 px-2 py-2"
                                title="Hasil tes National Geographic (CEFR)"
                            >
                                NatGeo
                            </th>
                            <th
                                data-tour="score-comment"
                                class="w-10 px-1 py-2"
                            >
                                <span class="sr-only">Komentar</span
                                ><MessageSquare class="mx-auto size-4" />
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(row, ri) in visible"
                            :key="row.enrollment_id"
                            class="group"
                            :class="!row.is_active && 'opacity-60'"
                        >
                            <td
                                class="sticky left-0 z-[1] border-b bg-card px-2 py-1 text-muted-foreground tabular-nums group-hover:bg-muted/50"
                            >
                                {{ row.roll_no }}
                            </td>
                            <td
                                class="sticky left-10 z-[1] max-w-64 border-r border-b bg-card px-2 py-1 group-hover:bg-muted/50"
                            >
                                <button
                                    type="button"
                                    class="block w-full truncate text-left font-medium hover:text-primary hover:underline"
                                    :title="row.name"
                                    @click="openPanel(row)"
                                >
                                    {{ row.name }}
                                </button>
                                <span class="text-xs text-muted-foreground"
                                    >{{ row.nis
                                    }}{{
                                        row.is_active ? '' : ' • nonaktif'
                                    }}</span
                                >
                            </td>
                            <td
                                v-for="(s, ci) in SKILLS"
                                :key="s"
                                class="border-b px-1 py-1"
                            >
                                <ScoreCell
                                    :model-value="row.scores[s]"
                                    :locked="locked"
                                    :saving="
                                        autosave.cells[
                                            `${row.enrollment_id}:${s}`
                                        ]?.status === 'saving'
                                    "
                                    :server-error="
                                        ['error', 'failed'].includes(
                                            autosave.cells[
                                                `${row.enrollment_id}:${s}`
                                            ]?.status ?? '',
                                        )
                                            ? autosave.cells[
                                                  `${row.enrollment_id}:${s}`
                                              ]?.message
                                            : null
                                    "
                                    :label="`${SKILL_LABEL[s]}, ${row.name}`"
                                    :cell="`${ri}:${ci}`"
                                    @commit="(v) => setScore(row, s, v)"
                                    @navigate="(d) => navigate(ri, ci, d)"
                                    @paste="(t) => onPaste(ri, ci, t)"
                                />
                            </td>
                            <td
                                v-for="s in SKILLS"
                                :key="`l-${s}`"
                                class="border-b bg-input-computed/60 px-1 py-1 text-center"
                            >
                                <LevelBadge
                                    :level="
                                        results.get(row.enrollment_id)!.level[s]
                                    "
                                    size="sm"
                                />
                            </td>
                            <td
                                class="border-b bg-input-computed/60 px-2 py-1 text-center"
                            >
                                <LevelBadge
                                    :level="
                                        results.get(row.enrollment_id)!
                                            .overall_level
                                    "
                                />
                                <span
                                    v-if="
                                        results.get(row.enrollment_id)!
                                            .total !== null
                                    "
                                    class="mt-0.5 block text-[11px] font-semibold tabular-nums"
                                >
                                    {{
                                        formatScore(
                                            results.get(row.enrollment_id)!
                                                .total,
                                        )
                                    }}
                                </span>
                            </td>
                            <td v-if="natgeoLevels" class="border-b px-1 py-1">
                                <NativeSelect
                                    :model-value="row.natgeo ?? ''"
                                    :disabled="locked"
                                    :aria-label="`Level National Geographic, ${row.name}`"
                                    :invalid="
                                        !!autosave.cells[
                                            `${row.enrollment_id}:natgeo`
                                        ]
                                    "
                                    :title="
                                        autosave.cells[
                                            `${row.enrollment_id}:natgeo`
                                        ]?.message
                                    "
                                    @update:model-value="
                                        (v) =>
                                            setScore(
                                                row,
                                                'natgeo',
                                                (v || null) as Level | null,
                                            )
                                    "
                                >
                                    <option value="">–</option>
                                    <option
                                        v-for="l in natgeoLevels"
                                        :key="l"
                                        :value="l"
                                    >
                                        {{ l }}
                                    </option>
                                </NativeSelect>
                            </td>
                            <td class="border-b px-1 py-1 text-center">
                                <button
                                    type="button"
                                    tabindex="-1"
                                    :aria-label="`Komentar ${row.name}`"
                                    :title="
                                        row.comment
                                            ? 'Komentar guru'
                                            : 'Komentar otomatis'
                                    "
                                    class="inline-flex size-8 items-center justify-center rounded-md hover:bg-muted"
                                    @click="openPanel(row)"
                                >
                                    <span
                                        :class="
                                            cn(
                                                'size-2.5 rounded-full border-2 border-primary',
                                                row.comment && 'bg-primary',
                                            )
                                        "
                                    />
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!visible.length">
                            <td
                                colspan="15"
                                class="py-8 text-center text-muted-foreground"
                            >
                                Tidak ada siswa yang cocok.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </template>

    <StudentPanel
        :row="panelRow"
        :result="panelRow ? results.get(panelRow.enrollment_id)! : null"
        :comments="comments"
        :natgeo-levels="natgeoLevels"
        :classroom="classroom"
        :locked="locked"
        :cells="autosave.cells"
        :position="
            panelRow ? (isPhone ? rows : visible).indexOf(panelRow) + 1 : 0
        "
        :count="(isPhone ? rows : visible).length"
        :phone="isPhone"
        tour="flow-panel-siswa"
        @close="panelRow = null"
        @step="stepPanel"
        @set="(field, value) => panelRow && setScore(panelRow, field, value)"
    />

    <FinalizeDialog
        v-model:open="finalizeOpen"
        :classroom="classroom"
        :results="[...results.values()]"
        :pending="autosave.pendingCount.value"
        @jump="jumpTo"
    />
    <UnfinalizeDialog v-model:open="unfinalizeOpen" :classroom="classroom" />

    <!-- 409: changed on another device -->
    <ResponsiveDialog
        :open="!!autosave.conflict.value"
        title="Nilai diubah di perangkat lain"
        :dismissible="false"
        @update:open="() => undefined"
    >
        <p v-if="autosave.conflict.value" class="text-sm">
            Nilai <strong>{{ autosave.conflict.value.row.name }}</strong> diubah
            di perangkat lain
            <template v-if="autosave.conflict.value.field !== 'comment'">
                ({{
                    autosave.conflict.value.field === 'natgeo'
                        ? 'NatGeo'
                        : SKILL_LABEL[autosave.conflict.value.field as Skill]
                }}
                {{
                    fieldValue(
                        autosave.conflict.value.current,
                        autosave.conflict.value.field,
                    ) ?? '–'
                }}
                → nilai Anda {{ autosave.conflict.value.mine ?? '–' }}).
            </template>
            Gunakan nilai terbaru atau simpan nilai Anda?
        </p>
        <template #footer>
            <Button
                variant="outline"
                @click="autosave.resolveConflict('theirs')"
                >Pakai nilai terbaru</Button
            >
            <Button @click="autosave.resolveConflict('mine')"
                >Timpa dengan nilai saya</Button
            >
        </template>
    </ResponsiveDialog>

    <ConfirmDialog
        v-model:open="pasteOpen"
        :title="`Tempel ${pastePlan?.cells.length ?? 0} nilai?`"
        :description="`${pastePlan?.overwrite ?? 0} nilai yang sudah ada akan ditimpa.`"
        :confirm-label="`Tempel dan timpa ${pastePlan?.overwrite ?? 0} nilai`"
        @confirm="pastePlan && applyPaste(pastePlan)"
    />

    <ConfirmDialog
        v-model:open="leaveOpen"
        :title="`Masih ada ${autosave.pendingCount.value} nilai yang belum tersimpan`"
        description="Tunggu sebentar sampai semua nilai tersimpan, atau tinggalkan halaman (perubahan disimpan sementara di browser ini)."
        confirm-label="Tinggalkan halaman"
        destructive
        @confirm="leave"
    />
</template>
