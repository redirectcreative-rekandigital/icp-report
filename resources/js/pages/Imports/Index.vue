<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCircle2,
    Download,
    FileDown,
    FileSpreadsheet,
    Info,
    Minus,
    RefreshCw,
    Sparkles,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import EmptyState from '@/components/app/EmptyState.vue';
import ImportDropzone from '@/components/app/ImportDropzone.vue';
import NativeSelect from '@/components/app/NativeSelect.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import TourIconButton from '@/components/app/TourIconButton.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { withPin } from '@/composables/usePinConfirm';
import { useDialogTour } from '@/composables/useTour';
import AppLayout from '@/layouts/AppLayout.vue';
import { cn } from '@/lib/utils';

/** /impor — three two-step imports + exports (PRD-03 §5–6). */
type Status = 'new' | 'updated' | 'unchanged' | 'warning' | 'error';
type Row = {
    line: number;
    sheet?: string;
    nipd: string;
    name: string;
    file_name: string;
    gender?: string | null;
    class: string;
    status: Status;
    messages: string[];
    changes: string[];
};
type ImportType = 'siswa' | 'nilai' | 'excel-lama';

const props = defineProps<{
    term: { id: number; label: string; classes: number } | null;
    classes: { id: number; name: string; final: boolean }[];
    preview: {
        type: ImportType;
        filename: string;
        overwrite: boolean;
        class: string | null;
        rows: Row[];
        summary: Record<Status | 'total' | 'importable', number>;
    } | null;
    result: {
        type: ImportType;
        message: string;
        term: string;
        class_id?: number;
    } | null;
}>();

const TYPE_LABEL: Record<ImportType, string> = {
    siswa: 'Impor siswa',
    nilai: 'Impor nilai kelas',
    'excel-lama': 'Impor file Excel lama',
};

/* ---------- uploads ---------- */
const studentUpload = useForm<{ file: File | null }>({ file: null });
const scoreUpload = useForm<{
    file: File | null;
    classroom_id: string;
    overwrite: boolean;
}>({
    file: null,
    classroom_id:
        new URLSearchParams(window.location.search).get('kelas') ?? '',
    overwrite: false,
});
const legacyUpload = useForm<{ file: File | null; overwrite: boolean }>({
    file: null,
    overwrite: false,
});

const progressOf = (f: {
    processing: boolean;
    progress?: { percentage?: number } | null;
}) => (f.processing ? (f.progress?.percentage ?? 0) : null);

function sendStudents(file: File): void {
    studentUpload.file = file;
    studentUpload.post('/impor/siswa/pratinjau', {
        forceFormData: true,
        preserveScroll: true,
    });
}

function sendScores(file: File): void {
    scoreUpload.file = file;
    scoreUpload.post('/impor/nilai/pratinjau', {
        forceFormData: true,
        preserveScroll: true,
    });
}

function sendLegacy(file: File): void {
    legacyUpload.file = file;
    legacyUpload.post('/impor/excel-lama/pratinjau', {
        forceFormData: true,
        preserveScroll: true,
    });
}

const openClasses = computed(() => props.classes.filter((c) => !c.final));
const exportClass = ref(props.classes[0] ? String(props.classes[0].id) : '');

/* ---------- preview ---------- */
const statusMeta: Record<
    Status,
    { label: string; icon: unknown; cls: string }
> = {
    new: {
        label: 'Baru',
        icon: Sparkles,
        cls: 'bg-status-meeting text-status-meeting-fg',
    },
    updated: {
        label: 'Diperbarui',
        icon: RefreshCw,
        cls: 'bg-status-above text-status-above-fg',
    },
    unchanged: {
        label: 'Tidak berubah',
        icon: Minus,
        cls: 'bg-status-draft text-status-draft-fg',
    },
    warning: {
        label: 'Peringatan',
        icon: AlertTriangle,
        cls: 'bg-status-incomplete text-status-incomplete-fg',
    },
    error: {
        label: 'Galat',
        icon: XCircle,
        cls: 'bg-status-below text-status-below-fg',
    },
};

const filter = ref<Status | 'all'>('all');
const visible = computed(() =>
    (props.preview?.rows ?? []).filter(
        (r) => filter.value === 'all' || r.status === filter.value,
    ),
);
const problems = computed(() =>
    (props.preview?.rows ?? []).filter(
        (r) => r.status === 'error' || r.status === 'warning',
    ),
);
const committing = ref(false);

function commit(): void {
    const run = () => {
        committing.value = true;
        router.post(
            '/impor/simpan',
            {},
            {
                preserveScroll: true,
                onFinish: () => (committing.value = false),
            },
        );
    };

    // Replacing existing scores needs the PIN (PRD-02 §6).
    if (props.preview?.overwrite) {
        void withPin(run, 'Konfirmasi timpa nilai');
    } else {
        run();
    }
}

function cancel(): void {
    router.delete('/impor', { preserveScroll: true });
}

useDialogTour(
    () => 'flow-pratinjau-impor',
    () => !!props.preview,
);
</script>

<template>
    <Head title="Impor/Ekspor" />
    <AppLayout :breadcrumbs="[{ title: 'Impor/Ekspor', href: '/impor' }]">
        <PageHeader
            title="Impor/Ekspor"
            tour="impor"
            description="Impor data dari Excel dalam dua tahap: pratinjau dulu, lalu simpan."
        />

        <Card v-if="!term">
            <EmptyState
                :icon="Info"
                title="Belum ada periode aktif"
                description="Impor memasukkan data ke kelas di periode aktif. Buat periode dan kelas terlebih dahulu."
            >
                <Button as-child
                    ><Link href="/periode">Buka Periode & Kelas</Link></Button
                >
            </EmptyState>
        </Card>

        <template v-else>
            <!-- Result after commit -->
            <Card
                v-if="result && !preview"
                class="mb-6 border-status-meeting-fg/30 bg-status-meeting"
            >
                <CardContent
                    class="flex flex-wrap items-center gap-3 py-4 text-status-meeting-fg"
                >
                    <CheckCircle2 class="size-6" />
                    <div class="flex-1">
                        <p class="font-semibold">
                            {{ TYPE_LABEL[result.type] }} — {{ result.term }}
                        </p>
                        <p class="text-sm">{{ result.message }}</p>
                    </div>
                    <Button
                        v-if="result.class_id"
                        variant="outline"
                        class="bg-card"
                        as-child
                        ><Link :href="`/kelas/${result.class_id}/nilai`"
                            >Buka kelas</Link
                        ></Button
                    >
                    <Button v-else variant="outline" class="bg-card" as-child
                        ><Link href="/kelas">Lihat kelas</Link></Button
                    >
                </CardContent>
            </Card>

            <!-- Preview -->
            <Card v-if="preview" class="mb-6">
                <CardHeader>
                    <div class="flex items-center gap-1">
                        <CardTitle
                            >Pratinjau — {{ TYPE_LABEL[preview.type]
                            }}{{
                                preview.class ? ` ${preview.class}` : ''
                            }}</CardTitle
                        >
                        <TourIconButton
                            tour="flow-pratinjau-impor"
                            class="-my-1"
                        />
                    </div>
                    <CardDescription>
                        {{ preview.filename }} → {{ term.label }}. Belum ada
                        data yang disimpan.
                        <strong
                            v-if="preview.overwrite"
                            class="text-status-incomplete-fg"
                            >Timpa nilai aktif (perlu PIN).</strong
                        >
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <p
                        data-tour="ip-summary"
                        class="mb-3 text-sm font-medium"
                        aria-live="polite"
                    >
                        {{ preview.summary.total }} baris •
                        {{ preview.summary.new }} baru •
                        {{ preview.summary.updated }} diperbarui •
                        {{ preview.summary.unchanged }} tidak berubah •
                        {{ preview.summary.warning }} peringatan •
                        {{ preview.summary.error }} galat
                    </p>

                    <div
                        data-tour="ip-filter"
                        class="mb-4 flex flex-wrap gap-2"
                    >
                        <button
                            v-for="key in [
                                'all',
                                'new',
                                'updated',
                                'unchanged',
                                'warning',
                                'error',
                            ] as const"
                            :key="key"
                            type="button"
                            :class="
                                cn(
                                    'rounded-full border px-3 py-1 text-sm font-medium transition-colors',
                                    filter === key
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'bg-card hover:bg-muted',
                                )
                            "
                            @click="filter = key"
                        >
                            {{
                                key === 'all' ? 'Semua' : statusMeta[key].label
                            }}
                            <span class="tabular-nums opacity-80">{{
                                key === 'all'
                                    ? preview.summary.total
                                    : preview.summary[key]
                            }}</span>
                        </button>
                    </div>

                    <div
                        data-tour="ip-table"
                        class="hidden max-h-[28rem] overflow-auto rounded-lg border md:block"
                    >
                        <Table>
                            <TableHeader class="sticky top-0 z-10 bg-card">
                                <TableRow>
                                    <TableHead class="w-24">Baris</TableHead>
                                    <TableHead class="w-36">Status</TableHead>
                                    <TableHead class="w-28">NIPD</TableHead>
                                    <TableHead>Nama</TableHead>
                                    <TableHead class="w-16">Kelas</TableHead>
                                    <TableHead>Keterangan</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="r in visible"
                                    :key="`${r.sheet ?? ''}${r.line}`"
                                >
                                    <TableCell
                                        class="text-muted-foreground tabular-nums"
                                    >
                                        {{ r.line
                                        }}<span
                                            v-if="r.sheet"
                                            class="block text-xs"
                                            >{{ r.sheet }}</span
                                        >
                                    </TableCell>
                                    <TableCell>
                                        <span
                                            :class="
                                                cn(
                                                    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold',
                                                    statusMeta[r.status].cls,
                                                )
                                            "
                                        >
                                            <component
                                                :is="statusMeta[r.status].icon"
                                                class="size-3"
                                            />
                                            {{ statusMeta[r.status].label }}
                                        </span>
                                    </TableCell>
                                    <TableCell class="font-mono text-sm">{{
                                        r.nipd || '–'
                                    }}</TableCell>
                                    <TableCell>{{ r.name || '–' }}</TableCell>
                                    <TableCell class="font-semibold">{{
                                        r.class || '–'
                                    }}</TableCell>
                                    <TableCell
                                        class="text-sm whitespace-normal"
                                    >
                                        <p
                                            v-for="c in r.changes"
                                            :key="c"
                                            class="text-status-above-fg"
                                        >
                                            {{ c }}
                                        </p>
                                        <p
                                            v-for="m in r.messages"
                                            :key="m"
                                            :class="
                                                r.status === 'error'
                                                    ? 'text-status-below-fg'
                                                    : 'text-status-incomplete-fg'
                                            "
                                        >
                                            {{ m }}
                                        </p>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="!visible.length">
                                    <TableCell
                                        colspan="6"
                                        class="py-6 text-center text-muted-foreground"
                                        >Tidak ada baris dengan status
                                        ini.</TableCell
                                    >
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <div data-tour="ip-table" class="md:hidden">
                        <p class="mb-2 text-xs text-muted-foreground">
                            Pratinjau lengkap lebih nyaman di laptop.
                        </p>
                        <ul class="grid gap-2">
                            <li
                                v-for="r in problems"
                                :key="`${r.sheet ?? ''}${r.line}`"
                                class="rounded-lg border p-3 text-sm"
                            >
                                <p
                                    class="mb-1 flex items-center gap-2 font-semibold"
                                >
                                    <span
                                        :class="
                                            cn(
                                                'rounded-full px-2 py-0.5 text-xs',
                                                statusMeta[r.status].cls,
                                            )
                                        "
                                        >{{ statusMeta[r.status].label }}</span
                                    >
                                    Baris {{ r.line }} •
                                    {{ r.nipd || 'tanpa NIPD' }}
                                </p>
                                <p v-for="m in r.messages" :key="m">{{ m }}</p>
                            </li>
                            <li
                                v-if="!problems.length"
                                class="rounded-lg border p-3 text-sm text-status-meeting-fg"
                            >
                                Semua baris valid.
                            </li>
                        </ul>
                    </div>

                    <div
                        data-tour="ip-actions"
                        class="mt-4 flex flex-wrap items-center gap-2"
                    >
                        <Button
                            :disabled="
                                !preview.summary.importable || committing
                            "
                            @click="commit"
                        >
                            <Spinner v-if="committing" />
                            {{
                                committing
                                    ? 'Mengimpor…'
                                    : `Impor ${preview.summary.importable} baris`
                            }}
                        </Button>
                        <Button
                            v-if="
                                preview.summary.error +
                                    preview.summary.warning >
                                0
                            "
                            variant="outline"
                            as="a"
                            href="/impor/laporan-galat"
                        >
                            <FileDown /> Unduh laporan galat (.xlsx)
                        </Button>
                        <Button
                            variant="ghost"
                            :disabled="committing"
                            @click="cancel"
                            >Batal</Button
                        >
                    </div>
                </CardContent>
            </Card>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Students -->
                <Card data-tour="import-students">
                    <CardHeader>
                        <CardTitle>Impor siswa</CardTitle>
                        <CardDescription
                            >Kolom: NIPD*, NISN*, Nama*, L/P, Kelas* (harus ada
                            di {{ term.label }}).</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="grid gap-3">
                        <ImportDropzone
                            :progress="progressOf(studentUpload)"
                            :disabled="studentUpload.processing"
                            @file="sendStudents"
                        />
                        <p
                            v-if="studentUpload.errors.file"
                            class="text-sm font-medium text-destructive"
                        >
                            {{ studentUpload.errors.file }}
                        </p>
                        <Button
                            variant="outline"
                            class="w-fit"
                            as="a"
                            href="/impor/template/siswa"
                            ><Download /> Unduh template_siswa.xlsx</Button
                        >
                    </CardContent>
                </Card>

                <!-- Scores of one class -->
                <Card data-tour="import-scores">
                    <CardHeader>
                        <CardTitle>Impor nilai kelas</CardTitle>
                        <CardDescription
                            >Isi nilai offline di template berisi daftar siswa,
                            lalu unggah. Sel kosong = nilai tidak
                            diubah.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="grid gap-3">
                        <div class="flex flex-wrap items-end gap-3">
                            <div class="grid gap-1.5">
                                <Label for="score-class">Kelas</Label>
                                <NativeSelect
                                    id="score-class"
                                    v-model="scoreUpload.classroom_id"
                                    class="w-40"
                                    :invalid="!!scoreUpload.errors.classroom_id"
                                >
                                    <option value="" disabled>
                                        Pilih kelas…
                                    </option>
                                    <option
                                        v-for="c in openClasses"
                                        :key="c.id"
                                        :value="String(c.id)"
                                    >
                                        {{ c.name }}
                                    </option>
                                </NativeSelect>
                            </div>
                            <Button
                                variant="outline"
                                :disabled="!scoreUpload.classroom_id"
                                as="a"
                                :href="
                                    scoreUpload.classroom_id
                                        ? `/impor/template/nilai/${scoreUpload.classroom_id}`
                                        : undefined
                                "
                            >
                                <Download /> Unduh template nilai
                            </Button>
                        </div>
                        <label
                            data-tour="import-overwrite"
                            class="flex items-start justify-between gap-3 text-sm"
                        >
                            <span>
                                <span class="block font-medium"
                                    >Timpa nilai yang sudah ada</span
                                >
                                <span class="block text-muted-foreground"
                                    >Ketik - di sel untuk mengosongkan. Perlu
                                    PIN saat menyimpan.</span
                                >
                            </span>
                            <Switch v-model="scoreUpload.overwrite" />
                        </label>
                        <ImportDropzone
                            v-if="scoreUpload.classroom_id"
                            :progress="progressOf(scoreUpload)"
                            :disabled="scoreUpload.processing"
                            @file="sendScores"
                        />
                        <p
                            v-else
                            class="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground"
                        >
                            Pilih kelas terlebih dahulu.
                        </p>
                        <p
                            v-if="
                                scoreUpload.errors.file ||
                                scoreUpload.errors.classroom_id
                            "
                            class="text-sm font-medium text-destructive"
                        >
                            {{
                                scoreUpload.errors.file ||
                                scoreUpload.errors.classroom_id
                            }}
                        </p>
                    </CardContent>
                </Card>

                <!-- Old Excel program -->
                <Card data-tour="import-legacy">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2"
                            ><FileSpreadsheet class="size-5 text-primary" />
                            Impor dari file Excel lama</CardTitle
                        >
                        <CardDescription>
                            File <code>ICP_English_Progress_Report.xlsx</code>:
                            membaca sheet Grade 3–6 (baris 6–45). Siswa baru
                            dibuat, dimasukkan ke kelas di kolom Kelas, lalu
                            nilainya diisi.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-3">
                        <label
                            class="flex items-center justify-between gap-3 text-sm"
                        >
                            <span class="font-medium"
                                >Timpa nilai yang sudah ada</span
                            >
                            <Switch v-model="legacyUpload.overwrite" />
                        </label>
                        <ImportDropzone
                            :progress="progressOf(legacyUpload)"
                            :disabled="legacyUpload.processing"
                            :max-mb="5"
                            @file="sendLegacy"
                        />
                        <p
                            v-if="legacyUpload.errors.file"
                            class="text-sm font-medium text-destructive"
                        >
                            {{ legacyUpload.errors.file }}
                        </p>
                    </CardContent>
                </Card>

                <!-- Exports -->
                <Card data-tour="export-card" class="h-fit">
                    <CardHeader>
                        <CardTitle>Ekspor</CardTitle>
                        <CardDescription
                            >Kelas final diekspor dari data saat finalisasi;
                            kelas Draft ditandai "(DRAFT)".</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="grid gap-3">
                        <Button
                            variant="outline"
                            class="justify-start"
                            as="a"
                            :href="`/ekspor/periode/${term.id}`"
                        >
                            <Download /> Rekap periode — {{ term.label }}
                        </Button>
                        <div class="flex flex-wrap gap-2">
                            <NativeSelect
                                v-model="exportClass"
                                class="w-32"
                                aria-label="Kelas untuk rekap"
                            >
                                <option
                                    v-for="c in classes"
                                    :key="c.id"
                                    :value="String(c.id)"
                                >
                                    {{ c.name }}
                                </option>
                            </NativeSelect>
                            <Button
                                variant="outline"
                                :disabled="!exportClass"
                                as="a"
                                :href="
                                    exportClass
                                        ? `/ekspor/kelas/${exportClass}`
                                        : undefined
                                "
                            >
                                <Download /> Rekap kelas
                            </Button>
                        </div>
                        <Button
                            variant="outline"
                            class="justify-start"
                            as="a"
                            :href="`/ekspor/siswa/${term.id}`"
                        >
                            <Download /> Daftar siswa — {{ term.label }}
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </template>
    </AppLayout>
</template>
