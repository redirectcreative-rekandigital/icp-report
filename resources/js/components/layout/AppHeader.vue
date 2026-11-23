<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { useEventListener } from '@vueuse/core';
import { CalendarRange, Lock, Menu } from 'lucide-vue-next';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { SidebarTrigger, useSidebar } from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(
    defineProps<{ breadcrumbs?: BreadcrumbItem[]; title?: string }>(),
    {
        breadcrumbs: () => [],
        title: undefined,
    },
);

const page = usePage();
const { setOpenMobile } = useSidebar();

function lock(): void {
    router.post('/pin/lock');
}

// Alt + L locks the screen (PRD-02 §5).
useEventListener(window, 'keydown', (e: KeyboardEvent) => {
    if (e.altKey && !e.ctrlKey && !e.metaKey && e.key.toLowerCase() === 'l') {
        e.preventDefault();
        lock();
    }
});

const mobileTitle = () =>
    props.title ?? props.breadcrumbs.at(-1)?.title ?? 'ICP Report';
</script>

<template>
    <!-- Phone (< 768 px): navy top app bar, 56 px -->
    <header
        class="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-2 bg-sidebar px-2 pt-[env(safe-area-inset-top)] text-sidebar-foreground md:hidden"
    >
        <Button
            variant="ghost"
            size="icon"
            class="size-11 text-sidebar-foreground hover:bg-sidebar-accent hover:text-white"
            aria-label="Buka menu"
            @click="setOpenMobile(true)"
        >
            <Menu class="size-5" />
        </Button>
        <h1 class="min-w-0 flex-1 truncate text-base font-semibold text-white">
            {{ mobileTitle() }}
        </h1>
        <slot name="mobile-actions" />
        <span
            v-if="page.props.activeTerm"
            data-tour="header-term"
            class="rounded-full bg-sidebar-accent px-2.5 py-1 text-xs font-semibold text-white"
        >
            {{ page.props.activeTerm.short }}
        </span>
        <Button
            variant="ghost"
            size="icon"
            class="size-11 text-sidebar-foreground hover:bg-sidebar-accent hover:text-white"
            aria-label="Kunci layar"
            data-tour="header-lock"
            @click="lock"
        >
            <Lock class="size-5" />
        </Button>
    </header>

    <!-- Tablet & desktop -->
    <header
        class="sticky top-0 z-30 hidden h-16 shrink-0 items-center gap-3 border-b bg-card/95 px-4 backdrop-blur md:flex lg:px-6"
    >
        <SidebarTrigger class="-ml-1 size-9" aria-label="Buka/tutup menu" />
        <div class="min-w-0 flex-1">
            <Breadcrumbs
                v-if="breadcrumbs.length"
                :breadcrumbs="breadcrumbs"
                class="hidden lg:flex"
            />
            <p
                v-if="breadcrumbs.length"
                class="truncate font-semibold lg:hidden"
            >
                {{ mobileTitle() }}
            </p>
        </div>
        <slot name="actions" />
        <span
            data-tour="header-term"
            class="hidden items-center gap-1.5 rounded-full bg-kpi-icon px-3 py-1 text-sm font-semibold text-primary sm:flex"
        >
            <CalendarRange class="size-4" />
            {{ page.props.activeTerm?.label ?? 'Belum ada periode aktif' }}
        </span>
        <Tooltip>
            <TooltipTrigger as-child>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-9"
                    data-tour="header-lock"
                    @click="lock"
                >
                    <Lock class="size-4" />
                    <span class="hidden xl:inline">Kunci layar</span>
                </Button>
            </TooltipTrigger>
            <TooltipContent>Kunci layar (Alt + L)</TooltipContent>
        </Tooltip>
    </header>
</template>
