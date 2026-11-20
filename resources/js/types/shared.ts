/** Shared Inertia props (app/Http/Middleware/HandleInertiaRequests.php). */

export type NavClass = {
    id: number;
    grade: number;
    name: string;
    status: 'draft' | 'final';
    complete: number;
    total: number;
    englishTeacher?: string | null;
};

export type ActiveTerm = {
    id: number;
    label: string; // "2026/2027 • Semester 1"
    short: string; // "2026/27 S1"
};

export type SharedProps = {
    app: { name: string };
    school: { name: string; program: string; logoUrl: string | null };
    session: { idleMinutes: number } | null;
    activeTerm: ActiveTerm | null;
    nav: { classes: NavClass[]; showDocuments: boolean } | null;
    flash: {
        success: string | null;
        error: string | null;
        pinConfirmRequired: boolean;
    };
    errors: Record<string, string>;
    sidebarOpen: boolean;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
    prev_page_url: string | null;
    next_page_url: string | null;
};
