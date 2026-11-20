<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    CalendarPlus,
    CalendarRange,
    CheckCircle2,
    ChevronRight,
    MoreVertical,
    Plus,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import EmptyState from '@/components/app/EmptyState.vue';
import NativeSelect from '@/components/app/NativeSelect.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import ResponsiveDialog from '@/components/app/ResponsiveDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { withPin } from '@/composables/usePinConfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';

type TermRow = {
    id: number;
    semester: number;
    label: string;
    report_date: string | null;
    is_active: boolean;
    classes: number;
    students: number;
    final: number;
    has_scores: boolean;
};
type Year = {
    id: number;
    name: string;
    starts_on: string | null;
    ends_on: string | null;
    terms: TermRow[];
};

const props = defineProps<{ years: Year[] }>();

// Suggest the next academic year (latest + 1, or the current school year).
const suggestedYear = computed(() => {
    const latest = props.years[0]?.name;
    const now = new Date();
    const start = latest
        ? Number(latest.slice(0, 4)) + 1
        : now.getMonth() >= 6
          ? now.getFullYear()
          : now.getFullYear() - 1;

    return `${start}/${start + 1}`;
});

const yearOpen = ref(false);
const yearForm = useForm({ name: '', starts_on: '', ends_on: '' });

function openYear(): void {
    yearForm.reset();
    yearForm.clearErrors();
    yearForm.name = suggestedYear.value;
    yearOpen.value = true;
}

function saveYear(): void {
    yearForm.post('/periode/tahun', {
        preserveScroll: true,
        onSuccess: () => (yearOpen.value = false),
    });
}

const termOpen = ref(false);
const termYear = ref<Year | null>(null);
const termForm = useForm({ semester: '1', report_date: '' });

function openTerm(year: Year): void {
    termYear.value = year;
    termForm.reset();
    termForm.clearErrors();
    termForm.semester = year.terms.some((t) => t.semester === 1) ? '2' : '1';
    termOpen.value = true;
}

function saveTerm(): void {
    termForm.post(`/periode/tahun/${termYear.value!.id}/semester`, {
        preserveScroll: true,
        onSuccess: () => (termOpen.value = false),
    });
}

const activating = ref<{ year: Year; term: TermRow } | null>(null);
const activateOpen = computed({
    get: () => !!activating.value,
    set: (v) => !v && (activating.value = null),
});
const busy = ref(false);

function activate(): void {
    busy.value = true;
    router.post(
        `/periode/${activating.value!.term.id}/aktifkan`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                busy.value = false;
                activating.value = null;
            },
        },
    );
}

const deleting = ref<{ year: Year; term?: TermRow } | null>(null);
const deleteOpen = computed({
    get: () => !!deleting.value,
    set: (v) => !v && (deleting.value = null),
});

function destroy(): void {
    const d = deleting.value!;
    const url = d.term
        ? `/periode/${d.term.id}`
        : `/periode/tahun/${d.year.id}`;
    void withPin(() => {
        busy.value = true;
        router.delete(url, {
            preserveScroll: true,
            onFinish: () => {
                busy.value = false;
                deleting.value = null;
            },
        });
    }, 'Konfirmasi hapus');
}

const deleteTitle = computed(() =>
    deleting.value?.term
        ? `Hapus ${deleting.value.year.name} ${deleting.value.term.label}?`
        : `Hapus tahun ajaran ${deleting.value?.year.name}?`,
);
</script>

<template>
    <Head title="Periode & Kelas" />
    <AppLayout :breadcrumbs="[{ title: 'Periode & Kelas', href: '/periode' }]">
        <PageHeader
            title="Periode & Kelas"
            tour="periode-index"
            description="Tahun ajaran, semester, dan kelas di setiap periode. Hanya satu periode yang aktif."
        >
            <Button data-tour="year-add" @click="openYear"
                ><CalendarPlus /> Tambah tahun ajaran</Button
            >
        </PageHeader>

        <Card v-if="!years.length">
            <EmptyState
                :icon="CalendarRange"
                title="Belum ada periode"
                description="Mulai dengan membuat tahun ajaran, lalu tambahkan semesternya."
            >
                <Button @click="openYear"
                    ><CalendarPlus /> Buat tahun ajaran</Button
                >
            </EmptyState>
        </Card>

        <div class="grid gap-4 lg:grid-cols-2">
            <Card v-for="year in years" :key="year.id">
                <CardHeader
                    class="flex flex-row items-start justify-between gap-2 space-y-0"
                >
                    <div>
                        <CardTitle class="text-xl">{{ year.name }}</CardTitle>
                        <p
                            v-if="year.starts_on || year.ends_on"
                            class="text-sm text-muted-foreground"
                        >
                            {{ formatDate(year.starts_on) }} –
                            {{ formatDate(year.ends_on) }}
                        </p>
                    </div>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Aksi tahun ajaran"
                                ><MoreVertical
                            /></Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                :disabled="year.terms.length >= 2"
                                @click="openTerm(year)"
                            >
                                <Plus /> Tambah semester
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                :disabled="year.terms.length > 0"
                                class="text-destructive"
                                @click="deleting = { year }"
                            >
                                <Trash2 /> Hapus tahun ajaran
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </CardHeader>
                <CardContent class="grid gap-2">
                    <div
                        v-for="term in year.terms"
                        :key="term.id"
                        data-tour="term-card"
                        class="rounded-lg border p-3 transition-colors"
                        :class="
                            term.is_active
                                ? 'border-primary/40 bg-accent/60'
                                : ''
                        "
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-semibold">{{ term.label }}</p>
                            <span
                                v-if="term.is_active"
                                data-tour="term-activate"
                                class="inline-flex items-center gap-1 rounded-full bg-primary px-2 py-0.5 text-xs font-semibold text-primary-foreground"
                            >
                                <CheckCircle2 class="size-3" /> Aktif
                            </span>
                            <span class="ml-auto text-xs text-muted-foreground"
                                >Tanggal rapor:
                                {{ formatDate(term.report_date) }}</span
                            >
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ term.classes }} kelas • {{ term.students }} siswa
                            • {{ term.final }}/{{ term.classes }} kelas final
                        </p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <Button size="sm" as-child data-tour="term-manage">
                                <Link :href="`/periode/${term.id}`"
                                    >Kelola kelas <ChevronRight
                                /></Link>
                            </Button>
                            <Button
                                v-if="!term.is_active"
                                size="sm"
                                variant="outline"
                                data-tour="term-activate"
                                @click="activating = { year, term }"
                            >
                                Jadikan aktif
                            </Button>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <span tabindex="0">
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            class="text-destructive"
                                            :disabled="term.has_scores"
                                            @click="deleting = { year, term }"
                                        >
                                            <Trash2 /> Hapus
                                        </Button>
                                    </span>
                                </TooltipTrigger>
                                <TooltipContent v-if="term.has_scores"
                                    >Periode yang sudah memiliki nilai tidak
                                    dapat dihapus.</TooltipContent
                                >
                            </Tooltip>
                        </div>
                    </div>
                    <button
                        v-if="year.terms.length < 2"
                        type="button"
                        class="flex min-h-12 items-center justify-center gap-2 rounded-lg border border-dashed text-sm font-medium text-muted-foreground hover:border-primary hover:text-primary"
                        @click="openTerm(year)"
                    >
                        <Plus class="size-4" /> Tambah semester
                    </button>
                </CardContent>
            </Card>
        </div>

        <ResponsiveDialog v-model:open="yearOpen" title="Tambah tahun ajaran">
            <form id="year-form" class="grid gap-4" @submit.prevent="saveYear">
                <div class="grid gap-1.5">
                    <Label for="year-name">Tahun ajaran</Label>
                    <Input
                        id="year-name"
                        v-model="yearForm.name"
                        placeholder="2026/2027"
                        maxlength="9"
                        class="tabular-nums"
                        :aria-invalid="!!yearForm.errors.name"
                    />
                    <InputError :message="yearForm.errors.name" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label for="starts_on">Mulai (opsional)</Label>
                        <Input
                            id="starts_on"
                            v-model="yearForm.starts_on"
                            type="date"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="ends_on">Selesai (opsional)</Label>
                        <Input
                            id="ends_on"
                            v-model="yearForm.ends_on"
                            type="date"
                        />
                    </div>
                </div>
                <InputError :message="yearForm.errors.ends_on" />
            </form>
            <template #footer>
                <Button variant="outline" @click="yearOpen = false"
                    >Batal</Button
                >
                <Button
                    type="submit"
                    form="year-form"
                    :disabled="yearForm.processing"
                >
                    <Spinner v-if="yearForm.processing" /> Simpan
                </Button>
            </template>
        </ResponsiveDialog>

        <ResponsiveDialog
            v-model:open="termOpen"
            :title="`Tambah semester — ${termYear?.name ?? ''}`"
            description="Pengaturan penilaian Kelas 3–6 otomatis disalin dari periode terakhir."
        >
            <form id="term-form" class="grid gap-4" @submit.prevent="saveTerm">
                <div class="grid gap-1.5">
                    <Label for="semester">Semester</Label>
                    <NativeSelect
                        id="semester"
                        v-model="termForm.semester"
                        :invalid="!!termForm.errors.semester"
                    >
                        <option value="1">Semester 1</option>
                        <option value="2">Semester 2</option>
                    </NativeSelect>
                    <InputError :message="termForm.errors.semester" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="report_date">Tanggal rapor</Label>
                    <Input
                        id="report_date"
                        v-model="termForm.report_date"
                        type="date"
                    />
                    <p class="text-xs text-muted-foreground">
                        Dipakai di tanda tangan rapor. Bisa diisi nanti.
                    </p>
                </div>
            </form>
            <template #footer>
                <Button variant="outline" @click="termOpen = false"
                    >Batal</Button
                >
                <Button
                    type="submit"
                    form="term-form"
                    :disabled="termForm.processing"
                >
                    <Spinner v-if="termForm.processing" /> Simpan
                </Button>
            </template>
        </ResponsiveDialog>

        <ConfirmDialog
            v-model:open="activateOpen"
            title="Ganti periode aktif?"
            :description="`Periode aktif akan berganti ke ${activating?.year.name} ${activating?.term.label}. Menu Kelas akan menampilkan kelas periode ini.`"
            :confirm-label="`Aktifkan ${activating?.term.label ?? ''}`"
            :processing="busy"
            @confirm="activate"
        />

        <ConfirmDialog
            v-model:open="deleteOpen"
            :title="deleteTitle"
            description="Semua kelas dan keanggotaan siswa di periode ini ikut terhapus. Data siswa tetap ada."
            :confirm-label="
                deleting?.term
                    ? `Hapus ${deleting.term.label}`
                    : `Hapus ${deleting?.year.name}`
            "
            destructive
            :processing="busy"
            @confirm="destroy"
        />
    </AppLayout>
</template>
