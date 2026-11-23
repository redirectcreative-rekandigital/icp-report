<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { onKeyStroke } from '@vueuse/core';
import {
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    History,
    Lock,
    LockOpen,
    Undo2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import EmptyState from '@/components/app/EmptyState.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import TourButton from '@/components/app/TourButton.vue';
import ReportTab from '@/components/reports/ReportTab.vue';
import type { BatchState, ReportStudent } from '@/components/reports/types';
import ScoresTab from '@/components/scores/ScoresTab.vue';
import type { ClassInfo } from '@/components/scores/types';
import UnfinalizeDialog from '@/components/scores/UnfinalizeDialog.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { ScoreRow } from '@/composables/useScoreAutosave';
import { usePageTour } from '@/composables/useTour';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Band, Level, ScoringConfig } from '@/lib/cefr';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { TourId } from '@/tours/types';

/** Class page with tabs Input Nilai | Rapor & Cetak | Riwayat (PRD-04 §3, PRD-08 §2.3). */
const props = defineProps<{
    classroom: ClassInfo;
    tab: 'nilai' | 'rapor' | 'riwayat';
    config?: ScoringConfig;
    comments?: Partial<Record<Band, string>>;
    natgeoLevels?: Level[] | null;
    rows?: ScoreRow[];
    history?: {
        id: number;
        action: 'finalize' | 'unfinalize';
        reason: string | null;
        at: string;
        students: number;
        device: string | null;
    }[];
    students?: ReportStudent[];
    paperDefault?: 'f4' | 'a4';
    runningBatch?: BatchState | null;
}>();

const page = usePage();

// Class switcher & ‹ › use the active-period classes from the sidebar data (grade → name order).
const siblings = computed(() => page.props.nav?.classes ?? []);
const index = computed(() =>
    siblings.value.findIndex((c) => c.id === props.classroom.id),
);
const prev = computed(() =>
    index.value > 0 ? siblings.value[index.value - 1] : null,
);
const next = computed(() =>
    index.value >= 0 && index.value < siblings.value.length - 1
        ? siblings.value[index.value + 1]
        : null,
);
const grades = computed(() =>
    [3, 4, 5, 6].filter((g) => siblings.value.some((c) => c.grade === g)),
);
const sidebarEntry = computed(() =>
    siblings.value.find((c) => c.id === props.classroom.id),
);

function go(id: number): void {
    // Keeps the current tab (PRD-08 §2.3); the score tab asks first if saves are pending.
    router.visit(`/kelas/${id}/${props.tab}`);
}

onKeyStroke(['ArrowLeft', 'ArrowRight'], (e) => {
    const t = e.target as HTMLElement;

    if (!e.altKey || ['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName)) {
        return;
    }

    const target = e.key === 'ArrowLeft' ? prev.value : next.value;

    if (target) {
        e.preventDefault();
        go(target.id);
    }
});

const tabs = computed(() => [
    {
        key: 'nilai',
        label: 'Input Nilai',
        short: 'Nilai',
        badge: `${sidebarEntry.value?.complete ?? props.classroom.complete}/${sidebarEntry.value?.total ?? props.classroom.total}`,
    },
    {
        key: 'rapor',
        label: 'Rapor & Cetak',
        short: 'Rapor',
        badge: props.classroom.status === 'final' ? '✓' : null,
    },
    { key: 'riwayat', label: 'Riwayat', short: 'Riwayat', badge: null },
]);

const unfinalizeOpen = ref(false);
const justFinalized = computed(() =>
    page.props.flash?.success?.includes('berhasil difinalkan'),
);
const tourId = computed<TourId>(
    () =>
        (
            ({
                nilai: 'kelas-nilai',
                rapor: 'kelas-rapor',
                riwayat: 'kelas-riwayat',
            }) as const
        )[props.tab],
);
usePageTour(() => tourId.value);
</script>

<template>
    <Head :title="`Kelas ${classroom.name}`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Kelas', href: '/kelas' },
            { title: classroom.name, href: `/kelas/${classroom.id}` },
        ]"
        :title="`Kelas ${classroom.name}`"
    >
        <!-- Header: class switcher + ‹ › -->
        <div class="mb-2 flex flex-wrap items-center gap-2">
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        data-tour="class-switcher"
                        class="flex items-center gap-2 rounded-lg px-1 text-left hover:bg-muted"
                    >
                        <h1 class="text-2xl font-bold md:text-[30px]">
                            Kelas {{ classroom.name }}
                        </h1>
                        <ChevronDown class="size-5 text-muted-foreground" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="start"
                    class="max-h-80 w-56 overflow-y-auto"
                >
                    <template v-for="(g, gi) in grades" :key="g">
                        <DropdownMenuSeparator v-if="gi > 0" />
                        <DropdownMenuLabel
                            class="text-xs text-muted-foreground uppercase"
                            >Kelas {{ g }}</DropdownMenuLabel
                        >
                        <DropdownMenuItem
                            v-for="c in siblings.filter((s) => s.grade === g)"
                            :key="c.id"
                            :class="
                                c.id === classroom.id &&
                                'bg-accent font-semibold'
                            "
                            @click="go(c.id)"
                        >
                            {{ c.name }}
                            <span class="ml-auto text-xs text-muted-foreground">
                                <Lock
                                    v-if="c.status === 'final'"
                                    class="inline size-3"
                                />
                                <template v-else
                                    >{{ c.complete }}/{{ c.total }}</template
                                >
                            </span>
                        </DropdownMenuItem>
                    </template>
                    <DropdownMenuItem v-if="!siblings.length" disabled
                        >Kelas ini bukan di periode aktif</DropdownMenuItem
                    >
                </DropdownMenuContent>
            </DropdownMenu>
            <TourButton :tour="tourId" />
            <StatusBadge :status="classroom.status" />
            <div class="ml-auto flex gap-1">
                <Button
                    variant="outline"
                    size="icon"
                    :disabled="!prev"
                    :aria-label="
                        prev
                            ? `Kelas sebelumnya: ${prev.name}`
                            : 'Tidak ada kelas sebelumnya'
                    "
                    @click="prev && go(prev.id)"
                >
                    <ChevronLeft />
                </Button>
                <Button
                    variant="outline"
                    size="icon"
                    :disabled="!next"
                    :aria-label="
                        next
                            ? `Kelas berikutnya: ${next.name}`
                            : 'Tidak ada kelas berikutnya'
                    "
                    @click="next && go(next.id)"
                >
                    <ChevronRight />
                </Button>
            </div>
        </div>
        <p class="mb-4 text-sm text-muted-foreground">
            {{ classroom.term }} • Wali kelas:
            {{ classroom.homeroom_teacher || '–' }} • Guru B. Inggris:
            {{ classroom.english_teacher || '–' }}
        </p>

        <!-- Tabs reflected in the URL (segmented control on phones) -->
        <nav
            data-tour="class-tabs"
            class="mb-5 grid grid-cols-3 gap-1 rounded-lg bg-muted p-1 md:flex md:w-fit"
            aria-label="Tab kelas"
        >
            <button
                v-for="t in tabs"
                :key="t.key"
                type="button"
                :aria-current="tab === t.key ? 'page' : undefined"
                :class="
                    cn(
                        'flex items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors',
                        tab === t.key
                            ? 'bg-card text-primary shadow-sm'
                            : 'text-muted-foreground hover:text-foreground',
                    )
                "
                @click="
                    tab !== t.key &&
                    router.visit(`/kelas/${classroom.id}/${t.key}`, {
                        preserveScroll: true,
                    })
                "
            >
                <span class="md:hidden">{{ t.short }}</span>
                <span class="hidden md:inline">{{ t.label }}</span>
                <span
                    v-if="t.badge"
                    class="rounded-full bg-background px-1.5 text-xs tabular-nums"
                    >{{ t.badge }}</span
                >
            </button>
        </nav>

        <ScoresTab
            v-if="tab === 'nilai' && config && comments && rows"
            :key="classroom.id"
            :classroom="classroom"
            :config="config"
            :comments="comments"
            :natgeo-levels="natgeoLevels ?? null"
            :rows="rows"
        />

        <template v-else-if="tab === 'rapor'">
            <div
                v-if="classroom.status === 'final'"
                class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-status-meeting-fg/30 bg-status-meeting px-4 py-3 text-sm text-status-meeting-fg"
            >
                <Lock
                    :class="cn('size-5', justFinalized && 'animate-level-pop')"
                />
                <span class="flex-1"
                    >Kelas final pada
                    {{ formatDateTime(classroom.finalized_at) }}. Rapor memakai
                    data saat finalisasi.</span
                >
                <Button
                    size="sm"
                    variant="outline"
                    class="bg-card text-destructive"
                    data-tour="unfinalize-btn"
                    @click="unfinalizeOpen = true"
                >
                    <Undo2 /> Batalkan Finalisasi
                </Button>
            </div>
            <div
                v-else
                class="mb-4 rounded-lg border border-status-incomplete-fg/30 bg-status-incomplete px-4 py-3 text-sm text-status-incomplete-fg"
            >
                Kelas belum final. Rapor yang diunduh/dicetak akan bertanda
                <strong>DRAFT</strong>.
            </div>
            <ReportTab
                v-if="students && paperDefault"
                :classroom="classroom"
                :students="students"
                :paper-default="paperDefault"
                :running-batch="runningBatch ?? null"
            />
            <UnfinalizeDialog
                v-model:open="unfinalizeOpen"
                :classroom="classroom"
            />
        </template>

        <template v-else>
            <Card v-if="!history?.length" data-tour="history-timeline">
                <EmptyState
                    :icon="History"
                    title="Belum ada riwayat finalisasi"
                    description="Riwayat finalisasi dan pembatalan kelas ini akan tampil di sini."
                />
            </Card>
            <ol
                v-else
                data-tour="history-timeline"
                class="relative ml-3 border-l-2 border-border"
            >
                <li v-for="h in history" :key="h.id" class="mb-6 ml-6">
                    <span
                        :class="
                            cn(
                                'absolute -left-[13px] flex size-6 items-center justify-center rounded-full ring-4 ring-background',
                                h.action === 'finalize'
                                    ? 'bg-status-meeting text-status-meeting-fg'
                                    : 'bg-status-incomplete text-status-incomplete-fg',
                            )
                        "
                    >
                        <Lock v-if="h.action === 'finalize'" class="size-3.5" />
                        <LockOpen v-else class="size-3.5" />
                    </span>
                    <p class="font-semibold">
                        {{
                            h.action === 'finalize'
                                ? `Difinalkan (${h.students} siswa)`
                                : 'Finalisasi dibatalkan'
                        }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ formatDateTime(h.at)
                        }}{{ h.device ? ` • ${h.device}` : '' }}
                    </p>
                    <p
                        v-if="h.reason"
                        class="mt-1 rounded-md bg-muted px-3 py-2 text-sm"
                    >
                        Alasan: {{ h.reason }}
                    </p>
                </li>
            </ol>
        </template>
    </AppLayout>
</template>
