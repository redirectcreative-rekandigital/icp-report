import type { Band, NatgeoLevel, Skill } from '@/lib/cefr';

export type ClassDist = {
    id: number;
    class: string;
    grade: number;
    correcting: boolean;
    levels: Record<Band, string[]>;
    skills: Record<Skill, number | null>;
    average: number | null;
};

export type ProgressPoint = {
    enrollment_id: number;
    term: string;
    term_full: string;
    classroom: { id: number; name: string; grade: number };
    scores: Record<Skill, number | null>;
    levels: Record<Skill, Band | null>;
    total: number;
    overall_level: Band;
    /** Grade 6 only: National Geographic CEFR placement level. */
    natgeo: NatgeoLevel | null; // sub-level, e.g. A2.2
    source: 'final' | 'correcting' | 'draft';
    pdf_url: string | null;
    change: 'up' | 'down' | 'same' | null;
    previous_level: Band | null;
};
