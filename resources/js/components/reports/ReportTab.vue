<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useStorage } from '@vueuse/core';
import {
    ChevronDown,
    Download,
    Eye,
    FileArchive,
    FileText,
    Info,
    Printer,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import LevelBadge from '@/components/app/LevelBadge.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import BatchProgressDialog from '@/components/reports/BatchProgressDialog.vue';
import PrintTipsDialog from '@/components/reports/PrintTipsDialog.vue';
import type { BatchState, ReportStudent } from '@/components/reports/types';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { printPdf } from '@/composables/usePrintPdf';
import { formatScore } from '@/lib/cefr';
import { cn } from '@/lib/utils';

/** Tab "Rapor & Cetak" (PRD-05 §3, PRD-08 §5.4). */
const props = defineProps<{
    classroom: { id: number; name: string; status: 'draft' | 'final' };
    students: ReportStudent[];
    paperDefault: 'f4' | 'a4';
    runningBatch: BatchState | null;
}>();

// Last choice remembered per device; school default otherwise (PRD-05 §3.1).
const paper = useStorage<'f4' | 'a4'>('report.paper', props.paperDefault);
const selected = ref<number[]>([]);
const printable = computed(() => props.students.filter((s) => s.printable));
const skipped = computed(() => props.students.length - printable.value.length);
const allChecked = computed(
    () =>
        printable.value.length > 0 &&
        printable.value.every((s) => selected.value.includes(s.enrollment_id)),
);

function toggle(id: number, on: boolean): void {
    selected.value = on
        ? [...new Set([...selected.value, id])]
        : selected.value.filter((x) => x !== id);
}

const pdfUrl = (id: number, inline = false) =>
    `/kelas/${props.classroom.id}/rapor/${id}/pdf?paper=${paper.value}${inline ? '&inline=1' : ''}`;

const batchOpen = ref(false);
const batchRequest = ref<{
    paper: 'f4' | 'a4';
    mode: 'merged' | 'zip';
    ids: number[] | null;
    print: boolean;
} | null>(null);
const resume = ref<BatchState | null>(null);
const resumeBanner = ref(props.runningBatch);

function runBatch(mode: 'merged' | 'zip', print: boolean): void {
    resume.value = null;
    batchRequest.value = {
        paper: paper.value,
        mode,
        ids: selected.value.length ? selected.value : null,
        print,
    };
    batchOpen.value = true;
}

function continueBatch(): void {
    resume.value = resumeBanner.value;
    batchRequest.value = null;
    resumeBanner.value = null;
    batchOpen.value = true;
}
</script>

<template>
    <!-- Resume an unfinished batch (PRD-05 §7.2) -->
    <div
        v-if="resumeBanner"
        class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-gold/50 bg-tag px-4 py-3 text-sm text-tag-foreground"
    >
        <Info class="size-4" />
        Ada proses yang belum selesai ({{ resumeBanner.done }}/{{
            resumeBanner.total
        }}).
        <span class="ml-auto flex gap-2">
            <Button size="sm" @click="continueBatch">Lanjutkan</Button>
            <Button size="sm" variant="ghost" @click="resumeBanner = null"
                >Abaikan</Button
            >
        </span>
    </div>

    <!-- Action bar -->
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <div
            data-tour="paper-select"
            class="flex items-center rounded-lg border bg-card p-0.5"
            role="radiogroup"
            aria-label="Ukuran kertas"
        >
            <button
                v-for="p in ['f4', 'a4'] as const"
                :key="p"
                type="button"
                role="radio"
                :aria-checked="paper === p"
                :class="
                    cn(
                        'rounded-md px-3 py-1.5 text-sm font-semibold',
                        paper === p
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground',
                    )
                "
                @click="paper = p"
            >
                {{ p.toUpperCase() }}
            </button>
        </div>
        <div class="ml-auto flex flex-wrap gap-2">
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button
                        variant="outline"
                        data-tour="report-download-all"
                        :disabled="!printable.length"
                    >
                        <Download />
                        {{
                            selected.length
                                ? `Unduh terpilih (${selected.length})`
                                : 'Unduh semua PDF'
                        }}
                        <ChevronDown />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem @click="runBatch('merged', false)"
                        ><FileText /> Satu file PDF</DropdownMenuItem
                    >
                    <DropdownMenuItem @click="runBatch('zip', false)"
                        ><FileArchive /> ZIP (satu PDF per
                        siswa)</DropdownMenuItem
                    >
                </DropdownMenuContent>
            </DropdownMenu>
            <Button
                data-tour="report-print-all"
                :disabled="!printable.length"
                @click="runBatch('merged', true)"
            >
                <Printer />
                {{
                    selected.length
                        ? `Cetak terpilih (${selected.length})`
                        : 'Cetak semua'
                }}
            </Button>
        </div>
    </div>

    <p v-if="skipped" class="mb-3 text-sm text-muted-foreground">
        {{ skipped }} siswa belum lengkap/nonaktif tidak ikut di "semua siswa".
    </p>

    <!-- Desktop/tablet table -->
    <Card class="hidden overflow-hidden p-0 md:block">
        <table class="w-full text-sm">
            <thead
                class="bg-muted/60 text-xs font-semibold text-muted-foreground"
            >
                <tr>
                    <th
                        data-tour="report-select"
                        class="w-10 px-3 py-2 text-left"
                    >
                        <Checkbox
                            :model-value="allChecked"
                            aria-label="Pilih semua"
                            @update:model-value="
                                (v) =>
                                    (selected = v
                                        ? printable.map((s) => s.enrollment_id)
                                        : [])
                            "
                        />
                    </th>
                    <th class="w-12 px-2 py-2 text-left">No</th>
                    <th class="px-2 py-2 text-left">Nama</th>
                    <th class="w-28 px-2 py-2">Level</th>
                    <th class="w-24 px-2 py-2">Total</th>
                    <th class="w-40 px-2 py-2 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="s in students"
                    :key="s.enrollment_id"
                    class="border-t"
                    :class="!s.printable && 'text-muted-foreground'"
                >
                    <td class="px-3 py-2">
                        <Checkbox
                            :disabled="!s.printable"
                            :model-value="selected.includes(s.enrollment_id)"
                            :aria-label="`Pilih ${s.name}`"
                            @update:model-value="
                                (v) => toggle(s.enrollment_id, !!v)
                            "
                        />
                    </td>
                    <td class="px-2 py-2 tabular-nums">{{ s.roll_no }}</td>
                    <td class="px-2 py-2 font-medium">
                        {{ s.name
                        }}<span v-if="!s.is_active" class="ml-1 text-xs"
                            >(nonaktif)</span
                        >
                    </td>
                    <td class="px-2 py-2 text-center">
                        <LevelBadge
                            :level="s.printable ? s.overall_level : null"
                        />
                    </td>
                    <td class="px-2 py-2 text-center">
                        <span v-if="s.printable" class="tabular-nums">{{
                            formatScore(s.total)
                        }}</span>
                        <StatusBadge v-else status="incomplete" size="sm" />
                    </td>
                    <td class="px-2 py-2">
                        <div
                            v-if="s.printable"
                            data-tour="report-actions"
                            class="flex justify-end gap-1"
                        >
                            <Button
                                variant="ghost"
                                size="icon"
                                as-child
                                :aria-label="`Pratinjau ${s.name}`"
                            >
                                <Link
                                    :href="`/kelas/${classroom.id}/rapor/${s.enrollment_id}`"
                                    ><Eye
                                /></Link>
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                as="a"
                                :href="pdfUrl(s.enrollment_id)"
                                :aria-label="`Unduh PDF ${s.name}`"
                                ><Download
                            /></Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                :aria-label="`Cetak ${s.name}`"
                                @click="
                                    printPdf(
                                        pdfUrl(s.enrollment_id, true),
                                        paper,
                                    )
                                "
                                ><Printer
                            /></Button>
                        </div>
                        <Tooltip v-else>
                            <TooltipTrigger as-child
                                ><span class="block text-right text-xs"
                                    >Nilai belum lengkap</span
                                ></TooltipTrigger
                            >
                            <TooltipContent
                                >Lengkapi keempat nilai di tab Input
                                Nilai.</TooltipContent
                            >
                        </Tooltip>
                    </td>
                </tr>
            </tbody>
        </table>
    </Card>

    <!-- Phone cards -->
    <ul class="grid gap-2 md:hidden">
        <li
            data-tour="report-row"
            v-for="s in students"
            :key="s.enrollment_id"
            class="rounded-xl border bg-card p-3"
            :class="!s.printable && 'opacity-60'"
        >
            <div class="flex items-center gap-2">
                <Checkbox
                    :disabled="!s.printable"
                    :model-value="selected.includes(s.enrollment_id)"
                    :aria-label="`Pilih ${s.name}`"
                    @update:model-value="(v) => toggle(s.enrollment_id, !!v)"
                />
                <span class="min-w-0 flex-1 truncate font-semibold">{{
                    s.name
                }}</span>
                <LevelBadge v-if="s.printable" :level="s.overall_level" />
                <StatusBadge v-else status="incomplete" size="sm" />
            </div>
            <div v-if="s.printable" class="mt-2 grid grid-cols-3 gap-2">
                <Button size="sm" variant="outline" as-child
                    ><Link
                        :href="`/kelas/${classroom.id}/rapor/${s.enrollment_id}`"
                        ><Eye /> Lihat</Link
                    ></Button
                >
                <Button
                    size="sm"
                    variant="outline"
                    as="a"
                    :href="pdfUrl(s.enrollment_id)"
                    ><Download /> PDF</Button
                >
                <Button
                    size="sm"
                    variant="outline"
                    @click="printPdf(pdfUrl(s.enrollment_id, true), paper)"
                    ><Printer /> Cetak</Button
                >
            </div>
        </li>
    </ul>

    <BatchProgressDialog
        v-model:open="batchOpen"
        :classroom-id="classroom.id"
        :request="batchRequest"
        :resume="resume"
        @finished="selected = []"
    />
    <PrintTipsDialog />
</template>
