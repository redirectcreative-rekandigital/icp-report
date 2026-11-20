import type { Level, Skill } from '@/lib/cefr';

export type ClassDist = {
    id: number;
    class: string;
    grade: number;
    correcting: boolean;
    levels: Record<Level, string[]>;
    skills: Record<Skill, number | null>;
    average: number | null;
};

export type ProgressPoint = {
    enrollment_id: number;
    term: string;
    term_full: string;
    classroom: { id: number; name: string; grade: number };
    pct: Record<Skill, number | null>;
    levels: Record<Skill, Level | null>;
    overall_pct: number;
    overall_level: Level;
    status: 'below' | 'meeting' | 'above';
    source: 'final' | 'correcting' | 'draft';
    pdf_url: string | null;
    change: 'up' | 'down' | 'same' | null;
    previous_level: Level | null;
};
