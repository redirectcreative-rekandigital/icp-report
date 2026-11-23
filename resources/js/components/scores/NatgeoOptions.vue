<script setup lang="ts">
import type { NatgeoGroups } from '@/lib/cefr';

/** <option>s for the National Geographic sub-level select: "Belum diisi", Pre-A1, then A1–B2 groups. */
defineProps<{ groups: NatgeoGroups }>();
</script>

<template>
    <option value="">Belum diisi</option>
    <template v-for="(subs, level) in groups" :key="level">
        <option v-if="subs.length === 1 && subs[0] === level" :value="level">
            {{ level }}
        </option>
        <optgroup v-else :label="level">
            <option v-for="s in subs" :key="s" :value="s">{{ s }}</option>
        </optgroup>
    </template>
</template>
