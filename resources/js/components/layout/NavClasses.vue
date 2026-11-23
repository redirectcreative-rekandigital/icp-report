<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { useStorage } from '@vueuse/core';
import { Check, ChevronRight, Lock, Plus, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { classesItem } from '@/lib/navigation';
import type { NavClass } from '@/types';

/** Menu "Kelas" with classes grouped per grade (PRD-08 §2). */
const page = usePage();
const { path, isActive } = useCurrentUrl();
const { state, isMobile } = useSidebar();

const classes = computed<NavClass[]>(() => page.props.nav?.classes ?? []);
const query = ref('');

const activeClassId = computed(() => {
    const m = path.value.match(/^\/kelas\/(\d+)/);

    return m ? Number(m[1]) : null;
});

const groups = computed(() => {
    const q = query.value.trim().toLowerCase();
    const filtered = q
        ? classes.value.filter((c) => c.name.toLowerCase().includes(q))
        : classes.value;

    return [3, 4, 5, 6]
        .map((grade) => ({
            grade,
            items: filtered
                .filter((c) => c.grade === grade)
                .sort((a, b) => a.name.localeCompare(b.name, 'id')),
        }))
        .filter((g) => g.items.length > 0);
});

const menuOpen = useStorage('nav.kelas.open', true);
const gradeOpen = useStorage<Record<string, boolean>>('nav.kelas.grades', {});

function isGradeOpen(grade: number): boolean {
    const active = classes.value.find((c) => c.id === activeClassId.value);

    if (active?.grade === grade) {
        return true;
    }

    return gradeOpen.value[grade] ?? false;
}

function setGradeOpen(grade: number, value: boolean): void {
    gradeOpen.value = { ...gradeOpen.value, [grade]: value };
}

function tooltip(c: NavClass): string {
    const status = c.status === 'final' ? 'Final' : 'Draft';

    return [
        c.name,
        c.homeroomTeacher,
        `${c.complete}/${c.total} lengkap`,
        status,
    ]
        .filter(Boolean)
        .join(' • ');
}

const collapsedIconMode = computed(
    () => state.value === 'collapsed' && !isMobile.value,
);
</script>

<template>
    <!-- Collapsed (72 px): plain icon link; flyout arrives with real class data. -->
    <SidebarMenuItem v-if="collapsedIconMode" data-tour="nav-kelas">
        <SidebarMenuButton
            as-child
            :is-active="isActive(classesItem.href, classesItem.prefix)"
            :tooltip="classesItem.title"
        >
            <Link :href="classesItem.href">
                <component :is="classesItem.icon" />
                <span>{{ classesItem.title }}</span>
            </Link>
        </SidebarMenuButton>
    </SidebarMenuItem>

    <Collapsible
        v-else
        v-model:open="menuOpen"
        as-child
        class="group/kelas"
        data-tour="nav-kelas"
    >
        <SidebarMenuItem>
            <CollapsibleTrigger as-child>
                <SidebarMenuButton
                    :is-active="isActive(classesItem.href, classesItem.prefix)"
                >
                    <component :is="classesItem.icon" />
                    <span>{{ classesItem.title }}</span>
                    <ChevronRight
                        class="ml-auto transition-transform duration-200 group-data-[state=open]/kelas:rotate-90"
                    />
                </SidebarMenuButton>
            </CollapsibleTrigger>
            <CollapsibleContent>
                <SidebarMenuSub class="mr-0 pr-0">
                    <SidebarMenuSubItem v-if="classes.length > 8" class="py-1">
                        <label class="relative block">
                            <Search
                                class="absolute top-1/2 left-2 size-3.5 -translate-y-1/2 text-sidebar-muted"
                            />
                            <input
                                v-model="query"
                                type="search"
                                placeholder="Cari kelas…"
                                class="h-8 w-full rounded-md border border-sidebar-border bg-sidebar-accent/40 pr-2 pl-7 text-sm text-sidebar-foreground placeholder:text-sidebar-muted focus:outline-2 focus:outline-sidebar-ring"
                            />
                        </label>
                    </SidebarMenuSubItem>

                    <SidebarMenuSubItem>
                        <SidebarMenuSubButton
                            as-child
                            :is-active="path === '/kelas'"
                        >
                            <Link href="/kelas">Semua kelas</Link>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>

                    <SidebarMenuSubItem
                        v-if="!classes.length"
                        class="px-2 py-1.5 text-xs text-sidebar-muted"
                    >
                        Belum ada kelas.
                        <Link
                            href="/periode"
                            class="mt-1 flex items-center gap-1 font-semibold text-sidebar-primary hover:underline"
                        >
                            <Plus class="size-3.5" /> Tambah kelas
                        </Link>
                    </SidebarMenuSubItem>

                    <Collapsible
                        v-for="group in groups"
                        :key="group.grade"
                        :open="query ? true : isGradeOpen(group.grade)"
                        as-child
                        class="group/grade"
                        @update:open="
                            (v: boolean) => setGradeOpen(group.grade, v)
                        "
                    >
                        <SidebarMenuSubItem>
                            <CollapsibleTrigger
                                class="flex w-full items-center justify-between rounded-md px-2 pt-2 pb-1 text-xs font-semibold tracking-wider text-sidebar-muted uppercase hover:text-sidebar-foreground"
                            >
                                Kelas {{ group.grade }}
                                <ChevronRight
                                    class="size-3.5 transition-transform duration-200 group-data-[state=open]/grade:rotate-90"
                                />
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <ul class="flex flex-col gap-0.5">
                                    <li v-for="c in group.items" :key="c.id">
                                        <SidebarMenuSubButton
                                            as-child
                                            :is-active="c.id === activeClassId"
                                            :title="tooltip(c)"
                                        >
                                            <Link
                                                :href="`/kelas/${c.id}`"
                                                :aria-current="
                                                    c.id === activeClassId
                                                        ? 'page'
                                                        : undefined
                                                "
                                                class="flex w-full items-center justify-between text-[15px]"
                                            >
                                                <span>{{ c.name }}</span>
                                                <span
                                                    v-if="c.status === 'final'"
                                                    class="flex items-center gap-1 text-xs text-sidebar-muted"
                                                >
                                                    <Lock class="size-3" />
                                                    Final
                                                </span>
                                                <span
                                                    v-else-if="
                                                        c.total > 0 &&
                                                        c.complete === c.total
                                                    "
                                                    class="flex items-center gap-1 text-xs text-sidebar-muted"
                                                >
                                                    <Check
                                                        class="size-3 text-sidebar-primary"
                                                    />
                                                    {{ c.complete }}/{{
                                                        c.total
                                                    }}
                                                </span>
                                                <span
                                                    v-else
                                                    class="text-xs text-sidebar-muted tabular-nums"
                                                >
                                                    {{ c.complete }}/{{
                                                        c.total
                                                    }}
                                                </span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </li>
                                </ul>
                            </CollapsibleContent>
                        </SidebarMenuSubItem>
                    </Collapsible>
                </SidebarMenuSub>
            </CollapsibleContent>
        </SidebarMenuItem>
    </Collapsible>
</template>
