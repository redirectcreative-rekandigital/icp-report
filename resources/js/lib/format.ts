/** Indonesian UI formatting (PRD-07 §11). Reports use English formatting elsewhere. */

const TZ = 'Asia/Jakarta';

export function formatNumber(value: number, digits = 1): string {
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: digits,
    }).format(value);
}

export function formatPercent(
    value: number | null | undefined,
    digits = 1,
): string {
    return value === null || value === undefined
        ? '–'
        : `${formatNumber(value, digits)}%`;
}

/** "18 Des 2026" */
export function formatDate(iso: string | null | undefined): string {
    if (!iso) {
        return '–';
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: TZ,
    }).format(new Date(iso));
}

/** "18 Desember 2026 14.05" */
export function formatDateTime(iso: string | null | undefined): string {
    if (!iso) {
        return '–';
    }

    const d = new Date(iso);
    const date = new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: TZ,
    }).format(d);
    const time = new Intl.DateTimeFormat('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
        timeZone: TZ,
    })
        .format(d)
        .replace(':', '.');

    return `${date} ${time}`;
}

/** "14:59" countdown */
export function formatCountdown(seconds: number): string {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;

    return `${m}:${String(s).padStart(2, '0')}`;
}
