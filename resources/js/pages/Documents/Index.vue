<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Download,
    FileText,
    MoreVertical,
    Pencil,
    Plus,
    Trash2,
    Upload,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import EmptyState from '@/components/app/EmptyState.vue';
import FormSheet from '@/components/app/FormSheet.vue';
import NativeSelect from '@/components/app/NativeSelect.vue';
import PageHeader from '@/components/app/PageHeader.vue';
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
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';

/** /dokumen — test instruments & scoring guides (PRD-03 §7). */
type Doc = {
    id: number;
    title: string;
    grade: number | null;
    category: string;
    original_name: string;
    size: number;
    extension: string;
    updated_at: string;
};

const props = defineProps<{
    documents: Doc[];
    categories: Record<string, string>;
    filters: { grade: number | null };
}>();

const grade = computed({
    get: () => (props.filters.grade ? String(props.filters.grade) : ''),
    set: (v) =>
        router.get('/dokumen', v ? { grade: v } : {}, {
            preserveScroll: true,
            preserveState: true,
        }),
});

const groups = computed(() =>
    Object.entries(props.categories).map(([key, label]) => ({
        key,
        label,
        items: props.documents.filter((d) => d.category === key),
    })),
);

const size = (b: number) =>
    b > 1048576
        ? `${(b / 1048576).toLocaleString('id-ID', { maximumFractionDigits: 1 })} MB`
        : `${Math.ceil(b / 1024)} KB`;

/* ---------- form ---------- */
const formOpen = ref(false);
const editing = ref<Doc | null>(null);
const form = useForm<{
    title: string;
    grade: string;
    category: string;
    file: File | null;
}>({ title: '', grade: '', category: 'tes', file: null });
const fileError = ref<string | null>(null);

function openCreate(category = 'tes'): void {
    editing.value = null;
    form.defaults({
        title: '',
        grade: props.filters.grade ? String(props.filters.grade) : '',
        category,
        file: null,
    });
    form.reset();
    form.clearErrors();
    fileError.value = null;
    formOpen.value = true;
}

function openEdit(d: Doc): void {
    editing.value = d;
    form.defaults({
        title: d.title,
        grade: d.grade ? String(d.grade) : '',
        category: d.category,
        file: null,
    });
    form.reset();
    form.clearErrors();
    fileError.value = null;
    formOpen.value = true;
}

function pick(e: Event): void {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    fileError.value = null;

    if (file && file.size > 10 * 1024 * 1024) {
        fileError.value = `Ukuran file ${size(file.size)} melebihi batas 10 MB.`;
        form.file = null;

        return;
    }

    form.file = file;

    if (file && !form.title) {
        form.title = file.name.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ');
    }
}

function save(): void {
    form.transform((d) => ({ ...d, grade: d.grade || null }));
    const url = editing.value ? `/dokumen/${editing.value.id}` : '/dokumen';
    form.post(url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => (formOpen.value = false),
    });
}

const deleting = ref<Doc | null>(null);
const deleteOpen = computed({
    get: () => !!deleting.value,
    set: (v) => !v && (deleting.value = null),
});

function destroy(): void {
    router.delete(`/dokumen/${deleting.value!.id}`, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <Head title="Dokumen" />
    <AppLayout :breadcrumbs="[{ title: 'Dokumen', href: '/dokumen' }]">
        <PageHeader
            title="Dokumen"
            tour="dokumen"
            description="Instrumen tes dan pedoman penskoran untuk diunduh & dicetak."
        >
            <NativeSelect
                v-model="grade"
                class="w-40"
                aria-label="Filter jenjang"
                data-tour="doc-grade"
            >
                <option value="">Semua jenjang</option>
                <option v-for="g in [3, 4, 5, 6]" :key="g" :value="String(g)">
                    Kelas {{ g }}
                </option>
            </NativeSelect>
            <Button data-tour="doc-upload" @click="openCreate()"
                ><Upload /> Unggah dokumen</Button
            >
        </PageHeader>

        <Card v-if="!documents.length">
            <EmptyState
                :icon="FileText"
                title="Belum ada dokumen"
                description="Unggah file tes (PDF/Word) dan pedoman penskoran agar guru bisa mengunduhnya."
            >
                <Button @click="openCreate()"><Upload /> Unggah dokumen</Button>
            </EmptyState>
        </Card>

        <div v-else class="grid gap-6 lg:grid-cols-3">
            <Card
                v-for="g in groups"
                :key="g.key"
                data-tour="doc-category"
                class="h-fit"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0"
                >
                    <CardTitle class="text-base">{{ g.label }}</CardTitle>
                    <Button size="sm" variant="ghost" @click="openCreate(g.key)"
                        ><Plus /> Tambah</Button
                    >
                </CardHeader>
                <CardContent>
                    <p
                        v-if="!g.items.length"
                        class="text-sm text-muted-foreground"
                    >
                        Belum ada.
                    </p>
                    <ul v-else class="divide-y">
                        <li
                            v-for="d in g.items"
                            :key="d.id"
                            data-tour="doc-item"
                            class="flex items-center gap-3 py-2.5"
                        >
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-kpi-icon text-[10px] font-bold text-primary uppercase"
                                >{{ d.extension }}</span
                            >
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate font-medium"
                                    :title="d.title"
                                >
                                    {{ d.title }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ d.grade ? `Kelas ${d.grade} • ` : ''
                                    }}{{ size(d.size) }} •
                                    {{ formatDate(d.updated_at) }}
                                </p>
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                as="a"
                                :href="`/dokumen/${d.id}/unduh`"
                                :aria-label="`Unduh ${d.title}`"
                                ><Download
                            /></Button>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        :aria-label="`Aksi ${d.title}`"
                                        ><MoreVertical
                                    /></Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem @click="openEdit(d)"
                                        ><Pencil /> Ubah / ganti
                                        file</DropdownMenuItem
                                    >
                                    <DropdownMenuItem
                                        class="text-destructive"
                                        @click="deleting = d"
                                        ><Trash2 /> Hapus</DropdownMenuItem
                                    >
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>

        <FormSheet
            v-model:open="formOpen"
            :title="editing ? 'Ubah dokumen' : 'Unggah dokumen'"
        >
            <form id="doc-form" class="grid gap-4" @submit.prevent="save">
                <div class="grid gap-1.5">
                    <Label for="doc-file"
                        >File
                        {{
                            editing ? '(kosongkan bila tidak diganti)' : ''
                        }}</Label
                    >
                    <Input
                        id="doc-file"
                        type="file"
                        accept=".pdf,.doc,.docx,.xls,.xlsx"
                        @change="pick"
                    />
                    <p class="text-xs text-muted-foreground">
                        PDF, Word, atau Excel • maks. 10 MB{{
                            editing
                                ? ` • sekarang: ${editing.original_name}`
                                : ''
                        }}
                    </p>
                    <InputError :message="fileError || form.errors.file" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="doc-title">Judul</Label>
                    <Input
                        id="doc-title"
                        v-model="form.title"
                        maxlength="150"
                        :aria-invalid="!!form.errors.title"
                    />
                    <InputError :message="form.errors.title" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label for="doc-cat">Kategori</Label>
                        <NativeSelect id="doc-cat" v-model="form.category">
                            <option
                                v-for="(label, key) in categories"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </option>
                        </NativeSelect>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="doc-grade">Jenjang (opsional)</Label>
                        <NativeSelect id="doc-grade" v-model="form.grade">
                            <option value="">Semua</option>
                            <option
                                v-for="g in [3, 4, 5, 6]"
                                :key="g"
                                :value="String(g)"
                            >
                                Kelas {{ g }}
                            </option>
                        </NativeSelect>
                    </div>
                </div>
                <div
                    v-if="form.progress"
                    class="h-2 overflow-hidden rounded-full bg-muted"
                >
                    <div
                        class="h-full bg-primary transition-[width]"
                        :style="{ width: `${form.progress.percentage}%` }"
                    />
                </div>
            </form>
            <template #footer>
                <Button variant="outline" @click="formOpen = false"
                    >Batal</Button
                >
                <Button
                    type="submit"
                    form="doc-form"
                    :disabled="form.processing || (!editing && !form.file)"
                >
                    <Spinner v-if="form.processing" />
                    {{ form.processing ? 'Mengunggah…' : 'Simpan' }}
                </Button>
            </template>
        </FormSheet>

        <ConfirmDialog
            v-model:open="deleteOpen"
            :title="`Hapus dokumen “${deleting?.title}”?`"
            description="File akan dihapus permanen."
            :confirm-label="`Hapus ${deleting?.title ?? ''}`"
            destructive
            @confirm="destroy"
        />
    </AppLayout>
</template>
