<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';
import PinPad from '@/components/app/PinPad.vue';
import SetupSteps from '@/components/app/SetupSteps.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import GuestLayout from '@/layouts/GuestLayout.vue';

/** /setup — 3-step wizard (PRD-02 §3). Step 3 (recovery code) is Pin/RecoveryCode. */
const props = defineProps<{
    defaults: {
        school_name: string;
        program_name: string;
        partner_name: string;
        city: string;
    };
}>();

const form = useForm({
    school_name: props.defaults.school_name ?? '',
    program_name: props.defaults.program_name ?? 'International Class Program',
    partner_name: props.defaults.partner_name ?? '',
    city: props.defaults.city ?? '',
    pin: '',
    pin_confirmation: '',
});

const step = ref<1 | 2>(1);
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

const step1Error = computed(() =>
    form.school_name.trim() ? null : 'Nama sekolah wajib diisi.',
);
const showStep1Error = ref(false);

function next(): void {
    showStep1Error.value = true;

    if (!step1Error.value) {
        step.value = 2;
    }
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
    form.post('/setup', {
        onError: (errors) => {
            if (errors.school_name) {
                step.value = 1;
            }

            fail(errors.pin ?? null);
            form.reset('pin', 'pin_confirmation');
            pinStage.value = 'first';
        },
    });
}

function backToStep1(): void {
    step.value = 1;
    form.reset('pin', 'pin_confirmation');
    pinStage.value = 'first';
    pinError.value = null;
}
</script>

<template>
    <Head title="Setup awal" />
    <GuestLayout wide>
        <SetupSteps :current="step" class="mb-6" />

        <form v-if="step === 1" class="grid gap-4" @submit.prevent="next">
            <div>
                <h1 class="text-xl font-bold">Selamat datang</h1>
                <p class="text-sm text-muted-foreground">
                    Lengkapi identitas sekolah. Data ini tampil di kop rapor dan
                    bisa diubah nanti di Pengaturan.
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
                    :aria-invalid="showStep1Error && !!step1Error"
                />
                <InputError
                    :message="
                        (showStep1Error && step1Error) ||
                        form.errors.school_name
                    "
                />
            </div>
            <div class="grid gap-1.5">
                <Label for="program_name">Nama program</Label>
                <Input
                    id="program_name"
                    v-model="form.program_name"
                    maxlength="100"
                    class="h-11"
                />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label for="partner_name">Nama mitra</Label>
                    <Input
                        id="partner_name"
                        v-model="form.partner_name"
                        maxlength="100"
                        class="h-11"
                        placeholder="mis. Partner English Academy"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="city">Kota</Label>
                    <Input
                        id="city"
                        v-model="form.city"
                        maxlength="60"
                        class="h-11"
                        placeholder="untuk tanda tangan rapor"
                    />
                </div>
            </div>
            <Button type="submit" class="mt-2 h-11">
                Lanjut <ArrowRight />
            </Button>
        </form>

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
                @click="backToStep1"
            >
                <ArrowLeft /> Kembali
            </Button>
        </div>
    </GuestLayout>
</template>
