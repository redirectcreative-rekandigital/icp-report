# ICP CEFR Report — SD Muhammadiyah Wonorejo

Aplikasi web rapor kemampuan bahasa Inggris berbasis CEFR (pengganti `ICP_English_Progress_Report.xlsx`).
Spesifikasi lengkap: [`docs/prd/`](docs/prd/00-overview.md). File Excel sumber: `docs/source/`.

Stack: Laravel 12 · Inertia v2 · Vue 3 + TypeScript · Tailwind CSS v4 · shadcn-vue (Reka UI) · MySQL.

## Status milestone

| Milestone | Status |
|---|---|
| **M1 — Fondasi**: layout Navy Akademik (sidebar, header, navigasi ponsel), PIN & setup awal, kode pemulihan, sesi & konfirmasi PIN, pengaturan sekolah & keamanan, log aktivitas | ✅ selesai |
| **M2 — Data master**: periode & semester (salin pengaturan penilaian), kelas (salin dari periode lain), siswa (filter, aksi massal), impor siswa dari Excel (pratinjau → simpan, laporan galat), ekspor daftar siswa | ✅ selesai |
| **M3 — Input nilai & mesin CEFR**: tabel nilai ala spreadsheet (autosave, offline, konflik 409), kalkulator CEFR PHP + TS dengan uji paritas Excel, komentar guru/otomatis, finalisasi & pembatalan (snapshot), pengaturan penilaian & deskriptor | ✅ selesai |
| **M4 — Rapor PDF**: rapor 1 halaman F4/A4 (DomPDF, tanpa Chrome), pratinjau identik dengan PDF, PDF per siswa & semua siswa (gabung/ZIP, bertahap tanpa queue, bisa dilanjutkan), cetak, pengaturan layout (preset, sakelar, kop, logo Muhammadiyah, warna, tanda tangan + NBM), snapshot saat finalisasi | ✅ selesai |
| **M5 — Dashboard & riwayat**: KPI, sebaran level per kelas/jenjang, rata-rata per skill, status, tren antar periode, sorotan otomatis, progres kelas, tab Perbandingan periode; profil siswa dengan grafik perkembangan, riwayat level & ringkasan naratif | ✅ selesai |
| **M6 — Pemolesan & rilis**: impor nilai kelas & file Excel lama, ekspor rekap kelas/periode, menu Dokumen, cadangan & pulihkan data, installer web, error boundary + laporan error klien, daftar pintasan (?), galeri `/_ui`, panduan deploy | ✅ selesai |

## Menjalankan di Laragon

```bash
composer install
npm install
cp .env.example .env          # DB: MySQL Laragon, database `icp_report`
php artisan key:generate
php artisan migrate --seed    # tabel + level CEFR & 20 deskriptor dari Excel
php artisan db:seed --class=DemoSeeder   # opsional: 2026/2027 S1, kelas 3A–6B, 22 siswa contoh Excel
npm run dev                   # atau `npm run build`
```

Buka `http://icp-report.test` (virtual host Laragon) atau `php artisan serve`.
Pertama kali dibuka → wizard **Setup awal** (identitas sekolah → buat PIN → simpan kode pemulihan).

PIN terlupa dan kode pemulihan hilang: `php artisan pin:reset`.

## Deploy

Lihat [`docs/DEPLOY.md`](docs/DEPLOY.md) (shared hosting cPanel, installer web, pembaruan, cadangan).

## Pengujian

```bash
./vendor/bin/pest      # SQLite in-memory (termasuk uji paritas CEFR)
npm test               # Vitest: uji paritas lib/cefr.ts dengan fixture yang sama
npm run types:check
npm run lint:check
```

## Panduan interaktif (tutorial)

Setiap menu punya tutorial singkat yang menyorot satu bagian layar sambil meredupkan sisanya
(PRD: [`docs/prd/prd-tutor.md`](docs/prd/prd-tutor.md)).

- **Muncul otomatis sekali** per perangkat/browser saat menu pertama kali dibuka; bisa ditutup kapan saja tanpa mengganggu.
- **Tombol "Tutorial"** di kanan judul halaman (ikon saja di ponsel) untuk menjalankannya lagi.
- **Tombol `?`** kecil di judul dialog/panel: finalisasi, panel siswa, pembuatan PDF massal, dan pratinjau impor.
- **Tampilkan ulang semua tutorial**: Pengaturan → Keamanan, atau dialog pintasan (`?`).
- Semua teks tutorial ada di satu file: [`resources/js/tours/content.ts`](resources/js/tours/content.ts).
- Auto-start ditunda bila ada dialog terbuka atau halaman belum siap; dalam mode private (penyimpanan diblokir)
  tutorial hanya bisa dibuka manual. Menghormati `prefers-reduced-motion`.

## Catatan teknis

- Satu PIN sekolah 4 digit (tanpa akun). Middleware: `pin.verified` (sesi + idle timeout + batas 12 jam),
  `pin.recent` (PIN ≤ 5 menit untuk aksi sensitif → klien menampilkan `PinConfirmDialog`).
- Rate limit PIN: 5×/15 menit per IP, 20×/jam global → kunci 30 menit.
- PIN & kode pemulihan hanya disimpan sebagai hash bcrypt; kode pemulihan yang sedang ditampilkan
  disimpan terenkripsi di sesi.
- `X-Frame-Options: SAMEORIGIN` (bukan DENY) karena cetak PDF memakai iframe same-origin (PRD-05 §7.3).
- PDF: DomPDF + font DejaVu Sans (ter-embed, subset), F4 = 215 × 330 mm. Uji otomatis memastikan tepat 1 halaman untuk semua preset, kertas, margin, dan komentar maksimum.
- Cetak massal: browser memanggil `/rapor/batch/{id}/next` berulang (maks. 5 rapor atau 20 detik per permintaan), lalu digabung FPDI/ZIP.
- Tanpa queue worker; pembersihan log (365 hari) via scheduler atau oportunistik saat login PIN.
