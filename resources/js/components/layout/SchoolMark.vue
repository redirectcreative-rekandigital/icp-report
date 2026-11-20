<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { GraduationCap } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

/** School logo, or a gold square with an icon when no logo is uploaded. */
withDefaults(defineProps<{ size?: 'sm' | 'md' | 'lg' }>(), { size: 'md' });

const page = usePage();
</script>

<template>
    <div
        :class="
            cn(
                'flex shrink-0 items-center justify-center overflow-hidden rounded-lg',
                size === 'sm' && 'size-8',
                size === 'md' && 'size-9',
                size === 'lg' && 'size-16 rounded-xl',
                page.props.school.logoUrl
                    ? 'bg-white'
                    : 'bg-gold text-gold-foreground',
            )
        "
    >
        <img
            v-if="page.props.school.logoUrl"
            :src="page.props.school.logoUrl"
            :alt="`Logo ${page.props.school.name}`"
            class="size-full object-contain p-0.5"
        />
        <GraduationCap v-else :class="size === 'lg' ? 'size-9' : 'size-5'" />
    </div>
</template>
