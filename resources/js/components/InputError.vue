<script setup lang="ts">
defineProps<{ message?: string | null | false }>();
</script>

<template>
    <p v-if="message" class="text-sm font-medium text-destructive" role="alert">
        {{ message }}
    </p>
</template>
