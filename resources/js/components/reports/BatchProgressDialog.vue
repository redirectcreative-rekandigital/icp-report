<script setup lang="ts">
import {
    CheckCircle2,
    Download,
    Printer,
    RotateCcw,
    XCircle,
} from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';
import ResponsiveDialog from '@/components/app/ResponsiveDialog.vue';
import type { BatchState } from '@/components/reports/types';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Spinner } from '@/components/ui/spinner';
import { printPdf } from '@/composables/usePrintPdf';
import { ApiError, http } from '@/lib/http';

/**
 * "Unduh semua PDF" / "Cetak semua" (PRD-05 §7.2): start → call /next until done →
 * download or print. Network hiccups are retried 3×; the batch can be resumed later.
 */

const props = defineProps<{
    classroomId: number;
    request: {
        paper: 'f4' | 'a4';
        mode: 'merged' | 'zip';
        ids: number[] | null;
        print: boolean;
    } | null;
    resume: BatchState | null;
}>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ finished: [] }>();

const state = ref<BatchState | null>(null);
const error = ref<string | null>(null);
const startedAt = ref(0);
const print = ref(false);
let stopped = false;

const pct = computed(() =>
    state.value?.total
        ? Math.round((state.value.done / state.value.total) * 100)
        : 0,
);
const eta = computed(() => {
    const s = state.value;

    if (!s || s.done === 0 || s.status !== 'running') {
        return null;
    }

    const perReport = (Date.now() - startedAt.value) / 1000 / s.done;
    const left = Math.round(perReport * (s.total - s.done));

    return left < 60
        ? `± ${left} detik lagi`
        : `± ${Math.ceil(left / 60)} menit lagi`;
});
const slow = ref(false);
let slowTimer: ReturnType<typeof setTimeout> | undefined;

async function loop(): Promise<void> {
    let retries = 0;
    startedAt.value = Date.now();
    slowTimer = setTimeout(() => (slow.value = true), 10000);

    while (!stopped && state.value?.status === 'running') {
        try {
            state.value = await http.post<BatchState>(
                `/rapor/batch/${state.value.id}/next`,
            );
            retries = 0;
        } catch (e) {
            if (++retries > 3) {
                error.value =
                    e instanceof ApiError
                        ? (e.body.message ?? 'Proses terhenti.')
                        : 'Proses terhenti.';

                return;
            }

            await new Promise((r) => setTimeout(r, 1000 * 2 ** retries));
        }
    }

    clearTimeout(slowTimer);

    if (state.value?.status === 'completed' && state.value.download_url) {
        finish(state.value.download_url);
    }
}

function finish(url: string): void {
    emit('finished');

    if (print.value) {
        printPdf(`${url}?inline=1`, state.value!.paper);
    } else {
        window.location.href = url; // attachment download, page stays
    }
}

watch(open, async (v) => {
    if (!v) {
        stopped = true;

        return;
    }

    stopped = false;
    error.value = null;
    slow.value = false;
    print.value = props.request?.print ?? false;

    if (props.resume) {
        state.value = props.resume;
    } else if (props.request) {
        try {
            state.value = await http.post<BatchState>(
                `/kelas/${props.classroomId}/rapor/batch`,
                {
                    paper: props.request.paper,
                    mode: props.request.mode,
                    enrollment_ids: props.request.ids,
                },
            );
        } catch (e) {
            error.value =
                e instanceof ApiError
                    ? (e.body.message ?? 'Gagal memulai.')
                    : 'Gagal memulai.';

            return;
        }
    }

    void loop();
});

onUnmounted(() => {
    stopped = true;
    clearTimeout(slowTimer);
});

async function retryFailed(): Promise<void> {
    if (!state.value) {
        return;
    }

    state.value = await http.post<BatchState>(
        `/rapor/batch/${state.value.id}/ulang`,
    );
    error.value = null;
    void loop();
}

async function cancel(): Promise<void> {
    stopped = true;

    if (state.value?.status === 'running') {
        await http
            .delete(`/rapor/batch/${state.value.id}`)
            .catch(() => undefined);
    }

    open.value = false;
    emit('finished');
}
</script>

<template>
    <ResponsiveDialog
        v-model:open="open"
        :title="
            print ? 'Menyiapkan cetak semua rapor' : 'Membuat PDF semua rapor'
        "
        :dismissible="state?.status !== 'running'"
        tour="flow-cetak-semua"
    >
        <div class="space-y-3 text-sm" aria-live="polite">
            <template v-if="!state && !error">
                <div class="flex items-center gap-2"><Spinner /> Memulai…</div>
            </template>

            <template v-else-if="state">
                <div
                    v-if="state.status === 'running'"
                    class="space-y-2"
                    data-tour="batch-progress"
                >
                    <p class="font-medium">
                        Membuat rapor
                        {{ Math.min(state.done + 1, state.total) }} dari
                        {{ state.total }}…
                    </p>
                    <Progress :model-value="pct" class="h-2.5" />
                    <p class="flex justify-between text-muted-foreground">
                        <span class="truncate">{{ state.current ?? '' }}</span>
                        <span>{{ eta ?? '' }}</span>
                    </p>
                    <p v-if="slow" class="text-xs text-muted-foreground">
                        Masih memproses, mohon tunggu…
                    </p>
                </div>

                <div
                    v-else-if="state.status === 'completed'"
                    class="flex items-start gap-2 text-status-meeting-fg"
                    data-tour="batch-result"
                >
                    <CheckCircle2 class="mt-0.5 size-5 shrink-0" />
                    <div>
                        <p class="font-semibold">
                            {{ state.done }} rapor berhasil{{
                                print
                                    ? ' — dialog cetak dibuka.'
                                    : ' — unduhan dimulai.'
                            }}
                        </p>
                        <a
                            v-if="state.download_url"
                            :href="state.download_url"
                            class="underline"
                            >Unduh ulang</a
                        >
                    </div>
                </div>

                <div
                    v-if="state.failed.length"
                    class="rounded-lg border border-destructive/30 bg-status-below p-3 text-status-below-fg"
                    data-tour="batch-failed"
                >
                    <p class="mb-1 flex items-center gap-1.5 font-semibold">
                        <XCircle class="size-4" />
                        {{ state.failed.length }} rapor gagal:
                    </p>
                    <ul class="list-disc pl-5">
                        <li v-for="f in state.failed" :key="f.name">
                            {{ f.name }} — {{ f.error }}
                        </li>
                    </ul>
                </div>
            </template>

            <p v-if="error" class="font-medium text-destructive">{{ error }}</p>
            <p class="text-xs text-muted-foreground">
                Disarankan memakai laptop untuk mencetak massal. Proses bisa
                dilanjutkan bila tab tertutup.
            </p>
        </div>

        <template #footer>
            <Button
                v-if="state?.failed.length || error"
                variant="outline"
                @click="retryFailed"
                ><RotateCcw /> Coba ulang yang gagal</Button
            >
            <Button
                v-if="state?.status === 'running' || error"
                variant="outline"
                @click="cancel"
                >Batalkan</Button
            >
            <template
                v-if="state?.status === 'completed' && state.download_url"
            >
                <Button
                    v-if="!print && state.mode === 'merged'"
                    variant="outline"
                    @click="
                        printPdf(`${state.download_url}?inline=1`, state.paper)
                    "
                >
                    <Printer /> Cetak
                </Button>
                <Button as="a" :href="state.download_url"
                    ><Download /> Unduh</Button
                >
            </template>
            <Button
                v-if="state?.status !== 'running'"
                variant="ghost"
                @click="open = false"
                >Tutup</Button
            >
        </template>
    </ResponsiveDialog>
</template>
