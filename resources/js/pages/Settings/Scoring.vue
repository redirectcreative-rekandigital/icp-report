<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, Eye, Lock } from 'lucide-vue-next';
import { CalendarRange } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import EmptyState from '@/components/app/EmptyState.vue';
import LevelBadge from '@/components/app/LevelBadge.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { withPin } from '@/composables/usePinConfirm';
import SettingsLayout from '@/layouts/SettingsLayout.vue';
import { LEVELS, SKILL_LABEL, SKILLS } from '@/lib/cefr';
import type { Level, ScoringConfig } from '@/lib/cefr';
import { ApiError, http } from '@/lib/http';
import { cn } from '@/lib/utils';

type ConfigRow = ScoringConfig & { id: number; grade: number };

const props = defineProps<{
    term: { id: number; label: string } | null;
    terms: { id: number; label: string; is_active: boolean }[];
    configs: ConfigRow[];
    finalClasses: string[];
}>();

const termId = ref(props.term ? String(props.term.id) : '');
watch(termId, (id) =>
    router.get('/pengaturan/penilaian', { term: id }, { preserveScroll: true }),
);

const grade = ref(props.configs[0]?.grade ?? 3);
const current = computed(() =>
    props.configs.find((c) => c.grade === grade.value)!,
);

type FormShape = {
    max: Record<string, number | string>;
    weight: Record<string, number | string>;
    thresholds: Record<Level, number | string>; // '' = level not measured
    expected_min: Level;
    expected_max: Level;
};

const toForm = (c: ConfigRow): FormShape => ({
    max: { ...c.max },
    weight: { ...c.weight },
    thresholds: Object.fromEntries(
        LEVELS.map((l) => [l, c.thresholds[l] ?? '']),
    ) as Record<Level, number | string>,
    expected_min: c.expected_min,
    expected_max: c.expected_max,
});

const form = useForm<FormShape>(
    current.value ? toForm(current.value) : ({} as FormShape),
);

watch(
    () => [grade.value, props.configs],
    () => {
        if (current.value) {
            form.defaults(toForm(current.value));
            form.reset();
            form.clearErrors();
            impact.value = null;
        }
    },
);

const weightTotal = computed(() =>
    SKILLS.reduce((s, k) => s + (Number(form.weight?.[k]) || 0), 0),
);

function payload() {
    const t = Object.fromEntries(
        LEVELS.map((l) => [
            l,
            form.thresholds[l] === '' ? null : form.thresholds[l],
        ]),
    );

    return { ...form.data(), thresholds: { ...t, 'Pre-A1': 0 } };
}

const impact = ref<{
    count: number;
    students: {
        name: string;
        class: string;
        from: Level | null;
        to: Level | null;
    }[];
} | null>(null);
const impactLoading = ref(false);

async function preview(): Promise<void> {
    impactLoading.value = true;
    form.clearErrors();

    try {
        impact.value = await http.post(
            `/pengaturan/penilaian/${current.value.id}/dampak`,
            payload(),
        );
    } catch (e) {
        if (e instanceof ApiError && e.status === 422) {
            form.setError(
                Object.fromEntries(
                    Object.entries(e.body.errors ?? {}).map(([k, v]) => [
                        k,
                        v[0],
                    ]),
                ) as never,
            );
        }
    } finally {
        impactLoading.value = false;
    }
}

function save(): void {
    void withPin(
        () =>
            form
                .transform(payload)
                .put(`/pengaturan/penilaian/${current.value.id}`, {
                    preserveScroll: true,
                }),
        'Konfirmasi ubah pengaturan penilaian',
    );
}

const err = (k: string) => (form.errors as Record<string, string>)[k];
</script>

<template>
    <Head title="Pengaturan Penilaian" />
    <SettingsLayout title="Penilaian" tour="set-penilaian">
        <div data-tour="sc-term" class="mb-4 flex flex-wrap items-center gap-3">
            <Label for="term">Periode</Label>
            <NativeSelect id="term" v-model="termId" class="w-64">
                <option v-for="t in terms" :key="t.id" :value="String(t.id)">
                    {{ t.label }}{{ t.is_active ? ' (aktif)' : '' }}
                </option>
            </NativeSelect>
        </div>

        <Card v-if="!term || !configs.length">
            <EmptyState
                :icon="CalendarRange"
                title="Belum ada periode"
                description="Pengaturan penilaian dibuat otomatis saat semester dibuat."
            />
        </Card>

        <template v-else>
            <div
                v-if="finalClasses.length"
                class="mb-4 flex items-start gap-2 rounded-lg border border-status-incomplete-fg/30 bg-status-incomplete px-4 py-3 text-sm text-status-incomplete-fg"
            >
                <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                Perubahan hanya berlaku untuk kelas Draft. Rapor kelas yang
                sudah final ({{ finalClasses.join(', ') }}) tidak berubah.
            </div>

            <div
                data-tour="sc-grade"
                class="mb-4 grid w-full grid-cols-4 gap-1 rounded-lg bg-muted p-1 sm:w-fit"
                role="tablist"
            >
                <button
                    v-for="c in configs"
                    :key="c.grade"
                    type="button"
                    role="tab"
                    :aria-selected="grade === c.grade"
                    :class="
                        cn(
                            'rounded-md px-4 py-2 text-sm font-medium',
                            grade === c.grade
                                ? 'bg-card text-primary shadow-sm'
                                : 'text-muted-foreground',
                        )
                    "
                    @click="grade = c.grade"
                >
                    Kelas {{ c.grade }}
                </button>
            </div>

            <form class="grid gap-6 xl:grid-cols-2" @submit.prevent="save">
                <Card data-tour="sc-max">
                    <CardHeader>
                        <CardTitle>Nilai maksimum & bobot</CardTitle>
                        <CardDescription
                            >Persentase = skor ÷ maksimum × 100. Overall =
                            rata-rata berbobot.</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <div
                            class="grid grid-cols-[1fr_6rem_6rem] items-center gap-x-3 gap-y-2 text-sm"
                        >
                            <span />
                            <span class="font-semibold text-muted-foreground"
                                >Maksimum</span
                            >
                            <span class="font-semibold text-muted-foreground"
                                >Bobot (%)</span
                            >
                            <template v-for="s in SKILLS" :key="s">
                                <span class="font-medium">{{
                                    SKILL_LABEL[s]
                                }}</span>
                                <Input
                                    v-model="form.max[s]"
                                    type="number"
                                    min="1"
                                    max="100"
                                    step="0.5"
                                    class="tabular-nums"
                                    :aria-invalid="!!err(`max.${s}`)"
                                    :aria-label="`Maksimum ${SKILL_LABEL[s]}`"
                                />
                                <Input
                                    v-model="form.weight[s]"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="1"
                                    class="tabular-nums"
                                    :aria-label="`Bobot ${SKILL_LABEL[s]}`"
                                />
                                <InputError
                                    v-if="err(`max.${s}`)"
                                    :message="err(`max.${s}`)"
                                    class="col-span-3"
                                />
                            </template>
                            <span class="font-semibold">Total bobot</span>
                            <span />
                            <span
                                :class="
                                    cn(
                                        'font-bold tabular-nums',
                                        weightTotal !== 100 &&
                                            'text-status-incomplete-fg',
                                    )
                                "
                                >{{ weightTotal }}</span
                            >
                        </div>
                        <p
                            v-if="weightTotal !== 100 && weightTotal > 0"
                            class="mt-2 text-xs text-status-incomplete-fg"
                        >
                            Total bukan 100 — tetap diizinkan karena bobot
                            dinormalisasi.
                        </p>
                        <InputError :message="err('weight')" class="mt-2" />
                    </CardContent>
                </Card>

                <Card data-tour="sc-thresholds">
                    <CardHeader>
                        <CardTitle>Ambang batas level CEFR (%)</CardTitle>
                        <CardDescription
                            >Level = level tertinggi yang ambangnya ≤
                            persentase. Kosongkan bila level tidak
                            diukur.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="grid gap-2">
                        <div
                            v-for="l in LEVELS"
                            :key="l"
                            class="grid grid-cols-[5rem_7rem_1fr] items-center gap-3"
                        >
                            <LevelBadge :level="l" />
                            <div class="relative">
                                <Input
                                    v-if="l !== 'Pre-A1'"
                                    v-model="form.thresholds[l]"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.5"
                                    placeholder="tidak diukur"
                                    class="tabular-nums"
                                    :aria-label="`Ambang ${l}`"
                                    :aria-invalid="!!err(`thresholds.${l}`)"
                                />
                                <div
                                    v-else
                                    class="flex h-9 items-center gap-1.5 rounded-md bg-input-computed px-3 text-sm text-muted-foreground"
                                >
                                    <Lock class="size-3.5" /> 0
                                </div>
                            </div>
                            <InputError :message="err(`thresholds.${l}`)" />
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5">
                                <Label for="exp-min"
                                    >Level harapan terendah</Label
                                >
                                <NativeSelect
                                    id="exp-min"
                                    v-model="form.expected_min"
                                    :invalid="!!err('expected_min')"
                                >
                                    <option
                                        v-for="l in LEVELS"
                                        :key="l"
                                        :value="l"
                                    >
                                        {{ l }}
                                    </option>
                                </NativeSelect>
                                <InputError :message="err('expected_min')" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="exp-max"
                                    >Level harapan tertinggi</Label
                                >
                                <NativeSelect
                                    id="exp-max"
                                    v-model="form.expected_max"
                                    :invalid="!!err('expected_max')"
                                >
                                    <option
                                        v-for="l in LEVELS"
                                        :key="l"
                                        :value="l"
                                    >
                                        {{ l }}
                                    </option>
                                </NativeSelect>
                                <InputError :message="err('expected_max')" />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="impact" class="xl:col-span-2">
                    <CardHeader>
                        <CardTitle class="text-base">
                            {{
                                impact.count
                                    ? `Perubahan ini akan mengubah hasil ${impact.count} siswa di kelas Draft`
                                    : 'Tidak ada siswa di kelas Draft yang levelnya berubah'
                            }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent v-if="impact.count">
                        <ul class="grid gap-1 text-sm sm:grid-cols-2">
                            <li
                                v-for="s in impact.students"
                                :key="s.name + s.class"
                                class="flex items-center gap-2"
                            >
                                <span class="min-w-0 flex-1 truncate"
                                    >{{ s.name }} ({{ s.class }})</span
                                >
                                <LevelBadge :level="s.from" size="sm" /> →
                                <LevelBadge :level="s.to" size="sm" />
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <div class="flex flex-wrap justify-end gap-2 xl:col-span-2">
                    <Button
                        type="button"
                        variant="outline"
                        data-tour="sc-impact"
                        :disabled="impactLoading"
                        @click="preview"
                    >
                        <Spinner v-if="impactLoading" /><Eye v-else /> Lihat
                        dampak
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.processing || !form.isDirty"
                    >
                        <Spinner v-if="form.processing" /> Simpan Kelas
                        {{ grade }}
                    </Button>
                </div>
            </form>
        </template>
    </SettingsLayout>
</template>
