<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    CircleHelp,
    Info,
    KeyRound,
    RotateCcw,
    ShieldAlert,
    Timer,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { withPin } from '@/composables/usePinConfirm';
import { resetTours } from '@/composables/useTour';
import SettingsLayout from '@/layouts/SettingsLayout.vue';
import { formatDateTime } from '@/lib/format';

const props = defineProps<{
    idleMinutes: number;
    pinChangedAt: string | null;
}>();

const pinForm = useForm({ current_pin: '', pin: '', pin_confirmation: '' });
const sessionForm = useForm({ idle_minutes: props.idleMinutes });
const confirmRegenerate = ref(false);
const regenerating = ref(false);

function digitsOnly(
    field: 'current_pin' | 'pin' | 'pin_confirmation',
    e: Event,
): void {
    pinForm[field] = (e.target as HTMLInputElement).value
        .replace(/\D/g, '')
        .slice(0, 4);
}

function changePin(): void {
    void withPin(
        () =>
            pinForm.put('/pengaturan/keamanan/pin', {
                preserveScroll: true,
                onSuccess: () => pinForm.reset(),
                onError: () => pinForm.reset('pin', 'pin_confirmation'),
            }),
        'Konfirmasi ganti PIN',
    );
}

function regenerate(): void {
    confirmRegenerate.value = false;
    void withPin(() => {
        regenerating.value = true;
        router.post(
            '/pengaturan/keamanan/kode-pemulihan',
            {},
            { onFinish: () => (regenerating.value = false) },
        );
    }, 'Konfirmasi buat kode baru');
}

function saveSession(): void {
    sessionForm.put('/pengaturan/keamanan/sesi', { preserveScroll: true });
}

const replaying = ref(false);

/** D7: forget every "seen" marker so page tours start again on this device. */
function replayTours(): void {
    replaying.value = true;
    resetTours();
    toast.success('Tutorial akan tampil lagi otomatis di perangkat ini.');
    replaying.value = false;
}
</script>

<template>
    <Head title="Pengaturan Keamanan" />
    <SettingsLayout title="Keamanan" tour="set-keamanan">
        <Alert class="mb-6 max-w-3xl border-gold/50 bg-tag">
            <ShieldAlert class="size-4 text-tag-foreground" />
            <AlertTitle class="text-tag-foreground"
                >PIN dipakai bersama</AlertTitle
            >
            <AlertDescription class="text-tag-foreground">
                Ganti PIN setiap semester dan setiap kali ada perubahan
                personel.
            </AlertDescription>
        </Alert>

        <div class="grid max-w-3xl gap-6">
            <Card data-tour="sec-pin">
                <CardHeader>
                    <CardTitle>Ganti PIN</CardTitle>
                    <CardDescription>
                        Terakhir diganti: {{ formatDateTime(pinChangedAt) }}.
                        Setelah diganti, semua perangkat lain harus memasukkan
                        PIN baru.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form
                        class="grid gap-4 sm:grid-cols-3"
                        @submit.prevent="changePin"
                    >
                        <div class="grid gap-1.5">
                            <Label for="current_pin">PIN lama</Label>
                            <Input
                                id="current_pin"
                                :model-value="pinForm.current_pin"
                                type="password"
                                inputmode="numeric"
                                autocomplete="current-password"
                                maxlength="4"
                                class="text-center tracking-[0.5em]"
                                :aria-invalid="!!pinForm.errors.current_pin"
                                @input="digitsOnly('current_pin', $event)"
                            />
                            <InputError :message="pinForm.errors.current_pin" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="new_pin">PIN baru</Label>
                            <Input
                                id="new_pin"
                                :model-value="pinForm.pin"
                                type="password"
                                inputmode="numeric"
                                autocomplete="new-password"
                                maxlength="4"
                                class="text-center tracking-[0.5em]"
                                :aria-invalid="!!pinForm.errors.pin"
                                @input="digitsOnly('pin', $event)"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="pin_confirmation"
                                >Ulangi PIN baru</Label
                            >
                            <Input
                                id="pin_confirmation"
                                :model-value="pinForm.pin_confirmation"
                                type="password"
                                inputmode="numeric"
                                autocomplete="new-password"
                                maxlength="4"
                                class="text-center tracking-[0.5em]"
                                @input="digitsOnly('pin_confirmation', $event)"
                            />
                        </div>
                        <InputError
                            :message="pinForm.errors.pin"
                            class="sm:col-span-3"
                        />
                        <div class="sm:col-span-3">
                            <Button
                                type="submit"
                                :disabled="
                                    pinForm.processing ||
                                    pinForm.current_pin.length < 4 ||
                                    pinForm.pin.length < 4 ||
                                    pinForm.pin_confirmation.length < 4
                                "
                            >
                                <Spinner v-if="pinForm.processing" />
                                {{
                                    pinForm.processing
                                        ? 'Menyimpan…'
                                        : 'Ganti PIN'
                                }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card data-tour="sec-recovery">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2"
                        ><KeyRound class="size-5 text-primary" /> Kode
                        pemulihan</CardTitle
                    >
                    <CardDescription
                        >Membuat kode baru membuat kode lama tidak
                        berlaku.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <Button
                        variant="outline"
                        :disabled="regenerating"
                        @click="confirmRegenerate = true"
                    >
                        <Spinner v-if="regenerating" /> Buat ulang kode
                        pemulihan
                    </Button>
                </CardContent>
            </Card>

            <Card data-tour="sec-session">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2"
                        ><Timer class="size-5 text-primary" /> Batas waktu
                        sesi</CardTitle
                    >
                    <CardDescription
                        >Sesi berakhir otomatis bila tidak ada aktivitas. Batas
                        absolut 12 jam.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <form
                        class="flex flex-wrap items-end gap-3"
                        @submit.prevent="saveSession"
                    >
                        <div class="grid gap-1.5">
                            <Label for="idle_minutes"
                                >Menit tanpa aktivitas (15–240)</Label
                            >
                            <Input
                                id="idle_minutes"
                                v-model.number="sessionForm.idle_minutes"
                                type="number"
                                min="15"
                                max="240"
                                class="w-32 tabular-nums"
                                :aria-invalid="
                                    !!sessionForm.errors.idle_minutes
                                "
                            />
                        </div>
                        <Button
                            type="submit"
                            :disabled="
                                sessionForm.processing || !sessionForm.isDirty
                            "
                        >
                            <Spinner v-if="sessionForm.processing" /> Simpan
                        </Button>
                        <InputError
                            :message="sessionForm.errors.idle_minutes"
                            class="w-full"
                        />
                    </form>
                </CardContent>
            </Card>

            <p class="flex items-start gap-2 text-sm text-muted-foreground">
                <Info class="mt-0.5 size-4 shrink-0" />
                Bila PIN dan kode pemulihan sama-sama hilang, pengembang dapat
                menjalankan perintah
                <code class="rounded bg-muted px-1">php artisan pin:reset</code>
                di server.
            </p>

            <Card data-tour="sec-tour">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2"
                        ><CircleHelp class="size-5 text-primary" />
                        Tutorial</CardTitle
                    >
                    <CardDescription>
                        Pengenalan tiap menu tampil otomatis sekali di perangkat
                        ini. Gunakan tombol ini bila ingin melihatnya lagi.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Button
                        variant="outline"
                        :disabled="replaying"
                        @click="replayTours"
                    >
                        <RotateCcw /> Tampilkan ulang semua tutorial
                    </Button>
                </CardContent>
            </Card>
        </div>

        <AlertDialog v-model:open="confirmRegenerate">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle
                        >Buat kode pemulihan baru?</AlertDialogTitle
                    >
                    <AlertDialogDescription>
                        Kode pemulihan lama langsung tidak berlaku. Simpan kode
                        baru yang akan ditampilkan.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Batal</AlertDialogCancel>
                    <AlertDialogAction @click="regenerate"
                        >Buat kode baru</AlertDialogAction
                    >
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </SettingsLayout>
</template>
