# PRD-00 — Ringkasan Produk: Web Rapor CEFR International Class Program

| Item | Isi |
|---|---|
| Produk | **ICP CEFR Report** — aplikasi web pengelolaan nilai & rapor kemampuan bahasa Inggris berbasis CEFR |
| Versi dokumen | 1.0 (draft untuk pengembangan) |
| Sekolah | **SD Muhammadiyah Wonorejo** |
| Pengguna | Satu sekolah, akses bersama dengan **satu PIN sekolah 4 digit** |
| Pengganti dari | `ICP_English_Progress_Report.xlsx` (program rapor Excel) |
| Bahasa | UI **Bahasa Indonesia**; rapor (layar & PDF) **Bahasa Inggris** |
| Stack | Laravel 12 + Inertia.js + Vue 3 (TypeScript) + Tailwind CSS + shadcn-vue — lihat PRD-01 |
| Hosting | Shared hosting (cPanel), pengembangan lokal di Laragon (PHP 8.3) |

## Daftar dokumen PRD

| No | Dokumen | Isi |
|---|---|---|
| 00 | `00-overview.md` | Latar belakang, tujuan, cakupan, persona, peta halaman, rencana rilis |
| 01 | `01-architecture.md` | Arsitektur, stack, struktur folder, database (ERD), deployment shared hosting |
| 02 | `02-pin-access.md` | Akses PIN, sesi, keamanan, kode pemulihan, log aktivitas |
| 03 | `03-master-data-import.md` | Periode, kelas, guru, siswa, impor/ekspor Excel, dokumen (opsional) |
| 04 | `04-scoring-cefr-engine.md` | Alur input nilai, autosave, mesin hitung CEFR, finalisasi & pembatalan |
| 05 | `05-report-pdf.md` | Desain rapor, pengaturan layout (preset & sakelar), kertas F4/A4, PDF per siswa & semua siswa, cetak |
| 06 | `06-dashboard-history.md` | Dashboard rekap, riwayat semester, perkembangan siswa |
| 07 | `07-design-system-ux.md` | Tema terpilih (Navy Akademik), warna, tipografi, komponen, animasi, loading, error handling, aksesibilitas |
| 08 | `08-navigation-mobile.md` | Menu **Kelas** (sub menu per jenjang, tab Input Nilai/Rapor), pemindah kelas cepat, tampilan mobile & responsif |

---

## 1. Latar belakang

Sekolah menjalankan **International Class Program (ICP)** bilingual untuk mapel bahasa Inggris bersama mitra penyedia program (tahun ke-4). Sekolah telah memiliki:

1. Instrumen tes Kelas 3–6 (Listening, Reading, Writing, Speaking; masing-masing skor 0–20).
2. Pedoman penskoran yang mengonversi skor ke level CEFR **Pre-A1, A1, A2, B1, B2** dengan ambang batas berbeda per kelas.
3. Program rapor Excel yang menghitung level dan mencetak rapor berbahasa Inggris.

Excel bekerja, tetapi punya keterbatasan: satu file per periode, rawan rumus terhapus, sulit dipakai bersama, tidak menyimpan riwayat antar semester, dan cetak rapor harus satu per satu. Web ini memindahkan **seluruh fungsi Excel** ke aplikasi web dengan pengalaman yang lebih nyaman, aman, dan menyimpan riwayat.

## 2. Tujuan

| Kode | Tujuan | Ukuran keberhasilan |
|---|---|---|
| G1 | Guru memasukkan nilai satu kelas dengan cepat dan tanpa kesalahan | Input 30 siswa × 4 skill ≤ 10 menit; 0 nilai di luar rentang tersimpan |
| G2 | Hasil level **identik** dengan Excel | 100% kasus uji paritas (PRD-04 §7) lulus |
| G3 | Rapor siap cetak tanpa pengeditan manual | Rapor PDF tepat 1 halaman (F4 bawaan, A4 opsional); PDF semua siswa satu kelas (≤ 40 siswa) berhasil di shared hosting |
| G4 | Riwayat perkembangan siswa antar semester tersedia | Halaman siswa menampilkan level setiap periode yang sudah difinalkan |
| G5 | Rekap untuk kepala sekolah & mitra | Dashboard sebaran level per kelas dan per angkatan |
| G6 | Tampilan jelas, formal, berwarna, interaktif | Kontras teks memenuhi WCAG AA; semua aksi punya status loading/sukses/gagal |

## 3. Cakupan

### Termasuk (v1)
- Akses dengan **satu PIN sekolah 4 digit** (tanpa akun/login), setup awal, kode pemulihan.
- Data master: tahun ajaran, semester (periode), kelas (3A, 3B, …), wali kelas & guru bahasa Inggris per kelas, siswa.
- **Impor** siswa dan nilai dari Excel; **ekspor** rekap nilai ke Excel.
- **Input nilai total per skill** (0–20; kelipatan 0,5) dalam tabel ala spreadsheet dengan autosave.
- Mesin hitung CEFR (persentase, level per skill, overall berbobot, status terhadap level harapan, skill terkuat) — logika sama dengan Excel.
- Komentar guru (opsional) dengan komentar otomatis sebagai bawaan.
- **Finalisasi** kelas (mengunci nilai & level) dan **Batalkan Finalisasi** (membuka kunci dengan alasan + PIN).
- Rapor berbahasa Inggris: pratinjau di layar, **unduh PDF per siswa & semua siswa** satu kelas, **cetak** langsung; kertas **F4 (bawaan) atau A4**.
- **Pengaturan layout rapor** berbasis preset (Lengkap/Ringkas/Minimal) dan sakelar bagian, kop dengan logo sekolah + **logo Muhammadiyah (opsional)**, penanda tangan dengan **NBM**.
- **Riwayat semester** & grafik perkembangan per siswa.
- **Dashboard rekap**.
- Pengaturan: identitas sekolah, logo, ambang batas, bobot, level harapan, deskriptor, ganti PIN, cadangan data.
- Menu **Dokumen (opsional)**: unggah/unduh file instrumen tes (Word/PDF).

### Tidak termasuk (v1)
- Tes online / siswa mengerjakan soal di web.
- Akun per pengguna, peran berbeda, portal orang tua.
- Banyak sekolah (multi-tenant).
- Input rincian rubrik (Writing per Part, kriteria Speaking) — **hanya nilai total**, sesuai Excel. (Kandidat v2.)
- Pengiriman rapor via email/WhatsApp. (Kandidat v2.)
- Aplikasi mobile native.

## 4. Persona (semua memakai PIN yang sama)

| Persona | Kebutuhan utama | Halaman yang sering dipakai |
|---|---|---|
| **Koordinator ICP** (admin praktis) | Menyiapkan periode, kelas, siswa, pengaturan; memfinalkan; mencetak semua rapor | Pengaturan, Periode & Kelas, Siswa, Kelas |
| **Guru bahasa Inggris / tutor mitra** | Memasukkan nilai cepat, menulis komentar | Kelas → tab Input Nilai |
| **Wali kelas** | Memeriksa & mencetak rapor kelasnya | Kelas → tab Rapor & Cetak |
| **Kepala sekolah / mitra** | Melihat rekap capaian | Dashboard |

> Karena PIN dipakai bersama, sistem tidak tahu *siapa* yang mengubah data. Log aktivitas mencatat **apa, kapan, dari perangkat mana** (IP + user agent ringkas). Lihat PRD-02.

## 5. Peta halaman (sitemap)

```
/pin                         Masuk PIN
/setup                       Setup awal (hanya saat PIN belum dibuat)
/                            Dashboard
/kelas                       Semua kelas (kartu per jenjang: progres, Draft/Final, aksi cepat)
/kelas/{kelas}/nilai         Tab Input Nilai (tabel nilai kelas)
/kelas/{kelas}/rapor         Tab Rapor & Cetak (daftar rapor, unduh semua, cetak)
/kelas/{kelas}/rapor/{siswa} Pratinjau rapor satu siswa
/kelas/{kelas}/riwayat       Tab Riwayat finalisasi kelas
/siswa                       Data siswa (cari, tambah, edit, impor)
/siswa/{id}                  Profil & riwayat perkembangan siswa
/periode                     Tahun ajaran & semester, kelas per periode
/impor                       Pusat impor/ekspor (siswa, nilai)
/dokumen                     Dokumen instrumen tes (opsional)
/pengaturan                  Tab: Sekolah • Rapor (layout) • Penilaian • Deskriptor • Keamanan • Cadangan
/log                         Log aktivitas
```

Navigasi utama (sidebar): **Dashboard, Kelas ▸, Siswa, Periode & Kelas, Impor/Ekspor, Dokumen, Pengaturan**. Menu **Kelas** menggabungkan Input Nilai dan Rapor, dengan **sub menu kelas yang dikelompokkan per jenjang**:

```
Kelas
 ├─ Semua kelas
 ├─ Kelas 3   3A ✓  3B 18/25
 ├─ Kelas 4   4A 🔒  4B 0/24
 ├─ Kelas 5   5A 🔒  5B 22/25
 └─ Kelas 6   6A 🔒  6B 9/24

Halaman kelas → [ Input Nilai ] [ Rapor & Cetak ] [ Riwayat ]
(Draft → tab Input Nilai terbuka otomatis; Final → tab Rapor & Cetak)
```
Di mobile, sidebar diganti top app bar + navigasi bawah (detail PRD-08).

Header menampilkan **periode aktif** (mis. "2026/2027 • Semester 1") dan tombol **Kunci layar**.

## 6. Alur utama (end-to-end)

1. **Setup awal** (sekali): buat PIN, isi nama sekolah → simpan kode pemulihan.
2. **Persiapan periode**: buat tahun ajaran + semester → buat kelas (otomatis menyalin pengaturan penilaian periode sebelumnya) → impor/tambahkan siswa ke kelas.
3. **Input nilai** per kelas (autosave) → lengkapi komentar bila perlu.
4. **Finalkan** kelas → nilai & level dikunci, snapshot rapor dibuat.
5. **Rapor & Cetak** (tab di halaman kelas yang sama): unduh PDF per siswa / semua siswa, atau cetak langsung.
6. Bila ada koreksi: **Batalkan Finalisasi** (alasan + PIN) → edit → finalkan lagi.
7. **Dashboard** & **riwayat** dipakai untuk evaluasi program.

## 7. Aturan bisnis inti (ringkas — detail di PRD-04)

- Skor per skill 0–20 (kelipatan 0,5). Maksimum skor dapat diubah per periode/kelas di Pengaturan.
- Persentase = skor ÷ maksimum × 100.
- Level = level tertinggi yang ambang batasnya ≤ persentase, menurut tabel **kelas (3–6)** siswa.
- Overall = rata-rata berbobot 4 persentase (bawaan 25% masing-masing), **hanya jika keempat skor terisi**.
- Status = Below / Meeting / Above Expected berdasarkan rentang level harapan per kelas.
- Ambang batas bawaan:

| Level | Kelas 3 | Kelas 4 | Kelas 5 | Kelas 6 |
|---|---|---|---|---|
| Pre-A1 | 0 | 0 | 0 | 0 |
| A1 | 60 | 50 | 30 | 25 |
| A2 | 85 | 80 | 55 | 45 |
| B1 | – | – | 85 | 70 |
| B2 | – | – | – | 90 |
| **Level harapan** | Pre-A1 – A1 | A1 | A1 – A2 | A2 – B1 |

## 8. Asumsi & batasan

- Satu sekolah, ± 4–12 kelas per periode, ≤ 40 siswa per kelas, ≤ 500 siswa aktif.
- Shared hosting: PHP ≥ 8.2, MySQL/MariaDB, **tanpa** Node.js, **tanpa** proses queue/worker permanen, batas waktu eksekusi ± 30–60 detik, cron mungkin tersedia (tidak diwajibkan).
- Perangkat guru: laptop/PC (utama), tablet, ponsel (terbatas untuk lihat rapor & dashboard).
- Koneksi internet sekolah bisa tidak stabil → autosave harus tahan gangguan.

## 9. Risiko & mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| PIN 4 digit mudah ditebak/bocor | Akses tidak sah ke data siswa | Rate limit & penguncian, sesi berakhir otomatis, PIN ulang untuk aksi sensitif, log aktivitas, opsi ganti PIN (PRD-02) |
| Batas waktu eksekusi saat cetak massal | PDF gagal | Pembuatan bertahap (chunk) dengan progress bar (PRD-05) |
| Hasil level berbeda dari Excel | Kepercayaan hilang | Uji paritas otomatis dengan data Excel (PRD-04 §7) |
| Dua guru mengedit kelas yang sama | Nilai tertimpa | Penguncian optimistis per baris (versi), notifikasi konflik (PRD-04) |
| Hosting tanpa SSH | Sulit migrasi/reset | Installer web sederhana, kode pemulihan PIN, panduan deploy (PRD-01) |

## 10. Rencana rilis (milestone)

| Milestone | Isi | Estimasi |
|---|---|---|
| M1 — Fondasi | Proyek Laravel+Inertia+Vue+shadcn-vue, layout, PIN & setup, pengaturan sekolah | 1 minggu |
| M2 — Data master | Periode, kelas, siswa, impor siswa | 1 minggu |
| M3 — Input & mesin CEFR | Tabel nilai, autosave, kalkulator + uji paritas, komentar, finalisasi/pembatalan | 1,5 minggu |
| M4 — Rapor | Pratinjau, pengaturan layout, PDF per siswa & semua siswa (F4/A4), cetak | 1,5 minggu |
| M5 — Dashboard & riwayat | Rekap, grafik, profil siswa | 1 minggu |
| M6 — Pemolesan & rilis | Animasi, error state, aksesibilitas, impor nilai, ekspor, cadangan, deploy | 1 minggu |

## 11. Glosarium

| Istilah | Arti |
|---|---|
| CEFR | Common European Framework of Reference for Languages |
| Periode | Kombinasi tahun ajaran + semester (mis. 2026/2027 Semester 1) |
| Kelas | Rombel dalam periode, mis. 3A; punya *grade* 3–6 |
| Enrollment | Keanggotaan siswa di satu kelas pada satu periode |
| Skor mentah | Nilai 0–20 per skill hasil tes |
| Finalisasi | Mengunci nilai & menyimpan snapshot hasil/rapor kelas |
| Snapshot | Salinan hasil perhitungan & teks rapor saat finalisasi, tidak berubah walau pengaturan diubah |
