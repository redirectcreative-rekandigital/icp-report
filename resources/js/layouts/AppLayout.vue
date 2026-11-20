<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { onErrorCaptured, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import ErrorState from '@/components/app/ErrorState.vue';
import OfflineBanner from '@/components/app/OfflineBanner.vue';
import PinConfirmDialog from '@/components/app/PinConfirmDialog.vue';
import SessionTimeoutDialog from '@/components/app/SessionTimeoutDialog.vue';
import ShortcutsDialog from '@/components/app/ShortcutsDialog.vue';
import AppHeader from '@/components/layout/AppHeader.vue';
import AppSidebar from '@/components/layout/AppSidebar.vue';
import MobileBottomNav from '@/components/layout/MobileBottomNav.vue';
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import { Toaster } from '@/components/ui/sonner';
import { requestSessionPin } from '@/composables/usePinConfirm';
import { reportClientError } from '@/lib/clientErrors';
import { onSessionLost } from '@/lib/http';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{ breadcrumbs?: BreadcrumbItem[]; title?: string }>(),
    {
        breadcrumbs: () => [],
        title: undefined,
    },
);

const page = usePage();

// Flash messages → toasts (success 3 s, errors stay until closed: PRD-07 §8.3).
watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) {
            toast.success(flash.success, { duration: 3000 });
        }

        if (flash?.error) {
            toast.error(flash.error, { duration: Infinity, closeButton: true });
        }
    },
    { immediate: true, deep: true },
);

// A JSON call hit 401 → ask for the PIN in place.
let off: (() => void) | undefined;
onMounted(() => {
    off = onSessionLost(() => void requestSessionPin());
});
onUnmounted(() => off?.());

// Error boundary for the page content (PRD-07 §8.2): show ErrorState instead of a blank page.
const crashed = ref(false);
const crashRef = ref<string | null>(null);
onErrorCaptured((error, instance) => {
    crashed.value = true;
    void reportClientError(
        error,
        instance?.$options.name ?? instance?.$options.__name,
    ).then((r) => (crashRef.value = r));

    return false; // handled: do not propagate
});
watch(
    () => page.url,
    () => {
        crashed.value = false;
        crashRef.value = null;
    },
);

function reload(): void {
    window.location.reload();
}
</script>

<template>
    <SidebarProvider :default-open="page.props.sidebarOpen">
        <AppSidebar />
        <SidebarInset class="min-w-0 bg-background">
            <AppHeader :breadcrumbs="breadcrumbs" :title="title">
                <template #actions><slot name="header-actions" /></template>
                <template #mobile-actions
                    ><slot name="mobile-actions"
                /></template>
            </AppHeader>
            <OfflineBanner />
            <main
                :key="page.url"
                class="mx-auto w-full max-w-[1440px] flex-1 animate-page-in px-4 pt-4 pb-24 sm:pt-6 md:px-6 md:pb-8 xl:px-8"
            >
                <ErrorState
                    v-if="crashed"
                    :error-ref="crashRef"
                    @retry="reload"
                />
                <slot v-else />
            </main>
        </SidebarInset>
        <MobileBottomNav />
    </SidebarProvider>

    <PinConfirmDialog />
    <SessionTimeoutDialog />
    <ShortcutsDialog />
    <Toaster position="top-center" rich-colors />
</template>
