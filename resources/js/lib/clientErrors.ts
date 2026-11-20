/**
 * Sends unexpected JavaScript errors to /client-errors (PRD-07 §8.2) so they can be fixed.
 * Deduplicated and capped per page load; never throws.
 */
const sent = new Set<string>();
const MAX_PER_LOAD = 5;

function xsrf(): string {
    const m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return m ? decodeURIComponent(m[1]) : '';
}

export async function reportClientError(
    error: unknown,
    component?: string,
): Promise<string | null> {
    const err = error instanceof Error ? error : new Error(String(error));
    const key = `${err.message}|${component ?? ''}`;

    if (sent.has(key) || sent.size >= MAX_PER_LOAD) {
        return null;
    }

    sent.add(key);

    try {
        const res = await fetch('/client-errors', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrf(),
            },
            body: JSON.stringify({
                message: err.message.slice(0, 500),
                stack: (err.stack ?? '').slice(0, 4000),
                url: window.location.pathname.slice(0, 300),
                component: component?.slice(0, 120),
            }),
        });

        return res.ok ? ((await res.json()) as { ref: string }).ref : null;
    } catch {
        return null;
    }
}
