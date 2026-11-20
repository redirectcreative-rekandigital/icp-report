/**
 * Small JSON client for non-Inertia calls (autosave, PIN confirm, ping).
 * Handles the uniform error format of PRD-07 §8.1.
 */

export type ApiErrorBody = {
    message?: string;
    code?: string;
    errors?: Record<string, string[]>;
    ref?: string;
    lockout_seconds?: number;
};

export class ApiError extends Error {
    constructor(
        public status: number,
        public body: ApiErrorBody,
    ) {
        super(body.message ?? `HTTP ${status}`);
    }

    get isNetwork(): boolean {
        return this.status === 0;
    }
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

type Listener = (error: ApiError) => void;
const sessionListeners = new Set<Listener>();

/** Notified on 401 (session locked/expired) so the app can show the PIN dialog. */
export function onSessionLost(listener: Listener): () => void {
    sessionListeners.add(listener);

    return () => sessionListeners.delete(listener);
}

async function send<T>(
    method: string,
    url: string,
    body?: unknown,
    retried = false,
): Promise<T> {
    let response: Response;

    try {
        response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: body === undefined ? undefined : JSON.stringify(body),
        });
    } catch {
        throw new ApiError(0, {
            message: 'Tidak dapat terhubung. Periksa koneksi lalu coba lagi.',
            code: 'NETWORK',
        });
    }

    // 419: CSRF token expired → any response refreshes the XSRF cookie; retry once silently.
    if (response.status === 419 && !retried) {
        await fetch('/pin/recent', { credentials: 'same-origin' }).catch(
            () => undefined,
        );

        return send<T>(method, url, body, true);
    }

    const text = await response.text();
    const data = text ? JSON.parse(text) : {};

    if (!response.ok) {
        const error = new ApiError(response.status, data);

        if (response.status === 401) {
            sessionListeners.forEach((l) => l(error));
        }

        throw error;
    }

    // The server touched the session; useSessionTimeout resets its timer.
    window.dispatchEvent(new Event('app:activity'));

    return data as T;
}

export const http = {
    get: <T>(url: string) => send<T>('GET', url),
    post: <T>(url: string, body?: unknown) => send<T>('POST', url, body ?? {}),
    put: <T>(url: string, body?: unknown) => send<T>('PUT', url, body ?? {}),
    patch: <T>(url: string, body?: unknown) =>
        send<T>('PATCH', url, body ?? {}),
    delete: <T>(url: string) => send<T>('DELETE', url),
};
