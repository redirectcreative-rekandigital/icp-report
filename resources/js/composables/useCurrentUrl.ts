import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/** Active-link helper for menus. */
export function useCurrentUrl() {
    const page = usePage();
    const path = computed(() => page.url.split('?')[0]);

    function isActive(href: string, prefix?: string): boolean {
        if (prefix) {
            return path.value === prefix || path.value.startsWith(prefix + '/');
        }

        return path.value === href;
    }

    return { path, isActive };
}
