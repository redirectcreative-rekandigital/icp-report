<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import {
    ChevronLeft,
    ChevronRight,
    Download,
    History,
    Search,
    X,
} from 'lucide-vue-next';
import { computed, reactive } from 'vue';
import EmptyState from '@/components/app/EmptyState.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

type Log = {
    id: number;
    created_at: string;
    action: string;
    category: string;
    description: string;
    device: string | null;
    ip: string | null;
    session_short: string | null;
};

type Filters = {
    from: string | null;
    to: string | null;
    category: string | null;
    q: string | null;
};

const props = defineProps<{
    logs: Paginated<Log>;
    filters: Filters;
    categories: Record<string, string>;
}>();

const f = reactive({
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    category: props.filters.category ?? '',
    q: props.filters.q ?? '',
});

const query = computed(() =>
    Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '')),
);

watchDebounced(
    () => ({ ...f }),
    () =>
        router.get('/log', query.value, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }),
    { debounce: 300, deep: true },
);

const hasFilters = computed(() => Object.keys(query.value).length > 0);
const exportUrl = computed(
    () =>
        '/log/ekspor?' +
        new URLSearchParams(query.value as Record<string, string>).toString(),
);

function reset(): void {
    Object.assign(f, { from: '', to: '', category: '', q: '' });
}

const badge: Record<string, string> = {
    akses: 'bg-accent text-primary',
    nilai: 'bg-status-meeting text-status-meeting-fg',
    finalisasi: 'bg-status-above text-status-above-fg',
    data: 'bg-tag text-tag-foreground',
    pengaturan: 'bg-status-draft text-status-draft-fg',
};
</script>

<template>
    <Head title="Log Aktivitas" />
    <AppLayout :breadcrumbs="[{ title: 'Log Aktivitas', href: '/log' }]">
        <PageHeader
            title="Log Aktivitas"
            tour="log"
            description="Apa yang diubah, kapan, dan dari perangkat mana. Disimpan 1 tahun."
        >
            <Button
                variant="outline"
                as="a"
                data-tour="log-export"
                :href="exportUrl"
            >
                <Download /> Ekspor CSV
            </Button>
        </PageHeader>

        <Card data-tour="log-filters" class="mb-4 p-4">
            <div
                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_auto_auto_auto_auto] lg:items-end"
            >
                <div class="grid gap-1.5">
                    <Label for="q">Cari</Label>
                    <div class="relative">
                        <Search
                            class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            id="q"
                            v-model="f.q"
                            placeholder="Cari deskripsi…"
                            class="pl-9"
                        />
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label for="category">Kategori</Label>
                    <select
                        id="category"
                        v-model="f.category"
                        class="h-9 rounded-md border border-input bg-card px-3 text-sm focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        <option value="">Semua kategori</option>
                        <option
                            v-for="(label, key) in categories"
                            :key="key"
                            :value="key"
                        >
                            {{ label }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="from">Dari</Label>
                    <Input id="from" v-model="f.from" type="date" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="to">Sampai</Label>
                    <Input id="to" v-model="f.to" type="date" />
                </div>
                <Button v-if="hasFilters" variant="ghost" @click="reset"
                    ><X /> Hapus filter</Button
                >
            </div>
        </Card>

        <Card v-if="!logs.data.length">
            <EmptyState
                :icon="History"
                :title="
                    hasFilters
                        ? 'Tidak ada log yang cocok'
                        : 'Belum ada aktivitas'
                "
                :description="
                    hasFilters
                        ? 'Ubah atau hapus filter untuk melihat log lain.'
                        : undefined
                "
            >
                <Button v-if="hasFilters" variant="outline" @click="reset"
                    >Hapus filter</Button
                >
            </EmptyState>
        </Card>

        <template v-else>
            <!-- Desktop/tablet table -->
            <Card
                data-tour="log-table"
                class="hidden overflow-hidden p-0 md:block"
            >
                <Table>
                    <TableHeader>
                        <TableRow class="bg-muted/50">
                            <TableHead class="w-44">Waktu</TableHead>
                            <TableHead class="w-28">Kategori</TableHead>
                            <TableHead>Deskripsi</TableHead>
                            <TableHead class="w-40">Perangkat</TableHead>
                            <TableHead class="w-32">IP</TableHead>
                            <TableHead class="w-20">Sesi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="log in logs.data" :key="log.id">
                            <TableCell
                                class="text-sm whitespace-nowrap text-muted-foreground"
                                >{{ formatDateTime(log.created_at) }}</TableCell
                            >
                            <TableCell>
                                <span
                                    :class="
                                        cn(
                                            'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                            badge[log.category],
                                        )
                                    "
                                >
                                    {{
                                        categories[log.category] ?? log.category
                                    }}
                                </span>
                            </TableCell>
                            <TableCell class="text-sm whitespace-normal">{{
                                log.description
                            }}</TableCell>
                            <TableCell class="text-sm text-muted-foreground">{{
                                log.device ?? '–'
                            }}</TableCell>
                            <TableCell
                                class="font-mono text-xs text-muted-foreground"
                                >{{ log.ip ?? '–' }}</TableCell
                            >
                            <TableCell
                                class="font-mono text-xs text-muted-foreground"
                                >{{ log.session_short ?? '–' }}</TableCell
                            >
                        </TableRow>
                    </TableBody>
                </Table>
            </Card>

            <!-- Phone cards (PRD-08 §5.6) -->
            <ul data-tour="log-cards" class="grid gap-2 md:hidden">
                <li
                    v-for="log in logs.data"
                    :key="log.id"
                    class="rounded-xl border bg-card p-3"
                >
                    <div class="mb-1 flex items-center justify-between gap-2">
                        <span
                            :class="
                                cn(
                                    'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                    badge[log.category],
                                )
                            "
                        >
                            {{ categories[log.category] ?? log.category }}
                        </span>
                        <span class="text-xs text-muted-foreground">{{
                            formatDateTime(log.created_at)
                        }}</span>
                    </div>
                    <p class="text-sm">{{ log.description }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ log.device ?? '–' }} • {{ log.ip ?? '–' }}
                    </p>
                </li>
            </ul>

            <nav
                class="mt-4 flex items-center justify-between gap-2 text-sm"
                aria-label="Paginasi"
            >
                <span class="text-muted-foreground">
                    {{ logs.from }}–{{ logs.to }} dari
                    {{ logs.total.toLocaleString('id-ID') }}
                </span>
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="!logs.prev_page_url"
                        as-child
                    >
                        <Link :href="logs.prev_page_url ?? '#'" preserve-scroll
                            ><ChevronLeft /> Sebelumnya</Link
                        >
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="!logs.next_page_url"
                        as-child
                    >
                        <Link :href="logs.next_page_url ?? '#'" preserve-scroll
                            >Berikutnya <ChevronRight
                        /></Link>
                    </Button>
                </div>
            </nav>
        </template>
    </AppLayout>
</template>
