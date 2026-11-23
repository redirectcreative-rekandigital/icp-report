<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import SchoolMark from '@/components/layout/SchoolMark.vue';
import { Toaster } from '@/components/ui/sonner';
import { cn } from '@/lib/utils';

/** Centered card on navy for /pin, /setup, recovery pages. */
withDefaults(defineProps<{ wide?: boolean }>(), { wide: false });

const page = usePage();
</script>

<template>
    <div
        class="flex min-h-dvh flex-col items-center justify-center bg-sidebar px-4 py-8 pt-[max(2rem,env(safe-area-inset-top))]"
    >
        <div
            class="pointer-events-none fixed inset-x-0 top-0 h-1.5 bg-gold"
            aria-hidden="true"
        />
        <div
            :class="
                cn('w-full animate-page-in', wide ? 'max-w-xl' : 'max-w-sm')
            "
        >
            <div class="mb-6 flex flex-col items-center gap-3 text-center">
                <SchoolMark size="lg" />
                <div>
                    <p class="text-lg font-bold text-white">
                        {{ page.props.school.name }}
                    </p>
                    <p class="text-sm text-sidebar-muted">
                        ICP Report • {{ page.props.school.program }}
                    </p>
                </div>
            </div>
            <div class="rounded-xl bg-card p-6 shadow-xl sm:p-8">
                <slot />
            </div>
            <div class="mt-4 text-center text-sm text-sidebar-muted">
                <slot name="below" />
            </div>
        </div>
    </div>
    <Toaster position="top-center" rich-colors />
</template>
