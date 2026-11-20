<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { useEventListener } from '@vueuse/core';
import { ImageUp, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
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
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import SettingsLayout from '@/layouts/SettingsLayout.vue';

type Values = {
    school_name: string;
    school_address: string;
    program_name: string;
    partner_name: string;
    city: string;
    principal_name: string;
    principal_id_label: 'NBM' | 'NIP' | 'none';
    principal_id_number: string;
    show_documents_menu: boolean;
};

const props = defineProps<{
    values: Values;
    logos: Record<'school' | 'second' | 'partner', string | null>;
}>();

const form = useForm<Values>({
    ...props.values,
    school_address: props.values.school_address ?? '',
    partner_name: props.values.partner_name ?? '',
    city: props.values.city ?? '',
    principal_name: props.values.principal_name ?? '',
    principal_id_number: props.values.principal_id_number ?? '',
    show_documents_menu: !!props.values.show_documents_menu,
});

function save(): void {
    form.put('/pengaturan/sekolah', {
        preserveScroll: true,
        onError: () => {
            document
                .querySelector<HTMLElement>('[aria-invalid="true"]')
                ?.focus();
        },
    });
}

// Alt + S saves the form (PRD-07 §12).
useEventListener(window, 'keydown', (e: KeyboardEvent) => {
    if (e.altKey && e.key.toLowerCase() === 's') {
        e.preventDefault();
        save();
    }
});

const logoSlots = [
    {
        slot: 'school',
        title: 'Logo sekolah',
        hint: 'Tampil di kiri kop rapor dan di layar PIN.',
    },
    {
        slot: 'second',
        title: 'Logo Muhammadiyah',
        hint: 'Opsional. Tampil di kanan kop bila diaktifkan di Pengaturan → Rapor.',
    },
    {
        slot: 'partner',
        title: 'Logo mitra ICP',
        hint: 'Opsional. Tampil di baris program.',
    },
] as const;

const uploading = ref<string | null>(null);
const logoErrors = ref<Record<string, string>>({});

function pickLogo(slot: string, e: Event): void {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';

    if (!file) {
        return;
    }

    if (!['image/png', 'image/jpeg'].includes(file.type)) {
        logoErrors.value[slot] = 'Logo harus berformat PNG atau JPG.';

        return;
    }

    if (file.size > 1024 * 1024) {
        logoErrors.value[slot] =
            `Ukuran file ${(file.size / 1024 / 1024).toLocaleString('id-ID', { maximumFractionDigits: 1 })} MB melebihi batas 1 MB.`;

        return;
    }

    delete logoErrors.value[slot];
    uploading.value = slot;
    router.post(
        `/pengaturan/sekolah/logo/${slot}`,
        { logo: file },
        {
            preserveScroll: true,
            forceFormData: true,
            onError: (errors) =>
                (logoErrors.value[slot] =
                    errors.logo ?? 'Logo gagal diunggah.'),
            onFinish: () => (uploading.value = null),
        },
    );
}

function removeLogo(slot: string): void {
    router.delete(`/pengaturan/sekolah/logo/${slot}`, {
        preserveScroll: true,
        onError: () => toast.error('Logo gagal dihapus.'),
    });
}
</script>

<template>
    <Head title="Pengaturan Sekolah" />
    <SettingsLayout title="Sekolah" tour="set-sekolah">
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
            <form class="grid gap-6" @submit.prevent="save">
                <Card data-tour="set-identity">
                    <CardHeader>
                        <CardTitle>Identitas sekolah</CardTitle>
                        <CardDescription
                            >Tampil di kop rapor dan halaman
                            PIN.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="grid gap-4">
                        <div class="grid gap-1.5">
                            <Label for="school_name"
                                >Nama sekolah
                                <span class="text-destructive">*</span></Label
                            >
                            <Input
                                id="school_name"
                                v-model="form.school_name"
                                maxlength="100"
                                :aria-invalid="!!form.errors.school_name"
                            />
                            <InputError :message="form.errors.school_name" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="school_address">Alamat</Label>
                            <Textarea
                                id="school_address"
                                v-model="form.school_address"
                                rows="2"
                                maxlength="200"
                                :aria-invalid="!!form.errors.school_address"
                            />
                            <InputError :message="form.errors.school_address" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-1.5">
                                <Label for="program_name"
                                    >Nama program
                                    <span class="text-destructive"
                                        >*</span
                                    ></Label
                                >
                                <Input
                                    id="program_name"
                                    v-model="form.program_name"
                                    maxlength="100"
                                    :aria-invalid="!!form.errors.program_name"
                                />
                                <InputError
                                    :message="form.errors.program_name"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="partner_name">Nama mitra</Label>
                                <Input
                                    id="partner_name"
                                    v-model="form.partner_name"
                                    maxlength="100"
                                />
                                <InputError
                                    :message="form.errors.partner_name"
                                />
                            </div>
                        </div>
                        <div class="grid gap-1.5 sm:max-w-xs">
                            <Label for="city"
                                >Kota (untuk tanda tangan rapor)</Label
                            >
                            <Input
                                id="city"
                                v-model="form.city"
                                maxlength="60"
                            />
                            <InputError :message="form.errors.city" />
                        </div>
                    </CardContent>
                </Card>

                <Card data-tour="set-principal">
                    <CardHeader>
                        <CardTitle>Kepala sekolah</CardTitle>
                        <CardDescription
                            >Nama dan nomor induk di bawah tanda tangan
                            rapor.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="grid gap-4">
                        <div class="grid gap-1.5">
                            <Label for="principal_name"
                                >Nama kepala sekolah</Label
                            >
                            <Input
                                id="principal_name"
                                v-model="form.principal_name"
                                maxlength="100"
                                placeholder="mis. Nama Lengkap, S.Pd., M.Pd."
                            />
                            <InputError :message="form.errors.principal_name" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Label nomor induk</Label>
                            <RadioGroup
                                v-model="form.principal_id_label"
                                class="flex flex-wrap gap-4"
                            >
                                <label
                                    v-for="opt in [
                                        { v: 'NBM', l: 'NBM' },
                                        { v: 'NIP', l: 'NIP' },
                                        { v: 'none', l: 'Tidak ditampilkan' },
                                    ]"
                                    :key="opt.v"
                                    class="flex cursor-pointer items-center gap-2 text-sm"
                                >
                                    <RadioGroupItem :value="opt.v" />
                                    {{ opt.l }}
                                </label>
                            </RadioGroup>
                        </div>
                        <div
                            v-if="form.principal_id_label !== 'none'"
                            class="grid gap-1.5 sm:max-w-xs"
                        >
                            <Label for="principal_id_number"
                                >{{ form.principal_id_label }} kepala
                                sekolah</Label
                            >
                            <Input
                                id="principal_id_number"
                                v-model="form.principal_id_number"
                                maxlength="30"
                                class="tabular-nums"
                            />
                            <InputError
                                :message="form.errors.principal_id_number"
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Tampilan menu</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <label
                            class="flex cursor-pointer items-center justify-between gap-4"
                        >
                            <span>
                                <span class="block text-sm font-medium"
                                    >Tampilkan menu Dokumen</span
                                >
                                <span
                                    class="block text-sm text-muted-foreground"
                                    >Unggah/unduh instrumen tes dan pedoman
                                    penskoran.</span
                                >
                            </span>
                            <Switch v-model="form.show_documents_menu" />
                        </label>
                    </CardContent>
                </Card>

                <div class="sticky bottom-20 z-10 flex justify-end md:bottom-4">
                    <Button
                        type="submit"
                        class="shadow-lg"
                        data-tour="set-save"
                        :disabled="form.processing || !form.isDirty"
                    >
                        <Spinner v-if="form.processing" />
                        {{
                            form.processing ? 'Menyimpan…' : 'Simpan perubahan'
                        }}
                    </Button>
                </div>
            </form>

            <Card data-tour="set-logos" class="h-fit">
                <CardHeader>
                    <CardTitle>Logo</CardTitle>
                    <CardDescription
                        >PNG/JPG, maks. 1 MB, disarankan persegi ≥ 300
                        px.</CardDescription
                    >
                </CardHeader>
                <CardContent class="grid gap-5">
                    <div
                        v-for="l in logoSlots"
                        :key="l.slot"
                        class="flex items-start gap-4"
                    >
                        <div
                            class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-muted"
                        >
                            <img
                                v-if="logos[l.slot]"
                                :src="logos[l.slot]!"
                                :alt="l.title"
                                class="size-full object-contain p-1"
                            />
                            <ImageUp
                                v-else
                                class="size-7 text-muted-foreground"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold">{{ l.title }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ l.hint }}
                            </p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    as="label"
                                    class="cursor-pointer"
                                    :disabled="uploading === l.slot"
                                >
                                    <Spinner v-if="uploading === l.slot" />
                                    <ImageUp v-else />
                                    {{ logos[l.slot] ? 'Ganti' : 'Unggah' }}
                                    <input
                                        type="file"
                                        accept="image/png,image/jpeg"
                                        class="sr-only"
                                        @change="pickLogo(l.slot, $event)"
                                    />
                                </Button>
                                <Button
                                    v-if="logos[l.slot]"
                                    size="sm"
                                    variant="ghost"
                                    class="text-destructive"
                                    @click="removeLogo(l.slot)"
                                >
                                    <Trash2 /> Hapus
                                </Button>
                            </div>
                            <InputError
                                :message="logoErrors[l.slot]"
                                class="mt-1"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </SettingsLayout>
</template>
