<script setup lang="ts">
import { Deferred, Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowDown,
    ArrowRight,
    ArrowUp,
    CalendarPlus,
    CheckCircle2,
    Circle,
    Download,
    Info,
    Lock,
    School,
    ShieldCheck,
    SlidersHorizontal,
    Target,
    TrendingUp,
    Users,
} from 'lucide-vue-next';
import { computed, defineAsyncComponent, ref } from 'vue';
import LevelBadge from '@/components/app/LevelBadge.vue';
import NativeSelect from '@/components/app/NativeSelect.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import ResponsiveDialog from '@/components/app/ResponsiveDialog.vue';
import StatCard from '@/components/app/StatCard.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import ChartCard from '@/components/charts/ChartCard.vue';
import type { ClassDist } from '@/components/charts/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { LEVELS, SKILL_LABEL, SKILLS } from '@/lib/cefr';
import type { Level, Skill } from '@/lib/cefr';
import { formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';

// Chart.js is only downloaded when charts are shown (PRD-01 §7).
const LevelByClassChart = defineAsyncComponent(
    () => import('@/components/charts/LevelByClassChart.vue'),
);
const LevelByGradeChart = defineAsyncComponent(
    () => import('@/components/charts/LevelByGradeChart.vue'),
);
const SkillAverageChart = defineAsyncComponent(
    () => import('@/components/charts/SkillAverageChart.vue'),
);
const StatusChart = defineAsyncComponent(
    () => import('@/components/charts/StatusChart.vue'),
);
const TrendChart = defineAsyncComponent(
    () => import('@/components/charts/TrendChart.vue'),
);

type Summary = {
    n: number;
    average: number | null;
    levels: Record<Level, number>;
};

const props = defineProps<{
    hasActiveTerm: boolean;
    classCount: number;
    studentCount: number;
    terms: { id: number; label: string; is_active: boolean }[];
    filters: {
        term: number | null;
        grade: number | null;
        final_only: boolean;
        tab: 'ringkasan' | 'perbandingan';
        cohort: 'same' | 'all';
    };
    term: { id: number; label: string } | null;
    kpis?: {
        assessed: number;
        students: number;
        final: number;
        classes: number;
        not_final: string[];
        average: number | null;
        previous_average: number | null;
        delta: number | null;
        meeting_or_above: number | null;
    };
    progress?: {
        id: number;
        name: string;
        grade: number;
        teacher: string | null;
        status: 'draft' | 'final';
        total: number;
        complete: number;
    }[];
    charts?: {
        byClass: ClassDist[];
        byGrade: {
            grade: number;
            levels: Record<Level, number>;
            skills: Record<Skill, number | null>;
        }[];
        status: {
            below: number;
            meeting: number;
            above: number;
            incomplete: number;
        };
        trend: {
            labels: string[];
            series: { grade: number; values: (number | null)[] }[];
        };
        insights: { tone: 'info' | 'warning'; text: string; href: string }[];
    };
    comparison?: {
        a_id: number;
        b_id: number;
        a: Summary & { label: string };
        b: Summary & { label: string };
        grades: { grade: number; a: Summary; b: Summary }[];
        moves: { up: number; same: number; down: number } | null;
    } | null;
}>();

const page = usePage();

/* ---------- filters ---------- */
function visit(changes: Record<string, unknown>): void {
    const q: Record<string, unknown> = {
        term: props.filters.term,
        grade: props.filters.grade,
        final_only: props.filters.final_only ? 1 : undefined,
        tab: props.filters.tab === 'perbandingan' ? 'perbandingan' : undefined,
        ...changes,
    };
    const query = Object.fromEntries(
        Object.entries(q).filter(
            ([, v]) => v !== undefined && v !== null && v !== '',
        ),
    ) as Record<string, string | number>;
    router.get('/', query, {
        preserveScroll: true,
        preserveState: true,
    });
}

const termModel = computed({
    get: () => String(props.filters.term ?? ''),
    set: (v) => visit({ term: v }),
});
const gradeModel = computed({
    get: () => String(props.filters.grade ?? ''),
    set: (v) => visit({ grade: v || undefined }),
});
const finalOnly = computed({
    get: () => props.filters.final_only,
    set: (v) => visit({ final_only: v ? 1 : undefined }),
});
const filtersOpen = ref(false);

/* ---------- onboarding (no data yet) ---------- */
const showOnboarding = computed(() => !props.term || props.classCount === 0);
const steps = computed(() => [
    {
        done: true,
        title: 'PIN sekolah & kode pemulihan',
        text: 'Aplikasi sudah terlindungi PIN.',
        href: '/pengaturan/keamanan',
        icon: ShieldCheck,
    },
    {
        done: !!page.props.school.logoUrl,
        title: 'Lengkapi identitas sekolah',
        text: 'Alamat, kepala sekolah & NBM, kota, dan logo untuk kop rapor.',
        href: '/pengaturan/sekolah',
        icon: School,
    },
    {
        done: props.hasActiveTerm && props.classCount > 0,
        title: 'Buat periode dan kelas',
        text: props.hasActiveTerm
            ? `${page.props.activeTerm?.label}: ${props.classCount} kelas.`
            : 'Tahun ajaran + semester, lalu kelas 3A–6B.',
        href: page.props.activeTerm
            ? `/periode/${page.props.activeTerm.id}`
            : '/periode',
        icon: CalendarPlus,
    },
    {
        done: props.studentCount > 0,
        title: 'Masukkan siswa ke kelas',
        text: props.studentCount
            ? `${props.studentCount} siswa terdaftar.`
            : 'Impor daftar siswa dari Excel atau tambah manual.',
        href: props.studentCount ? '/siswa' : '/impor',
        icon: Users,
    },
]);

/* ---------- charts ---------- */
const skillBy = ref<'class' | 'grade'>('class');
const skillGroups = computed(() =>
    !props.charts
        ? []
        : skillBy.value === 'class'
          ? props.charts.byClass
                .filter((c) => c.average !== null)
                .map((c) => ({ label: c.class, skills: c.skills }))
          : props.charts.byGrade.map((g) => ({
                label: `Kelas ${g.grade}`,
                skills: g.skills,
            })),
);
const hasAssessed = computed(() => (props.kpis?.assessed ?? 0) > 0);

const segment = ref<{ class: string; level: Level; names: string[] } | null>(
    null,
);
const segmentOpen = computed({
    get: () => !!segment.value,
    set: (v) => !v && (segment.value = null),
});

/* ---------- comparison ---------- */
const compareA = computed({
    get: () => String(props.comparison?.a_id ?? ''),
    set: (v) =>
        visit({
            tab: 'perbandingan',
            a: v,
            b: props.comparison?.b_id,
            cohort: props.filters.cohort,
        }),
});
const compareB = computed({
    get: () => String(props.comparison?.b_id ?? props.filters.term ?? ''),
    set: (v) =>
        visit({
            tab: 'perbandingan',
            a: props.comparison?.a_id,
            b: v,
            cohort: props.filters.cohort,
        }),
});
const cohort = computed({
    get: () => props.filters.cohort,
    set: (v) =>
        visit({
            tab: 'perbandingan',
            a: props.comparison?.a_id,
            b: props.comparison?.b_id,
            cohort: v,
        }),
});
const diff = (a: number | null, b: number | null) =>
    a === null || b === null ? null : Math.round((b - a) * 10) / 10;
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout :breadcrumbs="[{ title: 'Dashboard', href: '/' }]">
        <PageHeader
            :title="`Selamat datang di ${page.props.school.name}`"
            tour="dashboard"
            tour-wait-for="dash-charts"
            :description="
                term ? `Rekap capaian CEFR • ${term.label}` : undefined
            "
        >
            <Button
                v-if="!showOnboarding && term"
                variant="outline"
                as="a"
                :href="`/ekspor/periode/${term.id}`"
            >
                <Download /> Ekspor rekap periode (.xlsx)
            </Button>
        </PageHeader>

        <!-- Onboarding when there is nothing to recap yet -->
        <Card v-if="showOnboarding" class="max-w-3xl">
            <CardHeader
                ><CardTitle class="text-lg"
                    >Langkah persiapan</CardTitle
                ></CardHeader
            >
            <CardContent class="grid gap-2">
                <Link
                    v-for="s in steps"
                    :key="s.title"
                    :href="s.href"
                    class="flex items-start gap-3 rounded-lg border p-4 transition-all hover:-translate-y-0.5 hover:shadow-md"
                >
                    <CheckCircle2
                        v-if="s.done"
                        class="mt-0.5 size-5 shrink-0 text-status-meeting-fg"
                    />
                    <Circle
                        v-else
                        class="mt-0.5 size-5 shrink-0 text-muted-foreground"
                    />
                    <div class="min-w-0 flex-1">
                        <p
                            class="font-semibold"
                            :class="
                                s.done &&
                                'text-muted-foreground line-through decoration-1'
                            "
                        >
                            {{ s.title }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ s.text }}
                        </p>
                    </div>
                    <component
                        :is="s.icon"
                        class="size-5 shrink-0 text-primary"
                    />
                </Link>
            </CardContent>
        </Card>

        <template v-else>
            <!-- Tabs + filters (one row; bottom sheet on phones) -->
            <div class="mb-5 flex flex-wrap items-center gap-3">
                <div
                    data-tour="dash-tabs"
                    class="grid grid-cols-2 gap-1 rounded-lg bg-muted p-1"
                    role="tablist"
                >
                    <button
                        v-for="t in [
                            { k: 'ringkasan', l: 'Ringkasan' },
                            { k: 'perbandingan', l: 'Perbandingan' },
                        ]"
                        :key="t.k"
                        type="button"
                        role="tab"
                        :aria-selected="filters.tab === t.k"
                        :class="
                            cn(
                                'rounded-md px-4 py-1.5 text-sm font-medium',
                                filters.tab === t.k
                                    ? 'bg-card text-primary shadow-sm'
                                    : 'text-muted-foreground',
                            )
                        "
                        @click="
                            visit({
                                tab:
                                    t.k === 'perbandingan'
                                        ? 'perbandingan'
                                        : undefined,
                            })
                        "
                    >
                        {{ t.l }}
                    </button>
                </div>
                <template v-if="filters.tab === 'ringkasan'">
                    <Button
                        variant="outline"
                        class="md:hidden"
                        data-tour="dash-filters"
                        @click="filtersOpen = true"
                        ><SlidersHorizontal /> Filter</Button
                    >
                    <div
                        data-tour="dash-filters"
                        class="hidden flex-wrap items-center gap-3 md:flex"
                    >
                        <NativeSelect
                            v-model="termModel"
                            class="w-60"
                            aria-label="Periode"
                        >
                            <option
                                v-for="t in terms"
                                :key="t.id"
                                :value="String(t.id)"
                            >
                                {{ t.label }}{{ t.is_active ? ' (aktif)' : '' }}
                            </option>
                        </NativeSelect>
                        <NativeSelect
                            v-model="gradeModel"
                            class="w-36"
                            aria-label="Jenjang"
                        >
                            <option value="">Semua jenjang</option>
                            <option
                                v-for="g in [3, 4, 5, 6]"
                                :key="g"
                                :value="String(g)"
                            >
                                Kelas {{ g }}
                            </option>
                        </NativeSelect>
                        <label class="flex items-center gap-2 text-sm"
                            ><Switch v-model="finalOnly" /> Hanya kelas
                            final</label
                        >
                    </div>
                </template>
            </div>

            <!-- ================= RINGKASAN ================= -->
            <template v-if="filters.tab === 'ringkasan' && kpis">
                <div
                    data-tour="dash-kpi"
                    class="mb-6 grid grid-cols-2 gap-3 xl:grid-cols-4"
                >
                    <StatCard
                        :icon="Users"
                        label="Siswa dinilai"
                        :value="kpis.assessed"
                        :of="kpis.students"
                        :ring="
                            kpis.students
                                ? (kpis.assessed / kpis.students) * 100
                                : 0
                        "
                    >
                        nilai lengkap 4 skill
                    </StatCard>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <div>
                                <StatCard
                                    :icon="Lock"
                                    label="Kelas final"
                                    :value="kpis.final"
                                    :of="kpis.classes"
                                >
                                    {{
                                        kpis.not_final.length
                                            ? `${kpis.not_final.length} kelas belum final`
                                            : 'semua kelas final'
                                    }}
                                </StatCard>
                            </div>
                        </TooltipTrigger>
                        <TooltipContent v-if="kpis.not_final.length"
                            >Belum final:
                            {{ kpis.not_final.join(', ') }}</TooltipContent
                        >
                    </Tooltip>
                    <StatCard
                        :icon="TrendingUp"
                        label="Rata-rata overall"
                        :value="kpis.average"
                        suffix="%"
                        :digits="1"
                    >
                        <span
                            v-if="kpis.delta !== null"
                            :class="
                                cn(
                                    'inline-flex items-center gap-0.5 font-semibold',
                                    kpis.delta >= 0
                                        ? 'text-status-meeting-fg'
                                        : 'text-status-below-fg',
                                )
                            "
                        >
                            <ArrowUp
                                v-if="kpis.delta >= 0"
                                class="size-3"
                            /><ArrowDown v-else class="size-3" />
                            {{ Math.abs(kpis.delta).toLocaleString('id-ID') }}
                            dari periode lalu
                        </span>
                        <span v-else>belum ada periode pembanding</span>
                    </StatCard>
                    <StatCard
                        :icon="Target"
                        label="Sesuai/di atas harapan"
                        :value="kpis.meeting_or_above"
                        suffix="%"
                        >Meeting + Above</StatCard
                    >
                </div>

                <Deferred data="charts">
                    <template #fallback>
                        <div class="mb-6 grid gap-4 lg:grid-cols-2">
                            <ChartCard
                                title="Sebaran level per kelas"
                                loading
                            />
                            <ChartCard
                                title="Status terhadap harapan"
                                loading
                            />
                        </div>
                    </template>

                    <div v-if="charts">
                        <!-- Highlights -->
                        <div
                            v-if="charts.insights.length"
                            class="mb-6 grid gap-3 md:grid-cols-3"
                        >
                            <Link
                                v-for="i in charts.insights"
                                :key="i.text"
                                :href="i.href"
                                :class="
                                    cn(
                                        'flex items-start gap-2 rounded-xl border p-4 text-sm transition-shadow hover:shadow-md',
                                        i.tone === 'warning'
                                            ? 'border-status-incomplete-fg/30 bg-status-incomplete text-status-incomplete-fg'
                                            : 'bg-card',
                                    )
                                "
                            >
                                <AlertTriangle
                                    v-if="i.tone === 'warning'"
                                    class="mt-0.5 size-4 shrink-0"
                                />
                                <Info
                                    v-else
                                    class="mt-0.5 size-4 shrink-0 text-primary"
                                />
                                <span class="flex-1">{{ i.text }}</span>
                                <ArrowRight
                                    class="mt-0.5 size-4 shrink-0 opacity-60"
                                />
                            </Link>
                        </div>

                        <div
                            data-tour="dash-charts"
                            class="mb-6 grid gap-4 lg:grid-cols-5"
                        >
                            <ChartCard
                                class="lg:col-span-3"
                                title="Sebaran level per kelas"
                                description="Klik segmen untuk melihat daftar siswa."
                                :empty="!hasAssessed"
                            >
                                <LevelByClassChart
                                    :classes="charts.byClass"
                                    @select="segment = $event"
                                />
                                <template #table>
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr
                                                class="text-left text-muted-foreground"
                                            >
                                                <th class="py-1">Kelas</th>
                                                <th
                                                    v-for="l in LEVELS"
                                                    :key="l"
                                                    class="py-1 text-right"
                                                >
                                                    {{ l }}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="c in charts.byClass"
                                                :key="c.id"
                                                class="border-t"
                                            >
                                                <td class="py-1 font-semibold">
                                                    {{ c.class }}
                                                </td>
                                                <td
                                                    v-for="l in LEVELS"
                                                    :key="l"
                                                    class="py-1 text-right tabular-nums"
                                                >
                                                    {{ c.levels[l].length }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </template>
                            </ChartCard>
                            <ChartCard
                                class="lg:col-span-2"
                                title="Status terhadap harapan"
                                :empty="
                                    !hasAssessed && !charts.status.incomplete
                                "
                            >
                                <StatusChart :status="charts.status" />
                                <template #table>
                                    <table class="w-full text-sm">
                                        <tbody>
                                            <tr
                                                v-for="(v, k) in charts.status"
                                                :key="k"
                                                class="border-t"
                                            >
                                                <td class="py-1">{{ k }}</td>
                                                <td
                                                    class="py-1 text-right tabular-nums"
                                                >
                                                    {{ v }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </template>
                            </ChartCard>
                        </div>

                        <div class="mb-6 grid gap-4 lg:grid-cols-2">
                            <ChartCard
                                title="Sebaran level per jenjang"
                                :empty="!hasAssessed"
                            >
                                <LevelByGradeChart :grades="charts.byGrade" />
                                <template #table>
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr
                                                class="text-left text-muted-foreground"
                                            >
                                                <th class="py-1">Jenjang</th>
                                                <th
                                                    v-for="l in LEVELS"
                                                    :key="l"
                                                    class="py-1 text-right"
                                                >
                                                    {{ l }}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="g in charts.byGrade"
                                                :key="g.grade"
                                                class="border-t"
                                            >
                                                <td class="py-1 font-semibold">
                                                    Kelas {{ g.grade }}
                                                </td>
                                                <td
                                                    v-for="l in LEVELS"
                                                    :key="l"
                                                    class="py-1 text-right tabular-nums"
                                                >
                                                    {{ g.levels[l] }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </template>
                            </ChartCard>
                            <ChartCard
                                title="Rata-rata per skill"
                                description="Menunjukkan skill terlemah."
                                :empty="!hasAssessed"
                            >
                                <div class="mb-2 flex gap-1 text-xs">
                                    <button
                                        v-for="o in [
                                            { k: 'class', l: 'Per kelas' },
                                            { k: 'grade', l: 'Per jenjang' },
                                        ]"
                                        :key="o.k"
                                        type="button"
                                        :class="
                                            cn(
                                                'rounded-full border px-3 py-1',
                                                skillBy === o.k
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'bg-card',
                                            )
                                        "
                                        @click="
                                            skillBy = o.k as 'class' | 'grade'
                                        "
                                    >
                                        {{ o.l }}
                                    </button>
                                </div>
                                <SkillAverageChart :groups="skillGroups" />
                                <template #table>
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr
                                                class="text-left text-muted-foreground"
                                            >
                                                <th class="py-1"></th>
                                                <th
                                                    v-for="s in SKILLS"
                                                    :key="s"
                                                    class="py-1 text-right"
                                                >
                                                    {{ SKILL_LABEL[s] }}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="g in skillGroups"
                                                :key="g.label"
                                                class="border-t"
                                            >
                                                <td class="py-1 font-semibold">
                                                    {{ g.label }}
                                                </td>
                                                <td
                                                    v-for="s in SKILLS"
                                                    :key="s"
                                                    class="py-1 text-right tabular-nums"
                                                >
                                                    {{
                                                        formatPercent(
                                                            g.skills[s],
                                                        )
                                                    }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </template>
                            </ChartCard>
                        </div>

                        <ChartCard
                            v-if="charts.trend.labels.length"
                            class="mb-6"
                            title="Perbandingan periode"
                            description="Rata-rata overall per jenjang dari periode yang sudah final (maks. 6). Tes & ambang tiap jenjang berbeda."
                        >
                            <TrendChart
                                :labels="charts.trend.labels"
                                :series="charts.trend.series"
                            />
                            <template #table>
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr
                                            class="text-left text-muted-foreground"
                                        >
                                            <th class="py-1"></th>
                                            <th
                                                v-for="l in charts.trend.labels"
                                                :key="l"
                                                class="py-1 text-right"
                                            >
                                                {{ l }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="s in charts.trend.series"
                                            :key="s.grade"
                                            class="border-t"
                                        >
                                            <td class="py-1 font-semibold">
                                                Kelas {{ s.grade }}
                                            </td>
                                            <td
                                                v-for="(v, i) in s.values"
                                                :key="i"
                                                class="py-1 text-right tabular-nums"
                                            >
                                                {{ formatPercent(v) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </template>
                        </ChartCard>
                    </div>
                </Deferred>

                <!-- Class progress -->
                <Card v-if="progress" data-tour="dash-progress" class="mb-6">
                    <CardHeader
                        ><CardTitle class="text-base"
                            >Progres kelas</CardTitle
                        ></CardHeader
                    >
                    <CardContent>
                        <ul class="divide-y">
                            <li
                                v-for="c in progress"
                                :key="c.id"
                                class="flex flex-wrap items-center gap-x-4 gap-y-2 py-3"
                            >
                                <span class="w-12 text-lg font-bold">{{
                                    c.name
                                }}</span>
                                <span
                                    class="hidden w-40 truncate text-sm text-muted-foreground sm:block"
                                    >{{ c.teacher || '–' }}</span
                                >
                                <div class="min-w-32 flex-1">
                                    <div
                                        class="mb-1 flex justify-between text-xs text-muted-foreground"
                                    >
                                        <span
                                            >{{ c.complete }}/{{
                                                c.total
                                            }}
                                            lengkap</span
                                        >
                                        <span class="tabular-nums"
                                            >{{
                                                c.total
                                                    ? Math.round(
                                                          (c.complete /
                                                              c.total) *
                                                              100,
                                                      )
                                                    : 0
                                            }}%</span
                                        >
                                    </div>
                                    <div
                                        class="h-2 overflow-hidden rounded-full bg-muted"
                                    >
                                        <div
                                            :class="
                                                cn(
                                                    'h-full rounded-full',
                                                    c.total &&
                                                        c.complete === c.total
                                                        ? 'bg-secondary'
                                                        : c.complete /
                                                                Math.max(
                                                                    1,
                                                                    c.total,
                                                                ) >=
                                                            0.5
                                                          ? 'bg-gold'
                                                          : 'bg-muted-foreground/40',
                                                )
                                            "
                                            :style="{
                                                width: `${c.total ? (c.complete / c.total) * 100 : 0}%`,
                                            }"
                                        />
                                    </div>
                                </div>
                                <StatusBadge :status="c.status" size="sm" />
                                <Button size="sm" variant="outline" as-child>
                                    <Link
                                        :href="`/kelas/${c.id}/${c.status === 'final' ? 'rapor' : 'nilai'}`"
                                        >{{
                                            c.status === 'final'
                                                ? 'Rapor'
                                                : 'Isi Nilai'
                                        }}</Link
                                    >
                                </Button>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </template>

            <!-- ================= PERBANDINGAN ================= -->
            <template v-else-if="filters.tab === 'perbandingan'">
                <Card class="mb-4">
                    <CardContent class="flex flex-wrap items-end gap-3 pt-6">
                        <div class="grid gap-1.5">
                            <Label>Periode A</Label>
                            <NativeSelect v-model="compareA" class="w-60">
                                <option
                                    v-for="t in terms"
                                    :key="t.id"
                                    :value="String(t.id)"
                                >
                                    {{ t.label }}
                                </option>
                            </NativeSelect>
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Periode B</Label>
                            <NativeSelect v-model="compareB" class="w-60">
                                <option
                                    v-for="t in terms"
                                    :key="t.id"
                                    :value="String(t.id)"
                                >
                                    {{ t.label }}
                                </option>
                            </NativeSelect>
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Kohort</Label>
                            <NativeSelect v-model="cohort" class="w-48">
                                <option value="same">
                                    Siswa yang sama (NIS)
                                </option>
                                <option value="all">Semua siswa</option>
                            </NativeSelect>
                        </div>
                        <p
                            class="flex items-start gap-1.5 text-xs text-muted-foreground md:max-w-sm"
                        >
                            <Info class="mt-0.5 size-3.5 shrink-0" />
                            Tes dan ambang tiap jenjang berbeda, jadi metrik
                            utama adalah sebaran level; persen hanya pelengkap.
                            Hanya kelas final yang dihitung.
                        </p>
                    </CardContent>
                </Card>

                <Card v-if="!comparison">
                    <CardContent
                        class="py-10 text-center text-sm text-muted-foreground"
                        >Pilih dua periode yang berbeda.</CardContent
                    >
                </Card>
                <template v-else>
                    <div
                        v-if="comparison.moves"
                        class="mb-4 grid grid-cols-3 gap-3"
                    >
                        <StatCard
                            :icon="ArrowUp"
                            label="Naik level"
                            :value="comparison.moves.up"
                        />
                        <StatCard
                            :icon="ArrowRight"
                            label="Level tetap"
                            :value="comparison.moves.same"
                        />
                        <StatCard
                            :icon="ArrowDown"
                            label="Turun level"
                            :value="comparison.moves.down"
                        />
                    </div>
                    <Card class="overflow-hidden p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[720px] text-sm">
                                <thead
                                    class="bg-muted/60 text-xs text-muted-foreground"
                                >
                                    <tr>
                                        <th class="px-3 py-2 text-left">
                                            Jenjang
                                        </th>
                                        <th class="px-3 py-2 text-left">
                                            Periode
                                        </th>
                                        <th class="px-3 py-2 text-right">
                                            Siswa
                                        </th>
                                        <th
                                            v-for="l in LEVELS"
                                            :key="l"
                                            class="px-2 py-2 text-right"
                                        >
                                            <LevelBadge :level="l" size="sm" />
                                        </th>
                                        <th class="px-3 py-2 text-right">
                                            Rata-rata
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template
                                        v-for="row in [
                                            {
                                                grade: 0,
                                                a: comparison.a,
                                                b: comparison.b,
                                            },
                                            ...comparison.grades,
                                        ]"
                                        :key="row.grade"
                                    >
                                        <tr
                                            class="border-t"
                                            :class="
                                                row.grade === 0 &&
                                                'bg-accent/40 font-semibold'
                                            "
                                        >
                                            <td class="px-3 py-1.5" rowspan="3">
                                                {{
                                                    row.grade
                                                        ? `Kelas ${row.grade}`
                                                        : 'Semua'
                                                }}
                                            </td>
                                            <td
                                                class="px-3 py-1.5 text-muted-foreground"
                                            >
                                                A: {{ comparison.a.label }}
                                            </td>
                                            <td
                                                class="px-3 py-1.5 text-right tabular-nums"
                                            >
                                                {{ row.a.n }}
                                            </td>
                                            <td
                                                v-for="l in LEVELS"
                                                :key="l"
                                                class="px-2 py-1.5 text-right tabular-nums"
                                            >
                                                {{ row.a.levels[l] }}%
                                            </td>
                                            <td
                                                class="px-3 py-1.5 text-right tabular-nums"
                                            >
                                                {{
                                                    formatPercent(row.a.average)
                                                }}
                                            </td>
                                        </tr>
                                        <tr
                                            :class="
                                                row.grade === 0 &&
                                                'bg-accent/40 font-semibold'
                                            "
                                        >
                                            <td
                                                class="px-3 py-1.5 text-muted-foreground"
                                            >
                                                B: {{ comparison.b.label }}
                                            </td>
                                            <td
                                                class="px-3 py-1.5 text-right tabular-nums"
                                            >
                                                {{ row.b.n }}
                                            </td>
                                            <td
                                                v-for="l in LEVELS"
                                                :key="l"
                                                class="px-2 py-1.5 text-right tabular-nums"
                                            >
                                                {{ row.b.levels[l] }}%
                                            </td>
                                            <td
                                                class="px-3 py-1.5 text-right tabular-nums"
                                            >
                                                {{
                                                    formatPercent(row.b.average)
                                                }}
                                            </td>
                                        </tr>
                                        <tr
                                            class="text-xs"
                                            :class="
                                                row.grade === 0 &&
                                                'bg-accent/40'
                                            "
                                        >
                                            <td
                                                class="px-3 py-1.5 text-muted-foreground"
                                            >
                                                Selisih (B − A)
                                            </td>
                                            <td />
                                            <td
                                                v-for="l in LEVELS"
                                                :key="l"
                                                class="px-2 py-1.5 text-right tabular-nums"
                                            >
                                                {{
                                                    row.b.levels[l] -
                                                        row.a.levels[l] >
                                                    0
                                                        ? '+'
                                                        : ''
                                                }}{{
                                                    row.b.levels[l] -
                                                    row.a.levels[l]
                                                }}
                                            </td>
                                            <td
                                                class="px-3 py-1.5 text-right tabular-nums"
                                            >
                                                <template
                                                    v-if="
                                                        diff(
                                                            row.a.average,
                                                            row.b.average,
                                                        ) !== null
                                                    "
                                                >
                                                    {{
                                                        diff(
                                                            row.a.average,
                                                            row.b.average,
                                                        )! > 0
                                                            ? '+'
                                                            : ''
                                                    }}{{
                                                        diff(
                                                            row.a.average,
                                                            row.b.average,
                                                        )!.toLocaleString(
                                                            'id-ID',
                                                        )
                                                    }}
                                                </template>
                                                <template v-else>–</template>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </Card>
                </template>
            </template>
        </template>

        <!-- Students in a clicked segment -->
        <ResponsiveDialog
            v-model:open="segmentOpen"
            :title="`Kelas ${segment?.class} • level ${segment?.level}`"
            :description="`${segment?.names.length ?? 0} siswa`"
        >
            <ul
                class="max-h-80 divide-y overflow-y-auto rounded-lg border text-sm"
            >
                <li
                    v-for="n in segment?.names ?? []"
                    :key="n"
                    class="px-3 py-2"
                >
                    {{ n }}
                </li>
                <li
                    v-if="!segment?.names.length"
                    class="px-3 py-2 text-muted-foreground"
                >
                    Tidak ada siswa.
                </li>
            </ul>
        </ResponsiveDialog>

        <!-- Phone filters -->
        <ResponsiveDialog v-model:open="filtersOpen" title="Filter dashboard">
            <div class="grid gap-4">
                <div class="grid gap-1.5">
                    <Label>Periode</Label>
                    <NativeSelect v-model="termModel">
                        <option
                            v-for="t in terms"
                            :key="t.id"
                            :value="String(t.id)"
                        >
                            {{ t.label }}
                        </option>
                    </NativeSelect>
                </div>
                <div class="grid gap-1.5">
                    <Label>Jenjang</Label>
                    <NativeSelect v-model="gradeModel">
                        <option value="">Semua jenjang</option>
                        <option
                            v-for="g in [3, 4, 5, 6]"
                            :key="g"
                            :value="String(g)"
                        >
                            Kelas {{ g }}
                        </option>
                    </NativeSelect>
                </div>
                <label class="flex items-center justify-between gap-2 text-sm"
                    >Hanya kelas final <Switch v-model="finalOnly"
                /></label>
            </div>
            <template #footer
                ><Button class="w-full" @click="filtersOpen = false"
                    >Tampilkan</Button
                ></template
            >
        </ResponsiveDialog>
    </AppLayout>
</template>
