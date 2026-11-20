import type { Level, ScoringConfig, StudentResult } from '@/lib/cefr';

export type LevelMetaRow = {
    level: Level;
    short_name: string;
    name: string;
    cambridge: string;
    overall_comment: string;
};

export type ClassInfo = {
    id: number;
    grade: number;
    name: string;
    homeroom_teacher: string | null;
    english_teacher: string | null;
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
    levels: LevelMetaRow[];
};

export type ResultMap = Map<number, StudentResult>;
