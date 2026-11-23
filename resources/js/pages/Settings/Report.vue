<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useElementSize, watchDebounced } from '@vueuse/core';
import { ArrowDown, ArrowUp, RotateCcw } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import NativeSelect from '@/components/app/NativeSelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { withPin } from '@/composables/usePinConfirm';
import SettingsLayout from '@/layouts/SettingsLayout.vue';
import { clone } from '@/lib/clone';
import { http } from '@/lib/http';
import { cn } from '@/lib/utils';

type Role = 'parent' | 'homeroom' | 'principal';
type Sections = Record<string, boolean>;
type Layout = {
    preset: 'lengkap' | 'ringkas' | 'minimal' | 'kustom';
    paper_default: 'f4' | 'a4';
    margin_mm: number;
    theme: {
        preset: 'navy' | 'hijau' | 'kustom';
        primary: string;
        accent: string;
    };
    header: {
        extra_line: string;
        title: string;
    };
    sections: Sections;
    signatures: { role: Role; label: string }[];
    id_label: 'NBM' | 'NIP' | 'none';
};

const props = defineProps<{
    layout: Layout;
    presets: Record<'lengkap' | 'ringkas' | 'minimal', Sections>;
    themes: Record<'navy' | 'hijau', { primary: string; accent: string }>;
    roles: Record<Role, string>;
    sampleStudents: { id: number; label: string }[];
}>();

const form = useForm<Layout>(clone(props.layout));

const SECTION_LABELS: Record<string, string> = {
    level_map:
        'Peta level CEFR hasil placement test National Geographic (kelas 6)',
    cambridge: 'Label setara Cambridge di peta level',
    radar: 'Grafik garis nilai skill (Skill Profile)',
    can_do: 'Blok "What the student can do & Next steps"',
    strongest_skill: 'Kalimat "Strongest skill this term" di komentar otomatis',
    parent_signature: 'Kolom tanda tangan orang tua',
};

/* ---------- presets ---------- */
function applyPreset(p: 'lengkap' | 'ringkas' | 'minimal'): void {
    form.preset = p;
    form.sections = { ...props.presets[p] };
}

watch(
    () => ({ ...form.sections }),
    (s) => {
        const match = (
            Object.keys(props.presets) as (keyof typeof props.presets)[]
        ).find((p) =>
            Object.entries(props.presets[p]).every(([k, v]) => s[k] === v),
        );
        form.preset = match ?? 'kustom';
    },
);

/* ---------- colours ---------- */
function setTheme(preset: 'navy' | 'hijau' | 'kustom'): void {
    form.theme =
        preset === 'kustom'
            ? { ...form.theme, preset }
            : { preset, ...props.themes[preset] };
}

function contrast(hex: string): number {
    const [r, g, b] = [1, 3, 5]
        .map((i) => parseInt(hex.slice(i, i + 2), 16) / 255)
        .map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));
    const lum = 0.2126 * r + 0.7152 * g + 0.0722 * b;

    return 1.05 / (lum + 0.05);
}

const ratio = computed(() =>
    /^#[0-9a-fA-F]{6}$/.test(form.theme.primary)
        ? contrast(form.theme.primary)
        : 0,
);
const contrastOk = computed(() => ratio.value >= 4.5);

/* ---------- signatures ---------- */
const allRoles = Object.keys(props.roles) as Role[];
const included = (r: Role) => form.signatures.some((s) => s.role === r);

function toggleRole(role: Role, on: boolean): void {
    form.signatures = on
        ? [...form.signatures, { role, label: props.roles[role] }]
        : form.signatures.filter((s) => s.role !== role);
}

function move(i: number, d: -1 | 1): void {
    const list = [...form.signatures];
    [list[i], list[i + d]] = [list[i + d], list[i]];
    form.signatures = list;
}

/* ---------- live preview ---------- */
const previewPaper = ref<'f4' | 'a4'>(form.paper_default);
const previewStudent = ref('');
const html = ref('');
const previewing = ref(false);
const previewError = ref<string | null>(null);
const box = ref<HTMLElement | null>(null);
const { width } = useElementSize(box);
const MM = 96 / 25.4;
const pageW = computed(() => (previewPaper.value === 'a4' ? 210 : 215) * MM);
const pageH = computed(() => (previewPaper.value === 'a4' ? 297 : 330) * MM);
const scale = computed(() => (width.value || pageW.value) / pageW.value);

async function refresh(): Promise<void> {
    previewing.value = true;
    previewError.value = null;

    try {
        const res = await http.post<{ html: string }>(
            '/pengaturan/rapor/pratinjau',
            {
                ...form.data(),
                paper: previewPaper.value,
                enrollment_id: previewStudent.value
                    ? Number(previewStudent.value)
                    : null,
            },
        );
        html.value = res.html;
    } catch {
        previewError.value =
            'Pratinjau belum bisa dibuat. Periksa isian yang ditandai.';
    } finally {
        previewing.value = false;
    }
}

watchDebounced(
    () => [
        JSON.stringify(form.data()),
        previewPaper.value,
        previewStudent.value,
    ],
    refresh,
    { debounce: 500 },
);
onMounted(refresh);

/* ---------- save ---------- */
const err = (k: string) => (form.errors as Record<string, string>)[k];
const canSave = computed(
    () =>
        form.isDirty &&
        form.signatures.length >= 2 &&
        (form.theme.preset !== 'kustom' || contrastOk.value),
);

function save(): void {
    void withPin(
        () =>
            form.put('/pengaturan/rapor', {
                preserveScroll: true,
                onSuccess: () => form.defaults(),
            }),
        'Konfirmasi simpan layout rapor',
    );
}

const resetOpen = ref(false);

function reset(): void {
    router.post(
        '/pengaturan/rapor/bawaan',
        {},
        { onSuccess: () => router.reload() },
    );
    resetOpen.value = false;
}
</script>

<template>
    <Head title="Pengaturan Rapor" />
    <SettingsLayout title="Rapor" tour="set-rapor">
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            <form class="grid h-fit gap-6" @submit.prevent="save">
                <Card data-tour="rl-preset">
                    <CardHeader>
                        <CardTitle>Preset tata letak</CardTitle>
                        <CardDescription
                            >Memilih preset mengatur sakelar bagian; sakelar
                            tetap bisa diubah (menjadi
                            "Kustom").</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="grid gap-2 sm:grid-cols-3">
                        <button
                            v-for="(desc, p) in {
                                lengkap: 'Semua bagian seperti rapor Excel',
                                ringkas: 'Tanpa can-do & next steps',
                                minimal:
                                    'Kop, info, skor, komentar, tanda tangan',
                            }"
                            :key="p"
                            type="button"
                            :class="
                                cn(
                                    'rounded-lg border p-3 text-left transition-colors',
                                    form.preset === p
                                        ? 'border-primary bg-accent'
                                        : 'hover:bg-muted',
                                )
                            "
                            @click="applyPreset(p)"
                        >
                            <p class="font-semibold capitalize">{{ p }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ desc }}
                            </p>
                        </button>
                        <p
                            v-if="form.preset === 'kustom'"
                            class="text-sm text-muted-foreground sm:col-span-3"
                        >
                            Preset: <strong>Kustom</strong>
                        </p>
                    </CardContent>
                </Card>

                <Card data-tour="rl-sections">
                    <CardHeader
                        ><CardTitle>Sakelar bagian</CardTitle></CardHeader
                    >
                    <CardContent class="grid gap-3">
                        <label
                            v-for="(label, key) in SECTION_LABELS"
                            :key="key"
                            class="flex cursor-pointer items-center justify-between gap-3 text-sm"
                        >
                            {{ label }}
                            <Switch v-model="form.sections[key]" />
                        </label>
                    </CardContent>
                </Card>

                <Card data-tour="rl-header">
                    <CardHeader>
                        <CardTitle>Kop & identitas</CardTitle>
                        <CardDescription
                            >Nama dan logo sekolah diatur di
                            <Link href="/pengaturan/sekolah" class="underline"
                                >Pengaturan → Sekolah</Link
                            >. Logo National Geographic Learning dan baris "In
                            Collaboration with" tampil
                            otomatis.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="grid gap-4">
                        <div class="grid gap-1.5">
                            <Label for="extra"
                                >Baris kop tambahan (opsional)</Label
                            >
                            <Input
                                id="extra"
                                v-model="form.header.extra_line"
                                maxlength="80"
                                placeholder="mis. Majelis Dikdasmen PCM …"
                            />
                            <InputError :message="err('header.extra_line')" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="title">Judul rapor</Label>
                            <Input
                                id="title"
                                v-model="form.header.title"
                                maxlength="60"
                            />
                            <InputError :message="err('header.title')" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Warna kop</CardTitle>
                        <CardDescription
                            >Warna level (Beginner/Intermediate/Advanced) dan
                            peta CEFR tidak ikut berubah.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="grid gap-3">
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="t in [
                                    'navy',
                                    'hijau',
                                    'kustom',
                                ] as const"
                                :key="t"
                                type="button"
                                :class="
                                    cn(
                                        'flex items-center gap-2 rounded-lg border px-3 py-2 text-sm',
                                        form.theme.preset === t
                                            ? 'border-primary bg-accent font-semibold'
                                            : 'hover:bg-muted',
                                    )
                                "
                                @click="setTheme(t)"
                            >
                                <span v-if="t !== 'kustom'" class="flex">
                                    <span
                                        class="size-4 rounded-l"
                                        :style="{
                                            background: themes[t].primary,
                                        }"
                                    />
                                    <span
                                        class="size-4 rounded-r"
                                        :style="{
                                            background: themes[t].accent,
                                        }"
                                    />
                                </span>
                                {{
                                    t === 'navy'
                                        ? 'Navy'
                                        : t === 'hijau'
                                          ? 'Hijau Muhammadiyah'
                                          : 'Kustom'
                                }}
                            </button>
                        </div>
                        <div
                            v-if="form.theme.preset === 'kustom'"
                            class="grid gap-3 sm:grid-cols-2"
                        >
                            <div class="grid gap-1.5">
                                <Label>Warna utama</Label>
                                <div class="flex gap-2">
                                    <input
                                        v-model="form.theme.primary"
                                        type="color"
                                        class="h-9 w-12 cursor-pointer rounded border"
                                        aria-label="Pilih warna utama"
                                    />
                                    <Input
                                        v-model="form.theme.primary"
                                        maxlength="7"
                                        class="font-mono uppercase"
                                    />
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Warna aksen</Label>
                                <div class="flex gap-2">
                                    <input
                                        v-model="form.theme.accent"
                                        type="color"
                                        class="h-9 w-12 cursor-pointer rounded border"
                                        aria-label="Pilih warna aksen"
                                    />
                                    <Input
                                        v-model="form.theme.accent"
                                        maxlength="7"
                                        class="font-mono uppercase"
                                    />
                                </div>
                            </div>
                            <p
                                :class="
                                    cn(
                                        'text-sm sm:col-span-2',
                                        contrastOk
                                            ? 'text-status-meeting-fg'
                                            : 'font-medium text-destructive',
                                    )
                                "
                            >
                                Kontras teks putih:
                                {{ ratio.toFixed(1).replace('.', ',') }} : 1
                                {{
                                    contrastOk
                                        ? '✓'
                                        : '— kurang dari 4,5 : 1, pilih warna utama yang lebih gelap.'
                                }}
                            </p>
                            <InputError :message="err('theme.primary')" />
                        </div>
                    </CardContent>
                </Card>

                <Card data-tour="rl-signatures">
                    <CardHeader>
                        <CardTitle>Tanda tangan</CardTitle>
                        <CardDescription
                            >Pilih dan urutkan 2–4 penanda tangan. Label jabatan
                            dalam bahasa Inggris.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="grid gap-3">
                        <div class="flex flex-wrap gap-3">
                            <label
                                v-for="r in allRoles"
                                :key="r"
                                class="flex items-center gap-2 text-sm"
                            >
                                <Checkbox
                                    :model-value="included(r)"
                                    :disabled="
                                        included(r) &&
                                        form.signatures.length <= 2
                                    "
                                    @update:model-value="
                                        (v) => toggleRole(r, !!v)
                                    "
                                />
                                {{ roles[r] }}
                            </label>
                        </div>
                        <ol class="grid gap-2">
                            <li
                                v-for="(s, i) in form.signatures"
                                :key="s.role"
                                class="flex items-center gap-2"
                            >
                                <span
                                    class="w-5 text-sm text-muted-foreground tabular-nums"
                                    >{{ i + 1 }}.</span
                                >
                                <Input
                                    v-model="s.label"
                                    maxlength="40"
                                    class="flex-1"
                                    :aria-label="`Label ${roles[s.role]}`"
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :disabled="i === 0"
                                    aria-label="Naikkan"
                                    @click="move(i, -1)"
                                    ><ArrowUp
                                /></Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :disabled="i === form.signatures.length - 1"
                                    aria-label="Turunkan"
                                    @click="move(i, 1)"
                                    ><ArrowDown
                                /></Button>
                            </li>
                        </ol>
                        <InputError :message="err('signatures')" />
                        <div class="grid gap-1.5 sm:max-w-xs">
                            <Label for="id_label"
                                >Nomor induk di bawah nama</Label
                            >
                            <NativeSelect id="id_label" v-model="form.id_label">
                                <option value="NBM">NBM</option>
                                <option value="NIP">NIP</option>
                                <option value="none">Tidak ditampilkan</option>
                            </NativeSelect>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Kertas</CardTitle></CardHeader>
                    <CardContent class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="paper">Kertas bawaan</Label>
                            <NativeSelect
                                id="paper"
                                v-model="form.paper_default"
                            >
                                <option value="a4">A4 (210 × 297 mm)</option>
                                <option value="f4">
                                    F4 / Folio (215 × 330 mm)
                                </option>
                            </NativeSelect>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="margin"
                                >Margin: {{ form.margin_mm }} mm</Label
                            >
                            <input
                                id="margin"
                                v-model.number="form.margin_mm"
                                type="range"
                                min="8"
                                max="20"
                                class="h-9 accent-[var(--primary)]"
                            />
                        </div>
                    </CardContent>
                </Card>

                <div
                    class="sticky bottom-20 z-10 flex justify-end gap-2 md:bottom-4"
                >
                    <Button
                        type="button"
                        variant="outline"
                        class="bg-card"
                        @click="resetOpen = true"
                        ><RotateCcw /> Kembalikan ke bawaan</Button
                    >
                    <Button
                        type="submit"
                        data-tour="rl-save"
                        class="shadow-lg"
                        :disabled="!canSave || form.processing"
                    >
                        <Spinner v-if="form.processing" /> Simpan
                    </Button>
                </div>
            </form>

            <!-- Live preview -->
            <div data-tour="rl-preview" class="xl:sticky xl:top-20 xl:h-fit">
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <p class="font-semibold">Pratinjau</p>
                    <Spinner v-if="previewing" class="size-4" />
                    <div class="ml-auto flex gap-2">
                        <NativeSelect
                            v-model="previewStudent"
                            class="w-52"
                            aria-label="Data pratinjau"
                        >
                            <option value="">Data contoh (Kevin Wijaya)</option>
                            <option
                                v-for="s in sampleStudents"
                                :key="s.id"
                                :value="String(s.id)"
                            >
                                {{ s.label }}
                            </option>
                        </NativeSelect>
                        <NativeSelect
                            v-model="previewPaper"
                            class="w-20"
                            aria-label="Kertas pratinjau"
                        >
                            <option value="a4">A4</option>
                            <option value="f4">F4</option>
                        </NativeSelect>
                    </div>
                </div>
                <p v-if="previewError" class="mb-2 text-sm text-destructive">
                    {{ previewError }}
                </p>
                <div ref="box" class="w-full">
                    <div
                        class="relative overflow-hidden rounded-sm bg-white shadow-xl ring-1 ring-black/5"
                        :style="{ height: `${pageH * scale}px` }"
                    >
                        <iframe
                            v-if="html"
                            :srcdoc="html"
                            title="Pratinjau rapor"
                            class="absolute top-0 left-0 origin-top-left border-0"
                            :style="{
                                width: `${pageW}px`,
                                height: `${pageH}px`,
                                transform: `scale(${scale})`,
                            }"
                        />
                    </div>
                </div>
            </div>
        </div>

        <ConfirmDialog
            v-model:open="resetOpen"
            title="Kembalikan layout rapor ke bawaan?"
            description="Preset Lengkap, warna Navy, kertas F4, empat penanda tangan dengan NBM."
            confirm-label="Kembalikan ke bawaan"
            destructive
            @confirm="reset"
        />
    </SettingsLayout>
</template>
