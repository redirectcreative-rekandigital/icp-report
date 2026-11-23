<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, ImageUp } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import NativeSelect from '@/components/app/NativeSelect.vue';
import PinPad from '@/components/app/PinPad.vue';
import SetupSteps from '@/components/app/SetupSteps.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import GuestLayout from '@/layouts/GuestLayout.vue';
import { parseScore } from '@/lib/cefr';

/**
 * /setup — first-run wizard (PRD-02 §3, revisi 23-11-2026). Everything the report needs is
 * required before the dashboard opens. Step 4 (recovery code) is Pin/RecoveryCode.
 */
const props = defineProps<{
    defaults: {
        school_name: string;
        city: string;
        year: string;
        semester: 1 | 2;
    };
}>();

const form = useForm({
    school_name: props.defaults.school_name ?? '',
    city: props.defaults.city ?? 'Wonorejo',
    logo: null as File | null,
    principal_name: '',
    principal_id_label: 'NBM' as 'NBM' | 'NIP',
    principal_id_number: '',
    year: props.defaults.year,
    semester: String(props.defaults.semester),
    report_date: '',
    intermediate: '13',
    advanced: '20',
    pin: '',
    pin_confirmation: '',
});

const step = ref<1 | 2 | 3>(1);
const touched = ref({ 1: false, 2: false });

/* ---------- step 1: school ---------- */
const logoUrl = ref<string | null>(null);
const logoError = ref<string | null>(null);

function pickLogo(e: Event): void {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';

    if (!file) {
        return;
    }

    if (!['image/png', 'image/jpeg'].includes(file.type)) {
        logoError.value = 'Logo harus berformat PNG atau JPG.';

        return;
    }

    if (file.size > 1024 * 1024) {
        logoError.value = 'Ukuran logo maksimal 1 MB.';

        return;
    }

    logoError.value = null;
    form.logo = file;

    if (logoUrl.value) {
        URL.revokeObjectURL(logoUrl.value);
    }

    logoUrl.value = URL.createObjectURL(file);
}

onBeforeUnmount(() => logoUrl.value && URL.revokeObjectURL(logoUrl.value));

const step1Errors = computed<Record<string, string>>(() => {
    const e: Record<string, string> = {};

    if (!form.school_name.trim()) {
        e.school_name = 'Nama sekolah wajib diisi.';
    }

    if (!form.city.trim()) {
        e.city = 'Tempat wajib diisi (mis. Wonorejo).';
    }

    if (!form.logo) {
        e.logo = 'Unggah logo sekolah.';
    }

    if (!form.principal_name.trim()) {
        e.principal_name = 'Nama kepala sekolah wajib diisi.';
    }

    if (!form.principal_id_number.trim()) {
        e.principal_id_number = `${form.principal_id_label} kepala sekolah wajib diisi.`;
    }

    return e;
});

/* ---------- step 2: period & thresholds ---------- */
const step2Errors = computed<Record<string, string>>(() => {
    const e: Record<string, string> = {};
    const m = /^(\d{4})\/(\d{4})$/.exec(form.year.trim());

    if (!m) {
        e.year = 'Format tahun ajaran harus YYYY/YYYY, mis. 2026/2027.';
    } else if (Number(m[2]) !== Number(m[1]) + 1) {
        e.year =
            'Tahun kedua harus satu tahun setelah tahun pertama (mis. 2026/2027).';
    }

    if (!form.report_date) {
        e.report_date = 'Tanggal rapor wajib diisi.';
    }

    const i = parseScore(form.intermediate);
    const a = parseScore(form.advanced);

    if (!i.ok || i.value === null) {
        e.intermediate = 'Isi angka 5–25 (boleh ,5).';
    } else if (i.value <= 5) {
        e.intermediate =
            'Batas Intermediate harus lebih dari 5, supaya level Beginner tetap ada.';
    }

    if (!a.ok || a.value === null) {
        e.advanced = 'Isi angka 5–25 (boleh ,5).';
    } else if (i.ok && i.value !== null && a.value <= i.value) {
        e.advanced =
            'Batas Advanced harus lebih besar dari batas Intermediate.';
    }

    return e;
});

const STEP1 = [
    'school_name',
    'city',
    'logo',
    'principal_name',
    'principal_id_label',
    'principal_id_number',
];
const STEP2 = ['year', 'semester', 'report_date', 'intermediate', 'advanced'];

/** Client-side message first, then the server's. */
function err(field: string): string | undefined {
    const local =
        (touched.value[1] && step1Errors.value[field]) ||
        (touched.value[2] && step2Errors.value[field]);

    return (
        local ||
        (field === 'logo' ? logoError.value : null) ||
        (form.errors as Record<string, string>)[field] ||
        undefined
    );
}

function next(): void {
    if (step.value === 1) {
        touched.value[1] = true;

        if (!Object.keys(step1Errors.value).length) {
            step.value = 2;
        }
    } else if (step.value === 2) {
        touched.value[2] = true;

        if (!Object.keys(step2Errors.value).length) {
            step.value = 3;
        }
    }
}

function back(): void {
    if (step.value === 3) {
        form.reset('pin', 'pin_confirmation');
        pinStage.value = 'first';
        pinError.value = null;
    }

    step.value = step.value === 3 ? 2 : 1;
}

/* ---------- step 3: PIN ---------- */
const pinStage = ref<'first' | 'repeat'>('first');
const pinError = ref<string | null>(null);
const pad = ref<InstanceType<typeof PinPad> | null>(null);

const WEAK = ['1234', '4321', '1212', '2580'];

function isWeak(pin: string): boolean {
    return (
        new Set(pin).size === 1 ||
        WEAK.includes(pin) ||
        /^(19|20)\d{2}$/.test(pin)
    );
}

/** Reset first so PinPad re-triggers shake/clear even for an identical message. */
function fail(message: string | null): void {
    pinError.value = null;
    nextTick(() => (pinError.value = message));
}

function onPin(pin: string): void {
    pinError.value = null;

    if (pinStage.value === 'first') {
        if (isWeak(pin)) {
            fail('PIN terlalu mudah ditebak. Pilih kombinasi lain.');

            return;
        }

        form.pin = pin;
        pinStage.value = 'repeat';
        nextTick(() => pad.value?.clear());

        return;
    }

    if (pin !== form.pin) {
        fail('Kedua PIN tidak sama. Ulangi dari awal.');
        form.pin = '';
        pinStage.value = 'first';

        return;
    }

    form.pin_confirmation = pin;
    form.transform((d) => ({
        ...d,
        intermediate: d.intermediate.replace(',', '.'),
        advanced: d.advanced.replace(',', '.'),
    })).post('/setup', {
        forceFormData: true,
        onError: (errors) => {
            const fields = Object.keys(errors);

            if (fields.some((f) => STEP1.includes(f))) {
                step.value = 1;
            } else if (fields.some((f) => STEP2.includes(f))) {
                step.value = 2;
            }

            fail(errors.pin ?? null);
            form.reset('pin', 'pin_confirmation');
            pinStage.value = 'first';
        },
    });
}
</script>

<template>
    <Head title="Setup awal" />
    <GuestLayout wide>
        <SetupSteps :current="step" class="mb-6" />

        <!-- Step 1: school, Tempat, logo, principal -->
        <form v-if="step === 1" class="grid gap-4" @submit.prevent="next">
            <div>
                <h1 class="text-xl font-bold">Selamat datang</h1>
                <p class="text-sm text-muted-foreground">
                    Semua isian wajib — data ini tampil di rapor dan bisa diubah
                    nanti di Pengaturan.
                </p>
            </div>
            <div class="grid gap-1.5">
                <Label for="school_name"
                    >Nama sekolah <span class="text-destructive">*</span></Label
                >
                <Input
                    id="school_name"
                    v-model="form.school_name"
                    maxlength="100"
                    class="h-11"
                    autofocus
                    :aria-invalid="!!err('school_name')"
                />
                <InputError :message="err('school_name')" />
            </div>
            <div class="grid gap-1.5 sm:max-w-xs">
                <Label for="city"
                    >Tempat <span class="text-destructive">*</span></Label
                >
                <Input
                    id="city"
                    v-model="form.city"
                    maxlength="60"
                    class="h-11"
                    :aria-invalid="!!err('city')"
                />
                <p class="text-xs text-muted-foreground">
                    Ditulis sebelum tanggal rapor, mis. "{{
                        form.city || 'Wonorejo'
                    }}, 18 December 2026".
                </p>
                <InputError :message="err('city')" />
            </div>
            <div class="grid gap-1.5">
                <Label
                    >Logo sekolah <span class="text-destructive">*</span></Label
                >
                <div class="flex items-center gap-4">
                    <div
                        class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-muted"
                    >
                        <img
                            v-if="logoUrl"
                            :src="logoUrl"
                            alt="Logo sekolah"
                            class="size-full object-contain p-1"
                        />
                        <ImageUp v-else class="size-7 text-muted-foreground" />
                    </div>
                    <div class="grid gap-1">
                        <Button
                            type="button"
                            variant="outline"
                            as="label"
                            class="w-fit cursor-pointer"
                        >
                            <ImageUp />
                            {{ form.logo ? 'Ganti logo' : 'Pilih logo' }}
                            <input
                                type="file"
                                accept="image/png,image/jpeg"
                                class="sr-only"
                                @change="pickLogo"
                            />
                        </Button>
                        <p class="text-xs text-muted-foreground">
                            PNG/JPG, maks. 1 MB. Tampil di kiri kop rapor; logo
                            National Geographic sudah tersedia.
                        </p>
                    </div>
                </div>
                <InputError :message="err('logo')" />
            </div>
            <div class="grid gap-1.5">
                <Label for="principal_name"
                    >Nama kepala sekolah
                    <span class="text-destructive">*</span></Label
                >
                <Input
                    id="principal_name"
                    v-model="form.principal_name"
                    maxlength="100"
                    class="h-11"
                    placeholder="mis. Nama Lengkap, S.Pd., M.Pd."
                    :aria-invalid="!!err('principal_name')"
                />
                <InputError :message="err('principal_name')" />
            </div>
            <div class="grid gap-4 sm:grid-cols-[8rem_1fr]">
                <div class="grid gap-1.5">
                    <Label for="principal_id_label">Nomor induk</Label>
                    <NativeSelect
                        id="principal_id_label"
                        v-model="form.principal_id_label"
                        class="h-11"
                    >
                        <option value="NBM">NBM</option>
                        <option value="NIP">NIP</option>
                    </NativeSelect>
                </div>
                <div class="grid gap-1.5">
                    <Label for="principal_id_number"
                        >{{ form.principal_id_label }} kepala sekolah
                        <span class="text-destructive">*</span></Label
                    >
                    <Input
                        id="principal_id_number"
                        v-model="form.principal_id_number"
                        maxlength="30"
                        class="h-11 tabular-nums"
                        :aria-invalid="!!err('principal_id_number')"
                    />
                    <InputError :message="err('principal_id_number')" />
                </div>
            </div>
            <Button type="submit" class="mt-2 h-11">
                Lanjut <ArrowRight />
            </Button>
        </form>

        <!-- Step 2: first period + band thresholds -->
        <form v-else-if="step === 2" class="grid gap-4" @submit.prevent="next">
            <div>
                <h1 class="text-xl font-bold">Periode &amp; penilaian</h1>
                <p class="text-sm text-muted-foreground">
                    Periode pertama menjadi periode aktif. Batas level berlaku
                    untuk semua jenjang dan bisa diatur per jenjang nanti.
                </p>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="grid gap-1.5">
                    <Label for="year"
                        >Tahun ajaran
                        <span class="text-destructive">*</span></Label
                    >
                    <Input
                        id="year"
                        v-model="form.year"
                        maxlength="9"
                        class="h-11 tabular-nums"
                        placeholder="2026/2027"
                        :aria-invalid="!!err('year')"
                    />
                    <InputError :message="err('year')" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="semester"
                        >Semester <span class="text-destructive">*</span></Label
                    >
                    <NativeSelect
                        id="semester"
                        v-model="form.semester"
                        class="h-11"
                    >
                        <option value="1">Semester 1</option>
                        <option value="2">Semester 2</option>
                    </NativeSelect>
                    <InputError :message="err('semester')" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="report_date"
                        >Tanggal rapor
                        <span class="text-destructive">*</span></Label
                    >
                    <Input
                        id="report_date"
                        v-model="form.report_date"
                        type="date"
                        class="h-11"
                        :aria-invalid="!!err('report_date')"
                    />
                    <InputError :message="err('report_date')" />
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label for="intermediate"
                        >Batas Intermediate (per skill)
                        <span class="text-destructive">*</span></Label
                    >
                    <Input
                        id="intermediate"
                        v-model="form.intermediate"
                        inputmode="decimal"
                        class="h-11 tabular-nums"
                        :aria-invalid="!!err('intermediate')"
                    />
                    <InputError :message="err('intermediate')" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="advanced"
                        >Batas Advanced (per skill)
                        <span class="text-destructive">*</span></Label
                    >
                    <Input
                        id="advanced"
                        v-model="form.advanced"
                        inputmode="decimal"
                        class="h-11 tabular-nums"
                        :aria-invalid="!!err('advanced')"
                    />
                    <InputError :message="err('advanced')" />
                </div>
            </div>
            <p class="text-sm text-muted-foreground">
                Nilai tiap skill 5–25:
                <span class="font-semibold" style="color: var(--band-beginner)"
                    >Beginner</span
                >
                di bawah {{ form.intermediate }},
                <span class="font-semibold" style="color: #a17d00"
                    >Intermediate</span
                >
                mulai {{ form.intermediate }},
                <span class="font-semibold" style="color: var(--band-advanced)"
                    >Advanced</span
                >
                mulai {{ form.advanced }}. Level total memakai batas ×4.
            </p>
            <div class="mt-2 flex gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="h-11"
                    @click="back"
                >
                    <ArrowLeft /> Kembali
                </Button>
                <Button type="submit" class="h-11 flex-1">
                    Lanjut <ArrowRight />
                </Button>
            </div>
        </form>

        <!-- Step 3: PIN -->
        <div v-else class="flex flex-col items-center">
            <h1 class="text-xl font-bold">
                {{ pinStage === 'first' ? 'Buat PIN sekolah' : 'Ulangi PIN' }}
            </h1>
            <p class="mb-5 max-w-sm text-center text-sm text-muted-foreground">
                {{
                    pinStage === 'first'
                        ? 'PIN 4 digit ini dipakai bersama oleh semua guru. Hindari 1234, angka kembar, atau tahun.'
                        : 'Masukkan PIN yang sama sekali lagi.'
                }}
            </p>
            <PinPad
                ref="pad"
                :error="pinError"
                :processing="form.processing"
                @submit="onPin"
            />
            <Button
                variant="ghost"
                class="mt-4"
                :disabled="form.processing"
                @click="back"
            >
                <ArrowLeft /> Kembali
            </Button>
        </div>
    </GuestLayout>
</template>
