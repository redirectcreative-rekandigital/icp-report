<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { onKeyStroke, useElementSize, useStorage } from '@vueuse/core';
import {
    ArrowLeft,
    ChevronLeft,
    ChevronRight,
    Download,
    Info,
    Pencil,
    Printer,
    Share2,
    ZoomIn,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import TourButton from '@/components/app/TourButton.vue';
import PrintTipsDialog from '@/components/reports/PrintTipsDialog.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { printPdf } from '@/composables/usePrintPdf';
import { usePageTour } from '@/composables/useTour';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * Report preview (PRD-05 §4). Renders the same HTML template DomPDF uses, so the preview
 * matches the PDF; scaled to the paper ratio inside an iframe.
 */
const props = defineProps<{
    classroom: {
        id: number;
        name: string;
        status: 'draft' | 'final';
        term: string;
    };
    student: { enrollment_id: number; name: string; nipd: string };
    printable: boolean;
    prevId: number | null;
    nextId: number | null;
    position: number | null;
    count: number;
    paperDefault: 'f4' | 'a4';
    snapshotAt: string | null;
}>();

const paper = useStorage<'f4' | 'a4'>('report.paper', props.paperDefault);
const zoom = ref(1);
const loading = ref(true);
const box = ref<HTMLElement | null>(null);
const { width } = useElementSize(box);

const MM = 96 / 25.4;
const pageW = computed(() => (paper.value === 'a4' ? 210 : 215) * MM);
const pageH = computed(() => (paper.value === 'a4' ? 297 : 330) * MM);
const scale = computed(
    () =>
        Math.min(1.25, (width.value || pageW.value) / pageW.value) * zoom.value,
);

const base = computed(
    () => `/kelas/${props.classroom.id}/rapor/${props.student.enrollment_id}`,
);
const htmlUrl = computed(() => `${base.value}/html?paper=${paper.value}`);
const pdfUrl = computed(() => `${base.value}/pdf?paper=${paper.value}`);

function go(id: number | null): void {
    if (id) {
        router.visit(`/kelas/${props.classroom.id}/rapor/${id}`, {
            preserveScroll: true,
        });
    }
}

onKeyStroke(['[', ']'], (e) => go(e.key === '[' ? props.prevId : props.nextId));

const canShare = typeof navigator !== 'undefined' && 'share' in navigator;
const sharing = ref(false);

async function share(): Promise<void> {
    sharing.value = true;

    try {
        const res = await fetch(pdfUrl.value, { credentials: 'same-origin' });
        const name =
            /filename="([^"]+)"/.exec(
                res.headers.get('Content-Disposition') ?? '',
            )?.[1] ?? 'rapor.pdf';
        const file = new File([await res.blob()], name, {
            type: 'application/pdf',
        });

        if (navigator.canShare?.({ files: [file] })) {
            await navigator.share({ files: [file], title: name });
        } else {
            window.location.href = pdfUrl.value;
        }
    } catch (e) {
        if ((e as Error).name !== 'AbortError') {
            toast.error('PDF tidak dapat dibagikan. Gunakan tombol Unduh PDF.');
        }
    } finally {
        sharing.value = false;
    }
}

usePageTour(() => 'rapor-preview');
</script>

<template>
    <Head :title="`Rapor ${student.name}`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Kelas', href: '/kelas' },
            { title: classroom.name, href: `/kelas/${classroom.id}/rapor` },
            { title: student.name, href: base },
        ]"
        :title="student.name"
    >
        <Link
            :href="`/kelas/${classroom.id}/rapor`"
            class="mb-3 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" /> Rapor & Cetak {{ classroom.name }}
        </Link>

        <!-- Toolbar -->
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <div data-tour="preview-nav" class="flex gap-1">
                <Button
                    variant="outline"
                    size="icon"
                    :disabled="!prevId"
                    aria-label="Siswa sebelumnya"
                    @click="go(prevId)"
                    ><ChevronLeft
                /></Button>
                <Button
                    variant="outline"
                    size="icon"
                    :disabled="!nextId"
                    aria-label="Siswa berikutnya"
                    @click="go(nextId)"
                    ><ChevronRight
                /></Button>
            </div>
            <div class="min-w-0">
                <p class="truncate font-semibold">{{ student.name }}</p>
                <p class="text-xs text-muted-foreground">
                    {{ position ? `${position}/${count} • ` : '' }}NIPD
                    {{ student.nipd }}
                </p>
            </div>
            <TourButton tour="rapor-preview" />
            <div class="ml-auto flex flex-wrap items-center gap-2">
                <div
                    data-tour="preview-paper"
                    class="flex items-center rounded-lg border bg-card p-0.5"
                    role="radiogroup"
                    aria-label="Ukuran kertas"
                >
                    <button
                        v-for="p in ['a4', 'f4'] as const"
                        :key="p"
                        type="button"
                        role="radio"
                        :aria-checked="paper === p"
                        :class="
                            cn(
                                'rounded-md px-3 py-1 text-sm font-semibold',
                                paper === p
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground',
                            )
                        "
                        @click="
                            paper = p;
                            loading = true;
                        "
                    >
                        {{ p.toUpperCase() }}
                    </button>
                </div>
                <div
                    class="hidden items-center rounded-lg border bg-card p-0.5 sm:flex"
                    aria-label="Zoom"
                >
                    <ZoomIn class="mx-1.5 size-4 text-muted-foreground" />
                    <button
                        v-for="z in [0.75, 1, 1.25]"
                        :key="z"
                        type="button"
                        :class="
                            cn(
                                'rounded-md px-2 py-1 text-xs font-semibold',
                                zoom === z
                                    ? 'bg-accent text-primary'
                                    : 'text-muted-foreground',
                            )
                        "
                        @click="zoom = z"
                    >
                        {{ z * 100 }}%
                    </button>
                </div>
                <Button
                    v-if="classroom.status === 'draft'"
                    variant="outline"
                    size="sm"
                    as-child
                    class="hidden md:inline-flex"
                >
                    <Link :href="`/kelas/${classroom.id}/nilai`"
                        ><Pencil /> Edit nilai</Link
                    >
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    class="hidden md:inline-flex"
                    @click="printPdf(`${pdfUrl}&inline=1`, paper)"
                    ><Printer /> Cetak</Button
                >
                <Button
                    size="sm"
                    data-tour="preview-actions"
                    class="hidden md:inline-flex"
                    as="a"
                    :href="pdfUrl"
                    ><Download /> Unduh PDF</Button
                >
            </div>
        </div>

        <div
            v-if="snapshotAt"
            class="mb-3 flex items-start gap-2 rounded-lg border bg-muted/50 px-4 py-2 text-sm text-muted-foreground"
        >
            <Info class="mt-0.5 size-4 shrink-0" />
            Memakai data & layout saat finalisasi ({{
                formatDateTime(snapshotAt)
            }}). Untuk memakai layout terbaru, batalkan finalisasi lalu finalkan
            ulang.
        </div>
        <div
            v-if="classroom.status === 'draft'"
            class="mb-3 rounded-lg border border-status-incomplete-fg/30 bg-status-incomplete px-4 py-2 text-sm text-status-incomplete-fg"
        >
            Kelas belum final — rapor bertanda <strong>DRAFT</strong>.
        </div>

        <!-- Paper -->
        <div ref="box" class="relative w-full overflow-x-auto pb-28 md:pb-6">
            <div
                data-tour="preview-sheet"
                class="relative mx-auto origin-top-left overflow-hidden rounded-sm bg-white shadow-xl ring-1 ring-black/5"
                :style="{
                    width: `${pageW * scale}px`,
                    height: `${pageH * scale}px`,
                }"
            >
                <iframe
                    :key="htmlUrl"
                    :src="htmlUrl"
                    :title="`Rapor ${student.name}`"
                    class="absolute top-0 left-0 origin-top-left border-0"
                    :style="{
                        width: `${pageW}px`,
                        height: `${pageH}px`,
                        transform: `scale(${scale})`,
                    }"
                    @load="loading = false"
                />
                <div
                    v-if="loading"
                    class="absolute inset-0 flex items-center justify-center bg-white/70"
                >
                    <Spinner class="size-6" />
                </div>
            </div>
        </div>

        <!-- Phone sticky bar (PRD-08 §5.4) -->
        <div
            data-tour="preview-actions-mobile"
            class="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-30 grid grid-cols-3 gap-2 border-t bg-card p-2 md:hidden"
        >
            <Button size="sm" as="a" :href="pdfUrl"><Download /> PDF</Button>
            <Button
                size="sm"
                variant="outline"
                @click="printPdf(`${pdfUrl}&inline=1`, paper)"
                ><Printer /> Cetak</Button
            >
            <Button
                v-if="canShare"
                size="sm"
                variant="outline"
                :disabled="sharing"
                @click="share"
                ><Spinner v-if="sharing" /><Share2 v-else /> Bagikan</Button
            >
            <Button
                v-else
                size="sm"
                variant="outline"
                :disabled="!nextId"
                @click="go(nextId)"
                >Berikutnya <ChevronRight
            /></Button>
        </div>
        <PrintTipsDialog />
    </AppLayout>
</template>
