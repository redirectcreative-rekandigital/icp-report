<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Copy,
    Lock,
    MoreVertical,
    Pencil,
    Plus,
    School,
    Trash2,
    Users,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import EmptyState from '@/components/app/EmptyState.vue';
import FormSheet from '@/components/app/FormSheet.vue';
import NativeSelect from '@/components/app/NativeSelect.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import ResponsiveDialog from '@/components/app/ResponsiveDialog.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
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
import AppLayout from '@/layouts/AppLayout.vue';
import { ApiError, http } from '@/lib/http';

type ClassRow = {
    id: number;
    grade: number;
    name: string;
    homeroom_teacher: string | null;
    homeroom_id_number: string | null;
    status: 'draft' | 'final';
    students: number;
    has_scores: boolean;
};
type PlanRow = {
    source_id: number;
    source_name: string;
    name: string;
    grade: number;
    students: number;
    skip: string | null;
};

const props = defineProps<{
    term: {
        id: number;
        label: string;
        year: string;
        semester: number;
        report_date: string | null;
        is_active: boolean;
    };
    classes: ClassRow[];
    copySources: { id: number; label: string; is_active: boolean }[];
}>();

/* ---------- class form ---------- */
const formOpen = ref(false);
const editing = ref<ClassRow | null>(null);
const form = useForm({
    grade: '3',
    name: '',
    homeroom_teacher: '',
    homeroom_id_number: '',
});

function suggestName(grade: number): string {
    const used = new Set(
        props.classes
            .filter((c) => c.grade === grade)
            .map((c) => c.name.toUpperCase()),
    );

    for (const letter of 'ABCDEFGHIJ') {
        if (!used.has(`${grade}${letter}`)) {
            return `${grade}${letter}`;
        }
    }

    return `${grade}`;
}

function openCreate(): void {
    editing.value = null;
    form.defaults({
        grade: '3',
        name: suggestName(3),
        homeroom_teacher: '',
        homeroom_id_number: '',
    });
    form.reset();
    form.clearErrors();
    formOpen.value = true;
}

function openEdit(c: ClassRow): void {
    editing.value = c;
    form.defaults({
        grade: String(c.grade),
        name: c.name,
        homeroom_teacher: c.homeroom_teacher ?? '',
        homeroom_id_number: c.homeroom_id_number ?? '',
    });
    form.reset();
    form.clearErrors();
    formOpen.value = true;
}

// New class: re-suggest the name when the grade changes and the name still is a suggestion.
watch(
    () => form.grade,
    (g, old) => {
        if (
            !editing.value &&
            (form.name === '' || form.name === suggestName(Number(old)))
        ) {
            form.name = suggestName(Number(g));
        }
    },
);

function save(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => (formOpen.value = false),
    };

    if (editing.value) {
        form.put(`/data-kelas/${editing.value.id}`, options);
    } else {
        form.post(`/periode/${props.term.id}/kelas`, options);
    }
}

const gradeLocked = computed(() => !!editing.value?.has_scores);

/* ---------- delete ---------- */
const deleting = ref<ClassRow | null>(null);
const deleteOpen = computed({
    get: () => !!deleting.value,
    set: (v) => !v && (deleting.value = null),
});
const busy = ref(false);

function destroy(): void {
    const c = deleting.value!;
    void withPin(() => {
        busy.value = true;
        router.delete(`/data-kelas/${c.id}`, {
            preserveScroll: true,
            onFinish: () => {
                busy.value = false;
                deleting.value = null;
            },
        });
    }, `Konfirmasi hapus kelas ${c.name}`);
}

/* ---------- copy from another period ---------- */
const copyOpen = ref(false);
const copy = ref({
    source: '' as string,
    promote: false,
    withStudents: true,
    selected: [] as number[],
});
const sourceClasses = ref<
    { id: number; name: string; grade: number; students: number }[]
>([]);
const plan = ref<PlanRow[] | null>(null);
const copyError = ref<string | null>(null);
const copyBusy = ref(false);

function openCopy(): void {
    const prev = props.copySources[0];
    copy.value = {
        source: prev ? String(prev.id) : '',
        promote: false,
        withStudents: true,
        selected: [],
    };
    plan.value = null;
    copyError.value = null;
    copyOpen.value = true;

    if (prev) {
        void loadSource();
    }
}

async function loadSource(): Promise<void> {
    plan.value = null;
    copy.value.selected = [];
    sourceClasses.value = [];

    if (!copy.value.source) {
        return;
    }

    // Reuse the preview endpoint with every class of the source to list them.
    try {
        const res = await fetchPlan(true);
        sourceClasses.value = res.map((r) => ({
            id: r.source_id,
            name: r.source_name,
            grade: r.grade,
            students: r.students,
        }));
        copy.value.selected = sourceClasses.value.map((c) => c.id);
        plan.value = res;
    } catch (e) {
        copyError.value =
            e instanceof ApiError
                ? (e.body.message ?? 'Gagal memuat kelas.')
                : 'Gagal memuat kelas.';
    }
}

async function fetchPlan(all = false): Promise<PlanRow[]> {
    const { plan: rows } = await http.post<{ plan: PlanRow[] }>(
        `/periode/${props.term.id}/salin-kelas/pratinjau`,
        {
            source_term_id: Number(copy.value.source),
            class_ids: all ? [0] : copy.value.selected,
            promote: copy.value.promote,
            with_students: copy.value.withStudents,
            all,
        },
    );

    return rows;
}

async function refreshPlan(): Promise<void> {
    copyError.value = null;

    if (!copy.value.selected.length) {
        plan.value = [];

        return;
    }

    try {
        plan.value = await fetchPlan();
    } catch (e) {
        copyError.value =
            e instanceof ApiError
                ? (e.body.message ?? 'Gagal membuat pratinjau.')
                : 'Gagal membuat pratinjau.';
    }
}

watch(
    () => [
        copy.value.promote,
        copy.value.withStudents,
        copy.value.selected.length,
    ],
    () => void refreshPlan(),
);

function toggleClass(id: number, on: boolean): void {
    copy.value.selected = on
        ? [...copy.value.selected, id]
        : copy.value.selected.filter((x) => x !== id);
}

const toCreate = computed(() => (plan.value ?? []).filter((r) => !r.skip));

function runCopy(): void {
    copyBusy.value = true;
    router.post(
        `/periode/${props.term.id}/salin-kelas`,
        {
            source_term_id: Number(copy.value.source),
            class_ids: copy.value.selected,
            promote: copy.value.promote,
            with_students: copy.value.withStudents,
        },
        {
            preserveScroll: true,
            onSuccess: () => (copyOpen.value = false),
            onFinish: () => (copyBusy.value = false),
        },
    );
}
</script>

<template>
    <Head :title="`Kelas ${term.label}`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Periode & Kelas', href: '/periode' },
            { title: term.label, href: `/periode/${term.id}` },
        ]"
        :title="term.label"
    >
        <Link
            href="/periode"
            class="mb-3 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" /> Semua periode
        </Link>
        <PageHeader
            :title="`Kelas ${term.label}`"
            tour="periode-detail"
            :description="
                term.is_active ? 'Periode aktif' : 'Periode tidak aktif'
            "
        >
            <Button
                v-if="copySources.length"
                variant="outline"
                data-tour="class-copy"
                @click="openCopy"
                ><Copy /> Salin dari periode lain</Button
            >
            <Button data-tour="class-add" @click="openCreate"
                ><Plus /> Tambah kelas</Button
            >
        </PageHeader>

        <Card v-if="!classes.length">
            <EmptyState
                :icon="School"
                title="Belum ada kelas di periode ini"
                description="Tambahkan kelas 3A–6B atau salin dari periode sebelumnya."
            >
                <Button data-tour="class-add" @click="openCreate"
                    ><Plus /> Tambah kelas</Button
                >
                <Button
                    v-if="copySources.length"
                    variant="outline"
                    data-tour="class-copy"
                    @click="openCopy"
                    ><Copy /> Salin dari periode lalu</Button
                >
            </EmptyState>
        </Card>

        <template v-else>
            <Card
                data-tour="class-table"
                class="hidden overflow-hidden p-0 md:block"
            >
                <Table>
                    <TableHeader>
                        <TableRow class="bg-muted/50">
                            <TableHead class="w-20">Kelas</TableHead>
                            <TableHead class="w-20">Jenjang</TableHead>
                            <TableHead>Wali Kelas</TableHead>
                            <TableHead class="w-24 text-right">Siswa</TableHead>
                            <TableHead class="w-24">Status</TableHead>
                            <TableHead class="w-14" />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="c in classes" :key="c.id">
                            <TableCell class="text-base font-bold">{{
                                c.name
                            }}</TableCell>
                            <TableCell>Kelas {{ c.grade }}</TableCell>
                            <TableCell>
                                {{ c.homeroom_teacher || '–' }}
                                <span
                                    v-if="c.homeroom_id_number"
                                    class="block text-xs text-muted-foreground"
                                    >NBM {{ c.homeroom_id_number }}</span
                                >
                            </TableCell>
                            <TableCell class="text-right tabular-nums">{{
                                c.students
                            }}</TableCell>
                            <TableCell
                                ><StatusBadge :status="c.status"
                            /></TableCell>
                            <TableCell>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            :aria-label="`Aksi kelas ${c.name}`"
                                            ><MoreVertical
                                        /></Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem as-child>
                                            <Link :href="`/kelas/${c.id}`"
                                                ><Users /> Buka kelas</Link
                                            >
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            :disabled="c.status === 'final'"
                                            @click="openEdit(c)"
                                            ><Pencil /> Ubah</DropdownMenuItem
                                        >
                                        <DropdownMenuItem
                                            :disabled="
                                                c.status === 'final' ||
                                                c.has_scores
                                            "
                                            class="text-destructive"
                                            @click="deleting = c"
                                        >
                                            <Trash2 /> Hapus
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </Card>

            <!-- Phone cards -->
            <ul data-tour="class-cards" class="grid gap-2 md:hidden">
                <li
                    v-for="c in classes"
                    :key="c.id"
                    class="rounded-xl border bg-card p-4"
                >
                    <div class="flex items-center gap-2">
                        <span class="text-lg font-bold">{{ c.name }}</span>
                        <StatusBadge :status="c.status" size="sm" />
                        <span class="ml-auto text-sm text-muted-foreground"
                            >{{ c.students }} siswa</span
                        >
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Wali kelas: {{ c.homeroom_teacher || '–' }}
                    </p>
                    <div class="mt-3 flex gap-2">
                        <Button size="sm" variant="outline" as-child
                            ><Link :href="`/kelas/${c.id}`">Buka</Link></Button
                        >
                        <Button
                            size="sm"
                            variant="outline"
                            :disabled="c.status === 'final'"
                            @click="openEdit(c)"
                            ><Pencil /> Ubah</Button
                        >
                        <Button
                            size="sm"
                            variant="ghost"
                            class="text-destructive"
                            :disabled="c.status === 'final' || c.has_scores"
                            @click="deleting = c"
                        >
                            <Trash2 />
                        </Button>
                    </div>
                </li>
            </ul>
        </template>

        <FormSheet
            v-model:open="formOpen"
            :title="editing ? `Ubah kelas ${editing.name}` : 'Tambah kelas'"
            :description="term.label"
        >
            <form id="class-form" class="grid gap-4" @submit.prevent="save">
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label for="grade"
                            >Jenjang
                            <span class="text-destructive">*</span></Label
                        >
                        <NativeSelect
                            id="grade"
                            v-model="form.grade"
                            :disabled="gradeLocked"
                            :invalid="!!form.errors.grade"
                        >
                            <option
                                v-for="g in [3, 4, 5, 6]"
                                :key="g"
                                :value="String(g)"
                            >
                                Kelas {{ g }}
                            </option>
                        </NativeSelect>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="name"
                            >Nama kelas
                            <span class="text-destructive">*</span></Label
                        >
                        <Input
                            id="name"
                            v-model="form.name"
                            maxlength="10"
                            class="uppercase"
                            :aria-invalid="!!form.errors.name"
                        />
                    </div>
                </div>
                <p
                    v-if="gradeLocked"
                    class="flex items-start gap-1.5 text-xs text-muted-foreground"
                >
                    <Lock class="mt-0.5 size-3.5 shrink-0" /> Jenjang tidak
                    dapat diubah karena kelas sudah memiliki nilai (ambang batas
                    tiap jenjang berbeda).
                </p>
                <InputError :message="form.errors.grade || form.errors.name" />

                <div class="grid gap-1.5">
                    <Label for="homeroom_teacher">Wali kelas</Label>
                    <Input
                        id="homeroom_teacher"
                        v-model="form.homeroom_teacher"
                        maxlength="100"
                    />
                    <InputError :message="form.errors.homeroom_teacher" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="homeroom_id_number"
                        >NBM wali kelas (opsional)</Label
                    >
                    <Input
                        id="homeroom_id_number"
                        v-model="form.homeroom_id_number"
                        maxlength="30"
                        class="tabular-nums"
                    />
                    <InputError :message="form.errors.homeroom_id_number" />
                </div>
            </form>
            <template #footer>
                <Button variant="outline" @click="formOpen = false"
                    >Batal</Button
                >
                <Button
                    type="submit"
                    form="class-form"
                    :disabled="form.processing"
                >
                    <Spinner v-if="form.processing" />
                    {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
                </Button>
            </template>
        </FormSheet>

        <ConfirmDialog
            v-model:open="deleteOpen"
            :title="`Hapus kelas ${deleting?.name}?`"
            :description="
                deleting?.students
                    ? `${deleting.students} siswa akan dikeluarkan dari kelas ini (data siswa tetap ada).`
                    : 'Kelas ini belum memiliki siswa.'
            "
            :confirm-label="`Hapus kelas ${deleting?.name ?? ''}`"
            destructive
            :processing="busy"
            @confirm="destroy"
        />

        <ResponsiveDialog
            v-model:open="copyOpen"
            title="Salin kelas dari periode lain"
            :description="`Tujuan: ${term.label}`"
        >
            <div class="grid max-h-[60vh] gap-4 overflow-y-auto pb-1">
                <div class="grid gap-1.5">
                    <Label for="source">Periode sumber</Label>
                    <NativeSelect
                        id="source"
                        v-model="copy.source"
                        @change="loadSource"
                    >
                        <option value="" disabled>Pilih periode…</option>
                        <option
                            v-for="s in copySources"
                            :key="s.id"
                            :value="String(s.id)"
                        >
                            {{ s.label }}
                        </option>
                    </NativeSelect>
                </div>
                <label class="flex items-center justify-between gap-3 text-sm">
                    <span>
                        <span class="block font-medium"
                            >Naikkan jenjang (+1) untuk tahun ajaran baru</span
                        >
                        <span class="block text-muted-foreground"
                            >3A → 4A. Kelas 6 tidak ikut.</span
                        >
                    </span>
                    <Switch v-model="copy.promote" />
                </label>
                <label class="flex items-center justify-between gap-3 text-sm">
                    <span class="font-medium">Ikutkan siswa (yang aktif)</span>
                    <Switch v-model="copy.withStudents" />
                </label>

                <div v-if="sourceClasses.length" class="grid gap-1.5">
                    <p class="text-sm font-medium">Kelas yang disalin</p>
                    <div class="flex flex-wrap gap-2">
                        <label
                            v-for="c in sourceClasses"
                            :key="c.id"
                            class="flex cursor-pointer items-center gap-2 rounded-md border px-2.5 py-1.5 text-sm"
                        >
                            <Checkbox
                                :model-value="copy.selected.includes(c.id)"
                                @update:model-value="
                                    (v) => toggleClass(c.id, !!v)
                                "
                            />
                            {{ c.name }}
                        </label>
                    </div>
                </div>
                <p
                    v-else-if="copy.source && !copyError"
                    class="text-sm text-muted-foreground"
                >
                    Periode ini tidak memiliki kelas.
                </p>

                <InputError :message="copyError" />

                <div v-if="plan && plan.length" class="rounded-lg border">
                    <p
                        class="border-b bg-muted/50 px-3 py-2 text-sm font-semibold"
                    >
                        Pratinjau hasil
                    </p>
                    <ul class="divide-y text-sm">
                        <li
                            v-for="r in plan"
                            :key="r.source_id"
                            class="flex items-center gap-2 px-3 py-2"
                            :class="r.skip && 'text-muted-foreground'"
                        >
                            <span class="w-12">{{ r.source_name }}</span> →
                            <span class="w-12 font-semibold">{{ r.name }}</span>
                            <span v-if="r.skip" class="text-xs">{{
                                r.skip
                            }}</span>
                            <span
                                v-else-if="copy.withStudents"
                                class="ml-auto text-xs"
                                >{{ r.students }} siswa</span
                            >
                        </li>
                    </ul>
                </div>
            </div>
            <template #footer>
                <Button variant="outline" @click="copyOpen = false"
                    >Batal</Button
                >
                <Button
                    :disabled="!toCreate.length || copyBusy"
                    @click="runCopy"
                >
                    <Spinner v-if="copyBusy" /> Salin
                    {{ toCreate.length }} kelas
                </Button>
            </template>
        </ResponsiveDialog>
    </AppLayout>
</template>
