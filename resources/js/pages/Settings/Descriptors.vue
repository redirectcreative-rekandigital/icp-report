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
import { LEVELS, SKILL_LABEL, SKILLS } from '@/lib/cefr';
import type { Level, Skill } from '@/lib/cefr';
import { clone } from '@/lib/clone';
import { cn } from '@/lib/utils';

/** Report texts in English (PRD-01 §4.1 descriptors & level_meta). */
type Meta = {
    level: Level;
    short_name: string;
    name: string;
    cambridge: string;
    overall_comment: string;
};

const props = defineProps<{
    levels: Meta[];
    descriptors: Record<
        Level,
        Record<Skill, { can_do: string; next_step: string }>
    >;
}>();

const form = useForm({
    levels: Object.fromEntries(
        props.levels.map((m) => [
            m.level,
            {
                short_name: m.short_name,
                name: m.name,
                cambridge: m.cambridge,
                overall_comment: m.overall_comment,
            },
        ]),
    ) as Record<Level, Omit<Meta, 'level'>>,
    descriptors: clone(props.descriptors),
});

const level = ref<Level>('Pre-A1');
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
            Teks rapor berbahasa Inggris dari sheet Descriptors Excel. Perubahan
            berlaku untuk kelas Draft; rapor kelas final memakai teks saat
            finalisasi.
        </p>

        <div
            data-tour="ds-level"
            class="mb-4 flex flex-wrap gap-1 rounded-lg bg-muted p-1 sm:w-fit"
            role="tablist"
        >
            <button
                v-for="l in LEVELS"
                :key="l"
                type="button"
                role="tab"
                :aria-selected="level === l"
                :class="
                    cn(
                        'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium',
                        level === l
                            ? 'bg-card shadow-sm'
                            : 'text-muted-foreground',
                    )
                "
                @click="level = l"
            >
                <LevelBadge :level="l" size="sm" />
                <span class="hidden sm:inline">{{
                    form.levels[l].short_name
                }}</span>
            </button>
        </div>

        <form class="grid gap-6" @submit.prevent="save">
            <Card data-tour="ds-meta">
                <CardHeader>
                    <CardTitle>Level {{ level }}</CardTitle>
                    <CardDescription
                        >Nama level, setara Cambridge, dan komentar otomatis
                        (mengikuti nama siswa).</CardDescription
                    >
                </CardHeader>
                <CardContent class="grid gap-4">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="grid gap-1.5">
                            <Label>Nama singkat</Label>
                            <Input
                                v-model="form.levels[level].short_name"
                                maxlength="40"
                            />
                            <InputError
                                :message="err(`levels.${level}.short_name`)"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Nama level</Label>
                            <Input
                                v-model="form.levels[level].name"
                                maxlength="80"
                            />
                            <InputError
                                :message="err(`levels.${level}.name`)"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Setara Cambridge</Label>
                            <Input
                                v-model="form.levels[level].cambridge"
                                maxlength="80"
                            />
                            <InputError
                                :message="err(`levels.${level}.cambridge`)"
                            />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Komentar otomatis — "{Nama siswa} …"</Label>
                        <Textarea
                            v-model="form.levels[level].overall_comment"
                            rows="3"
                            maxlength="400"
                        />
                        <InputError
                            :message="err(`levels.${level}.overall_comment`)"
                        />
                    </div>
                </CardContent>
            </Card>

            <Card data-tour="ds-cando">
                <CardHeader>
                    <CardTitle
                        >What the student can do & Next steps —
                        {{ level }}</CardTitle
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
                                v-model="form.descriptors[level][s].can_do"
                                rows="3"
                                maxlength="300"
                            />
                            <InputError
                                :message="
                                    err(`descriptors.${level}.${s}.can_do`)
                                "
                            />
                        </div>
                        <div class="grid gap-1">
                            <Label class="text-xs text-muted-foreground"
                                >Next step</Label
                            >
                            <Textarea
                                v-model="form.descriptors[level][s].next_step"
                                rows="3"
                                maxlength="300"
                            />
                            <InputError
                                :message="
                                    err(`descriptors.${level}.${s}.next_step`)
                                "
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
            title="Kembalikan semua teks ke bawaan Excel?"
            description="Semua perubahan deskriptor dan komentar otomatis diganti dengan teks asli dari file Excel."
            confirm-label="Kembalikan ke bawaan"
            destructive
            @confirm="reset"
        />
    </SettingsLayout>
</template>
