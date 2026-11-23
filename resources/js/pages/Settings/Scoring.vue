<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, CalendarRange, Eye } from 'lucide-vue-next';
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
import { formatScore, SCORE_MAX, SCORE_MIN } from '@/lib/cefr';
import type { Band, ScoringConfig } from '@/lib/cefr';
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
    props.configs.find((c) => c.grade === grade.value),
);

type FormShape = { intermediate: number | string; advanced: number | string };
const toForm = (c?: ConfigRow): FormShape => ({
    intermediate: c?.intermediate ?? 13,
    advanced: c?.advanced ?? 20,
});

const form = useForm<FormShape>(toForm(current.value));

watch(
    () => [grade.value, props.configs],
    () => {
        form.defaults(toForm(current.value));
        form.reset();
        form.clearErrors();
        impact.value = null;
    },
);

/** Ranges shown as a live preview (per skill, and ×4 for the total). */
const ranges = computed(() => {
    const i = Number(form.intermediate) || 0;
    const a = Number(form.advanced) || 0;
    const below = (v: number) => formatScore(v - 0.5);

    return [
        {
            band: 'Beginner' as Band,
            skill: `${SCORE_MIN} – ${below(i)}`,
            total: `${SCORE_MIN * 4} – ${below(i * 4)}`,
        },
        {
            band: 'Intermediate' as Band,
            skill: `${formatScore(i)} – ${below(a)}`,
            total: `${formatScore(i * 4)} – ${below(a * 4)}`,
        },
        {
            band: 'Advanced' as Band,
            skill: `${formatScore(a)} – ${SCORE_MAX}`,
            total: `${formatScore(a * 4)} – ${SCORE_MAX * 4}`,
        },
    ];
});

const impact = ref<{
    count: number;
    students: {
        name: string;
        class: string;
        from: Band | null;
        to: Band | null;
    }[];
} | null>(null);
const impactLoading = ref(false);

async function preview(): Promise<void> {
    if (!current.value) {
        return;
    }

    impactLoading.value = true;
    form.clearErrors();

    try {
        impact.value = await http.post(
            `/pengaturan/penilaian/${current.value.id}/dampak`,
            form.data(),
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
            form.put(`/pengaturan/penilaian/${current.value!.id}`, {
                preserveScroll: true,
            }),
        'Konfirmasi ubah pengaturan penilaian',
    );
}
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
                <Card data-tour="sc-thresholds">
                    <CardHeader>
                        <CardTitle>Batas level penilaian guru</CardTitle>
                        <CardDescription
                            >Nilai tiap skill {{ SCORE_MIN }}–{{ SCORE_MAX }}.
                            Level total memakai batas yang sama ×4 (total
                            {{ SCORE_MIN * 4 }}–{{
                                SCORE_MAX * 4
                            }}).</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="grid gap-3">
                        <div
                            class="grid grid-cols-[8rem_7rem_1fr] items-center gap-3"
                        >
                            <LevelBadge level="Intermediate" />
                            <Input
                                v-model="form.intermediate"
                                type="number"
                                :min="SCORE_MIN"
                                :max="SCORE_MAX"
                                step="0.5"
                                class="tabular-nums"
                                aria-label="Batas minimum Intermediate"
                                :aria-invalid="!!form.errors.intermediate"
                            />
                            <span class="text-sm text-muted-foreground"
                                >nilai minimum</span
                            >
                            <InputError
                                :message="form.errors.intermediate"
                                class="col-span-3"
                            />
                            <LevelBadge level="Advanced" />
                            <Input
                                v-model="form.advanced"
                                type="number"
                                :min="SCORE_MIN"
                                :max="SCORE_MAX"
                                step="0.5"
                                class="tabular-nums"
                                aria-label="Batas minimum Advanced"
                                :aria-invalid="!!form.errors.advanced"
                            />
                            <span class="text-sm text-muted-foreground"
                                >nilai minimum</span
                            >
                            <InputError
                                :message="form.errors.advanced"
                                class="col-span-3"
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card data-tour="sc-max">
                    <CardHeader>
                        <CardTitle>Rentang nilai</CardTitle>
                        <CardDescription
                            >Pratinjau dari batas di samping.</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <table class="w-full text-sm">
                            <thead class="text-left text-muted-foreground">
                                <tr>
                                    <th class="py-1 font-semibold">Level</th>
                                    <th class="py-1 font-semibold">
                                        Per skill
                                    </th>
                                    <th class="py-1 font-semibold">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="r in ranges"
                                    :key="r.band"
                                    class="border-t"
                                >
                                    <td class="py-1.5">
                                        <LevelBadge :level="r.band" size="sm" />
                                    </td>
                                    <td class="py-1.5 tabular-nums">
                                        {{ r.skill }}
                                    </td>
                                    <td class="py-1.5 tabular-nums">
                                        {{ r.total }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
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
