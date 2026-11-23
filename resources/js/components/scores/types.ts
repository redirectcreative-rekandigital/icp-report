import type { Band, Level, ScoringConfig, StudentResult } from '@/lib/cefr';

export type ClassInfo = {
    id: number;
    grade: number;
    name: string;
    homeroom_teacher: string | null;
    status: 'draft' | 'final';
    term: string;
    term_id: number;
    finalized_at: string | null;
    last_event: {
        action: 'finalize' | 'unfinalize';
        reason: string | null;
        at: string;
    } | null;
    complete: number;
    total: number;
};

export type GridProps = {
    classroom: ClassInfo;
    config: ScoringConfig;
    /** Automatic comment per band (fallback when the teacher writes none). */
    comments: Partial<Record<Band, string>>;
    /** Grade 6 only: the National Geographic CEFR levels; null for other grades. */
    natgeoLevels: Level[] | null;
};

export type ResultMap = Map<number, StudentResult>;
