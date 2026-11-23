<script setup lang="ts">
import { MessageSquare } from 'lucide-vue-next';

/** One-line comment preview (grid + phone cards): the teacher's text, or the automatic one greyed. */
defineProps<{ comment: string | null; auto: string | null }>();
</script>

<template>
    <span class="flex min-w-0 items-center gap-1.5">
        <MessageSquare
            class="size-3.5 shrink-0"
            :class="comment ? 'text-primary' : 'text-muted-foreground'"
            aria-hidden="true"
        />
        <span v-if="comment" class="truncate">{{ comment }}</span>
        <span
            v-else-if="auto"
            class="truncate text-muted-foreground italic"
            :title="'Komentar otomatis'"
            >{{ auto }}</span
        >
        <span v-else class="truncate text-xs text-muted-foreground"
            >Komentar otomatis muncul setelah nilai lengkap</span
        >
    </span>
</template>
