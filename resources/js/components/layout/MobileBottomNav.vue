<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutDashboard, LayoutGrid, Menu, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import {
    Drawer,
    DrawerContent,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { secondaryItems } from '@/lib/navigation';
import { cn } from '@/lib/utils';

/** Phone bottom navigation, 64 px + safe area (PRD-08 §4.2). */
const page = usePage();
const { isActive } = useCurrentUrl();
const moreOpen = ref(false);

const moreItems = computed(() =>
    secondaryItems.filter(
        (i) =>
            i.href !== '/siswa' &&
            (i.href !== '/dokumen' || page.props.nav?.showDocuments !== false),
    ),
);
const moreActive = computed(() =>
    moreItems.value.some((i) => isActive(i.href, i.prefix)),
);

const tabs = computed(() => [
    {
        label: 'Beranda',
        href: '/',
        icon: LayoutDashboard,
        active: isActive('/'),
    },
    {
        label: 'Kelas',
        href: '/kelas',
        icon: LayoutGrid,
        active: isActive('/kelas', '/kelas'),
    },
    {
        label: 'Siswa',
        href: '/siswa',
        icon: Users,
        active: isActive('/siswa', '/siswa'),
    },
]);

const itemClass = (active: boolean) =>
    cn(
        'flex h-16 flex-1 flex-col items-center justify-center gap-1 text-xs transition-colors',
        active ? 'font-bold text-white' : 'text-sidebar-muted',
    );
const iconClass = (active: boolean) =>
    cn(
        'flex h-7 w-12 items-center justify-center rounded-full',
        active && 'bg-sidebar-accent text-sidebar-accent-icon',
    );
</script>

<template>
    <nav
        data-tour="nav-bottom"
        class="fixed inset-x-0 bottom-0 z-40 flex bg-sidebar pb-[env(safe-area-inset-bottom)] shadow-[0_-1px_0_var(--sidebar-border)] md:hidden"
        aria-label="Navigasi utama"
    >
        <Link
            v-for="t in tabs"
            :key="t.href"
            :href="t.href"
            :class="itemClass(t.active)"
            :aria-current="t.active ? 'page' : undefined"
        >
            <span :class="iconClass(t.active)"
                ><component :is="t.icon" class="size-5"
            /></span>
            {{ t.label }}
        </Link>
        <button
            type="button"
            :class="itemClass(moreActive)"
            @click="moreOpen = true"
        >
            <span :class="iconClass(moreActive)"><Menu class="size-5" /></span>
            Lainnya
        </button>
    </nav>

    <Drawer v-model:open="moreOpen">
        <DrawerContent class="pb-[calc(env(safe-area-inset-bottom)+1rem)]">
            <DrawerHeader class="text-left">
                <DrawerTitle>Menu lainnya</DrawerTitle>
            </DrawerHeader>
            <ul class="grid gap-1 px-3">
                <li v-for="item in moreItems" :key="item.href">
                    <Link
                        :href="item.href"
                        :class="
                            cn(
                                'flex min-h-12 items-center gap-3 rounded-lg px-3 text-[15px] font-medium',
                                isActive(item.href, item.prefix)
                                    ? 'bg-accent text-primary'
                                    : 'hover:bg-muted',
                            )
                        "
                        @click="moreOpen = false"
                    >
                        <component
                            :is="item.icon"
                            class="size-5 text-primary"
                        />
                        {{ item.title }}
                    </Link>
                </li>
            </ul>
        </DrawerContent>
    </Drawer>
</template>
