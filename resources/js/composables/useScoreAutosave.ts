import { useEventListener, useOnline } from '@vueuse/core';
import type { Ref } from 'vue';
import { computed, reactive, ref, watch } from 'vue';
import { requestSessionPin } from '@/composables/usePinConfirm';
import type { Level, Skill } from '@/lib/cefr';
import { ApiError, http } from '@/lib/http';

/**
 * Autosave for the score grid (PRD-04 §4.3–4.6).
 * - one queue per student row; requests for a row are sent one at a time so `version` stays consistent
 * - offline / server errors: kept in the queue (and sessionStorage), retried 1-2-4-8-16 s, then "Gagal"
 * - 401/419: ask for the PIN in place, then resend; 409: conflict dialog; 423: class finalised elsewhere
 */

export type Field = Skill | 'natgeo' | 'comment';
export type ScoreRow = {
    enrollment_id: number;
    roll_no: number;
    student_id: number;
    nipd: string;
    name: string;
    is_active: boolean;
    scores: Record<Skill, number | null>;
    natgeo: Level | null;
    comment: string | null;
    version: number;
};
type Current = {
    scores: Record<Skill, number | null>;
    natgeo: Level | null;
    comment: string | null;
    version: number;
};

/** Reads one editable field of a row. */
export function fieldValue(row: Current, field: Field): unknown {
    return field === 'comment' || field === 'natgeo'
        ? row[field]
        : row.scores[field];
}

/** Writes one editable field of a row. */
export function assignField(row: Current, field: Field, value: unknown): void {
    if (field === 'comment') {
        row.comment = value as string | null;
    } else if (field === 'natgeo') {
        row.natgeo = value as Level | null;
    } else {
        row.scores[field] = value as number | null;
    }
}
export type Conflict = {
    row: ScoreRow;
    field: Field;
    mine: unknown;
    current: Current;
};
type CellState = { status: 'saving' | 'error' | 'failed'; message?: string };
type SaveResponse = Current & {
    class_progress: { complete: number; total: number };
};

const BACKOFF = [1000, 2000, 4000, 8000, 16000];

export function useScoreAutosave(options: {
    classroomId: number;
    rows: Ref<ScoreRow[]>;
    onProgress: (p: { complete: number; total: number }) => void;
    onLocked: () => void;
}) {
    const storageKey = `scores:pending:${options.classroomId}`;
    const queue = reactive(new Map<number, Partial<Record<Field, unknown>>>());
    const inflight = reactive(new Set<number>());
    const attempts = new Map<number, number>();
    const cells = reactive<Record<string, CellState>>({});
    const conflict = ref<Conflict | null>(null);
    const savedAt = ref<number | null>(null);
    const paused = ref(false);
    const online = useOnline();

    const key = (id: number, field: Field) => `${id}:${field}`;
    const rowById = (id: number) =>
        options.rows.value.find((r) => r.enrollment_id === id);

    const pendingCount = computed(() =>
        [...queue.values()].reduce((n, f) => n + Object.keys(f).length, 0),
    );
    const failedCount = computed(
        () => Object.values(cells).filter((c) => c.status === 'failed').length,
    );
    const saving = computed(() => inflight.size > 0);

    function persist(): void {
        try {
            const data = Object.fromEntries(
                [...queue.entries()].filter(([, f]) => Object.keys(f).length),
            );

            if (Object.keys(data).length) {
                sessionStorage.setItem(storageKey, JSON.stringify(data));
            } else {
                sessionStorage.removeItem(storageKey);
            }
        } catch {
            // storage unavailable (private mode): the in-memory queue still works
        }
    }

    function enqueue(row: ScoreRow, field: Field, value: unknown): void {
        queue.set(row.enrollment_id, {
            ...(queue.get(row.enrollment_id) ?? {}),
            [field]: value,
        });
        cells[key(row.enrollment_id, field)] = { status: 'saving' };
        persist();
        void pump(row.enrollment_id);
    }

    function finishField(id: number, field: Field, sent: unknown): void {
        const fields = queue.get(id);

        if (fields && fields[field] === sent) {
            delete fields[field];

            if (!Object.keys(fields).length) {
                queue.delete(id);
            }
        }

        persist();
    }

    async function pump(id: number): Promise<void> {
        if (
            inflight.has(id) ||
            paused.value ||
            conflict.value ||
            !online.value
        ) {
            return;
        }

        const fields = queue.get(id);
        const row = rowById(id);

        if (!fields || !row || !Object.keys(fields).length) {
            return;
        }

        const [field, value] = Object.entries(fields)[0] as [Field, unknown];
        inflight.add(id);
        let retryIn: number | null = null;

        try {
            const res = await http.patch<SaveResponse>(
                `/kelas/${options.classroomId}/skor/${id}`,
                {
                    field,
                    value,
                    version: row.version,
                },
            );

            row.version = res.version;

            // Only take the server value when nothing newer is waiting for this cell.
            if (
                queue.get(id)?.[field] === value ||
                !(field in (queue.get(id) ?? {}))
            ) {
                assignField(row, field, fieldValue(res, field));
            }

            finishField(id, field, value);
            delete cells[key(id, field)];
            attempts.delete(id);
            savedAt.value = Date.now();
            options.onProgress(res.class_progress);
        } catch (e) {
            const err = e instanceof ApiError ? e : new ApiError(0, {});

            if (err.status === 422) {
                cells[key(id, field)] = {
                    status: 'error',
                    message: err.body.errors?.value?.[0] ?? err.body.message,
                };
                finishField(id, field, value);
            } else if (err.status === 409) {
                conflict.value = {
                    row,
                    field,
                    mine: value,
                    current: (err.body as unknown as { current: Current })
                        .current,
                };
            } else if (err.status === 423) {
                queue.clear();
                persist();
                options.onLocked();
            } else if (err.status === 401 || err.status === 419) {
                paused.value = true;
                const ok = await requestSessionPin();
                paused.value = false;

                if (ok) {
                    retryIn = 0;
                }
            } else if (err.status === 429) {
                retryIn = 2000;
            } else if (!online.value) {
                // wait for the 'online' event
            } else {
                const n = (attempts.get(id) ?? 0) + 1;
                attempts.set(id, n);

                if (n <= BACKOFF.length) {
                    retryIn = BACKOFF[n - 1];
                } else {
                    cells[key(id, field)] = {
                        status: 'failed',
                        message: err.body.message ?? 'Gagal menyimpan.',
                    };
                }
            }
        } finally {
            inflight.delete(id);
        }

        if (retryIn !== null) {
            setTimeout(() => void pump(id), retryIn);
        } else if (cells[key(id, field)]?.status !== 'failed') {
            void pump(id); // next waiting field of this row (no-op when the queue is empty)
        }
        // "failed": stays queued until the user presses "Coba lagi" (retryFailed).
    }

    function pumpAll(): void {
        for (const id of queue.keys()) {
            void pump(id);
        }
    }

    /** Manual "Coba lagi" after repeated failures. */
    function retryFailed(): void {
        for (const [k, c] of Object.entries(cells)) {
            if (c.status === 'failed') {
                cells[k] = { status: 'saving' };
            }
        }

        attempts.clear();
        pumpAll();
    }

    function resolveConflict(choice: 'theirs' | 'mine'): void {
        const c = conflict.value;

        if (!c) {
            return;
        }

        conflict.value = null;

        if (choice === 'theirs') {
            c.row.scores = { ...c.current.scores };
            c.row.natgeo = c.current.natgeo;
            c.row.comment = c.current.comment;
            c.row.version = c.current.version;
            queue.delete(c.row.enrollment_id);
            Object.keys(cells)
                .filter((k) => k.startsWith(`${c.row.enrollment_id}:`))
                .forEach((k) => delete cells[k]);
            persist();
        } else {
            c.row.version = c.current.version; // resend my value on top of the latest version
        }

        pumpAll();
    }

    // Back online → send everything (PRD-04 §4.5).
    const backOnline = ref(0);
    watch(online, (now, before) => {
        if (now && !before && pendingCount.value) {
            backOnline.value = pendingCount.value;
            pumpAll();
        }
    });

    useEventListener(window, 'beforeunload', (e: BeforeUnloadEvent) => {
        if (pendingCount.value) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    /* ---------- recovery from a previous session (PRD-04 §4.6) ---------- */
    const restorable = ref<Record<
        string,
        Partial<Record<Field, unknown>>
    > | null>(null);

    try {
        const raw = sessionStorage.getItem(storageKey);
        const parsed = raw ? JSON.parse(raw) : null;

        if (parsed && Object.keys(parsed).length) {
            restorable.value = parsed;
        }
    } catch {
        restorable.value = null;
    }

    const restorableCount = computed(() =>
        restorable.value
            ? Object.values(restorable.value).reduce(
                  (n, f) => n + Object.keys(f).length,
                  0,
              )
            : 0,
    );

    function restore(): void {
        for (const [id, fields] of Object.entries(restorable.value ?? {})) {
            const row = rowById(Number(id));

            if (!row) {
                continue;
            }

            for (const [field, value] of Object.entries(fields) as [
                Field,
                unknown,
            ][]) {
                assignField(row, field, value);
                enqueue(row, field, value);
            }
        }

        restorable.value = null;
    }

    function discardRestorable(): void {
        restorable.value = null;
        persist();
    }

    return {
        enqueue,
        cells,
        conflict,
        resolveConflict,
        pendingCount,
        failedCount,
        saving,
        savedAt,
        online,
        backOnline,
        retryFailed,
        restorableCount,
        restore,
        discardRestorable,
    };
}
