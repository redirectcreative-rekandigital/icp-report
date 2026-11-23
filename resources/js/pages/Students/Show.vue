<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    FileDown,
    Pencil,
    Trash2,
    UserCheck,
    UserX,
} from 'lucide-vue-next';
import { defineAsyncComponent, ref } from 'vue';

const StudentProgressChart = defineAsyncComponent(
    () => import('@/components/charts/StudentProgressChart.vue'),
);
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import FormSheet from '@/components/app/FormSheet.vue';
import LevelBadge from '@/components/app/LevelBadge.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import StudentFormFields from '@/components/app/StudentFormFields.vue';
import TourButton from '@/components/app/TourButton.vue';
import ChartCard from '@/components/charts/ChartCard.vue';
import type { ProgressPoint } from '@/components/charts/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { withPin } from '@/composables/usePinConfirm';
import { usePageTour } from '@/composables/useTour';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Band } from '@/lib/cefr';
import { formatScore, SKILL_LABEL, SKILLS } from '@/lib/cefr';
import { cn } from '@/lib/utils';

/** Student profile. Progress chart & level history arrive in M5 (PRD-06 §4). */
const props = defineProps<{
    student: {
        id: number;
        nipd: string;
        nisn: string;
        name: string;
        gender: 'L' | 'P' | null;
        is_active: boolean;
        notes: string | null;
        has_scores: boolean;
        classroom_id: number | null;
    };
    history: {
        id: number;
        term: string;
        term_active: boolean;
        classroom: {
            id: number;
            name: string;
            grade: number;
            status: 'draft' | 'final';
        };
        roll_no: number;
    }[];
    points: ProgressPoint[];
    latestLevel: Band | null;
    narrative: string | null;
    classes: { id: number; name: string; grade: number; final: boolean }[];
}>();

const chartMode = ref<'overall' | 'skills'>('overall');

const formOpen = ref(false);
const form = useForm({
    nipd: '',
    nisn: '',
    name: '',
    gender: '' as '' | 'L' | 'P',
    classroom_id: '',
    notes: '',
});

function openEdit(): void {
    const s = props.student;
    form.defaults({
        nipd: s.nipd,
        nisn: s.nisn,
        name: s.name,
        gender: s.gender ?? '',
        classroom_id: s.classroom_id ? String(s.classroom_id) : '',
        notes: s.notes ?? '',
    });
    form.reset();
    form.clearErrors();
    formOpen.value = true;
}

function save(): void {
    form.transform((d) => ({
        ...d,
        gender: d.gender || null,
        classroom_id: d.classroom_id ? Number(d.classroom_id) : null,
    })).put(`/siswa/${props.student.id}`, {
        preserveScroll: true,
        onSuccess: () => (formOpen.value = false),
    });
}

const busy = ref(false);

function setActive(active: boolean): void {
    busy.value = true;
    router.post(
        '/siswa/massal',
        { action: active ? 'activate' : 'deactivate', ids: [props.student.id] },
        {
            preserveScroll: true,
            onFinish: () => (busy.value = false),
        },
    );
}

const deleteOpen = ref(false);

function destroy(): void {
    void withPin(() => {
        busy.value = true;
        router.delete(`/siswa/${props.student.id}`, {
            onFinish: () => (busy.value = false),
        });
    }, `Konfirmasi hapus ${props.student.name}`);
}

usePageTour(() => 'siswa-profil');
</script>

<template>
    <Head :title="student.name" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Siswa', href: '/siswa' },
            { title: student.name, href: `/siswa/${student.id}` },
        ]"
        :title="student.name"
    >
        <Link
            href="/siswa"
            class="mb-3 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" /> Daftar siswa
        </Link>

        <div
            class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="flex items-center gap-4">
                <div
                    class="flex size-14 shrink-0 items-center justify-center rounded-full bg-kpi-icon text-xl font-bold text-primary"
                >
                    {{ student.name.charAt(0) }}
                </div>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <h1
                            class="text-2xl font-bold break-words md:text-[30px]"
                        >
                            {{ student.name }}
                        </h1>
                        <TourButton tour="siswa-profil" />
                    </div>
                    <p class="text-muted-foreground">
                        NIPD {{ student.nipd }} • NISN {{ student.nisn }} •
                        {{
                            student.gender === 'L'
                                ? 'Laki-laki'
                                : student.gender === 'P'
                                  ? 'Perempuan'
                                  : 'L/P belum diisi'
                        }}
                        <span
                            v-if="!student.is_active"
                            class="ml-1 rounded-full bg-status-draft px-2 py-0.5 text-xs font-semibold text-status-draft-fg"
                            >Nonaktif</span
                        >
                    </p>
                </div>
            </div>
            <div data-tour="profile-actions" class="flex flex-wrap gap-2">
                <Button variant="outline" @click="openEdit"
                    ><Pencil /> Ubah</Button
                >
                <Button
                    v-if="student.is_active"
                    variant="outline"
                    :disabled="busy"
                    @click="setActive(false)"
                    ><UserX /> Nonaktifkan</Button
                >
                <Button
                    v-else
                    variant="outline"
                    :disabled="busy"
                    @click="setActive(true)"
                    ><UserCheck /> Aktifkan</Button
                >
                <Button
                    variant="ghost"
                    class="text-destructive"
                    :disabled="student.has_scores"
                    :title="
                        student.has_scores
                            ? 'Siswa yang sudah memiliki nilai tidak dapat dihapus; gunakan Nonaktifkan.'
                            : undefined
                    "
                    @click="deleteOpen = true"
                >
                    <Trash2 /> Hapus
                </Button>
            </div>
        </div>

        <!-- Progress (PRD-06 §4) -->
        <div class="mb-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
            <ChartCard
                data-tour="profile-chart"
                title="Perkembangan level"
                :description="
                    points.some((p) => p.source === 'draft')
                        ? 'Garis putus-putus = nilai Draft (belum final).'
                        : 'Overall per periode; warna titik = level.'
                "
                :empty="!points.length"
                :filename="`Perkembangan_${student.nipd}`"
            >
                <div class="mb-2 flex gap-1 text-xs">
                    <button
                        v-for="o in [
                            { k: 'overall', l: 'Overall' },
                            { k: 'skills', l: 'Per skill' },
                        ]"
                        :key="o.k"
                        type="button"
                        :class="
                            cn(
                                'rounded-full border px-3 py-1',
                                chartMode === o.k
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'bg-card',
                            )
                        "
                        @click="chartMode = o.k as 'overall' | 'skills'"
                    >
                        {{ o.l }}
                    </button>
                </div>
                <StudentProgressChart :points="points" :mode="chartMode" />
                <template #table>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-muted-foreground">
                                <th class="py-1">Periode</th>
                                <th class="py-1 text-right">Total</th>
                                <th class="py-1 text-right">Level</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="p in points"
                                :key="p.enrollment_id"
                                class="border-t"
                            >
                                <td class="py-1">
                                    {{ p.term }} ({{ p.classroom.name }})
                                </td>
                                <td class="py-1 text-right tabular-nums">
                                    {{ formatScore(p.total) }}
                                </td>
                                <td class="py-1 text-right">
                                    {{ p.overall_level }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </template>
            </ChartCard>

            <Card data-tour="profile-summary" class="h-fit">
                <CardHeader
                    ><CardTitle class="text-base"
                        >Ringkasan</CardTitle
                    ></CardHeader
                >
                <CardContent class="space-y-3 text-sm">
                    <div class="flex items-center gap-3">
                        <span class="text-muted-foreground"
                            >Level terakhir (final)</span
                        >
                        <LevelBadge
                            :level="latestLevel"
                            size="lg"
                            class="ml-auto"
                        />
                    </div>
                    <p class="text-muted-foreground">
                        {{ points.length }} periode tercatat
                    </p>
                    <p
                        v-if="narrative"
                        class="rounded-lg bg-accent/60 p-3 leading-relaxed"
                    >
                        {{ narrative }}
                    </p>
                    <p v-else class="text-muted-foreground">
                        Ringkasan perkembangan muncul setelah ada dua periode
                        yang sudah final.
                    </p>
                </CardContent>
            </Card>
        </div>

        <Card
            v-if="points.length"
            data-tour="profile-history"
            class="mb-6 overflow-hidden p-0"
        >
            <CardHeader class="px-6 pt-6"
                ><CardTitle class="text-base"
                    >Riwayat level per periode</CardTitle
                ></CardHeader
            >
            <!-- Desktop table -->
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full text-sm">
                    <thead class="bg-muted/60 text-xs text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 text-left">Periode</th>
                            <th class="px-2 py-2 text-left">Kelas</th>
                            <th v-for="s in SKILLS" :key="s" class="px-2 py-2">
                                {{ SKILL_LABEL[s].charAt(0) }}
                            </th>
                            <th class="px-2 py-2">Total</th>
                            <th class="px-2 py-2">Perubahan</th>
                            <th class="px-2 py-2">NatGeo</th>
                            <th class="px-4 py-2 text-right">Rapor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="p in [...points].reverse()"
                            :key="p.enrollment_id"
                            class="border-t"
                        >
                            <td class="px-4 py-2">
                                {{ p.term }}
                                <span
                                    v-if="p.source !== 'final'"
                                    class="ml-1 rounded-full bg-tag px-1.5 text-[10px] font-semibold text-tag-foreground"
                                >
                                    {{
                                        p.source === 'draft'
                                            ? 'Draft'
                                            : 'Dikoreksi'
                                    }}
                                </span>
                            </td>
                            <td class="px-2 py-2 font-semibold">
                                {{ p.classroom.name }}
                            </td>
                            <td
                                v-for="s in SKILLS"
                                :key="s"
                                class="px-2 py-2 text-center"
                            >
                                <LevelBadge :level="p.levels[s]" size="sm" />
                                <span
                                    class="block text-[11px] text-muted-foreground tabular-nums"
                                    >{{ formatScore(p.scores[s]) }}</span
                                >
                            </td>
                            <td class="px-2 py-2 text-center">
                                <LevelBadge :level="p.overall_level" />
                                <span
                                    class="block text-[11px] font-semibold tabular-nums"
                                    >{{ formatScore(p.total) }}</span
                                >
                            </td>
                            <td class="px-2 py-2 text-center text-xs">
                                <span
                                    v-if="p.change"
                                    :class="
                                        cn(
                                            'inline-flex items-center gap-0.5 font-semibold',
                                            p.change === 'up'
                                                ? 'text-status-meeting-fg'
                                                : p.change === 'down'
                                                  ? 'text-status-below-fg'
                                                  : 'text-muted-foreground',
                                        )
                                    "
                                >
                                    {{ p.previous_level }} →
                                    {{ p.overall_level }}
                                    <ArrowUp
                                        v-if="p.change === 'up'"
                                        class="size-3"
                                    /><ArrowDown
                                        v-else-if="p.change === 'down'"
                                        class="size-3"
                                    />
                                </span>
                                <span v-else class="text-muted-foreground"
                                    >–</span
                                >
                            </td>
                            <td class="px-2 py-2 text-center">
                                <LevelBadge
                                    v-if="p.natgeo"
                                    :level="p.natgeo"
                                    size="sm"
                                />
                                <span v-else class="text-muted-foreground"
                                    >–</span
                                >
                            </td>
                            <td class="px-4 py-2 text-right">
                                <Button
                                    v-if="p.pdf_url"
                                    size="sm"
                                    variant="outline"
                                    as="a"
                                    :href="p.pdf_url"
                                    ><FileDown /> PDF</Button
                                >
                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
                                    >–</span
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- Phone cards -->
            <ul class="grid gap-2 p-4 md:hidden">
                <li
                    v-for="p in [...points].reverse()"
                    :key="p.enrollment_id"
                    class="rounded-xl border p-3"
                >
                    <div class="flex items-center gap-2">
                        <span class="font-semibold"
                            >{{ p.term }} • {{ p.classroom.name }}</span
                        >
                        <LevelBadge :level="p.overall_level" class="ml-auto" />
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Total {{ formatScore(p.total)
                        }}<template v-if="p.change">
                            • {{ p.previous_level }} →
                            {{ p.overall_level }}</template
                        >
                        <template v-if="p.source === 'draft'">
                            • Draft</template
                        >
                    </p>
                    <Button
                        v-if="p.pdf_url"
                        size="sm"
                        variant="outline"
                        class="mt-2"
                        as="a"
                        :href="p.pdf_url"
                        ><FileDown /> PDF</Button
                    >
                </li>
            </ul>
        </Card>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
            <Card>
                <CardHeader><CardTitle>Riwayat kelas</CardTitle></CardHeader>
                <CardContent>
                    <p
                        v-if="!history.length"
                        class="text-sm text-muted-foreground"
                    >
                        Belum terdaftar di kelas mana pun.
                    </p>
                    <ul v-else class="divide-y">
                        <li
                            v-for="h in history"
                            :key="h.id"
                            class="flex flex-wrap items-center gap-x-3 gap-y-1 py-3"
                        >
                            <span class="font-medium">{{ h.term }}</span>
                            <span
                                v-if="h.term_active"
                                class="rounded-full bg-primary px-2 py-0.5 text-xs font-semibold text-primary-foreground"
                                >Aktif</span
                            >
                            <Link
                                :href="`/kelas/${h.classroom.id}`"
                                class="ml-auto font-semibold hover:underline"
                                >Kelas {{ h.classroom.name }}</Link
                            >
                            <span class="text-sm text-muted-foreground"
                                >No. {{ h.roll_no }}</span
                            >
                            <StatusBadge
                                :status="h.classroom.status"
                                size="sm"
                            />
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <div class="grid h-fit gap-6">
                <Card v-if="student.notes">
                    <CardHeader
                        ><CardTitle class="text-base"
                            >Catatan</CardTitle
                        ></CardHeader
                    >
                    <CardContent class="text-sm whitespace-pre-line">{{
                        student.notes
                    }}</CardContent>
                </Card>
            </div>
        </div>

        <FormSheet v-model:open="formOpen" title="Ubah data siswa">
            <form id="student-edit" @submit.prevent="save">
                <StudentFormFields :form="form" :classes="classes" />
            </form>
            <template #footer>
                <Button variant="outline" @click="formOpen = false"
                    >Batal</Button
                >
                <Button
                    type="submit"
                    form="student-edit"
                    :disabled="form.processing"
                    ><Spinner v-if="form.processing" /> Simpan</Button
                >
            </template>
        </FormSheet>

        <ConfirmDialog
            v-model:open="deleteOpen"
            :title="`Hapus ${student.name}?`"
            description="Data siswa dan keanggotaan kelasnya dihapus permanen."
            :confirm-label="`Hapus ${student.name}`"
            destructive
            :processing="busy"
            @confirm="destroy"
        />
    </AppLayout>
</template>
