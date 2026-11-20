export type TourStep = {
    /** Value of a `data-tour` attribute; empty = centred step without spotlight (PRD-TUTOR D8). */
    anchor?: string;
    /** Tried first below 768 px (bottom nav, card lists…). */
    mobileAnchor?: string;
    title: string;
    /** 1–2 short sentences. */
    text: string;
    side?: 'top' | 'bottom' | 'left' | 'right';
};

export type TourDef = { title: string; steps: TourStep[] };

export type TourId =
    | 'dashboard'
    | 'kelas-index'
    | 'kelas-nilai'
    | 'kelas-rapor'
    | 'kelas-riwayat'
    | 'rapor-preview'
    | 'siswa-index'
    | 'siswa-profil'
    | 'periode-index'
    | 'periode-detail'
    | 'impor'
    | 'dokumen'
    | 'log'
    | 'set-sekolah'
    | 'set-rapor'
    | 'set-penilaian'
    | 'set-deskriptor'
    | 'set-keamanan'
    | 'set-cadangan'
    | 'flow-finalkan'
    | 'flow-panel-siswa'
    | 'flow-pratinjau-impor'
    | 'flow-cetak-semua';
