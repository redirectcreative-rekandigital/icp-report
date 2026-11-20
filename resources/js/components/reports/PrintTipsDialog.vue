<script setup lang="ts">
import { Printer } from 'lucide-vue-next';
import { ref } from 'vue';
import ResponsiveDialog from '@/components/app/ResponsiveDialog.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { usePrintTips } from '@/composables/usePrintPdf';

/** Shown before the first print (PRD-05 §7.3). Mounted once per page that prints. */
const { tipsOpen, tipsPaper, confirm } = usePrintTips();
const dontShow = ref(true);
</script>

<template>
    <ResponsiveDialog
        :open="tipsOpen"
        title="Petunjuk cetak"
        @update:open="(v) => !v && (tipsOpen = false)"
    >
        <ul class="list-disc space-y-1.5 pl-5 text-sm">
            <li>
                Pilih ukuran kertas
                <strong>{{ tipsPaper === 'f4' ? 'F4 / Folio' : 'A4' }}</strong>
                di dialog cetak.
                <span v-if="tipsPaper === 'f4'" class="text-muted-foreground">
                    Bila printer tidak punya F4, tambahkan ukuran kustom 215 ×
                    330 mm atau gunakan A4.
                </span>
            </li>
            <li>
                Skala <strong>100% / Actual size</strong> (jangan "Fit to
                page").
            </li>
            <li>Matikan <strong>header & footer</strong> browser.</li>
        </ul>
        <label class="mt-4 flex items-center gap-2 text-sm">
            <Checkbox v-model="dontShow" /> Jangan tampilkan lagi
        </label>
        <template #footer>
            <Button @click="confirm(dontShow)"><Printer /> Lanjut cetak</Button>
        </template>
    </ResponsiveDialog>
</template>
