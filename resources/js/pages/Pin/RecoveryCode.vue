<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useClipboard } from '@vueuse/core';
import { Check, Copy, Download, KeyRound } from 'lucide-vue-next';
import { computed } from 'vue';
import SetupSteps from '@/components/app/SetupSteps.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import GuestLayout from '@/layouts/GuestLayout.vue';

/** One-time recovery code display (setup step 3, after recovery, after regenerate). */
const props = defineProps<{
    code: string;
    context: 'setup' | 'recover' | 'regenerate';
}>();

const form = useForm({ saved: false });
const { copy, copied } = useClipboard({ copiedDuring: 2000 });

const heading = computed(
    () =>
        ({
            setup: 'Simpan kode pemulihan',
            recover: 'PIN berhasil direset',
            regenerate: 'Kode pemulihan baru',
        })[props.context],
);

function download(): void {
    const text = [
        'ICP CEFR Report — Kode Pemulihan PIN',
        '',
        props.code,
        '',
        'Gunakan kode ini di halaman "Lupa PIN" bila PIN sekolah terlupa.',
        'Kode hanya berlaku satu kali. Simpan di tempat aman, jangan dibagikan.',
        `Dibuat: ${new Date().toLocaleString('id-ID', { timeZone: 'Asia/Jakarta' })}`,
    ].join('\r\n');
    const url = URL.createObjectURL(new Blob([text], { type: 'text/plain' }));
    const a = document.createElement('a');
    a.href = url;
    a.download = 'kode-pemulihan-icp.txt';
    a.click();
    URL.revokeObjectURL(url);
}

function finish(): void {
    form.post('/kode-pemulihan');
}
</script>

<template>
    <Head :title="heading" />
    <GuestLayout wide>
        <SetupSteps v-if="context === 'setup'" :current="3" class="mb-6" />

        <div class="mb-4 flex items-start gap-3">
            <div
                class="flex size-10 shrink-0 items-center justify-center rounded-full bg-kpi-icon text-primary"
            >
                <KeyRound class="size-5" />
            </div>
            <div>
                <h1 class="text-xl font-bold">{{ heading }}</h1>
                <p class="text-sm text-muted-foreground">
                    Kode ini dipakai untuk membuat PIN baru jika PIN terlupa.
                    Kode hanya ditampilkan
                    <strong>sekali</strong> dan kode lama tidak berlaku lagi.
                </p>
            </div>
        </div>

        <div
            class="my-5 rounded-xl border-2 border-dashed border-gold bg-tag px-4 py-5 text-center font-mono text-2xl font-bold tracking-[0.2em] text-foreground select-all sm:text-3xl"
            aria-label="Kode pemulihan"
        >
            {{ code }}
        </div>

        <div class="mb-6 grid grid-cols-2 gap-3">
            <Button variant="outline" class="h-11" @click="copy(code)">
                <Check v-if="copied" class="text-status-meeting-fg" />
                <Copy v-else />
                {{ copied ? 'Tersalin' : 'Salin' }}
            </Button>
            <Button variant="outline" class="h-11" @click="download">
                <Download /> Unduh .txt
            </Button>
        </div>

        <form @submit.prevent="finish">
            <label
                class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 hover:bg-muted/50"
            >
                <Checkbox v-model="form.saved" class="mt-0.5" />
                <span class="text-sm font-medium"
                    >Saya sudah menyimpan kode pemulihan</span
                >
            </label>
            <InputError :message="form.errors.saved" class="mt-1" />

            <Button
                type="submit"
                class="mt-4 h-11 w-full"
                :disabled="!form.saved || form.processing"
            >
                <Spinner v-if="form.processing" />
                Selesai
            </Button>
        </form>
    </GuestLayout>
</template>
