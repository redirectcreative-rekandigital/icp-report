<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- the Inertia form object is shared on purpose */
import type { InertiaForm } from '@inertiajs/vue3';
import NativeSelect from '@/components/app/NativeSelect.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Textarea } from '@/components/ui/textarea';

/** Student form fields (PRD-03 §4.2), shared by the list and the profile page. */
type StudentForm = {
    nipd: string;
    nisn: string;
    name: string;
    gender: '' | 'L' | 'P';
    classroom_id: string;
    notes: string;
};

defineProps<{
    form: InertiaForm<StudentForm>;
    classes: { id: number; name: string; final: boolean }[];
    classDisabled?: boolean;
}>();
</script>

<template>
    <div class="grid gap-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-1.5">
                <Label for="nipd"
                    >NIPD <span class="text-destructive">*</span></Label
                >
                <Input
                    id="nipd"
                    v-model="form.nipd"
                    maxlength="5"
                    inputmode="numeric"
                    autocomplete="off"
                    placeholder="maks. 5 digit"
                    class="font-mono"
                    :aria-invalid="!!form.errors.nipd"
                />
                <InputError :message="form.errors.nipd" />
            </div>
            <div class="grid gap-1.5">
                <Label for="nisn"
                    >NISN <span class="text-destructive">*</span></Label
                >
                <Input
                    id="nisn"
                    v-model="form.nisn"
                    maxlength="10"
                    inputmode="numeric"
                    autocomplete="off"
                    placeholder="maks. 10 digit"
                    class="font-mono"
                    :aria-invalid="!!form.errors.nisn"
                />
                <InputError :message="form.errors.nisn" />
            </div>
        </div>
        <div class="grid gap-1.5">
            <Label for="student-name"
                >Nama lengkap <span class="text-destructive">*</span></Label
            >
            <Input
                id="student-name"
                v-model="form.name"
                maxlength="100"
                autocomplete="off"
                :aria-invalid="!!form.errors.name"
            />
            <InputError :message="form.errors.name" />
        </div>
        <div class="grid gap-1.5">
            <Label>Jenis kelamin</Label>
            <RadioGroup v-model="form.gender" class="flex gap-5">
                <label class="flex cursor-pointer items-center gap-2 text-sm"
                    ><RadioGroupItem value="L" /> Laki-laki</label
                >
                <label class="flex cursor-pointer items-center gap-2 text-sm"
                    ><RadioGroupItem value="P" /> Perempuan</label
                >
                <label
                    class="flex cursor-pointer items-center gap-2 text-sm text-muted-foreground"
                    ><RadioGroupItem value="" /> Tidak diisi</label
                >
            </RadioGroup>
        </div>
        <div class="grid gap-1.5">
            <Label for="classroom_id">Kelas (periode aktif)</Label>
            <NativeSelect
                id="classroom_id"
                v-model="form.classroom_id"
                :disabled="classDisabled"
                :invalid="!!form.errors.classroom_id"
            >
                <option value="">Tanpa kelas</option>
                <option
                    v-for="c in classes"
                    :key="c.id"
                    :value="String(c.id)"
                    :disabled="c.final"
                >
                    {{ c.name }}{{ c.final ? ' (final)' : '' }}
                </option>
            </NativeSelect>
            <p v-if="classDisabled" class="text-xs text-muted-foreground">
                Kelas diatur dari periode aktif.
            </p>
            <InputError :message="form.errors.classroom_id" />
        </div>
        <div class="grid gap-1.5">
            <Label for="notes">Catatan</Label>
            <Textarea
                id="notes"
                v-model="form.notes"
                rows="3"
                maxlength="300"
            />
            <p class="text-right text-xs text-muted-foreground tabular-nums">
                {{ form.notes.length }}/300
            </p>
            <InputError :message="form.errors.notes" />
        </div>
    </div>
</template>
