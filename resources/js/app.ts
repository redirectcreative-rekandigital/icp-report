import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import '../css/app.css';
import { reportClientError } from '@/lib/clientErrors';

const appName = import.meta.env.VITE_APP_NAME || 'ICP CEFR Report';

createInertiaApp({
    title: (title) => (title ? `${title} • ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });

        // Errors not caught by a layout's error boundary are still reported (PRD-07 §8.2).
        app.config.errorHandler = (error, instance) => {
            console.error(error);
            void reportClientError(error, instance?.$options.__name);
        };

        app.use(plugin).mount(el);
    },
    // Thin gold bar, shown only for visits slower than 250 ms (PRD-07 §6–7).
    progress: {
        color: '#E3A82B',
        delay: 250,
    },
});

window.addEventListener('unhandledrejection', (e) => {
    // Network failures are handled by the UI (offline banner, retries); report real bugs only.
    if (e.reason instanceof Error && !/fetch|network/i.test(e.reason.message)) {
        void reportClientError(e.reason, 'unhandledrejection');
    }
});
