<script setup lang="ts">
import { useOnline } from '@vueuse/core';
import { WifiOff } from 'lucide-vue-next';

const online = useOnline();
</script>

<template>
    <Transition
        enter-active-class="transition duration-200"
        enter-from-class="-translate-y-2 opacity-0"
        leave-active-class="transition duration-200"
        leave-to-class="-translate-y-2 opacity-0"
    >
        <div
            v-if="!online"
            role="status"
            aria-live="polite"
            class="flex items-center gap-2 border-b border-tag-foreground/20 bg-tag px-4 py-2 text-sm font-medium text-tag-foreground"
        >
            <WifiOff class="size-4 shrink-0" />
            Anda sedang offline. Perubahan disimpan sementara dan dikirim
            otomatis saat koneksi kembali.
        </div>
    </Transition>
</template>
