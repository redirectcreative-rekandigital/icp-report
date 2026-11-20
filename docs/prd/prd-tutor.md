# PRD-TUTOR — Tutorial / User Guide Interaktif

> Status: **disetujui pengguna (2026-11-22)**, siap dieksekusi.
> Dokumen ini lengkap supaya pekerjaan bisa **dilanjutkan oleh model/pengembang lain** bila terputus.
> Progres eksekusi dicatat di **§9 Checklist** — centang `[x]` setiap selesai.

## 1. Tujuan

Guru dan koordinator memahami setiap menu dan alur penting **tanpa membaca manual**:
penjelasan langkah demi langkah yang **menyorot satu bagian** layar sementara **sisanya diredupkan**,
dengan tombol **Sebelumnya / Berikutnya / Tutup**.

## 2. Keputusan (hasil sesi tanya-jawab)

| # | Keputusan | Pilihan |
|---|---|---|
| D1 | Teknologi | **driver.js** (npm, MIT, ± 5 KB), diberi gaya tema Navy (PRD-07) & teks Bahasa Indonesia |
| D2 | Pemicu | **Otomatis sekali** saat menu/dialog pertama kali dibuka di perangkat itu (bisa langsung ditutup) **+ tombol Tutorial** |
| D3 | Cakupan | **Semua menu/tab + flow utama** (daftar §6) |
| D4 | Tombol | Di **samping kanan judul menu**: tombol outline kecil **ikon `CircleHelp` + teks "Tutorial"**; di ponsel (< 640 px) **ikon saja** |
| D5 | Flow kecil | **Tombol `?` kecil di judul dialog/panel** + otomatis sekali saat dialog pertama kali dibuka |
| D6 | Penyimpanan "sudah dilihat" | **Per perangkat/browser** — `localStorage` |
| D7 | Reset | Tombol **"Tampilkan ulang semua tutorial"** di dialog pintasan (`?`) **dan** Pengaturan → Keamanan |
| D8 | Elemen tidak ada di layar | Langkah **tetap ditampilkan sebagai kotak di tengah layar tanpa sorotan** (tidak dilewati) |
| D9 | Panjang | **Singkat: 4–8 langkah** per tutorial (Dashboard boleh ± 10 karena memuat pengenalan aplikasi), 1–2 kalimat per langkah, penanda **"Langkah 2 dari 6"** |
| D10 | Pengenalan aplikasi | Diletakkan **di awal tutorial Dashboard** (sidebar & menu Kelas per jenjang, periode aktif, Kunci layar, navigasi bawah ponsel) |
| A1 | (asumsi, disetujui) | Halaman **PIN, Setup awal, Kode pemulihan** tidak diberi tutorial |
| A2 | (asumsi, disetujui) | Teks ditulis pengembang berdasarkan PRD; **semua teks di satu file** (`resources/js/tours/content.ts`) agar mudah dikoreksi |
| A3 | (asumsi, disetujui) | Auto-start **ditunda** bila ada dialog lain terbuka atau halaman masih memuat |

## 3. Perilaku (UX)

### 3.1 Popover tutorial
- Kotak putih `rounded-xl`, bayangan, judul tebal warna `--primary`, isi 14 px warna `--foreground`.
- Penanda progres: **"Langkah {n} dari {total}"** (teks kecil `--muted-foreground`).
- Tombol: **Sebelumnya** (outline; disembunyikan di langkah 1), **Berikutnya** (primer) → di langkah terakhir menjadi **Selesai**, dan **×** (Tutup, `aria-label="Tutup tutorial"`).
- Overlay redup: hitam opacity **0,6**; elemen tersorot diberi ruang 6 px & sudut 10 px.
- Keyboard: `→` berikutnya, `←` sebelumnya, `Esc` tutup (bawaan driver.js).
- `prefers-reduced-motion` → animasi driver.js dimatikan.
- Klik overlay **tidak** menutup tutorial (hindari tertutup tak sengaja); tutup lewat × / Esc / Selesai.
- Setelah tutorial ditutup dengan cara apa pun → ditandai sudah dilihat (§3.3).

### 3.2 Langkah tanpa elemen (D8)
- Target dicari via `[data-tour="<anchor>"]`; dipilih **elemen pertama yang terlihat** (punya ukuran & tidak `display:none`).
- Bila tidak ada → langkah tampil **di tengah layar tanpa sorotan** (driver.js: step tanpa `element`).
- Anchor boleh punya **varian ponsel**: `mobileAnchor` dicoba dulu bila lebar < 768 px (mis. navigasi bawah, kartu siswa).

### 3.3 Auto-start sekali (D2, D5, D6, A3)
- Kunci `localStorage`: `tour.seen.<tourId>` = ISO timestamp.
- Halaman: 700 ms setelah halaman ter-mount, **bila** belum dilihat **dan** tidak ada `[role="dialog"][data-state="open"]` **dan** tidak sedang navigasi Inertia → mulai. Bila syarat gagal, coba lagi tiap 1 s maks. 5 kali, lalu menyerah (tetap belum dilihat).
- Dialog/panel (flow): 400 ms setelah dialog terbuka & kontennya ter-render, bila belum dilihat.
- Hanya **satu** tutorial aktif sekaligus; memulai tutorial baru menutup yang lama.
- Private mode / storage diblokir → semua akses dibungkus `try/catch`; tutorial tetap bisa dibuka manual, auto-start tidak berjalan (hindari muncul terus).

### 3.4 Tombol
- **TourButton** (halaman): `variant="outline" size="sm"`, ikon `CircleHelp`, teks "Tutorial" (`hidden sm:inline`), `aria-label="Tutorial halaman ini"`. Diletakkan **langsung di kanan judul `h1`** (inline, sejajar baseline), bukan di grup aksi kanan.
- **TourIconButton** (dialog): tombol ghost ikon `CircleHelp` ukuran 32 px di sebelah judul dialog, `aria-label="Tutorial"`.

### 3.5 Reset (D7)
- Fungsi `resetTours()` menghapus semua kunci `tour.seen.*`.
- **ShortcutsDialog** (`?`): tombol "Tampilkan ulang semua tutorial" di bagian bawah.
- **Pengaturan → Keamanan**: kartu kecil "Tutorial" dengan tombol yang sama. Toast: "Tutorial akan tampil lagi otomatis di perangkat ini."

## 4. Arsitektur

```
resources/js/tours/
  content.ts        # SEMUA teks & daftar langkah per tourId (satu-satunya file yang perlu diedit untuk mengubah isi)
  types.ts          # TourId, TourStep, TourDef
resources/js/composables/useTour.ts   # startTour, autoStartTour, resetTours, isTourSeen; adaptor driver.js
resources/js/components/app/TourButton.vue      # tombol halaman (ikon + "Tutorial")
resources/js/components/app/TourIconButton.vue  # tombol ? untuk dialog/panel
resources/css/app.css   # gaya .driver-popover tema Navy (+ pointer-events fix, §7)
```

### 4.1 Tipe
```ts
type TourStep = {
  anchor?: string;        // nilai data-tour; kosong = langkah tengah layar
  mobileAnchor?: string;  // dipakai lebih dulu bila < 768 px
  title: string;
  text: string;           // 1–2 kalimat
  side?: 'top' | 'bottom' | 'left' | 'right';
};
type TourDef = { title: string; steps: TourStep[] };
```

### 4.2 Integrasi halaman
- `PageHeader.vue` mendapat prop opsional `tour?: TourId` → render `<TourButton>` di kanan judul **dan** memanggil `autoStartTour(tour)` saat mount.
- Halaman tanpa `PageHeader` (judul custom): Classes/Show (class switcher), Students/Show, Reports/Preview → letakkan `<TourButton>` manual di kanan judul + `autoStartTour`.
- **Pengaturan**: `SettingsLayout` menerima prop `tour` dari tiap halaman tab.
- **Kelas**: tourId mengikuti tab aktif (`kelas-nilai` / `kelas-rapor` / `kelas-riwayat`).
- `ResponsiveDialog.vue` & panel siswa (Sheet): prop opsional `tour?: TourId` → `<TourIconButton>` di judul + auto-start saat `open` menjadi `true`.

### 4.3 Anchor
Elemen diberi atribut `data-tour="<anchor>"`. Nama anchor = kebab-case, unik per halaman (§6). **Jangan** memakai selector class/struktur DOM — rapuh.

## 5. Gaya (CSS driver.js)
```css
.driver-popover { border-radius: 14px; padding: 18px; max-width: 340px; color: var(--foreground); font-family: inherit; box-shadow: 0 20px 40px rgb(0 0 0 / .25); pointer-events: auto; }
.driver-popover-title { color: var(--primary); font-weight: 700; font-size: 16px; }
.driver-popover-description { font-size: 14px; line-height: 1.5; }
.driver-popover-progress-text { color: var(--muted-foreground); font-size: 12px; }
.driver-popover-next-btn { background: var(--primary); color: #fff; border: 0; text-shadow: none; border-radius: 8px; padding: 6px 12px; }
.driver-popover-prev-btn { background: #fff; color: var(--foreground); border: 1px solid var(--border); border-radius: 8px; padding: 6px 12px; text-shadow: none; }
```
Opsi driver.js: `showProgress: true`, `progressText: 'Langkah {{current}} dari {{total}}'`, `nextBtnText: 'Berikutnya'`, `prevBtnText: 'Sebelumnya'`, `doneBtnText: 'Selesai'`, `overlayOpacity: 0.6`, `stagePadding: 6`, `stageRadius: 10`, `allowClose: true`, `overlayClickBehavior: 'nextStep'` → **ganti** agar klik overlay tidak menutup (pakai fungsi kosong), `animate: !reducedMotion`, `popoverClass: 'icp-tour'`, `onDestroyed` → tandai sudah dilihat.

## 6. Isi tutorial (tourId → langkah)

Format: `anchor` — **Judul**: teks. (`∅` = tengah layar)

### 6.1 `dashboard` — Dashboard (+ pengenalan aplikasi)
1. ∅ — **Selamat datang**: Tutorial singkat ini mengenalkan menu utama dan isi Dashboard. Tekan Berikutnya, atau × untuk menutup.
2. `nav-sidebar` / mobile `nav-bottom` — **Menu utama**: Semua menu ada di sini. Di ponsel, menu ada di bar bawah dan tombol Lainnya.
3. `nav-kelas` — **Menu Kelas per jenjang**: Kelas periode aktif dikelompokkan per jenjang. Angka 18/25 = siswa yang nilainya lengkap; ikon gembok = kelas sudah final.
4. `header-term` — **Periode aktif**: Data yang tampil mengikuti periode aktif. Ganti periode aktif di menu Periode & Kelas.
5. `header-lock` — **Kunci layar**: Kunci layar saat meninggalkan komputer (Alt + L). Masuk lagi dengan PIN sekolah.
6. `dash-filters` — **Filter**: Pilih periode, jenjang, atau tampilkan hanya kelas final.
7. `dash-kpi` — **Ringkasan angka**: Jumlah siswa dinilai, kelas final, rata-rata overall, dan persentase siswa sesuai/di atas harapan.
8. `dash-charts` — **Grafik**: Klik segmen untuk melihat daftar siswa. Ikon tabel menampilkan angka; ikon unduh menyimpan gambar.
9. `dash-progress` — **Progres kelas**: Kelas yang paling belum lengkap ada di atas. Klik Isi Nilai untuk langsung mengisi.
10. `dash-tabs` — **Perbandingan**: Bandingkan dua periode: sebaran level dan siswa yang naik/turun level.

### 6.2 `kelas-index` — Semua kelas
1. `kelas-term` — **Pilih periode**: Kelas yang tampil mengikuti periode ini.
2. `kelas-card` — **Kartu kelas**: Nama kelas, guru, dan progres pengisian nilai. Klik kartu untuk membuka kelas.
3. `kelas-progress` — **Progres**: Abu-abu < 50 %, kuning 50–99 %, hijau = semua siswa lengkap.
4. `kelas-status` — **Status**: Draft = nilai masih bisa diubah; Final = terkunci dan rapor siap dicetak.

### 6.3 `kelas-nilai` — Kelas → Input Nilai
1. `class-switcher` — **Pindah kelas**: Klik nama kelas untuk pindah ke kelas lain; tombol ‹ › ke kelas sebelumnya/berikutnya.
2. `class-tabs` — **Tab kelas**: Input Nilai, Rapor & Cetak, dan Riwayat finalisasi.
3. `score-grid` / mobile `score-list` — **Isi nilai**: Ketik nilai 0–20 (boleh ,5). Enter/↓ ke bawah, Tab ke kanan. Bisa tempel blok dari Excel.
4. `score-levels` — **Level otomatis**: Level CEFR, overall, dan status dihitung langsung saat mengetik.
5. `save-indicator` — **Tersimpan otomatis**: Tidak ada tombol simpan. Status penyimpanan tampil di sini; saat offline, nilai dikirim otomatis begitu koneksi kembali.
6. `score-comment` — **Komentar & panel siswa**: Klik nama atau titik komentar untuk membuka panel siswa.
7. `score-import` — **Impor & ekspor**: Isi nilai offline lewat template Excel, lalu impor. Ekspor menghasilkan rekap kelas.
8. `finalize-btn` — **Finalkan**: Setelah semua lengkap, finalkan kelas untuk mengunci nilai dan menyiapkan rapor (perlu PIN).

### 6.4 `kelas-rapor` — Kelas → Rapor & Cetak
1. `paper-select` — **Ukuran kertas**: F4 (bawaan) atau A4. Pilihan diingat di perangkat ini.
2. `report-actions` / mobile `report-row` — **Per siswa**: Pratinjau, unduh PDF, atau cetak langsung.
3. `report-select` — **Pilih siswa**: Centang beberapa siswa untuk mengunduh/mencetak sebagian saja.
4. `report-download-all` — **Unduh semua**: Satu file PDF atau ZIP berisi satu PDF per siswa.
5. `report-print-all` — **Cetak semua**: Rapor dibuat bertahap lalu dialog cetak dibuka.
6. `unfinalize-btn` — **Batalkan finalisasi**: Untuk koreksi nilai setelah final; wajib alasan dan PIN.

### 6.5 `kelas-riwayat` — Kelas → Riwayat
1. `history-timeline` — **Riwayat finalisasi**: Setiap finalisasi dan pembatalan tercatat dengan waktu, perangkat, dan alasan.
2. ∅ — **Rapor lama tetap aman**: Rapor kelas final memakai data saat finalisasi, walau pengaturan diubah kemudian.

### 6.6 `rapor-preview` — Pratinjau rapor
1. `preview-nav` — **Siswa lain**: Pindah ke siswa sebelumnya/berikutnya (tombol ‹ › atau [ ]).
2. `preview-paper` — **Kertas & zoom**: Lihat tampilan F4 atau A4 dan perbesar/perkecil.
3. `preview-sheet` — **Rapor**: Tampilan ini sama persis dengan PDF. Tanda DRAFT muncul bila kelas belum final.
4. `preview-actions` / mobile `preview-actions-mobile` — **Unduh & cetak**: Unduh PDF atau cetak langsung. Di ponsel bisa dibagikan ke aplikasi lain.

### 6.7 `siswa-index` — Siswa
1. `student-search` — **Cari**: Cari nama atau NIS (tekan / untuk langsung mengetik).
2. `student-filters` / mobile `student-filter-btn` — **Filter**: Tampilkan per periode, kelas, atau status aktif.
3. `student-add` / mobile `student-fab` — **Tambah siswa**: Isi NIS, nama, dan kelas. Untuk banyak siswa, gunakan Impor dari Excel.
4. `student-table` / mobile `student-cards` — **Daftar siswa**: Centang beberapa siswa untuk pindah kelas, keluarkan dari kelas, atau nonaktifkan sekaligus.
5. `student-row-actions` — **Ubah & profil**: Ubah data atau buka profil perkembangan siswa.

### 6.8 `siswa-profil` — Profil siswa
1. `profile-actions` — **Kelola siswa**: Ubah data, nonaktifkan, atau hapus (hapus hanya bila belum ada nilai).
2. `profile-chart` — **Grafik perkembangan**: Overall per semester; warna titik = level, garis putus-putus = belum final.
3. `profile-summary` — **Ringkasan**: Level final terakhir dan cerita perkembangan otomatis.
4. `profile-history` — **Riwayat level**: Level per skill tiap periode dan rapor PDF-nya.

### 6.9 `periode-index` — Periode & Kelas
1. `year-add` — **Tahun ajaran**: Buat tahun ajaran (mis. 2026/2027), lalu tambahkan Semester 1 dan 2.
2. `term-card` — **Semester**: Setiap semester punya kelas sendiri. Pengaturan penilaian disalin otomatis dari periode sebelumnya.
3. `term-activate` — **Periode aktif**: Hanya satu periode aktif; menu Kelas mengikuti periode ini.
4. `term-manage` — **Kelola kelas**: Tambah kelas, wali kelas, dan guru bahasa Inggris.

### 6.10 `periode-detail` — Kelas dalam periode
1. `class-add` — **Tambah kelas**: Isi jenjang, nama kelas, wali kelas (dengan NBM), dan guru bahasa Inggris.
2. `class-copy` — **Salin dari periode lain**: Salin kelas sekaligus siswanya; bisa naik jenjang (3A → 4A) untuk tahun ajaran baru.
3. `class-table` / mobile `class-cards` — **Daftar kelas**: Ubah atau hapus kelas dari menu ⋮. Kelas final tidak bisa diubah.

### 6.11 `impor` — Impor/Ekspor
1. ∅ — **Dua tahap**: Setiap impor menampilkan pratinjau dulu; tidak ada data yang tersimpan sebelum Anda menekan Impor.
2. `import-students` — **Impor siswa**: Unduh template, isi NIS, nama, L/P, dan kelas, lalu unggah.
3. `import-scores` — **Impor nilai kelas**: Pilih kelas, unduh template berisi daftar siswa, isi nilai, lalu unggah. Sel kosong = nilai tidak diubah.
4. `import-overwrite` — **Timpa nilai**: Aktifkan bila ingin mengganti nilai yang sudah ada (perlu PIN saat menyimpan).
5. `import-legacy` — **File Excel lama**: Pindahkan data dari ICP_English_Progress_Report.xlsx (sheet Grade 3–6).
6. `export-card` — **Ekspor**: Rekap periode, rekap kelas, dan daftar siswa dalam Excel.

### 6.12 `dokumen` — Dokumen
1. `doc-upload` — **Unggah**: PDF, Word, atau Excel maksimal 10 MB.
2. `doc-grade` — **Filter jenjang**: Tampilkan dokumen untuk jenjang tertentu.
3. `doc-category` — **Kategori**: Instrumen Tes, Pedoman Penskoran, dan Lainnya.
4. `doc-item` — **Unduh & kelola**: Unduh file, atau ganti/hapus dari menu ⋮.

### 6.13 `log` — Log Aktivitas
1. `log-filters` — **Filter**: Cari dan saring berdasarkan kategori dan tanggal.
2. `log-table` / mobile `log-cards` — **Catatan aktivitas**: Siapa pun yang memakai PIN tercatat: apa yang diubah, kapan, dan dari perangkat mana.
3. `log-export` — **Ekspor CSV**: Unduh log sesuai filter untuk arsip.

### 6.14 Pengaturan (tab)
- `set-sekolah`: `set-identity` **Identitas sekolah** (tampil di kop rapor) • `set-principal` **Kepala sekolah & NBM** • `set-logos` **Logo** (logo sekolah, Muhammadiyah, mitra) • `set-save` **Simpan** (Alt + S).
- `set-rapor`: `rl-preset` **Preset** (Lengkap/Ringkas/Minimal) • `rl-sections` **Bagian rapor** (nyalakan/matikan) • `rl-header` **Kop & logo Muhammadiyah** • `rl-signatures` **Tanda tangan & NBM** • `rl-preview` **Pratinjau langsung** • `rl-save` **Simpan (perlu PIN)**; ∅ **Rapor final tidak berubah**.
- `set-penilaian`: `sc-term` **Periode** • `sc-grade` **Jenjang** • `sc-max` **Maksimum & bobot** • `sc-thresholds` **Ambang batas level** • `sc-impact` **Lihat dampak** sebelum menyimpan • ∅ **Hanya kelas Draft yang berubah**.
- `set-deskriptor`: `ds-level` **Pilih level** • `ds-meta` **Nama level & komentar otomatis** • `ds-cando` **Can-do & next steps per skill** • `ds-reset` **Kembalikan ke teks Excel**.
- `set-keamanan`: `sec-pin` **Ganti PIN** (perangkat lain keluar) • `sec-recovery` **Kode pemulihan** • `sec-session` **Batas waktu sesi** • `sec-tour` **Tutorial** (tampilkan ulang).
- `set-cadangan`: `bk-download` **Unduh cadangan** (simpan tiap akhir semester) • `bk-restore` **Pulihkan** (mengganti semua data; ketik PULIHKAN).

### 6.15 Flow kecil (dialog/panel)
- `flow-finalkan` (FinalizeDialog): `fin-summary` **Ringkasan** (sebaran level & status) • `fin-incomplete` **Belum lengkap** (Lompat ke barisnya) • `fin-check` **Konfirmasi** • `fin-submit` **Finalkan** (perlu PIN; nilai terkunci, bisa dibatalkan dengan alasan).
- `flow-panel-siswa` (StudentPanel): `panel-scores` **Nilai** (input besar; Enter pindah ke skill berikutnya) • `panel-summary` **Ringkasan & radar** • `panel-auto` **Komentar otomatis** • `panel-comment` **Komentar guru** (kosongkan = otomatis; tersimpan otomatis) • `panel-nav` **Siswa berikutnya** ([ ]).
- `flow-pratinjau-impor` (kartu pratinjau di Impor): `ip-summary` **Ringkasan status** • `ip-filter` **Saring status** • `ip-table` **Keterangan per baris** • `ip-actions` **Impor / laporan galat / batal**.
- `flow-cetak-semua` (BatchProgressDialog): `batch-progress` **Proses bertahap** (aman untuk hosting) • `batch-failed` **Gagal & coba ulang** • `batch-result` **Unduh/Cetak hasil** • ∅ **Bisa dilanjutkan** bila tab tertutup.

## 7. Jebakan teknis yang sudah diketahui
1. **Dialog modal (Reka UI) memblok klik di luar dialog** (`pointer-events: none` pada body + `onPointerDownOutside` menutup dialog). Popover driver.js dipasang di `<body>` → wajib:
   - CSS `.driver-popover, .driver-overlay { pointer-events: auto; }`
   - Di `ResponsiveDialog`, `Sheet` panel siswa, dan `AlertDialog` yang punya tutorial: handler `@interact-outside` / `@pointer-down-outside` yang memanggil `event.preventDefault()` bila `event.target` berada di dalam `.driver-popover` atau `.driver-overlay`.
   - Focus trap dialog: tombol popover tetap bisa diklik (klik tetap terkirim walau fokus ditarik kembali).
2. **z-index**: overlay driver.js (10000) > dialog shadcn (50) — sudah aman; jangan turunkan.
3. **Elemen di dalam kontainer scroll** (tabel nilai `overflow-auto`): driver.js menggulir otomatis; pastikan anchor berada pada elemen yang benar-benar tampil.
4. **Pergantian halaman Inertia** saat tutorial aktif → panggil `destroy()` pada event `router.on('before')`.
5. **Halaman dengan deferred props** (Dashboard grafik) → auto-start menunggu sampai anchor grafik muncul atau 5 percobaan (§3.3) — langkah tanpa anchor tetap tampil di tengah (D8).
6. **Tes jsdom**: driver.js membutuhkan `getBoundingClientRect` → aman di jsdom (nol), cukup uji logika (§8).

## 8. Pengujian
- **Vitest (unit)**: `useTour` — penanda sudah dilihat & reset; resolusi anchor (terlihat / varian ponsel / tidak ada → tengah); auto-start tidak berjalan bila sudah dilihat atau ada dialog terbuka; konten: setiap tourId punya 1–10 langkah, setiap langkah punya judul & teks ≤ 220 karakter.
- **Vitest (komponen)**: `PageHeader` dengan prop `tour` merender tombol "Tutorial" di samping judul.
- **E2E manual/skrip** (Playwright + Edge, lihat riwayat M5): buka Dashboard pertama kali → popover muncul, overlay ada; Berikutnya/Sebelumnya mengubah "Langkah n dari N"; Tutup; muat ulang → tidak muncul otomatis; tombol Tutorial memulai lagi; dialog Finalkan → tutorial flow muncul & tombol popover bisa diklik tanpa menutup dialog.
- Regresi: semua tes Pest & Vitest yang ada tetap lulus; `vue-tsc`, `eslint`, `npm run build` bersih.

## 9. Checklist eksekusi
- [x] T1 Pasang `driver.js` (npm), buat `tours/types.ts`, `tours/content.ts` (isi §6)
- [x] T2 `composables/useTour.ts` (start/auto/reset/seen, adaptor driver.js, opsi §5, destroy saat navigasi)
- [x] T3 CSS tema popover + perbaikan pointer-events (§5, §7.1)
- [x] T4 `TourButton.vue`, `TourIconButton.vue`; prop `tour` di `PageHeader`, `SettingsLayout`, `ResponsiveDialog`
- [x] T5 Anchor `data-tour` + tombol di layout global (sidebar, menu Kelas, header periode, Kunci layar, navigasi bawah)
- [x] T6 Anchor + tombol: Dashboard, Kelas (index), Kelas/Show (3 tab), Pratinjau rapor
- [x] T7 Anchor + tombol: Siswa, Profil siswa, Periode, Detail periode, Impor/Ekspor, Dokumen, Log
- [x] T8 Anchor + tombol: 6 tab Pengaturan; reset di Keamanan & ShortcutsDialog
- [x] T9 Flow kecil: FinalizeDialog, StudentPanel, pratinjau impor, BatchProgressDialog (+ guard interact-outside)
- [x] T10 Tes Vitest (§8) + jalankan seluruh suite, lint, types, build
- [ ] T11 Verifikasi browser nyata (Playwright + Edge) skenario §8, termasuk tutorial di dalam dialog
- [x] T12 Update README (fitur tutorial) & ringkasan ke pengguna

> Catatan T11: verifikasi otomatis belum dijalankan (butuh sesi browser + backend hidup). Semua skenario
> sudah tercakup tes jsdom/Vitest; langkah manual di §8 masih perlu dijalankan sekali di Edge sebelum rilis.
