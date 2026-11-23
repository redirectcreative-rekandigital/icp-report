<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { RotateCcw } from 'lucide-vue-next';
import { ref } from 'vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import LevelBadge from '@/components/app/LevelBadge.vue';
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
import { Textarea } from '@/components/ui/textarea';
import SettingsLayout from '@/layouts/SettingsLayout.vue';
import { SKILL_LABEL, SKILLS } from '@/lib/cefr';
import type { Band, Level, Skill } from '@/lib/cefr';
import { clone } from '@/lib/clone';
import { cn } from '@/lib/utils';

/**
 * Report texts in English: per band the can-do / next step per skill and the automatic
 * comment; plus the CEFR level names shown on the grade 6 National Geographic map.
 */
type Meta = {
    level: Level;
    short_name: string;
    name: string;
    cambridge: string;
};

const props = defineProps<{
    bands: Band[];
    comments: Record<Band, string>;
    descriptors: Record<
        Band,
        Record<Skill, { can_do: string; next_step: string }>
    >;
    levels: Meta[];
}>();

const form = useForm({
    comments: clone(props.comments),
    descriptors: clone(props.descriptors),
    levels: Object.fromEntries(
        props.levels.map((m) => [
            m.level,
            { short_name: m.short_name, name: m.name, cambridge: m.cambridge },
        ]),
    ) as Record<Level, Omit<Meta, 'level'>>,
});

const band = ref<Band>(props.bands[0]);
const resetOpen = ref(false);
const err = (k: string) => (form.errors as Record<string, string>)[k];

function save(): void {
    form.put('/pengaturan/deskriptor', { preserveScroll: true });
}

function reset(): void {
    router.post(
        '/pengaturan/deskriptor/bawaan',
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                resetOpen.value = false;
                router.reload();
            },
        },
    );
}
</script>

<template>
    <Head title="Deskriptor" />
    <SettingsLayout title="Deskriptor" tour="set-deskriptor">
        <p class="mb-4 max-w-3xl text-sm text-muted-foreground">
            Teks rapor berbahasa Inggris per level penilaian guru. Perubahan
            berlaku untuk kelas Draft; rapor kelas final memakai teks saat
            finalisasi.
        </p>

        <div
            data-tour="ds-level"
            class="mb-4 flex flex-wrap gap-1 rounded-lg bg-muted p-1 sm:w-fit"
            role="tablist"
        >
            <button
                v-for="b in bands"
                :key="b"
                type="button"
                role="tab"
                :aria-selected="band === b"
                :class="
                    cn(
                        'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium',
                        band === b
                            ? 'bg-card shadow-sm'
                            : 'text-muted-foreground',
                    )
                "
                @click="band = b"
            >
                <LevelBadge :level="b" size="sm" />
            </button>
        </div>

        <form class="grid gap-6" @submit.prevent="save">
            <Card data-tour="ds-meta">
                <CardHeader>
                    <CardTitle>Komentar otomatis — {{ band }}</CardTitle>
                    <CardDescription
                        >Dipakai bila guru tidak menulis komentar sendiri,
                        mengikuti level total siswa.</CardDescription
                    >
                </CardHeader>
                <CardContent class="grid gap-1.5">
                    <Label>"{Nama siswa} …"</Label>
                    <Textarea
                        v-model="form.comments[band]"
                        rows="3"
                        maxlength="400"
                    />
                    <InputError :message="err(`comments.${band}`)" />
                </CardContent>
            </Card>

            <Card data-tour="ds-cando">
                <CardHeader>
                    <CardTitle
                        >What the student can do & Next steps —
                        {{ band }}</CardTitle
                    >
                    <CardDescription
                        >Dipilih per skill sesuai level skill
                        tersebut.</CardDescription
                    >
                </CardHeader>
                <CardContent class="grid gap-5">
                    <div
                        v-for="s in SKILLS"
                        :key="s"
                        class="grid gap-3 md:grid-cols-[7rem_1fr_1fr]"
                    >
                        <p class="font-semibold">{{ SKILL_LABEL[s] }}</p>
                        <div class="grid gap-1">
                            <Label class="text-xs text-muted-foreground"
                                >Can do</Label
                            >
                            <Textarea
                                v-model="form.descriptors[band][s].can_do"
                                rows="3"
                                maxlength="300"
                            />
                            <InputError
                                :message="
                                    err(`descriptors.${band}.${s}.can_do`)
                                "
                            />
                        </div>
                        <div class="grid gap-1">
                            <Label class="text-xs text-muted-foreground"
                                >Next step</Label
                            >
                            <Textarea
                                v-model="form.descriptors[band][s].next_step"
                                rows="3"
                                maxlength="300"
                            />
                            <InputError
                                :message="
                                    err(`descriptors.${band}.${s}.next_step`)
                                "
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Peta level CEFR (Kelas 6)</CardTitle>
                    <CardDescription
                        >Nama level di peta "Online placement test aligned with
                        the CEFR level map" pada rapor Kelas 6.</CardDescription
                    >
                </CardHeader>
                <CardContent class="grid gap-3">
                    <div
                        v-for="m in levels"
                        :key="m.level"
                        class="grid items-start gap-2 sm:grid-cols-[4.5rem_1fr_1fr_1fr]"
                    >
                        <LevelBadge :level="m.level" class="mt-1.5" />
                        <div class="grid gap-1">
                            <Input
                                v-model="form.levels[m.level].short_name"
                                maxlength="40"
                                :aria-label="`Nama singkat ${m.level}`"
                                placeholder="Nama singkat"
                            />
                            <InputError
                                :message="err(`levels.${m.level}.short_name`)"
                            />
                        </div>
                        <div class="grid gap-1">
                            <Input
                                v-model="form.levels[m.level].name"
                                maxlength="80"
                                :aria-label="`Nama level ${m.level}`"
                                placeholder="Nama level"
                            />
                            <InputError
                                :message="err(`levels.${m.level}.name`)"
                            />
                        </div>
                        <div class="grid gap-1">
                            <Input
                                v-model="form.levels[m.level].cambridge"
                                maxlength="80"
                                :aria-label="`Setara Cambridge ${m.level}`"
                                placeholder="Setara Cambridge"
                            />
                            <InputError
                                :message="err(`levels.${m.level}.cambridge`)"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <p
                v-if="Object.keys(form.errors).length"
                class="text-sm font-medium text-destructive"
            >
                Ada {{ Object.keys(form.errors).length }} isian yang perlu
                diperbaiki (periksa tab level lain).
            </p>

            <div
                class="sticky bottom-20 z-10 flex justify-end gap-2 md:bottom-4"
            >
                <Button
                    type="button"
                    variant="outline"
                    class="bg-card"
                    data-tour="ds-reset"
                    @click="resetOpen = true"
                    ><RotateCcw /> Kembalikan ke bawaan</Button
                >
                <Button
                    type="submit"
                    class="shadow-lg"
                    :disabled="form.processing || !form.isDirty"
                >
                    <Spinner v-if="form.processing" /> Simpan deskriptor
                </Button>
            </div>
        </form>

        <ConfirmDialog
            v-model:open="resetOpen"
            title="Kembalikan semua teks ke bawaan?"
            description="Semua perubahan deskriptor dan komentar otomatis diganti dengan teks bawaan."
            confirm-label="Kembalikan ke bawaan"
            destructive
            @confirm="reset"
        />
    </SettingsLayout>
</template>
