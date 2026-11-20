<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import ResponsiveDialog from '@/components/app/ResponsiveDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { withPin } from '@/composables/usePinConfirm';

/** "Batalkan Finalisasi" with a mandatory reason + PIN (PRD-04 §6.2). */
const props = defineProps<{ classroom: { id: number; name: string } }>();
const open = defineModel<boolean>('open', { required: true });
const form = useForm({ reason: '' });

watch(open, (v) => v && (form.reset(), form.clearErrors()));

function submit(): void {
    void withPin(
        () =>
            form.post(`/kelas/${props.classroom.id}/batalkan-final`, {
                onSuccess: () => (open.value = false),
            }),
        `Konfirmasi batalkan finalisasi ${props.classroom.name}`,
    );
}
</script>

<template>
    <ResponsiveDialog
        v-model:open="open"
        :title="`Batalkan finalisasi ${classroom.name}?`"
        description="Nilai akan dapat diedit kembali. Rapor yang sudah dicetak mungkin berbeda dengan rapor setelah koreksi."
    >
        <form
            id="unfinalize-form"
            class="grid gap-1.5"
            @submit.prevent="submit"
        >
            <Label for="reason"
                >Alasan <span class="text-destructive">*</span></Label
            >
            <Textarea
                id="reason"
                v-model="form.reason"
                rows="3"
                maxlength="300"
                placeholder="mis. Koreksi nilai Speaking Kevin"
                :aria-invalid="!!form.errors.reason"
            />
            <div class="flex justify-between text-xs">
                <InputError :message="form.errors.reason" />
                <span class="ml-auto text-muted-foreground tabular-nums"
                    >{{ form.reason.trim().length }}/300 (min. 10)</span
                >
            </div>
        </form>
        <template #footer>
            <Button variant="outline" @click="open = false">Batal</Button>
            <Button
                type="submit"
                form="unfinalize-form"
                variant="destructive"
                :disabled="form.reason.trim().length < 10 || form.processing"
            >
                <Spinner v-if="form.processing" /> Batalkan Finalisasi
            </Button>
        </template>
    </ResponsiveDialog>
</template>
