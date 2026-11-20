// @vitest-environment jsdom
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';
import { tours } from '@/tours/content';
import type { TourId } from '@/tours/types';

/**
 * Guards PRD-TUTOR §4.3: every `anchor`/`mobileAnchor` used in content.ts must appear as
 * `data-tour="…"` in some .vue file. A missing anchor silently degrades to a centred step (§3.2),
 * which is allowed for intentionally centred steps (no anchor) but never for a typo.
 */
const ROOT = join(process.cwd(), 'resources', 'js');

function vueFiles(dir: string): string[] {
    return readdirSync(dir).flatMap((name) => {
        const full = join(dir, name);
        const stat = statSync(full);

        if (stat.isDirectory()) {
            return name === 'ui' ? [] : vueFiles(full);
        }

        return name.endsWith('.vue') ? [full] : [];
    });
}

const anchorsInDom = new Set(
    vueFiles(ROOT).flatMap((file) =>
        [
            ...readFileSync(file, 'utf8').matchAll(/data-tour="([a-z0-9-]+)"/g),
        ].map((m) => m[1]),
    ),
);

describe('tour anchors (PRD-TUTOR §4.3)', () => {
    it('collects anchors from the app pages and components', () => {
        expect(anchorsInDom.size).toBeGreaterThan(40);
    });

    it.each(Object.keys(tours) as TourId[])(
        '%s: every anchor exists as data-tour',
        (id) => {
            const missing = tours[id].steps
                .flatMap((s) => [s.anchor, s.mobileAnchor])
                .filter((a): a is string => !!a && !anchorsInDom.has(a));

            expect(missing, 'missing data-tour in .vue files').toEqual([]);
        },
    );
});
