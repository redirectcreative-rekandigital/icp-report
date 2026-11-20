// @vitest-environment jsdom
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { tours } from '@/tours/content';
import type { TourId } from '@/tours/types';
import {
    autoStartTour,
    buildSteps,
    isTourSeen,
    markTourSeen,
    resetTours,
    resolveAnchor,
} from './useTour';

/* jsdom has no layout: give visible elements a box, keep [hidden]/display:none invisible (§7.6). */
beforeAll(() => {
    vi.spyOn(Element.prototype, 'getBoundingClientRect').mockImplementation(
        function (this: Element) {
            const invisible =
                this.hasAttribute('hidden') ||
                (this instanceof HTMLElement && this.style.display === 'none');

            return {
                x: 0,
                y: 0,
                bottom: invisible ? 0 : 50,
                height: invisible ? 0 : 50,
                left: 0,
                right: invisible ? 0 : 100,
                top: 0,
                width: invisible ? 0 : 100,
                toJSON: () => ({}),
            } as DOMRect;
        },
    );
    window.innerWidth = 1200;
});

/* jsdom: driver.js is never driven here (no drive()) — only the pure logic is tested (§8). */

describe('tour seen markers (D6)', () => {
    beforeEach(() => resetTours());

    it('is not seen until marked, then stays seen', () => {
        expect(isTourSeen('dashboard')).toBe(false);
        markTourSeen('dashboard');
        expect(isTourSeen('dashboard')).toBe(true);
    });

    it('resetTours clears every marker but not other keys', () => {
        markTourSeen('dashboard');
        markTourSeen('impor');
        window.localStorage.setItem('other.key', 'keep');
        resetTours();
        expect(isTourSeen('dashboard')).toBe(false);
        expect(isTourSeen('impor')).toBe(false);
        expect(window.localStorage.getItem('other.key')).toBe('keep');
        window.localStorage.removeItem('other.key');
    });
});

describe('content integrity (§8)', () => {
    it('every tour has 1–10 steps and each step has a title and short text', () => {
        const ids = Object.keys(tours) as TourId[];

        expect(ids.length).toBeGreaterThanOrEqual(23);

        for (const id of ids) {
            const t = tours[id];
            expect(t.steps.length, id).toBeGreaterThanOrEqual(1);
            expect(t.steps.length, id).toBeLessThanOrEqual(10);

            for (const s of t.steps) {
                expect(s.title.length, `${id}/${s.title}`).toBeGreaterThan(0);
                expect(s.text.length, `${id}/${s.title}`).toBeLessThanOrEqual(
                    220,
                );
            }
        }
    });

    it('every anchor mentioned in a step is a well-formed name or the step is centred', () => {
        // Whether an anchor is actually rendered is asserted by the page tests (M5Pages, M6Pages).
        for (const id of Object.keys(tours) as TourId[]) {
            for (const s of tours[id].steps) {
                if (s.anchor !== undefined) {
                    expect(s.anchor, `${id}/${s.title}`).toMatch(
                        /^[a-z][a-z0-9-]*$/,
                    );
                }

                if (s.mobileAnchor !== undefined) {
                    expect(s.mobileAnchor, `${id}/${s.title}`).toMatch(
                        /^[a-z][a-z0-9-]*$/,
                    );
                }
            }
        }
    });
});

describe('anchor resolution (§3.2, D8)', () => {
    it('returns the first visible element with the anchor name', () => {
        document.body.innerHTML =
            '<div data-tour="x" hidden></div><div data-tour="x" style="width:10px;height:10px"></div>';
        expect(resolveAnchor({ anchor: 'x' })).toBe(
            document.querySelectorAll('[data-tour="x"]')[1],
        );
    });

    it('falls back to the desktop anchor when no phone variant exists', () => {
        window.innerWidth = 375;
        document.body.innerHTML =
            '<div data-tour="desk" style="width:10px;height:10px"></div>';
        expect(resolveAnchor({ anchor: 'desk', mobileAnchor: 'phone' })).toBe(
            document.querySelector('[data-tour="desk"]'),
        );
    });

    it('prefers the phone variant below 768 px', () => {
        window.innerWidth = 375;
        document.body.innerHTML =
            '<div data-tour="desk" style="width:10px;height:10px"></div><div data-tour="phone" style="width:10px;height:10px"></div>';
        expect(resolveAnchor({ anchor: 'desk', mobileAnchor: 'phone' })).toBe(
            document.querySelector('[data-tour="phone"]'),
        );
    });

    it('returns undefined when nothing is visible → the step is shown centred', () => {
        document.body.innerHTML = '<div data-tour="gone" hidden></div>';
        expect(resolveAnchor({ anchor: 'gone' })).toBeUndefined();
    });

    it('buildSteps maps missing anchors to element: undefined (centred step, D8)', () => {
        document.body.innerHTML = '';
        const steps = buildSteps('log');
        expect(steps[0].element).toBeUndefined();
        expect(steps[0].popover).toMatchObject({ title: 'Filter' });
    });
});

describe('auto-start (§3.3, D2/D5)', () => {
    beforeEach(() => {
        resetTours();
        document.body.innerHTML = '';
    });

    it('does not start when the tour was already seen', () => {
        markTourSeen('log');
        autoStartTour('log');
        expect(isTourSeen('log')).toBe(true);
        expect(document.querySelector('.driver-popover')).toBeNull();
    });

    it('does not start while a dialog is open, and can be cancelled', () => {
        document.body.innerHTML = '<div role="dialog" data-state="open"></div>';
        const cancel = autoStartTour('log', { delay: 10 });
        cancel();
        expect(isTourSeen('log')).toBe(false);
    });

    it('waits for a missing anchor and gives up after 5 tries without marking it seen', async () => {
        autoStartTour('log', { delay: 1, waitFor: 'never-rendered' });
        await new Promise((r) => setTimeout(r, 30));
        expect(isTourSeen('log')).toBe(false);
    });

    it('one tour at a time: a second auto-start does not stack popovers', async () => {
        document.body.innerHTML =
            '<div data-tour="log-filters" style="width:10px;height:10px"></div>';
        autoStartTour('log', { delay: 1 });
        autoStartTour('log', { delay: 1 });
        await new Promise((r) => setTimeout(r, 30));
        expect(
            document.querySelectorAll('.driver-popover').length,
        ).toBeLessThanOrEqual(1);
    });
});
