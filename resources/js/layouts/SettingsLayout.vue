<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import AppLayout from '@/layouts/AppLayout.vue';
import { cn } from '@/lib/utils';
import type { TourId } from '@/tours/types';

/** Pengaturan tabs (PRD-00 §5): Sekolah • Rapor • Penilaian • Deskriptor • Keamanan • Cadangan. */
const props = defineProps<{ title: string; tour?: TourId }>();
const { isActive } = useCurrentUrl();

const tabs: { label: string; href: string; soon?: string }[] = [
    { label: 'Sekolah', href: '/pengaturan/sekolah' },
    { label: 'Rapor', href: '/pengaturan/rapor' },
    { label: 'Penilaian', href: '/pengaturan/penilaian' },
    { label: 'Deskriptor', href: '/pengaturan/deskriptor' },
    { label: 'Keamanan', href: '/pengaturan/keamanan' },
    { label: 'Cadangan', href: '/pengaturan/cadangan' },
];
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Pengaturan', href: '/pengaturan/sekolah' },
            { title: props.title, href: '#' },
        ]"
        title="Pengaturan"
    >
        <PageHeader title="Pengaturan" :tour="props.tour" />

        <!-- Desktop: underline tabs; phone: horizontally scrollable chips (PRD-08 §5.6) -->
        <nav
            data-tour="settings-tabs"
            class="-mx-4 mb-6 overflow-x-auto px-4 md:mx-0 md:px-0"
            aria-label="Tab pengaturan"
        >
            <ul class="flex min-w-max gap-2 md:gap-1 md:border-b">
                <li v-for="t in tabs" :key="t.href">
                    <Link
                        :href="t.href"
                        :aria-current="isActive(t.href) ? 'page' : undefined"
                        :class="
                            cn(
                                'flex items-center gap-1.5 rounded-full border px-4 py-2 text-sm font-medium whitespace-nowrap transition-colors md:-mb-px md:rounded-none md:border-0 md:border-b-2 md:px-3',
                                isActive(t.href)
                                    ? 'border-primary bg-primary text-primary-foreground md:border-primary md:bg-transparent md:text-primary'
                                    : 'bg-card text-muted-foreground hover:text-foreground md:border-transparent md:bg-transparent',
                            )
                        "
                    >
                        {{ t.label }}
                        <span
                            v-if="t.soon"
                            class="rounded-full bg-tag px-1.5 text-[10px] leading-4 font-semibold text-tag-foreground"
                        >
                            {{ t.soon }}
                        </span>
                    </Link>
                </li>
            </ul>
        </nav>

        <slot />
    </AppLayout>
</template>
