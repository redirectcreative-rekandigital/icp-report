<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { CalendarRange } from 'lucide-vue-next';
import { computed } from 'vue';
import NavClasses from '@/components/layout/NavClasses.vue';
import SchoolMark from '@/components/layout/SchoolMark.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboardItem, secondaryItems } from '@/lib/navigation';

const page = usePage();
const { isActive } = useCurrentUrl();

const items = computed(() =>
    secondaryItems.filter(
        (i) => i.href !== '/dokumen' || page.props.nav?.showDocuments !== false,
    ),
);
</script>

<template>
    <Sidebar collapsible="icon" class="border-r-0">
        <SidebarHeader class="pt-4">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="hover:bg-sidebar-accent"
                    >
                        <Link href="/">
                            <SchoolMark />
                            <div class="grid flex-1 text-left leading-tight">
                                <span
                                    class="truncate text-[15px] font-extrabold text-white"
                                    >ICP Report</span
                                >
                                <span
                                    class="truncate text-xs text-sidebar-muted"
                                    >{{ page.props.school.name }}</span
                                >
                            </div>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent data-tour="nav-sidebar">
            <SidebarGroup>
                <SidebarMenu class="gap-1">
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="isActive(dashboardItem.href)"
                            :tooltip="dashboardItem.title"
                        >
                            <Link :href="dashboardItem.href">
                                <component :is="dashboardItem.icon" />
                                <span>{{ dashboardItem.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <NavClasses />

                    <SidebarMenuItem v-for="item in items" :key="item.href">
                        <SidebarMenuButton
                            as-child
                            :is-active="isActive(item.href, item.prefix)"
                            :tooltip="item.title"
                        >
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter class="group-data-[collapsible=icon]:hidden">
            <Link
                href="/periode"
                class="block rounded-lg border border-sidebar-border bg-sidebar-accent/50 p-3 transition-colors hover:bg-sidebar-accent"
            >
                <p
                    class="flex items-center gap-1.5 text-xs font-semibold tracking-wider text-sidebar-muted uppercase"
                >
                    <CalendarRange class="size-3.5" /> Periode aktif
                </p>
                <p class="mt-1 text-sm font-semibold text-white">
                    {{ page.props.activeTerm?.label ?? 'Belum ada periode' }}
                </p>
            </Link>
        </SidebarFooter>
        <SidebarRail />
    </Sidebar>
</template>
