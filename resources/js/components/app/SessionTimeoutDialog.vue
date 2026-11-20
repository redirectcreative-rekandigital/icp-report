<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ResponsiveDialog from '@/components/app/ResponsiveDialog.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useSessionTimeout } from '@/composables/useSessionTimeout';
import { formatCountdown } from '@/lib/format';

/** "Sesi akan berakhir dalam 2:00 — Tetap masuk?" (PRD-02 §5) */
const { remaining, warning, stayIn } = useSessionTimeout();
const busy = ref(false);
const open = computed({
    get: () => warning.value && remaining.value > 0,
    set: () => undefined,
});

async function keep(): Promise<void> {
    busy.value = true;

    try {
        await stayIn();
    } finally {
        busy.value = false;
    }
}

function lockNow(): void {
    router.post('/pin/lock');
}
</script>

<template>
    <ResponsiveDialog
        v-model:open="open"
        title="Sesi akan berakhir"
        :dismissible="false"
    >
        <p class="text-center text-sm text-muted-foreground" aria-live="polite">
            Tidak ada aktivitas. Sesi akan berakhir dalam
        </p>
        <p
            class="py-2 text-center text-4xl font-bold text-primary tabular-nums"
        >
            {{ formatCountdown(remaining) }}
        </p>
        <template #footer>
            <Button variant="outline" class="w-full sm:w-auto" @click="lockNow"
                >Kunci sekarang</Button
            >
            <Button class="w-full sm:w-auto" :disabled="busy" @click="keep">
                <Spinner v-if="busy" /> Tetap masuk
            </Button>
        </template>
    </ResponsiveDialog>
</template>
