<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { AlertTriangle, ArrowRight, Lock } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import LevelBadge from '@/components/app/LevelBadge.vue';
import ResponsiveDialog from '@/components/app/ResponsiveDialog.vue';
import type { ClassInfo } from '@/components/scores/types';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import { withPin } from '@/composables/usePinConfirm';
import { LEVELS } from '@/lib/cefr';
import type { StudentResult } from '@/lib/cefr';
import { http } from '@/lib/http';

/** "Finalkan" (PRD-04 §6.1): server readiness check → summary + checklist → PIN → finalize. */
const props = defineProps<{
    classroom: ClassInfo;
    results: StudentResult[];
    pending: number;
}>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ jump: [enrollmentId: number] }>();

type Missing = { enrollment_id: number; name: string; missing: string[] };
const loading = ref(false);
const incomplete = ref<Missing[] | null>(null);
const checked = ref(false);
const busy = ref(false);

watch(open, async (v) => {
    if (!v) {
        return;
    }

    checked.value = false;
    incomplete.value = null;
    loading.value = true;

    try {
        incomplete.value = (
            await http.get<{ incomplete: Missing[] }>(
                `/kelas/${props.classroom.id}/kesiapan-final`,
            )
        ).incomplete;
    } finally {
        loading.value = false;
    }
});

const complete = computed(() =>
    props.results.filter((r) => r.status !== 'incomplete'),
);
const dist = computed(() =>
    LEVELS.map((l) => ({
        level: l,
        n: complete.value.filter((r) => r.overall_level === l).length,
    })),
);
const count = (s: string) =>
    complete.value.filter((r) => r.status === s).length;

function finalize(): void {
    void withPin(() => {
        busy.value = true;
        router.post(
            `/kelas/${props.classroom.id}/finalkan`,
            {},
            {
                onSuccess: () => (open.value = false),
                onFinish: () => (busy.value = false),
            },
        );
    }, `Konfirmasi finalisasi kelas ${props.classroom.name}`);
}
</script>

<template>
    <ResponsiveDialog
        v-model:open="open"
        :title="`Finalkan kelas ${classroom.name}`"
        tour="flow-finalkan"
    >
        <div v-if="loading" class="flex justify-center py-6"><Spinner /></div>

        <div
            v-else-if="incomplete?.length"
            class="space-y-3"
            data-tour="fin-incomplete"
        >
            <p
                class="flex items-start gap-2 text-sm font-medium text-status-incomplete-fg"
            >
                <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                {{ incomplete.length }} siswa belum lengkap:
            </p>
            <ul
                class="max-h-64 divide-y overflow-y-auto rounded-lg border text-sm"
            >
                <li
                    v-for="m in incomplete"
                    :key="m.enrollment_id"
                    class="flex items-center gap-2 px-3 py-2"
                >
                    <span class="min-w-0 flex-1 truncate"
                        ><strong>{{ m.name }}</strong> ({{
                            m.missing.join(', ')
                        }})</span
                    >
                    <Button
                        size="sm"
                        variant="ghost"
                        @click="emit('jump', m.enrollment_id)"
                        >Lompat <ArrowRight
                    /></Button>
                </li>
            </ul>
        </div>

        <div
            v-else-if="incomplete"
            class="space-y-4 text-sm"
            data-tour="fin-summary"
        >
            <p>
                {{ complete.length }} siswa akan difinalkan. Nilai dan level
                dikunci, lalu rapor siap dicetak.
            </p>
            <div class="flex flex-wrap gap-2">
                <span
                    v-for="d in dist"
                    v-show="d.n"
                    :key="d.level"
                    class="inline-flex items-center gap-1"
                >
                    <LevelBadge :level="d.level" size="sm" /><strong
                        class="tabular-nums"
                        >{{ d.n }}</strong
                    >
                </span>
            </div>
            <p class="text-muted-foreground">
                Below {{ count('below') }} • Meeting {{ count('meeting') }} •
                Above {{ count('above') }}
            </p>
            <p v-if="pending" class="font-medium text-status-incomplete-fg">
                Tunggu {{ pending }} perubahan selesai tersimpan.
            </p>
            <label
                class="flex cursor-pointer items-start gap-3 rounded-lg border p-3"
                data-tour="fin-check"
            >
                <Checkbox v-model="checked" class="mt-0.5" />
                <span class="font-medium"
                    >Saya sudah memeriksa nilai dan komentar</span
                >
            </label>
        </div>

        <template #footer>
            <Button variant="outline" @click="open = false">Batal</Button>
            <Button
                data-tour="fin-submit"
                :disabled="
                    !incomplete ||
                    incomplete.length > 0 ||
                    !checked ||
                    pending > 0 ||
                    busy
                "
                @click="finalize"
            >
                <Spinner v-if="busy" /><Lock v-else />
                {{ busy ? 'Memfinalkan…' : 'Finalkan' }}
            </Button>
        </template>
    </ResponsiveDialog>
</template>
