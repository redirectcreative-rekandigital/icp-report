/**
 * Deep copy of page data. Inertia page props are Vue reactive proxies, which
 * `structuredClone` refuses ("DataCloneError") — props are plain JSON, so a JSON
 * round-trip is safe and works on proxies.
 */
export function clone<T>(value: T): T {
    return JSON.parse(JSON.stringify(value)) as T;
}
