<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CookieIcon, Info, WifiOff } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref } from 'vue';
import PinPad from '@/components/app/PinPad.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import GuestLayout from '@/layouts/GuestLayout.vue';

/** /pin — PRD-02 §4. */
const props = defineProps<{
    lockoutSeconds: number;
    status: string | null;
}>();

const form = useForm({ pin: '' });
const pad = ref<InstanceType<typeof PinPad> | null>(null);
const networkError = ref(false);
const cookiesBlocked = ref(false);

let offException: (() => void) | undefined;

onMounted(() => {
    cookiesBlocked.value = !navigator.cookieEnabled;
    // Offline / server unreachable: show the retry message instead of Inertia's error modal.
    offException = router.on('exception', (event) => {
        event.preventDefault();
        networkError.value = true;
        pad.value?.clear();
    });
});
onUnmounted(() => offException?.());

function submit(pin: string): void {
    networkError.value = false;
    form.pin = pin;
    form.post('/pin', {
        preserveScroll: true,
        onError: () => pad.value?.clear(),
    });
}

function retry(): void {
    networkError.value = false;
    router.reload();
}
</script>

<template>
    <Head title="Masukkan PIN" />
    <GuestLayout>
        <h1 class="mb-1 text-center text-xl font-bold">Masukkan PIN</h1>
        <p class="mb-5 text-center text-sm text-muted-foreground">
            PIN sekolah 4 digit
        </p>

        <Alert
            v-if="props.status"
            class="mb-4 border-status-incomplete-fg/30 bg-status-incomplete text-status-incomplete-fg"
        >
            <Info class="size-4" />
            <AlertDescription class="text-status-incomplete-fg">{{
                props.status
            }}</AlertDescription>
        </Alert>

        <Alert v-if="cookiesBlocked" variant="destructive" class="mb-4">
            <CookieIcon class="size-4" />
            <AlertDescription
                >Browser menolak cookie. Aktifkan cookie untuk situs
                ini.</AlertDescription
            >
        </Alert>

        <div
            v-if="networkError"
            class="mb-4 rounded-lg border border-destructive/30 bg-status-below p-3 text-center text-sm text-destructive"
        >
            <p class="flex items-center justify-center gap-1.5 font-medium">
                <WifiOff class="size-4" /> Tidak dapat terhubung. Periksa
                koneksi lalu coba lagi.
            </p>
            <Button size="sm" variant="outline" class="mt-2" @click="retry"
                >Coba lagi</Button
            >
        </div>

        <PinPad
            ref="pad"
            :error="form.errors.pin ?? null"
            :lockout-seconds="props.lockoutSeconds"
            :processing="form.processing"
            @submit="submit"
        />

        <template #below>
            <Link
                href="/pin/recover"
                class="font-medium text-white underline-offset-4 hover:underline"
                >Lupa PIN?</Link
            >
        </template>
    </GuestLayout>
</template>
