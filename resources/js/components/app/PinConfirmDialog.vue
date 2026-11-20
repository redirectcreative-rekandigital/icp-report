<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import PinPad from '@/components/app/PinPad.vue';
import ResponsiveDialog from '@/components/app/ResponsiveDialog.vue';
import { Button } from '@/components/ui/button';
import { usePinConfirm } from '@/composables/usePinConfirm';
import { ApiError, http } from '@/lib/http';

/** Single global PIN dialog for sensitive actions and locked sessions (PRD-02 §6, §11). */
const { state, settlePinDialog } = usePinConfirm();

const error = ref<string | null>(null);
const lockout = ref(0);
const processing = ref(false);
const success = ref(false);
const pad = ref<InstanceType<typeof PinPad> | null>(null);

watch(
    () => state.open,
    (open) => {
        if (open) {
            error.value = null;
            success.value = false;
            pad.value?.clear();
        }
    },
);

async function submit(pin: string): Promise<void> {
    processing.value = true;
    error.value = null;

    try {
        // A locked session re-verifies via POST /pin; otherwise confirm within the live session.
        await http.post(state.mode === 'session' ? '/pin' : '/pin/confirm', {
            pin,
        });
        success.value = true;
        setTimeout(() => {
            settlePinDialog(true);

            if (state.mode === 'session') {
                router.reload();
            }
        }, 350);
    } catch (e) {
        if (e instanceof ApiError) {
            if (e.status === 401 && state.mode === 'confirm') {
                // Session was locked meanwhile: switch to re-login mode.
                state.mode = 'session';
                state.title = 'Sesi telah dikunci';
            }

            error.value =
                e.body.errors?.pin?.[0] ??
                e.body.message ??
                'PIN tidak dapat diperiksa.';
            lockout.value = e.body.lockout_seconds ?? 0;
        } else {
            error.value = 'Terjadi kesalahan. Coba lagi.';
        }
    } finally {
        processing.value = false;
    }
}

function cancel(): void {
    settlePinDialog(false);
}

function onOpenChange(value: boolean): void {
    if (!value && state.open) {
        cancel();
    }
}
</script>

<template>
    <ResponsiveDialog
        :open="state.open"
        :title="state.title"
        :description="state.description"
        :dismissible="state.mode !== 'session'"
        @update:open="onOpenChange"
    >
        <div class="flex justify-center py-2">
            <PinPad
                ref="pad"
                compact
                :error="error"
                :lockout-seconds="lockout"
                :processing="processing"
                :success="success"
                :capture-keyboard="state.open"
                @submit="submit"
            />
        </div>
        <template #footer>
            <Button
                v-if="state.mode !== 'session'"
                variant="outline"
                class="w-full sm:w-auto"
                @click="cancel"
            >
                Batal
            </Button>
            <Button
                v-else
                variant="ghost"
                class="w-full sm:w-auto"
                as="a"
                href="/pin"
            >
                Ke halaman PIN
            </Button>
        </template>
    </ResponsiveDialog>
</template>
