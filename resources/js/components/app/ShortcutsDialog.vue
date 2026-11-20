<script setup lang="ts">
import { onKeyStroke } from '@vueuse/core';
import { RotateCcw } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import ResponsiveDialog from '@/components/app/ResponsiveDialog.vue';
import { Button } from '@/components/ui/button';
import { resetTours } from '@/composables/useTour';

/** "?" shows the keyboard shortcuts (PRD-07 §12). */
const open = ref(false);

onKeyStroke('?', (e) => {
    const t = e.target as HTMLElement;

    if (
        ['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName) ||
        t.isContentEditable
    ) {
        return;
    }

    e.preventDefault();
    open.value = !open.value;
});

function replayTours() {
    resetTours();
    open.value = false;
    toast.success('Tutorial akan tampil lagi otomatis di perangkat ini.');
}

const groups = [
    {
        title: 'Umum',
        items: [
            ['/', 'Fokus ke kolom pencarian'],
            ['Alt + L', 'Kunci layar'],
            ['Alt + S', 'Simpan form'],
            ['?', 'Tampilkan daftar pintasan'],
        ],
    },
    {
        title: 'Tabel nilai',
        items: [
            ['Enter / ↓', 'Simpan & pindah ke bawah'],
            ['Shift + Enter / ↑', 'Pindah ke atas'],
            ['Tab / Shift + Tab', 'Pindah kanan / kiri'],
            ['Esc', 'Batalkan perubahan sel'],
            ['Ctrl + V', 'Tempel blok nilai dari Excel'],
            [
                '[  /  ]',
                'Siswa sebelumnya / berikutnya (panel & pratinjau rapor)',
            ],
            ['Alt + ← / →', 'Kelas sebelumnya / berikutnya'],
        ],
    },
];
</script>

<template>
    <ResponsiveDialog v-model:open="open" title="Pintasan keyboard">
        <div class="grid gap-4">
            <section v-for="g in groups" :key="g.title">
                <h3
                    class="mb-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                >
                    {{ g.title }}
                </h3>
                <dl class="grid gap-1.5 text-sm">
                    <div
                        v-for="[k, v] in g.items"
                        :key="k"
                        class="flex items-center justify-between gap-3"
                    >
                        <dt>
                            <kbd
                                class="rounded border bg-muted px-1.5 py-0.5 font-mono text-xs"
                                >{{ k }}</kbd
                            >
                        </dt>
                        <dd class="text-right text-muted-foreground">
                            {{ v }}
                        </dd>
                    </div>
                </dl>
            </section>
            <Button
                variant="outline"
                size="sm"
                class="justify-self-start"
                @click="replayTours"
            >
                <RotateCcw class="size-4" /> Tampilkan ulang semua tutorial
            </Button>
        </div>
    </ResponsiveDialog>
</template>
