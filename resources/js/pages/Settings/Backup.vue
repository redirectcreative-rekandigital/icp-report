<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    DatabaseBackup,
    Download,
    Upload,
} from 'lucide-vue-next';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
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
import { requirePin, withPin } from '@/composables/usePinConfirm';
import SettingsLayout from '@/layouts/SettingsLayout.vue';
import { formatDateTime } from '@/lib/format';

/** Pengaturan → Cadangan (PRD-02 §6: both actions need the PIN within 5 minutes). */
const props = defineProps<{
    lastBackupAt: string | null;
    maxUploadMb: number;
}>();

const downloading = ref(false);

async function download(): Promise<void> {
    if (
        !(await requirePin(
            'Konfirmasi unduh cadangan',
            'Cadangan berisi seluruh data siswa dan nilai.',
        ))
    ) {
        return;
    }

    downloading.value = true;
    window.location.href = '/pengaturan/cadangan/unduh';
    setTimeout(() => (downloading.value = false), 4000);
}

const form = useForm<{ file: File | null; confirm: string }>({
    file: null,
    confirm: '',
});
const fileError = ref<string | null>(null);

function pick(e: Event): void {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    fileError.value =
        file && file.size > props.maxUploadMb * 1048576
            ? `File melebihi batas unggah server (${props.maxUploadMb} MB).`
            : null;
    form.file = fileError.value ? null : file;
}

function restore(): void {
    void withPin(
        () =>
            form.post('/pengaturan/cadangan/pulihkan', { forceFormData: true }),
        'Konfirmasi pulihkan data',
    );
}
</script>

<template>
    <Head title="Cadangan" />
    <SettingsLayout title="Cadangan" tour="set-cadangan">
        <div class="grid max-w-3xl gap-6">
            <Card data-tour="bk-download">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2"
                        ><DatabaseBackup class="size-5 text-primary" /> Unduh
                        cadangan</CardTitle
                    >
                    <CardDescription>
                        Satu file .zip berisi semua data (periode, kelas, siswa,
                        nilai, finalisasi, pengaturan, log) beserta logo,
                        snapshot rapor, dan dokumen. Simpan di tempat aman —
                        file ini berisi data pribadi siswa.
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex flex-wrap items-center gap-3">
                    <Button :disabled="downloading" @click="download"
                        ><Spinner v-if="downloading" /><Download v-else /> Unduh
                        cadangan (.zip)</Button
                    >
                    <span class="text-sm text-muted-foreground"
                        >Terakhir diunduh:
                        {{ formatDateTime(lastBackupAt) }}</span
                    >
                </CardContent>
            </Card>

            <Card data-tour="bk-restore" class="border-destructive/30">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2"
                        ><Upload class="size-5 text-destructive" /> Pulihkan
                        dari cadangan</CardTitle
                    >
                    <CardDescription
                        >Mengganti <strong>seluruh</strong> data saat ini dengan
                        isi file cadangan.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <div
                        class="mb-4 flex items-start gap-2 rounded-lg bg-status-below px-3 py-2 text-sm text-status-below-fg"
                    >
                        <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                        <span>
                            Data yang dibuat setelah cadangan akan hilang. PIN
                            sekolah kembali ke PIN saat cadangan dibuat, dan
                            semua perangkat (termasuk perangkat ini) harus
                            memasukkan PIN lagi.
                        </span>
                    </div>
                    <form class="grid gap-4" @submit.prevent="restore">
                        <div class="grid gap-1.5">
                            <Label for="backup-file"
                                >File cadangan (.zip, maks.
                                {{ maxUploadMb }} MB)</Label
                            >
                            <Input
                                id="backup-file"
                                type="file"
                                accept=".zip"
                                @change="pick"
                            />
                            <InputError
                                :message="fileError || form.errors.file"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="confirm"
                                >Ketik <strong>PULIHKAN</strong> untuk
                                mengonfirmasi</Label
                            >
                            <Input
                                id="confirm"
                                v-model="form.confirm"
                                autocomplete="off"
                                class="max-w-xs uppercase"
                                :aria-invalid="!!form.errors.confirm"
                            />
                            <InputError :message="form.errors.confirm" />
                        </div>
                        <Button
                            type="submit"
                            variant="destructive"
                            class="w-fit"
                            :disabled="
                                !form.file ||
                                form.confirm !== 'PULIHKAN' ||
                                form.processing
                            "
                        >
                            <Spinner v-if="form.processing" />
                            {{
                                form.processing
                                    ? 'Memulihkan…'
                                    : 'Pulihkan data'
                            }}
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    </SettingsLayout>
</template>
