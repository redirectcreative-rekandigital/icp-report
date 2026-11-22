import { router } from '@inertiajs/vue3';
import { driver } from 'driver.js';
import type { DriveStep, Driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import { onBeforeUnmount, onMounted, watch } from 'vue';
import type { WatchSource } from 'vue';
import { tours } from '@/tours/content';
import type { TourId, TourStep } from '@/tours/types';

/** Interactive tutorials (PRD-TUTOR). One tour at a time; "seen" is remembered per browser. */
const KEY = 'tour.seen.';
const MOBILE = 768;

export const tourConfig = {
    /** Off in unit tests so mounting a page never schedules a tour. */
    autoStart: import.meta.env.MODE !== 'test',
};

let active: Driver | null = null;
let routerHooked = false;

export function isTourSeen(id: TourId): boolean {
    try {
        return window.localStorage.getItem(KEY + id) !== null;
    } catch {
        return true; // storage blocked → never auto-start (it would show on every visit)
    }
}

export function markTourSeen(id: TourId): void {
    try {
        window.localStorage.setItem(KEY + id, new Date().toISOString());
    } catch {
        // ignore
    }
}

export function resetTours(): void {
    try {
        const ls = window.localStorage;
        Object.keys(ls)
            .filter((k) => k.startsWith(KEY))
            .forEach((k) => ls.removeItem(k));
    } catch {
        // ignore
    }
}

export function isTourActive(): boolean {
    return active?.isActive() ?? false;
}

/** True when a DOM event comes from the tour popover/overlay (dialogs must not close on it). */
export function isTourEvent(event: Event): boolean {
    const target =
        (event as CustomEvent<{ originalEvent?: Event }>).detail?.originalEvent
            ?.target ?? event.target;

    return (
        target instanceof Element &&
        target.closest('.driver-popover, .driver-overlay') !== null
    );
}

function isVisible(el: Element): boolean {
    const r = el.getBoundingClientRect();

    return (
        r.width > 0 &&
        r.height > 0 &&
        getComputedStyle(el).visibility !== 'hidden'
    );
}

/** First visible `[data-tour=…]`; the phone variant is tried first below 768 px (PRD-TUTOR §3.2). */
export function resolveAnchor(
    step: Pick<TourStep, 'anchor' | 'mobileAnchor'>,
): Element | undefined {
    const names =
        window.innerWidth < MOBILE
            ? [step.mobileAnchor, step.anchor]
            : [step.anchor];

    for (const name of names) {
        if (!name) {
            continue;
        }

        const found = [
            ...document.querySelectorAll(`[data-tour="${name}"]`),
        ].find(isVisible);

        if (found) {
            return found;
        }
    }

    return undefined;
}

export function buildSteps(id: TourId): DriveStep[] {
    return tours[id].steps.map((s) => ({
        // Resolved when the step is shown, not when the tour starts: dialog content changes
        // under the tour (spinner → summary, running → done). Nothing visible → centred (D8).
        element:
            s.anchor || s.mobileAnchor
                ? ((() => resolveAnchor(s) ?? null) as () => Element)
                : undefined,
        popover: {
            title: s.title,
            description: s.text,
            side: s.side,
            align: 'start',
        },
    }));
}

function hookRouter(): void {
    if (routerHooked) {
        return;
    }

    routerHooked = true;
    // Leaving the page closes the tour; partial reloads (autosave, deferred props) do not.
    router.on('before', (event) => {
        if (
            active &&
            event.detail.visit.url.pathname !== window.location.pathname
        ) {
            active.destroy();
        }
    });
}

export function startTour(id: TourId): void {
    active?.destroy();
    hookRouter();

    const reduced =
        window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ??
        false;
    const d = driver({
        steps: buildSteps(id),
        showProgress: true,
        progressText: 'Langkah {{current}} dari {{total}}',
        nextBtnText: 'Berikutnya',
        prevBtnText: 'Sebelumnya',
        doneBtnText: 'Selesai',
        showButtons: ['next', 'previous', 'close'],
        overlayOpacity: 0.6,
        stagePadding: 6,
        stageRadius: 10,
        allowClose: true,
        overlayClickBehavior: 'none',
        animate: !reduced,
        smoothScroll: !reduced,
        popoverClass: 'icp-tour',
        onPopoverRender: (popover) => {
            popover.closeButton.setAttribute('aria-label', 'Tutup tutorial');
            popover.closeButton.setAttribute('title', 'Tutup');
        },
        onDestroyed: () => {
            markTourSeen(id);

            if (active === d) {
                active = null;
            }
        },
    });

    active = d;
    d.drive();
}

type AutoOptions = { delay?: number; inDialog?: boolean; waitFor?: string };

/** Starts a tour once per browser when nothing else is going on (PRD-TUTOR §3.3). Returns a cancel function. */
export function autoStartTour(id: TourId, opts: AutoOptions = {}): () => void {
    if (!tourConfig.autoStart || isTourSeen(id)) {
        return () => undefined;
    }

    let tries = 0;
    let timer: ReturnType<typeof setTimeout> | undefined;

    const attempt = () => {
        tries++;
        const busy =
            isTourActive() ||
            (!opts.inDialog &&
                document.querySelector(
                    '[role="dialog"][data-state="open"], [role="alertdialog"][data-state="open"]',
                ) !== null);
        const waiting =
            (opts.waitFor !== undefined &&
                !document.querySelector(`[data-tour="${opts.waitFor}"]`)) ||
            // Dialogs load their content after opening: wait until any step has its element.
            (opts.inDialog === true &&
                !tours[id].steps.some((s) => resolveAnchor(s)));

        if ((busy || waiting) && tries < 6) {
            timer = setTimeout(attempt, 1000);

            return;
        }

        if (!busy && !isTourSeen(id)) {
            startTour(id);
        }
    };

    timer = setTimeout(attempt, opts.delay ?? 700);

    return () => clearTimeout(timer);
}

/** Page tour: auto-start once after mount. */
export function usePageTour(
    id: () => TourId | undefined,
    opts: AutoOptions = {},
): void {
    let cancel = () => {};

    onMounted(() => {
        const current = id();

        if (current) {
            cancel = autoStartTour(current, opts);
        }
    });
    watch(id, (current, previous) => {
        if (current && current !== previous) {
            cancel();
            cancel = autoStartTour(current, opts);
        }
    });
    onBeforeUnmount(() => cancel());
}

/** Dialog/panel tour: auto-start once when `open` turns true. */
export function useDialogTour(
    id: () => TourId | undefined,
    open: WatchSource<boolean>,
): void {
    let cancel = () => {};

    watch(
        open,
        (isOpen) => {
            cancel();
            const current = id();

            if (isOpen && current) {
                cancel = autoStartTour(current, { delay: 400, inDialog: true });
            } else if (!isOpen && isTourActive()) {
                active?.destroy();
            }
        },
        { immediate: true },
    );
    onBeforeUnmount(() => cancel());
}
