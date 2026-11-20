<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import GuestLayout from '@/layouts/GuestLayout.vue';

/** /pin/recover — PRD-02 §8. */
const form = useForm({ code: '', pin: '', pin_confirmation: '' });

function submit(): void {
    form.post('/pin/recover', {
        onError: () => form.reset('pin', 'pin_confirmation'),
    });
}

function onlyDigits(field: 'pin' | 'pin_confirmation', e: Event): void {
    form[field] = (e.target as HTMLInputElement).value
        .replace(/\D/g, '')
        .slice(0, 4);
}
</script>

<template>
    <Head title="Lupa PIN" />
    <GuestLayout>
        <h1 class="mb-1 text-xl font-bold">Lupa PIN</h1>
        <p class="mb-5 text-sm text-muted-foreground">
            Masukkan kode pemulihan yang disimpan saat setup, lalu buat PIN
            baru.
        </p>

        <form class="grid gap-4" @submit.prevent="submit">
            <div class="grid gap-1.5">
                <Label for="code">Kode pemulihan</Label>
                <Input
                    id="code"
                    v-model="form.code"
                    placeholder="XXXX-XXXX-XXXX"
                    autocomplete="off"
                    autocapitalize="characters"
                    class="h-11 font-mono text-base tracking-widest uppercase"
                    :aria-invalid="!!form.errors.code"
                    autofocus
                />
                <InputError :message="form.errors.code" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="grid gap-1.5">
                    <Label for="pin">PIN baru</Label>
                    <Input
                        id="pin"
                        :model-value="form.pin"
                        type="password"
                        inputmode="numeric"
                        autocomplete="new-password"
                        maxlength="4"
                        class="h-11 text-center text-lg tracking-[0.5em]"
                        :aria-invalid="!!form.errors.pin"
                        @input="onlyDigits('pin', $event)"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="pin_confirmation">Ulangi PIN</Label>
                    <Input
                        id="pin_confirmation"
                        :model-value="form.pin_confirmation"
                        type="password"
                        inputmode="numeric"
                        autocomplete="new-password"
                        maxlength="4"
                        class="h-11 text-center text-lg tracking-[0.5em]"
                        @input="onlyDigits('pin_confirmation', $event)"
                    />
                </div>
            </div>
            <InputError :message="form.errors.pin" />

            <Button type="submit" class="h-11" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                {{ form.processing ? 'Memeriksa…' : 'Reset PIN' }}
            </Button>
        </form>

        <template #below>
            <Link
                href="/pin"
                class="inline-flex items-center gap-1 font-medium text-white hover:underline"
            >
                <ArrowLeft class="size-4" /> Kembali ke PIN
            </Link>
        </template>
    </GuestLayout>
</template>
