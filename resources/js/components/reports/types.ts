export type BatchState = {
    id: string;
    status: 'running' | 'completed' | 'failed' | 'cancelled';
    total: number;
    done: number;
    failed: { name: string; error: string }[];
    current: string | null;
    mode: 'merged' | 'zip';
    paper: 'f4' | 'a4';
    download_url: string | null;
};

export type ReportStudent = {
    enrollment_id: number;
    roll_no: number;
    name: string;
    nis: string;
    is_active: boolean;
    overall_level: 'Pre-A1' | 'A1' | 'A2' | 'B1' | 'B2' | null;
    status: 'below' | 'meeting' | 'above' | 'incomplete' | null;
    printable: boolean;
};
