import { reactive } from 'vue';
import { http } from '@/lib/http';

/**
 * Global state for PinConfirmDialog (PRD-02 §6).
 *
 *   await withPin(() => router.put('/pengaturan/keamanan/pin', form))
 *
 * Asks the server whether the PIN was entered in the last 5 minutes; if not,
 * opens the dialog and runs the action only after the PIN is confirmed.
 */

type Mode = 'confirm' | 'session';

const state = reactive({
    open: false,
    mode: 'confirm' as Mode,
    title: '',
    description: '',
});

let pending: ((ok: boolean) => void) | null = null;

function open(
    mode: Mode,
    title: string,
    description: string,
): Promise<boolean> {
    // A second request while open shares the same answer.
    if (state.open && pending) {
        const previous = pending;

        return new Promise((resolve) => {
            pending = (ok) => {
                previous(ok);
                resolve(ok);
            };
        });
    }

    state.mode = mode;
    state.title = title;
    state.description = description;
    state.open = true;

    return new Promise((resolve) => {
        pending = resolve;
    });
}

export function settlePinDialog(ok: boolean): void {
    state.open = false;
    pending?.(ok);
    pending = null;
}

export async function requirePin(
    title = 'Konfirmasi PIN',
    description = 'Tindakan ini memerlukan PIN sekolah.',
): Promise<boolean> {
    try {
        const { recent } = await http.get<{ recent: boolean }>('/pin/recent');

        if (recent) {
            return true;
        }
    } catch {
        // Fall through to the dialog; the server still enforces pin.recent.
    }

    return open('confirm', title, description);
}

export async function withPin<T>(
    action: () => T,
    title?: string,
    description?: string,
): Promise<T | undefined> {
    return (await requirePin(title, description)) ? action() : undefined;
}

/** Session ended (401) while working: ask for the PIN without leaving the page. */
export function requestSessionPin(): Promise<boolean> {
    return open(
        'session',
        'Sesi telah dikunci',
        'Masukkan PIN untuk melanjutkan. Isian Anda tidak hilang.',
    );
}

export function usePinConfirm() {
    return { state, requirePin, withPin, requestSessionPin, settlePinDialog };
}
