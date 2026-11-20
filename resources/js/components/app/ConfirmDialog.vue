<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/** Confirmation with a specific action label, e.g. "Hapus kelas 5A" (PRD-07 §8.3). */
defineProps<{
    title: string;
    description?: string;
    confirmLabel: string;
    destructive?: boolean;
    processing?: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ confirm: [] }>();
</script>

<template>
    <AlertDialog v-model:open="open">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ title }}</AlertDialogTitle>
                <AlertDialogDescription v-if="description">{{
                    description
                }}</AlertDialogDescription>
            </AlertDialogHeader>
            <slot />
            <AlertDialogFooter>
                <AlertDialogCancel :disabled="processing"
                    >Batal</AlertDialogCancel
                >
                <Button
                    :variant="destructive ? 'destructive' : 'default'"
                    :disabled="processing"
                    @click="emit('confirm')"
                >
                    <Spinner v-if="processing" />
                    {{ confirmLabel }}
                </Button>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
