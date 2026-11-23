<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarPlus, Copy, Plus, School } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ClassCard from '@/components/app/ClassCard.vue';
import EmptyState from '@/components/app/EmptyState.vue';
import NativeSelect from '@/components/app/NativeSelect.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

type ClassRow = {
    id: number;
    grade: number;
    name: string;
    homeroom_teacher: string | null;
    status: 'draft' | 'final';
    total: number;
    complete: number;
};

const props = defineProps<{
    term: { id: number; label: string; is_active: boolean } | null;
    terms: { id: number; label: string; is_active: boolean }[];
    classes: ClassRow[];
}>();

const selected = ref(props.term ? String(props.term.id) : '');
watch(selected, (id) =>
    router.get('/kelas', { term: id }, { preserveScroll: true }),
);

const groups = computed(() =>
    [3, 4, 5, 6]
        .map((g) => ({
            grade: g,
            items: props.classes.filter((c) => c.grade === g),
        }))
        .filter((g) => g.items.length),
);
</script>

<template>
    <Head title="Kelas" />
    <AppLayout :breadcrumbs="[{ title: 'Kelas', href: '/kelas' }]">
        <PageHeader
            title="Kelas"
            tour="kelas-index"
            :description="
                term
                    ? term.label +
                      (term.is_active ? '' : ' (bukan periode aktif)')
                    : undefined
            "
        >
            <NativeSelect
                v-if="terms.length > 1"
                v-model="selected"
                class="w-64"
                aria-label="Periode"
                data-tour="kelas-term"
            >
                <option v-for="t in terms" :key="t.id" :value="String(t.id)">
                    {{ t.label }}{{ t.is_active ? ' (aktif)' : '' }}
                </option>
            </NativeSelect>
        </PageHeader>

        <Card v-if="!term">
            <EmptyState
                :icon="CalendarPlus"
                title="Belum ada periode aktif"
                description="Buat tahun ajaran dan semester terlebih dahulu."
            >
                <Button as-child
                    ><Link href="/periode"
                        ><CalendarPlus /> Buat periode</Link
                    ></Button
                >
            </EmptyState>
        </Card>

        <Card v-else-if="!classes.length">
            <EmptyState
                :icon="School"
                title="Belum ada kelas di periode ini"
                description="Tambahkan kelas atau salin dari periode lalu."
            >
                <Button as-child
                    ><Link :href="`/periode/${term.id}`"
                        ><Plus /> Tambah kelas</Link
                    ></Button
                >
                <Button variant="outline" as-child
                    ><Link :href="`/periode/${term.id}`"
                        ><Copy /> Salin dari periode lalu</Link
                    ></Button
                >
            </EmptyState>
        </Card>

        <section v-for="g in groups" :key="g.grade" class="mb-8">
            <h2
                class="mb-3 text-sm font-bold tracking-wider text-muted-foreground uppercase"
            >
                Kelas {{ g.grade }}
                <span class="font-medium normal-case"
                    >• {{ g.items.length }} kelas</span
                >
            </h2>
            <div
                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4"
            >
                <ClassCard
                    v-for="c in g.items"
                    :id="c.id"
                    :key="c.id"
                    :name="c.name"
                    :homeroom-teacher="c.homeroom_teacher"
                    :status="c.status"
                    :complete="c.complete"
                    :total="c.total"
                />
            </div>
        </section>
    </AppLayout>
</template>
