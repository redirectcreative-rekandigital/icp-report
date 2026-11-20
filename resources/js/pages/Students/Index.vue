<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { onKeyStroke, watchDebounced } from '@vueuse/core';
import {
    ChevronLeft,
    ChevronRight,
    FileUp,
    MoreVertical,
    Pencil,
    Plus,
    Search,
    SlidersHorizontal,
    UserX,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import EmptyState from '@/components/app/EmptyState.vue';
import FormSheet from '@/components/app/FormSheet.vue';
import NativeSelect from '@/components/app/NativeSelect.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import ResponsiveDialog from '@/components/app/ResponsiveDialog.vue';
import StudentFormFields from '@/components/app/StudentFormFields.vue';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    nis: string;
    name: string;
    gender: 'L' | 'P' | null;
    is_active: boolean;
    notes: string | null;
    classroom: { id: number; name: string; final: boolean } | null;
    last_level: string | null;
};
type ClassOpt = { id: number; name: string; grade: number; final: boolean };

const props = defineProps<{
    students: Paginated<Row>;
    filters: {
        q: string;
        term: number | null;
        class: string;
        status: 'active' | 'inactive' | 'all';
        per_page: number;
    };
    terms: { id: number; label: string; is_active: boolean }[];
    classes: ClassOpt[];
    activeTermId: number | null;
}>();

/* ---------- filters ---------- */
const f = reactive({
    q: props.filters.q,
    term: props.filters.term ? String(props.filters.term) : '',
    class: props.filters.class,
    status: props.filters.status,
    per_page: String(props.filters.per_page),
});

function query(): Record<string, string> {
    const q: Record<string, string> = {};

    if (f.q) {
        q.q = f.q;
    }

    if (f.term && Number(f.term) !== props.activeTermId) {
        q.term = f.term;
    }

    if (f.class) {
        q.class = f.class;
    }

    if (f.status !== 'active') {
        q.status = f.status;
    }

    if (f.per_page !== '25') {
        q.per_page = f.per_page;
    }

    return q;
}

function apply(): void {
    router.get('/siswa', query(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['students', 'filters', 'classes'],
    });
}

watchDebounced(() => f.q, apply, { debounce: 300 });
watch(() => [f.class, f.status, f.per_page], apply);
watch(
    () => f.term,
    () => {
        f.class = '';
        apply();
    },
);

const hasFilters = computed(() => !!(f.q || f.class || f.status !== 'active'));

function resetFilters(): void {
    Object.assign(f, { q: '', class: '', status: 'active' });
}

const searchInput = ref<InstanceType<typeof Input> | null>(null);
onKeyStroke('/', (e) => {
    const t = e.target as HTMLElement;

    if (!['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName)) {
        e.preventDefault();
        (searchInput.value?.$el as HTMLInputElement | undefined)?.focus();
    }
});

const filtersOpen = ref(false);
const viewingActive = computed(
    () => !f.term || Number(f.term) === props.activeTermId,
);

/* ---------- selection & bulk ---------- */
const selected = ref<number[]>([]);
watch(
    () => props.students.data,
    () =>
        (selected.value = selected.value.filter((id) =>
            props.students.data.some((s) => s.id === id),
        )),
);

const allChecked = computed(
    () =>
        props.students.data.length > 0 &&
        props.students.data.every((s) => selected.value.includes(s.id)),
);

function toggleAll(on: boolean): void {
    selected.value = on ? props.students.data.map((s) => s.id) : [];
}

function toggle(id: number, on: boolean): void {
    selected.value = on
        ? [...new Set([...selected.value, id])]
        : selected.value.filter((x) => x !== id);
}

const moveOpen = ref(false);
const moveTo = ref('');
const bulkBusy = ref(false);

function bulk(action: 'move' | 'remove' | 'deactivate' | 'activate'): void {
    bulkBusy.value = true;
    router.post(
        '/siswa/massal',
        {
            action,
            ids: selected.value,
            classroom_id: action === 'move' ? Number(moveTo.value) : null,
            term_id:
                action === 'remove'
                    ? Number(f.term || props.activeTermId)
                    : null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                selected.value = [];
                moveOpen.value = false;
            },
            onFinish: () => (bulkBusy.value = false),
        },
    );
}

/* ---------- form ---------- */
const formOpen = ref(false);
const editing = ref<Row | null>(null);
const form = useForm({
    nis: '',
    name: '',
    gender: '' as '' | 'L' | 'P',
    classroom_id: '' as string,
    notes: '',
});

function openCreate(): void {
    editing.value = null;
    form.defaults({
        nis: '',
        name: '',
        gender: '',
        classroom_id:
            f.class && f.class !== 'none' && viewingActive.value ? f.class : '',
        notes: '',
    });
    form.reset();
    form.clearErrors();
    formOpen.value = true;
}

function openEdit(s: Row): void {
    editing.value = s;
    form.defaults({
        nis: s.nis,
        name: s.name,
        gender: s.gender ?? '',
        classroom_id:
            viewingActive.value && s.classroom ? String(s.classroom.id) : '',
        notes: s.notes ?? '',
    });
    form.reset();
    form.clearErrors();
    formOpen.value = true;
}

function save(): void {
    form.transform((d) => {
        const out: Record<string, unknown> = {
            ...d,
            gender: d.gender || null,
            classroom_id: d.classroom_id ? Number(d.classroom_id) : null,
        };

        // Editing while viewing another period: leave the active-period class untouched.
        if (editing.value && !viewingActive.value) {
            delete out.classroom_id;
        }

        return out;
    });

    const options = {
        preserveScroll: true,
        onSuccess: () => (formOpen.value = false),
    };

    if (editing.value) {
        form.put(`/siswa/${editing.value.id}`, options);
    } else {
        form.post('/siswa', options);
    }
}

const activeClasses = computed(() =>
    viewingActive.value ? props.classes : [],
);
</script>

<template>
    <Head title="Siswa" />
    <AppLayout :breadcrumbs="[{ title: 'Siswa', href: '/siswa' }]">
        <PageHeader
            title="Siswa"
            tour="siswa-index"
            :description="`${students.total.toLocaleString('id-ID')} siswa`"
        >
            <Button variant="outline" as-child class="hidden sm:inline-flex">
                <Link href="/impor"><FileUp /> Impor dari Excel</Link>
            </Button>
            <Button
                class="hidden sm:inline-flex"
                data-tour="student-add"
                @click="openCreate"
                ><Plus /> Tambah siswa</Button
            >
        </PageHeader>

        <!-- Filters -->
        <div
            class="sticky top-14 z-20 -mx-4 mb-4 bg-background/95 px-4 py-2 backdrop-blur md:static md:mx-0 md:bg-transparent md:p-0"
        >
            <div class="flex gap-2">
                <div data-tour="student-search" class="relative flex-1">
                    <Search
                        class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        ref="searchInput"
                        v-model="f.q"
                        type="search"
                        placeholder="Cari nama atau NIS…  ( / )"
                        class="h-10 bg-card pl-9"
                    />
                </div>
                <Button
                    variant="outline"
                    class="h-10 md:hidden"
                    data-tour="student-filter-btn"
                    @click="filtersOpen = true"
                    ><SlidersHorizontal /> Filter</Button
                >
                <div data-tour="student-filters" class="hidden gap-2 md:flex">
                    <NativeSelect
                        v-model="f.term"
                        class="w-56"
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
                        v-model="f.class"
                        class="w-36"
                        aria-label="Kelas"
                    >
                        <option value="">Semua kelas</option>
                        <option value="none">Tanpa kelas</option>
                        <option
                            v-for="c in classes"
                            :key="c.id"
                            :value="String(c.id)"
                        >
                            {{ c.name }}
                        </option>
                    </NativeSelect>
                    <NativeSelect
                        v-model="f.status"
                        class="w-32"
                        aria-label="Status"
                    >
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                        <option value="all">Semua</option>
                    </NativeSelect>
                </div>
            </div>
        </div>

        <!-- Bulk bar -->
        <div
            v-if="selected.length"
            class="sticky top-16 z-20 mb-3 flex flex-wrap items-center gap-2 rounded-lg border border-primary/30 bg-accent px-3 py-2 text-sm"
        >
            <span class="font-semibold">{{ selected.length }} dipilih</span>
            <Button
                size="sm"
                variant="outline"
                class="bg-card"
                :disabled="!activeClasses.length"
                @click="moveOpen = true"
                >Pindahkan ke kelas…</Button
            >
            <Button
                size="sm"
                variant="outline"
                class="bg-card"
                :disabled="bulkBusy"
                @click="bulk('remove')"
                >Keluarkan dari kelas</Button
            >
            <Button
                v-if="f.status !== 'inactive'"
                size="sm"
                variant="outline"
                class="bg-card"
                :disabled="bulkBusy"
                @click="bulk('deactivate')"
            >
                <UserX /> Nonaktifkan
            </Button>
            <Button
                v-else
                size="sm"
                variant="outline"
                class="bg-card"
                :disabled="bulkBusy"
                @click="bulk('activate')"
                >Aktifkan</Button
            >
            <Button
                size="sm"
                variant="ghost"
                class="ml-auto"
                @click="selected = []"
                ><X /> Batal</Button
            >
        </div>

        <Card v-if="!students.data.length">
            <EmptyState
                :icon="Users"
                :title="
                    f.q
                        ? `Tidak ada siswa bernama '${f.q}'`
                        : hasFilters
                          ? 'Tidak ada siswa yang cocok'
                          : 'Belum ada siswa'
                "
                :description="
                    hasFilters
                        ? undefined
                        : 'Tambahkan siswa satu per satu atau impor dari Excel.'
                "
            >
                <Button
                    v-if="hasFilters"
                    variant="outline"
                    @click="resetFilters"
                    >Hapus filter</Button
                >
                <template v-else>
                    <Button @click="openCreate"><Plus /> Tambah siswa</Button>
                    <Button variant="outline" as-child
                        ><Link href="/impor"
                            ><FileUp /> Impor dari Excel</Link
                        ></Button
                    >
                </template>
            </EmptyState>
        </Card>

        <template v-else>
            <Card
                data-tour="student-table"
                class="hidden overflow-hidden p-0 md:block"
            >
                <Table>
                    <TableHeader>
                        <TableRow class="bg-muted/50">
                            <TableHead class="w-10"
                                ><Checkbox
                                    :model-value="allChecked"
                                    aria-label="Pilih semua"
                                    @update:model-value="(v) => toggleAll(!!v)"
                            /></TableHead>
                            <TableHead class="w-32">NIS</TableHead>
                            <TableHead>Nama</TableHead>
                            <TableHead class="w-14">L/P</TableHead>
                            <TableHead class="w-24">Kelas</TableHead>
                            <TableHead class="w-28">Level terakhir</TableHead>
                            <TableHead class="w-14" />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="s in students.data"
                            :key="s.id"
                            :data-state="
                                selected.includes(s.id) ? 'selected' : undefined
                            "
                        >
                            <TableCell>
                                <Checkbox
                                    :model-value="selected.includes(s.id)"
                                    :aria-label="`Pilih ${s.name}`"
                                    @update:model-value="
                                        (v) => toggle(s.id, !!v)
                                    "
                                />
                            </TableCell>
                            <TableCell class="font-mono text-sm">{{
                                s.nis
                            }}</TableCell>
                            <TableCell class="max-w-72">
                                <Link
                                    :href="`/siswa/${s.id}`"
                                    class="block truncate font-medium hover:text-primary hover:underline"
                                    :title="s.name"
                                    >{{ s.name }}</Link
                                >
                                <span
                                    v-if="!s.is_active"
                                    class="text-xs text-muted-foreground"
                                    >Nonaktif</span
                                >
                            </TableCell>
                            <TableCell>{{ s.gender ?? '–' }}</TableCell>
                            <TableCell>
                                <Link
                                    v-if="s.classroom"
                                    :href="`/kelas/${s.classroom.id}`"
                                    class="font-semibold hover:underline"
                                    >{{ s.classroom.name }}</Link
                                >
                                <span v-else class="text-muted-foreground"
                                    >–</span
                                >
                            </TableCell>
                            <TableCell class="text-muted-foreground">{{
                                s.last_level ?? '–'
                            }}</TableCell>
                            <TableCell>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            data-tour="student-row-actions"
                                            :aria-label="`Aksi ${s.name}`"
                                            ><MoreVertical
                                        /></Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem @click="openEdit(s)"
                                            ><Pencil /> Ubah</DropdownMenuItem
                                        >
                                        <DropdownMenuItem as-child
                                            ><Link :href="`/siswa/${s.id}`"
                                                >Lihat profil</Link
                                            ></DropdownMenuItem
                                        >
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </Card>

            <!-- Phone cards (PRD-08 §5.5) -->
            <ul data-tour="student-cards" class="grid gap-2 md:hidden">
                <li
                    v-for="s in students.data"
                    :key="s.id"
                    class="flex items-center gap-3 rounded-xl border bg-card p-3"
                >
                    <Checkbox
                        :model-value="selected.includes(s.id)"
                        :aria-label="`Pilih ${s.name}`"
                        @update:model-value="(v) => toggle(s.id, !!v)"
                    />
                    <Link :href="`/siswa/${s.id}`" class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ s.name }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ s.nis }} • {{ s.classroom?.name ?? 'Tanpa kelas'
                            }}{{ s.is_active ? '' : ' • Nonaktif' }}
                        </p>
                    </Link>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-11"
                        :aria-label="`Ubah ${s.name}`"
                        @click="openEdit(s)"
                        ><Pencil
                    /></Button>
                </li>
            </ul>

            <nav
                class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm"
                aria-label="Paginasi"
            >
                <div class="flex items-center gap-2 text-muted-foreground">
                    {{ students.from }}–{{ students.to }} dari
                    {{ students.total.toLocaleString('id-ID') }}
                    <NativeSelect
                        v-model="f.per_page"
                        class="w-20"
                        aria-label="Baris per halaman"
                    >
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </NativeSelect>
                </div>
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="!students.prev_page_url"
                        as-child
                    >
                        <Link
                            :href="students.prev_page_url ?? '#'"
                            preserve-scroll
                            preserve-state
                            ><ChevronLeft /> Sebelumnya</Link
                        >
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="!students.next_page_url"
                        as-child
                    >
                        <Link
                            :href="students.next_page_url ?? '#'"
                            preserve-scroll
                            preserve-state
                            >Berikutnya <ChevronRight
                        /></Link>
                    </Button>
                </div>
            </nav>
        </template>

        <!-- FAB on phones -->
        <Button
            data-tour="student-fab"
            class="fixed right-4 bottom-[calc(5rem+env(safe-area-inset-bottom))] z-30 size-14 rounded-full shadow-lg sm:hidden"
            aria-label="Tambah siswa"
            @click="openCreate"
        >
            <Plus class="size-6" />
        </Button>

        <FormSheet
            v-model:open="formOpen"
            :title="editing ? 'Ubah data siswa' : 'Tambah siswa'"
        >
            <form id="student-form" @submit.prevent="save">
                <StudentFormFields
                    :form="form"
                    :classes="activeClasses"
                    :class-disabled="!!editing && !viewingActive"
                />
            </form>
            <template #footer>
                <Button variant="outline" @click="formOpen = false"
                    >Batal</Button
                >
                <Button
                    type="submit"
                    form="student-form"
                    :disabled="form.processing"
                >
                    <Spinner v-if="form.processing" />
                    {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
                </Button>
            </template>
        </FormSheet>

        <ResponsiveDialog
            v-model:open="moveOpen"
            :title="`Pindahkan ${selected.length} siswa`"
            description="Kelas tujuan di periode aktif."
        >
            <div class="grid gap-1.5">
                <Label for="move-to">Kelas tujuan</Label>
                <NativeSelect id="move-to" v-model="moveTo">
                    <option value="" disabled>Pilih kelas…</option>
                    <option
                        v-for="c in activeClasses"
                        :key="c.id"
                        :value="String(c.id)"
                        :disabled="c.final"
                    >
                        {{ c.name }}{{ c.final ? ' (final)' : '' }}
                    </option>
                </NativeSelect>
            </div>
            <template #footer>
                <Button variant="outline" @click="moveOpen = false"
                    >Batal</Button
                >
                <Button :disabled="!moveTo || bulkBusy" @click="bulk('move')"
                    ><Spinner v-if="bulkBusy" /> Pindahkan</Button
                >
            </template>
        </ResponsiveDialog>

        <ResponsiveDialog v-model:open="filtersOpen" title="Filter siswa">
            <div class="grid gap-4">
                <div class="grid gap-1.5">
                    <Label>Periode</Label>
                    <NativeSelect v-model="f.term">
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
                    <Label>Kelas</Label>
                    <NativeSelect v-model="f.class">
                        <option value="">Semua kelas</option>
                        <option value="none">Tanpa kelas</option>
                        <option
                            v-for="c in classes"
                            :key="c.id"
                            :value="String(c.id)"
                        >
                            {{ c.name }}
                        </option>
                    </NativeSelect>
                </div>
                <div class="grid gap-1.5">
                    <Label>Status</Label>
                    <NativeSelect v-model="f.status">
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                        <option value="all">Semua</option>
                    </NativeSelect>
                </div>
            </div>
            <template #footer>
                <Button class="w-full" @click="filtersOpen = false"
                    >Tampilkan</Button
                >
            </template>
        </ResponsiveDialog>
    </AppLayout>
</template>
