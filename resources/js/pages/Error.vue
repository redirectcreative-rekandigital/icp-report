<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useClipboard } from '@vueuse/core';
import { Ban, Copy, SearchX, ServerCrash, Wrench } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

/** Friendly 403/404/500/503 page (PRD-07 §8.2). Standalone: works without a session. */
const props = defineProps<{ status: number; errorRef: string }>();
function goBack(): void {
    window.history.back();
}
const { copy, copied } = useClipboard({ copiedDuring: 2000 });

const content = computed(
    () =>
        ({
            403: {
                icon: Ban,
                title: 'Akses ditolak',
                text: 'Anda tidak diizinkan membuka halaman ini.',
            },
            404: {
                icon: SearchX,
                title: 'Data tidak ditemukan',
                text: 'Halaman atau data yang dicari tidak ada atau sudah dihapus.',
            },
            500: {
                icon: ServerCrash,
                title: 'Terjadi kesalahan di server',
                text: 'Coba lagi beberapa saat. Bila berulang, laporkan kode di bawah.',
            },
            503: {
                icon: Wrench,
                title: 'Sedang pemeliharaan',
                text: 'Aplikasi sedang diperbarui. Silakan kembali beberapa menit lagi.',
            },
        })[props.status] ?? {
            icon: ServerCrash,
            title: 'Terjadi kesalahan',
            text: 'Silakan coba lagi.',
        },
);
</script>

<template>
    <Head :title="content.title" />
    <div class="flex min-h-dvh items-center justify-center bg-background px-4">
        <div class="max-w-md text-center">
            <div
                class="mx-auto mb-5 flex size-20 items-center justify-center rounded-full bg-kpi-icon text-primary"
            >
                <component :is="content.icon" class="size-10" />
            </div>
            <p class="text-sm font-semibold text-muted-foreground">
                Kode {{ status }}
            </p>
            <h1 class="mt-1 text-2xl font-bold">{{ content.title }}</h1>
            <p class="mt-2 text-muted-foreground">{{ content.text }}</p>
            <button
                v-if="status >= 500"
                type="button"
                class="mx-auto mt-4 flex items-center gap-2 rounded-md bg-muted px-3 py-1.5 font-mono text-sm"
                @click="copy(props.errorRef)"
            >
                {{ props.errorRef }} <Copy class="size-3.5" />
                <span class="font-sans text-xs text-muted-foreground">{{
                    copied ? 'tersalin' : 'salin'
                }}</span>
            </button>
            <div class="mt-6 flex justify-center gap-2">
                <Button variant="outline" @click="goBack">Kembali</Button>
                <Button as="a" href="/">Ke Dashboard</Button>
            </div>
        </div>
    </div>
</template>
