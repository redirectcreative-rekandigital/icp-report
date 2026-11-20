<script setup lang="ts">
import { Download, Table2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

/**
 * Frame for a chart: title, "Lihat tabel" toggle (accessible table view) and "Unduh PNG".
 * The chart slot receives a ref setter so this card can export the canvas.
 */
const props = defineProps<{
    title: string;
    description?: string;
    loading?: boolean;
    empty?: boolean;
    filename?: string;
}>();

const showTable = ref(false);
const canvasHost = ref<HTMLElement | null>(null);

function downloadPng(): void {
    const canvas = canvasHost.value?.querySelector('canvas');

    if (!canvas) {
        return;
    }

    // Draw on white so the PNG is readable outside the app.
    const out = document.createElement('canvas');
    out.width = canvas.width;
    out.height = canvas.height;
    const ctx = out.getContext('2d')!;
    ctx.fillStyle = '#FFFFFF';
    ctx.fillRect(0, 0, out.width, out.height);
    ctx.drawImage(canvas, 0, 0);

    const a = document.createElement('a');
    a.href = out.toDataURL('image/png');
    a.download = `${props.filename ?? props.title}.png`;
    a.click();
}
</script>

<template>
    <Card class="min-w-0">
        <CardHeader
            class="flex flex-row items-start justify-between gap-2 space-y-0"
        >
            <div class="min-w-0">
                <CardTitle class="text-base">{{ title }}</CardTitle>
                <CardDescription v-if="description">{{
                    description
                }}</CardDescription>
            </div>
            <div v-if="!loading && !empty" class="flex shrink-0 gap-1">
                <Button
                    variant="ghost"
                    size="icon"
                    :aria-pressed="showTable"
                    :title="showTable ? 'Lihat grafik' : 'Lihat tabel'"
                    @click="showTable = !showTable"
                >
                    <Table2 />
                </Button>
                <Button
                    v-if="!showTable"
                    variant="ghost"
                    size="icon"
                    title="Unduh PNG"
                    @click="downloadPng"
                    ><Download
                /></Button>
            </div>
        </CardHeader>
        <CardContent>
            <div v-if="loading" class="space-y-2">
                <Skeleton class="h-48 w-full" />
            </div>
            <p
                v-else-if="empty"
                class="py-10 text-center text-sm text-muted-foreground"
            >
                Belum ada nilai di periode ini — mulai dari menu Kelas.
            </p>
            <div v-else-if="showTable" class="max-h-80 overflow-auto">
                <slot name="table" />
            </div>
            <div v-else ref="canvasHost"><slot /></div>
        </CardContent>
    </Card>
</template>
