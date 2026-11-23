import type { TourDef, TourId } from './types';

/**
 * ALL tutorial text lives here (PRD-TUTOR §6). Edit freely: each step points at an element
 * with `data-tour="<anchor>"`; a step whose element is not on screen is shown centred.
 */
export const tours: Record<TourId, TourDef> = {
    dashboard: {
        title: 'Dashboard',
        steps: [
            {
                title: 'Selamat datang',
                text: 'Tutorial singkat ini mengenalkan menu utama dan isi Dashboard. Tekan Berikutnya, atau × untuk menutup.',
            },
            {
                anchor: 'nav-sidebar',
                mobileAnchor: 'nav-bottom',
                title: 'Menu utama',
                text: 'Semua menu ada di sini. Di ponsel, menu ada di bar bawah dan tombol Lainnya.',
                side: 'right',
            },
            {
                anchor: 'nav-kelas',
                title: 'Menu Kelas per jenjang',
                text: 'Kelas periode aktif dikelompokkan per jenjang. Angka 18/25 = siswa yang nilainya lengkap; ikon gembok = kelas sudah final.',
                side: 'right',
            },
            {
                anchor: 'header-term',
                title: 'Periode aktif',
                text: 'Data yang tampil mengikuti periode aktif. Ganti periode aktif di menu Periode & Kelas.',
                side: 'bottom',
            },
            {
                anchor: 'header-lock',
                title: 'Kunci layar',
                text: 'Kunci layar saat meninggalkan komputer (Alt + L). Masuk lagi dengan PIN sekolah.',
                side: 'bottom',
            },
            {
                anchor: 'dash-filters',
                title: 'Filter',
                text: 'Pilih periode, jenjang, atau tampilkan hanya kelas yang sudah final.',
            },
            {
                anchor: 'dash-kpi',
                title: 'Ringkasan angka',
                text: 'Jumlah siswa dinilai, kelas final, rata-rata total nilai, dan persentase siswa di level Intermediate ke atas.',
            },
            {
                anchor: 'dash-charts',
                title: 'Grafik',
                text: 'Klik segmen untuk melihat daftar siswa. Ikon tabel menampilkan angka; ikon unduh menyimpan gambar.',
            },
            {
                anchor: 'dash-progress',
                title: 'Progres kelas',
                text: 'Kelas yang paling belum lengkap ada di atas. Klik Isi Nilai untuk langsung mengisi.',
            },
            {
                anchor: 'dash-tabs',
                title: 'Perbandingan',
                text: 'Bandingkan dua periode: sebaran level dan siswa yang naik/turun level.',
            },
        ],
    },
    'kelas-index': {
        title: 'Semua kelas',
        steps: [
            {
                anchor: 'kelas-term',
                title: 'Pilih periode',
                text: 'Kelas yang tampil mengikuti periode ini.',
            },
            {
                anchor: 'kelas-card',
                title: 'Kartu kelas',
                text: 'Nama kelas, guru, dan progres pengisian nilai. Klik kartu untuk membuka kelas.',
            },
            {
                anchor: 'kelas-progress',
                title: 'Progres',
                text: 'Abu-abu di bawah 50 %, kuning 50–99 %, hijau berarti semua siswa sudah lengkap.',
            },
            {
                anchor: 'kelas-status',
                title: 'Status',
                text: 'Draft = nilai masih bisa diubah. Final = nilai terkunci dan rapor siap dicetak.',
            },
        ],
    },
    'kelas-nilai': {
        title: 'Input Nilai',
        steps: [
            {
                anchor: 'class-switcher',
                title: 'Pindah kelas',
                text: 'Klik nama kelas untuk pindah ke kelas lain; tombol ‹ › ke kelas sebelumnya/berikutnya.',
            },
            {
                anchor: 'class-tabs',
                title: 'Tab kelas',
                text: 'Input Nilai, Rapor & Cetak, dan Riwayat finalisasi.',
            },
            {
                anchor: 'score-grid',
                mobileAnchor: 'score-list',
                title: 'Isi nilai',
                text: 'Ketik nilai 5–25 (boleh ,5). Enter/↓ pindah ke bawah, Tab ke kanan. Bisa juga tempel blok nilai dari Excel. Kelas 6: pilih juga level National Geographic.',
            },
            {
                anchor: 'score-levels',
                title: 'Level otomatis',
                text: 'Level Beginner / Intermediate / Advanced dan total nilai dihitung langsung saat Anda mengetik.',
            },
            {
                anchor: 'save-indicator',
                title: 'Tersimpan otomatis',
                text: 'Tidak ada tombol simpan. Status penyimpanan tampil di sini; saat offline, nilai dikirim otomatis begitu koneksi kembali.',
            },
            {
                anchor: 'score-comment',
                title: 'Komentar & panel siswa',
                text: 'Klik nama siswa atau ikon komentar untuk membuka panel siswa.',
            },
            {
                anchor: 'score-import',
                title: 'Impor & ekspor',
                text: 'Isi nilai offline lewat template Excel, lalu impor. Ekspor menghasilkan rekap kelas.',
            },
            {
                anchor: 'finalize-btn',
                title: 'Finalkan',
                text: 'Setelah semua lengkap, finalkan kelas untuk mengunci nilai dan menyiapkan rapor (perlu PIN).',
            },
        ],
    },
    'kelas-rapor': {
        title: 'Rapor & Cetak',
        steps: [
            {
                anchor: 'paper-select',
                title: 'Ukuran kertas',
                text: 'F4 (bawaan) atau A4. Pilihan diingat di perangkat ini.',
            },
            {
                anchor: 'report-actions',
                mobileAnchor: 'report-row',
                title: 'Per siswa',
                text: 'Pratinjau, unduh PDF, atau cetak langsung rapor satu siswa.',
            },
            {
                anchor: 'report-select',
                title: 'Pilih siswa',
                text: 'Centang beberapa siswa untuk mengunduh atau mencetak sebagian saja.',
            },
            {
                anchor: 'report-download-all',
                title: 'Unduh semua',
                text: 'Satu file PDF gabungan, atau ZIP berisi satu PDF per siswa.',
            },
            {
                anchor: 'report-print-all',
                title: 'Cetak semua',
                text: 'Rapor dibuat bertahap, lalu dialog cetak dibuka.',
            },
            {
                anchor: 'unfinalize-btn',
                title: 'Batalkan finalisasi',
                text: 'Untuk koreksi nilai setelah final; wajib mengisi alasan dan PIN.',
            },
        ],
    },
    'kelas-riwayat': {
        title: 'Riwayat',
        steps: [
            {
                anchor: 'history-timeline',
                title: 'Riwayat finalisasi',
                text: 'Setiap finalisasi dan pembatalan tercatat dengan waktu, perangkat, dan alasan.',
            },
            {
                title: 'Rapor lama tetap aman',
                text: 'Rapor kelas final memakai data saat finalisasi, walaupun pengaturan diubah kemudian.',
            },
        ],
    },
    'rapor-preview': {
        title: 'Pratinjau rapor',
        steps: [
            {
                anchor: 'preview-nav',
                title: 'Siswa lain',
                text: 'Pindah ke siswa sebelumnya/berikutnya dengan tombol ‹ › atau tombol [ dan ].',
            },
            {
                anchor: 'preview-paper',
                title: 'Kertas & zoom',
                text: 'Lihat tampilan F4 atau A4, lalu perbesar/perkecil.',
            },
            {
                anchor: 'preview-sheet',
                title: 'Rapor',
                text: 'Tampilan ini sama persis dengan PDF. Tanda DRAFT muncul bila kelas belum final.',
            },
            {
                anchor: 'preview-actions',
                mobileAnchor: 'preview-actions-mobile',
                title: 'Unduh & cetak',
                text: 'Unduh PDF atau cetak langsung. Di ponsel, PDF bisa dibagikan ke aplikasi lain.',
            },
        ],
    },
    'siswa-index': {
        title: 'Siswa',
        steps: [
            {
                anchor: 'student-search',
                title: 'Cari',
                text: 'Cari nama, NIPD, atau NISN. Tekan / untuk langsung mengetik.',
            },
            {
                anchor: 'student-filters',
                mobileAnchor: 'student-filter-btn',
                title: 'Filter',
                text: 'Tampilkan siswa per periode, kelas, atau status aktif.',
            },
            {
                anchor: 'student-add',
                mobileAnchor: 'student-fab',
                title: 'Tambah siswa',
                text: 'Isi NIPD (maks. 5 digit), NISN (maks. 10 digit), nama, dan kelas. Untuk banyak siswa sekaligus, gunakan Impor dari Excel.',
            },
            {
                anchor: 'student-table',
                mobileAnchor: 'student-cards',
                title: 'Daftar siswa',
                text: 'Centang beberapa siswa untuk pindah kelas, keluarkan dari kelas, atau nonaktifkan sekaligus.',
            },
            {
                anchor: 'student-row-actions',
                title: 'Ubah & profil',
                text: 'Ubah data siswa atau buka profil perkembangannya.',
            },
        ],
    },
    'siswa-profil': {
        title: 'Profil siswa',
        steps: [
            {
                anchor: 'profile-actions',
                title: 'Kelola siswa',
                text: 'Ubah data, nonaktifkan, atau hapus (hapus hanya bisa bila siswa belum punya nilai).',
            },
            {
                anchor: 'profile-chart',
                title: 'Grafik perkembangan',
                text: 'Total nilai per semester. Warna titik = level; garis putus-putus = belum final.',
            },
            {
                anchor: 'profile-summary',
                title: 'Ringkasan',
                text: 'Level final terakhir dan cerita perkembangan yang dibuat otomatis.',
            },
            {
                anchor: 'profile-history',
                title: 'Riwayat level',
                text: 'Level per skill di setiap periode, lengkap dengan rapor PDF-nya.',
            },
        ],
    },
    'periode-index': {
        title: 'Periode & Kelas',
        steps: [
            {
                anchor: 'year-add',
                title: 'Tahun ajaran',
                text: 'Buat tahun ajaran (mis. 2026/2027), lalu tambahkan Semester 1 dan 2.',
            },
            {
                anchor: 'term-card',
                title: 'Semester',
                text: 'Setiap semester punya kelas sendiri. Pengaturan penilaian disalin otomatis dari periode sebelumnya.',
            },
            {
                anchor: 'term-activate',
                title: 'Periode aktif',
                text: 'Hanya satu periode yang aktif; menu Kelas mengikuti periode ini.',
            },
            {
                anchor: 'term-manage',
                title: 'Kelola kelas',
                text: 'Tambah kelas dan wali kelas untuk semester ini.',
            },
        ],
    },
    'periode-detail': {
        title: 'Kelas dalam periode',
        steps: [
            {
                anchor: 'class-add',
                title: 'Tambah kelas',
                text: 'Isi jenjang, nama kelas, dan wali kelas (dengan NBM).',
            },
            {
                anchor: 'class-copy',
                title: 'Salin dari periode lain',
                text: 'Salin kelas sekaligus siswanya; bisa naik jenjang (3A → 4A) untuk tahun ajaran baru.',
            },
            {
                anchor: 'class-table',
                mobileAnchor: 'class-cards',
                title: 'Daftar kelas',
                text: 'Ubah atau hapus kelas dari menu ⋮. Kelas final tidak bisa diubah.',
            },
        ],
    },
    impor: {
        title: 'Impor/Ekspor',
        steps: [
            {
                title: 'Dua tahap',
                text: 'Setiap impor menampilkan pratinjau dulu. Tidak ada data yang tersimpan sebelum Anda menekan Impor.',
            },
            {
                anchor: 'import-students',
                title: 'Impor siswa',
                text: 'Unduh template, isi NIPD, NISN, nama, L/P, dan kelas, lalu unggah.',
            },
            {
                anchor: 'import-scores',
                title: 'Impor nilai kelas',
                text: 'Pilih kelas, unduh template berisi daftar siswa, isi nilai 5–25 (kelas 6: juga kolom NatGeo), lalu unggah. Sel kosong = nilai tidak diubah.',
            },
            {
                anchor: 'import-overwrite',
                title: 'Timpa nilai',
                text: 'Aktifkan bila ingin mengganti nilai yang sudah ada (perlu PIN saat menyimpan).',
            },
            {
                anchor: 'import-legacy',
                title: 'File Excel lama',
                text: 'Pindahkan data dari file ICP_English_Progress_Report.xlsx (sheet Grade 3–6).',
            },
            {
                anchor: 'export-card',
                title: 'Ekspor',
                text: 'Unduh rekap periode, rekap kelas, dan daftar siswa dalam Excel.',
            },
        ],
    },
    dokumen: {
        title: 'Dokumen',
        steps: [
            {
                anchor: 'doc-upload',
                title: 'Unggah',
                text: 'Unggah PDF, Word, atau Excel, maksimal 10 MB.',
            },
            {
                anchor: 'doc-grade',
                title: 'Filter jenjang',
                text: 'Tampilkan dokumen untuk jenjang tertentu saja.',
            },
            {
                anchor: 'doc-category',
                title: 'Kategori',
                text: 'Dokumen dikelompokkan: Instrumen Tes, Pedoman Penskoran, dan Lainnya.',
            },
            {
                anchor: 'doc-item',
                title: 'Unduh & kelola',
                text: 'Klik untuk mengunduh; ganti file atau hapus dari menu ⋮.',
            },
        ],
    },
    log: {
        title: 'Log Aktivitas',
        steps: [
            {
                anchor: 'log-filters',
                title: 'Filter',
                text: 'Cari dan saring berdasarkan kategori dan tanggal.',
            },
            {
                anchor: 'log-table',
                mobileAnchor: 'log-cards',
                title: 'Catatan aktivitas',
                text: 'Semua perubahan oleh pengguna PIN tercatat: apa yang diubah, kapan, dan dari perangkat mana.',
            },
            {
                anchor: 'log-export',
                title: 'Ekspor CSV',
                text: 'Unduh log sesuai filter untuk arsip.',
            },
        ],
    },
    'set-sekolah': {
        title: 'Pengaturan Sekolah',
        steps: [
            {
                anchor: 'settings-tabs',
                title: 'Tab pengaturan',
                text: 'Pengaturan dibagi menjadi enam tab. Setiap tab punya tutorialnya sendiri.',
            },
            {
                anchor: 'set-identity',
                title: 'Identitas sekolah',
                text: 'Nama sekolah tampil di kop rapor; Tempat ditulis sebelum tanggal rapor (mis. Wonorejo, 18 December 2026).',
            },
            {
                anchor: 'set-principal',
                title: 'Kepala sekolah',
                text: 'Nama dan NBM kepala sekolah untuk tanda tangan rapor.',
            },
            {
                anchor: 'set-logos',
                title: 'Logo',
                text: 'Logo sekolah untuk kiri kop rapor. Logo National Geographic Learning sudah tersedia otomatis.',
            },
            {
                anchor: 'set-save',
                title: 'Simpan',
                text: 'Simpan perubahan (Alt + S).',
            },
        ],
    },
    'set-rapor': {
        title: 'Pengaturan Rapor',
        steps: [
            {
                anchor: 'rl-preset',
                title: 'Preset',
                text: 'Pilih tata letak cepat: Lengkap, Ringkas, atau Minimal.',
            },
            {
                anchor: 'rl-sections',
                title: 'Bagian rapor',
                text: 'Nyalakan atau matikan bagian yang tampil di rapor.',
            },
            {
                anchor: 'rl-header',
                title: 'Kop & logo',
                text: 'Atur baris tambahan dan judul di kop rapor. Logo National Geographic sudah tersedia otomatis.',
            },
            {
                anchor: 'rl-signatures',
                title: 'Tanda tangan',
                text: 'Pilih penanda tangan dan apakah NBM ditampilkan.',
            },
            {
                anchor: 'rl-preview',
                title: 'Pratinjau langsung',
                text: 'Setiap perubahan langsung terlihat di sini.',
            },
            {
                anchor: 'rl-save',
                title: 'Simpan',
                text: 'Menyimpan tata letak rapor memerlukan PIN.',
            },
            {
                title: 'Rapor final tidak berubah',
                text: 'Perubahan hanya berlaku untuk kelas Draft dan finalisasi berikutnya.',
            },
        ],
    },
    'set-penilaian': {
        title: 'Pengaturan Penilaian',
        steps: [
            {
                anchor: 'sc-term',
                title: 'Periode',
                text: 'Pengaturan penilaian berlaku per periode.',
            },
            {
                anchor: 'sc-grade',
                title: 'Jenjang',
                text: 'Setiap jenjang (Kelas 3–6) punya pengaturan sendiri.',
            },
            {
                anchor: 'sc-thresholds',
                title: 'Batas level',
                text: 'Nilai minimum per skill untuk Intermediate dan Advanced. Level total memakai batas yang sama ×4.',
            },
            {
                anchor: 'sc-max',
                title: 'Rentang nilai',
                text: 'Pratinjau rentang nilai tiap level, per skill dan total.',
            },
            {
                anchor: 'sc-impact',
                title: 'Lihat dampak',
                text: 'Sebelum menyimpan, lihat berapa siswa yang levelnya akan berubah.',
            },
            {
                title: 'Hanya kelas Draft yang berubah',
                text: 'Kelas yang sudah final tetap memakai pengaturan saat finalisasi.',
            },
        ],
    },
    'set-deskriptor': {
        title: 'Pengaturan Deskriptor',
        steps: [
            {
                anchor: 'ds-level',
                title: 'Pilih level',
                text: 'Deskriptor diatur per level Beginner, Intermediate, dan Advanced.',
            },
            {
                anchor: 'ds-meta',
                title: 'Komentar otomatis',
                text: 'Teks yang tampil di rapor bila guru tidak menulis komentar sendiri.',
            },
            {
                anchor: 'ds-cando',
                title: 'Can-do & next steps',
                text: 'Pernyataan kemampuan dan langkah berikutnya untuk setiap skill.',
            },
            {
                anchor: 'ds-reset',
                title: 'Kembalikan ke teks asli',
                text: 'Kembalikan semua teks ke isi bawaan.',
            },
        ],
    },
    'set-keamanan': {
        title: 'Pengaturan Keamanan',
        steps: [
            {
                anchor: 'sec-pin',
                title: 'Ganti PIN',
                text: 'Setelah PIN diganti, semua perangkat lain harus masuk ulang.',
            },
            {
                anchor: 'sec-recovery',
                title: 'Kode pemulihan',
                text: 'Dipakai bila PIN lupa. Simpan di tempat aman; membuat kode baru membatalkan kode lama.',
            },
            {
                anchor: 'sec-session',
                title: 'Batas waktu sesi',
                text: 'Layar terkunci otomatis bila tidak dipakai selama waktu ini.',
            },
            {
                anchor: 'sec-tour',
                title: 'Tutorial',
                text: 'Tampilkan lagi semua tutorial otomatis di perangkat ini.',
            },
        ],
    },
    'set-cadangan': {
        title: 'Cadangan',
        steps: [
            {
                anchor: 'bk-download',
                title: 'Unduh cadangan',
                text: 'Satu file .zip berisi semua data. Simpan setiap akhir semester.',
            },
            {
                anchor: 'bk-restore',
                title: 'Pulihkan',
                text: 'Mengganti SEMUA data dengan isi cadangan. Ketik PULIHKAN dan masukkan PIN untuk melanjutkan.',
            },
        ],
    },
    'flow-finalkan': {
        title: 'Finalkan kelas',
        steps: [
            {
                anchor: 'fin-summary',
                title: 'Ringkasan',
                text: 'Sebaran level Beginner / Intermediate / Advanced siswa yang akan dikunci.',
            },
            {
                anchor: 'fin-incomplete',
                title: 'Belum lengkap',
                text: 'Siswa yang nilainya belum lengkap (kelas 6: termasuk level National Geographic). Klik Lompat untuk langsung ke barisnya.',
            },
            {
                anchor: 'fin-check',
                title: 'Konfirmasi',
                text: 'Centang untuk memastikan nilai sudah diperiksa.',
            },
            {
                anchor: 'fin-submit',
                title: 'Finalkan',
                text: 'Perlu PIN. Nilai terkunci dan rapor siap dicetak; bisa dibatalkan dengan alasan.',
            },
        ],
    },
    'flow-panel-siswa': {
        title: 'Panel siswa',
        steps: [
            {
                anchor: 'panel-scores',
                title: 'Nilai',
                text: 'Isi nilai 5–25 per skill. Enter pindah ke skill berikutnya. Kelas 6: pilih juga level National Geographic.',
            },
            {
                anchor: 'panel-summary',
                title: 'Ringkasan & grafik',
                text: 'Level per skill, total nilai, dan grafik garis nilai tiap skill.',
            },
            {
                anchor: 'panel-auto',
                title: 'Komentar otomatis',
                text: 'Teks bawaan dari deskriptor level siswa.',
            },
            {
                anchor: 'panel-comment',
                title: 'Komentar guru',
                text: 'Tulis komentar sendiri; kosongkan untuk memakai komentar otomatis. Tersimpan otomatis.',
            },
            {
                anchor: 'panel-nav',
                title: 'Siswa berikutnya',
                text: 'Pindah ke siswa sebelumnya/berikutnya (tombol [ dan ]).',
            },
        ],
    },
    'flow-pratinjau-impor': {
        title: 'Pratinjau impor',
        steps: [
            {
                anchor: 'ip-summary',
                title: 'Ringkasan',
                text: 'Jumlah baris baru, diperbarui, tidak berubah, peringatan, dan galat.',
            },
            {
                anchor: 'ip-filter',
                title: 'Saring status',
                text: 'Tampilkan hanya baris dengan status tertentu.',
            },
            {
                anchor: 'ip-table',
                title: 'Keterangan per baris',
                text: 'Perubahan dan pesan galat untuk setiap baris file.',
            },
            {
                anchor: 'ip-actions',
                title: 'Impor atau batal',
                text: 'Impor baris yang valid, unduh laporan galat, atau batalkan.',
            },
        ],
    },
    'flow-cetak-semua': {
        title: 'Cetak/Unduh semua',
        steps: [
            {
                anchor: 'batch-progress',
                title: 'Proses bertahap',
                text: 'Rapor dibuat sedikit demi sedikit supaya aman untuk hosting.',
            },
            {
                anchor: 'batch-failed',
                title: 'Gagal & coba ulang',
                text: 'Rapor yang gagal dibuat bisa dicoba ulang.',
            },
            {
                anchor: 'batch-result',
                title: 'Hasil',
                text: 'Setelah selesai, unduh atau cetak hasilnya.',
            },
            {
                title: 'Bisa dilanjutkan',
                text: 'Bila tab tertutup, buka lagi menu Rapor & Cetak untuk melanjutkan proses.',
            },
        ],
    },
};
