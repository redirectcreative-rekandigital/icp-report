import { router, usePage } from '@inertiajs/vue3';
import { useEventListener, useIntervalFn } from '@vueuse/core';
import { computed, onUnmounted, ref } from 'vue';
import { http } from '@/lib/http';

/**
 * Client mirror of the server idle timeout (PRD-02 §5): warn 2 minutes before expiry.
 * Any Inertia visit or JSON call resets the timer (the server touches the session too).
 */
const WARN_SECONDS = 120;
const lastActivity = ref(Date.now());

export function markActivity(): void {
    lastActivity.value = Date.now();
}

export function useSessionTimeout() {
    const page = usePage();
    const idleSeconds = computed(
        () =>
            ((page.props.session as { idleMinutes?: number } | null)
                ?.idleMinutes ?? 60) * 60,
    );
    const remaining = ref(idleSeconds.value);
    const warning = computed(() => remaining.value <= WARN_SECONDS);

    onUnmounted(router.on('finish', markActivity));
    useEventListener(window, 'app:activity', markActivity);

    useIntervalFn(() => {
        remaining.value = Math.max(
            0,
            idleSeconds.value -
                Math.floor((Date.now() - lastActivity.value) / 1000),
        );

        if (remaining.value === 0) {
            // Server has expired the session too; this lands on /pin with a message.
            router.visit(window.location.href, { preserveState: false });
            markActivity();
        }
    }, 1000);

    async function stayIn(): Promise<void> {
        await http.post('/ping');
        markActivity();
        remaining.value = idleSeconds.value;
    }

    return { remaining, warning, stayIn };
}
