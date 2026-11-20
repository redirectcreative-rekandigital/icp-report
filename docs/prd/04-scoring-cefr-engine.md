# PRD-04 — Input Nilai, Mesin Hitung CEFR & Finalisasi

> Modul paling penting. Guru memasukkan **nilai total per skill** (Listening, Reading, Writing, Speaking; 0–20) seperti di Excel. Level CEFR, overall, status, dan skill terkuat dihitung otomatis. Kelas dapat **difinalkan** (dikunci) dan **dibatalkan finalisasinya** (dibuka kembali untuk koreksi).

## 1. User stories
| ID | Sebagai | Saya ingin | Agar |
|---|---|---|---|
| US-04.1 | Guru | melihat semua kelas dan progres pengisiannya | tahu kelas mana yang belum selesai |
| US-04.2 | Guru | mengetik nilai seperti di Excel (Enter/Tab, tempel dari Excel) | input cepat |
| US-04.3 | Guru | melihat level langsung setelah mengetik | bisa memeriksa kewajaran nilai |
| US-04.4 | Guru | nilai tersimpan otomatis | tidak kehilangan data saat lupa menyimpan/koneksi putus |
| US-04.5 | Guru | menulis atau mengganti komentar otomatis | rapor lebih personal |
| US-04.6 | Koordinator | memfinalkan kelas | nilai tidak berubah setelah rapor dicetak |
| US-04.7 | Koordinator | membatalkan finalisasi dengan alasan | memperbaiki kesalahan nilai |

## 2. Alur pengguna

```
PIN → Kelas (sub menu jenjang / Semua kelas) → tab Input Nilai
      → ketik nilai (autosave + level langsung)
      → panel siswa: komentar
      → cek kelengkapan → [Finalkan] (PIN) → status FINAL (terkunci)
      → tab Rapor & Cetak (PDF per siswa / semua siswa / cetak)
      ↺ [Batalkan Finalisasi] (alasan + PIN) → status DRAFT → koreksi → Finalkan lagi
```

Status kelas:
```
DRAFT ──(Finalkan: semua lengkap + PIN)──▶ FINAL
FINAL ──(Batalkan finalisasi: alasan + PIN)──▶ DRAFT
```

## 3. Halaman Semua Kelas (`/kelas`) dan tab kelas

Menu **Kelas** menggabungkan Input Nilai dan Rapor. Setiap kelas punya satu halaman dengan tiga tab:

| Tab | URL | Isi |
|---|---|---|
| **Input Nilai** | `/kelas/{kelas}/nilai` | Tabel nilai (§4), finalisasi/pembatalan (§6) |
| **Rapor & Cetak** | `/kelas/{kelas}/rapor` | Daftar rapor, pratinjau, unduh PDF per siswa/semua siswa, cetak (PRD-05) |
| **Riwayat** | `/kelas/{kelas}/riwayat` | Timeline finalisasi & pembatalan (§6.2) |

Tab bawaan saat membuka kelas: **Draft → Input Nilai**, **Final → Rapor & Cetak**. Tab aktif tercermin di URL sehingga bisa dibagikan/di-bookmark. Badge kecil di tab: "Input Nilai 18/25", "Rapor ✓" (jika final).

Halaman **Semua kelas** (`/kelas`):
- Judul "Kelas" + periode aktif; pemilih periode (dropdown) untuk melihat periode lain (periode non-aktif hanya-baca jika kelasnya final).
- Grid **kartu kelas** dikelompokkan per grade (Kelas 3, 4, 5, 6). Isi kartu:
  - Nama kelas besar (mis. **5A**), nama guru bahasa Inggris.
  - **Progress bar** "18 / 25 siswa lengkap" (warna: abu < 50%, kuning 50–99%, hijau 100%).
  - Badge status: `Draft` (abu-abu) / `Final` (hijau tua dengan ikon gembok).
  - Mini distribusi level (5 segmen warna level) bila ada nilai.
  - Tombol: **Isi Nilai** (Draft) atau **Rapor** (Final); menu ⋮ berisi Unduh semua PDF & Cetak (hanya Final).
- Skeleton kartu saat memuat; empty state jika belum ada kelas.

## 4. Tab Input Nilai (`/kelas/{kelas}/nilai`)

### 4.1 Tata letak
- **Header halaman**: breadcrumb (Kelas › 5A), judul "Kelas 5A ▾ • 2026/2027 Semester 1" (class switcher), tab Input Nilai | Rapor & Cetak | Riwayat, info "Level harapan: A1 – A2", indikator simpan global (**SaveIndicator**), tombol: `Impor Nilai`, `Unduh Template`, `Ekspor`, `Finalkan` (primer) / `Batalkan Finalisasi` (jika final).
- **Bar ringkasan** (sticky di atas tabel): Lengkap 18/25 • Belum lengkap 7 • Rata-rata Overall 64,3% • Sebaran level (chip berwarna dengan jumlah).
- **Filter & alat**: cari nama, filter "Belum lengkap / Lengkap / Semua", toggle "Tampilkan persentase".
- **Tabel** (sticky header, kolom No & Nama sticky kiri):

| No | Nama (NIS) | Listening | Reading | Writing | Speaking | L | R | W | S | Overall | Status | 💬 |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 1 | Kevin Wijaya | [15] | [17] | [11] | [13] | A2 | B1 | A2 | A2 | **A2** 70,0% | Meeting | ● |

  - Kolom input (kuning muda) = sel yang dapat diedit; kolom hasil (abu muda) = dihitung.
  - Kolom level berupa **LevelBadge** berwarna; bila toggle persentase aktif, tampil "75%" kecil di bawah badge.
  - Kolom Status: badge Below (merah), Meeting (hijau), Above (biru), "Belum lengkap" (oranye garis putus).
  - 💬 = indikator komentar manual (titik terisi) / otomatis (titik kosong). Klik → panel siswa.
- **Mobile (< 768 px)**: tabel diganti daftar kartu siswa; ketuk kartu → form 4 input besar (`inputmode="decimal"`) dengan tombol Berikutnya/Sebelumnya. Spesifikasi lengkap: PRD-08 §5.3.
- **Pindah kelas cepat**: judul halaman berupa *class switcher* (dropdown dikelompokkan per jenjang) + tombol ‹ › kelas sebelumnya/berikutnya; sub menu sidebar juga menampilkan kelas per jenjang (PRD-08 §2).

### 4.2 Interaksi sel nilai (komponen `ScoreCell`)
| Aksi | Perilaku |
|---|---|
| Klik / fokus | Sel disorot (ring warna primer), isi terpilih |
| Ketik | Hanya angka, koma, titik; maksimum 4 karakter |
| `Enter` / `↓` | Simpan & pindah ke baris bawah, kolom sama |
| `Shift+Enter` / `↑` | Pindah ke atas |
| `Tab` / `Shift+Tab` | Pindah kanan/kiri (melompati kolom hasil) |
| `Esc` | Batalkan perubahan sel (kembali ke nilai tersimpan) |
| `Delete` / kosongkan | Nilai dikosongkan (null) |
| Tempel (Ctrl+V) blok dari Excel | Mengisi sel mulai dari sel aktif ke kanan/bawah; tiap sel divalidasi; konfirmasi bila menimpa > 1 sel berisi: "Tempel 24 nilai dan timpa 6 nilai yang ada?" |
| Nilai tidak valid | Border merah + ikon ⚠ + tooltip: "Nilai harus 0–20 (kelipatan 0,5)". Tidak dikirim ke server; level tidak berubah |
| Valid | Badge level di baris itu beranimasi ("pop" 200 ms) bila levelnya berubah |

Normalisasi: `12,5` → `12.5`; `"015"` → `15`; `12.25` → ditolak; ditampilkan dengan format lokal Indonesia (`12,5`).

### 4.3 Autosave
- Dipicu saat sel kehilangan fokus **atau** 800 ms setelah berhenti mengetik (debounce) — mana yang lebih dulu, dengan nilai valid yang berbeda dari nilai tersimpan.
- Permintaan: `PATCH /kelas/{kelas}/skor/{enrollment}` body `{ "field": "listening", "value": 15, "version": 7 }`.
- Respons sukses (200): `{ "score": {...}, "result": {...hasil hitung server...}, "version": 8, "class_progress": {...} }` → UI mengganti hasil instan klien dengan hasil server (seharusnya sama).
- Antrean per sel; permintaan untuk baris yang sama dikirim berurutan (tidak paralel) agar `version` konsisten.
- **SaveIndicator** (header) menampilkan status gabungan:
  - `Tersimpan ✓` (hijau, memudar setelah 2 detik)
  - `Menyimpan…` (spinner)
  - `3 perubahan menunggu` (saat offline/antrean)
  - `Gagal menyimpan — Coba lagi` (merah, klik untuk ulang)
- Sel yang sedang disimpan menampilkan titik kecil berdenyut di pojok; sel gagal diberi garis merah putus-putus + tooltip alasan.

### 4.4 Penanganan error autosave
| Kode | Arti | Respons UI |
|---|---|---|
| 422 | Validasi gagal di server | Sel merah + pesan server; nilai tidak tersimpan |
| 409 | Konflik versi (diubah di tab/perangkat lain) | Dialog: "Nilai Kevin Wijaya diubah di perangkat lain (Listening 15 → 16). Gunakan nilai terbaru atau simpan nilai Anda?" → [Pakai nilai terbaru] [Timpa dengan nilai saya] |
| 423 | Kelas sudah final | Toast: "Kelas ini sudah difinalkan di perangkat lain. Halaman dimuat ulang dalam mode baca." → muat ulang |
| 401/419 | Sesi habis/terkunci | Perubahan disimpan ke `sessionStorage`, dialog PIN; setelah PIN benar antrean dikirim ulang otomatis |
| 429 | Terlalu banyak permintaan | Tunda & coba lagi otomatis |
| 5xx / jaringan | Gangguan server/koneksi | Coba ulang otomatis dengan jeda 1s, 2s, 4s, 8s, 16s; setelah itu status "Gagal" + tombol ulang manual |

### 4.5 Offline
- Banner kuning di atas: "Anda sedang offline. Perubahan disimpan sementara dan dikirim otomatis saat koneksi kembali." (memakai `useOnline`).
- Saat kembali online: antrean dikirim, toast "5 perubahan berhasil disimpan."

### 4.6 Perlindungan kehilangan data
- `beforeunload` peringatan bila ada antrean belum terkirim.
- Navigasi Inertia ke halaman lain saat antrean ada → dialog "Masih ada 2 nilai yang belum tersimpan. Tunggu sebentar / Tinggalkan halaman".
- Pemulihan: bila halaman dibuka kembali dan `sessionStorage` berisi perubahan tertunda untuk kelas ini → banner "Ada 3 perubahan yang belum tersimpan dari sesi sebelumnya. [Simpan sekarang] [Buang]".

### 4.7 Panel siswa (Sheet kanan)
Dibuka dengan klik nama/ikon 💬.
- Ringkasan: nama, NIS, kelas, 4 skor + level, overall, status, skill terkuat, **mini radar**.
- **Komentar**:
  - Pratinjau komentar otomatis (teks bahasa Inggris dari `level_meta.overall_comment` + "Strongest skill this term: …").
  - Textarea "Komentar guru (opsional, bahasa Inggris)" maks 600 karakter dengan penghitung; tombol "Gunakan komentar otomatis sebagai dasar" (menyalin teks otomatis ke textarea untuk disunting).
  - Autosave sama seperti nilai (field `teacher_comment`).
- Navigasi siswa sebelumnya/berikutnya (`[` dan `]`).
- Tautan "Pratinjau rapor".

## 5. Mesin hitung CEFR (`CefrCalculator`)

### 5.1 Masukan
- `grade` (3–6) → memilih `ScoringConfig` periode kelas tersebut.
- Skor: `listening`, `reading`, `writing`, `speaking` (nullable decimal).
- `ScoringConfig`: `max[skill]` (bawaan 20), `weight[skill]` (bawaan 25), `thresholds[level]` (persen, null = tidak diukur), `expected_min`, `expected_max`.

### 5.2 Aturan (identik dengan Excel)
1. **Persentase skill** = `skor / max[skill] × 100` (tanpa pembulatan untuk perbandingan; ditampilkan 1 desimal). Skor null → persentase null.
2. **Level skill** = level **terakhir** (urutan Pre-A1 → B2) yang `threshold ≠ null` dan `persentase ≥ threshold`. (Setara `MATCH(pct, thresholds, 1)` dengan nilai 999 untuk level tidak diukur.)
3. **Overall %** = `Σ(pct × weight) / Σ(weight)` — **hanya jika keempat skor terisi**; selain itu null.
4. **Overall level** = aturan nomor 2 terhadap Overall %.
5. **Status**:
   - Jika ada siswa tetapi skor belum lengkap → `incomplete` ("Belum lengkap").
   - `index(overall_level) < index(expected_min)` → `below` (Below Expected).
   - `index(overall_level) > index(expected_max)` → `above` (Above Expected).
   - Selain itu → `meeting` (Meeting Expected).
6. **Skill terkuat** = skill dengan persentase tertinggi; bila sama, ambil urutan pertama: Listening, Reading, Writing, Speaking. Null bila overall null.
7. **Komentar rapor** = komentar guru jika tidak kosong; selain itu `"{Nama} {overall_comment[level]} Strongest skill this term: {skill}."`

### 5.3 Validasi konfigurasi (Pengaturan → Penilaian)
- Ambang batas Pre-A1 selalu 0 (terkunci).
- Ambang yang terisi harus **naik tegas** dari atas ke bawah dan ≤ 100; level setelah level kosong juga harus kosong.
- `max` 1–100; bobot 0–100 dan total bobot > 0 (tampilkan total; peringatan bila ≠ 100 tetapi tetap diizinkan karena dinormalisasi).
- `expected_min ≤ expected_max`.
- Perubahan konfigurasi pada periode yang punya kelas **final** → peringatan: "Perubahan hanya berlaku untuk kelas Draft. Rapor kelas yang sudah final tidak berubah."
- Pratinjau dampak: "Perubahan ini akan mengubah level 7 siswa di kelas Draft (lihat daftar)."

### 5.4 Implementasi ganda
- PHP: `App\Domain\Cefr\CefrCalculator::calculate(ScoringConfig $c, array $scores): StudentResult` — fungsi murni.
- TypeScript: `resources/js/lib/cefr.ts` dengan tanda tangan sama, dipakai untuk umpan balik instan di tabel & panel.
- Konfigurasi kelas dikirim sebagai prop Inertia ke halaman tabel.

## 6. Finalisasi & pembatalan

### 6.1 Finalkan
Prasyarat: kelas berstatus Draft, **semua** siswa aktif di kelas memiliki 4 skor, tidak ada antrean autosave.

Alur UI:
1. Klik **Finalkan** → jika ada yang belum lengkap, dialog daftar: "7 siswa belum lengkap: Citra (Writing, Speaking), …" dengan tautan lompat ke barisnya. Tombol Finalkan nonaktif.
2. Jika lengkap → dialog ringkasan: jumlah siswa, sebaran level, jumlah Below/Meeting/Above, checklist "Saya sudah memeriksa nilai dan komentar". 
3. Konfirmasi PIN (bila belum dalam 5 menit).
4. Server (`FinalizationService::finalize`) dalam satu transaksi:
   - Hitung ulang semua hasil dengan konfigurasi saat ini.
   - Tulis `finalizations` (`action=finalize`, `config_snapshot` berisi scoring config + deskriptor + level_meta + data identitas sekolah & guru).
   - Tulis `final_results` per siswa (`is_current=true`), set `is_current=false` untuk snapshot lama.
   - Set `classrooms.status=final`, `finalized_at`, `current_finalization_id`.
   - Log `class.finalized`.
5. UI: konfeti kecil/animasi centang (≤ 1 detik, hormati reduced motion), toast "Kelas 5A berhasil difinalkan. Rapor siap dicetak." lalu otomatis pindah ke tab **Rapor & Cetak**.

Setelah final: tabel jadi **mode baca** (sel tidak bisa diedit, ikon gembok di header kolom), banner hijau "Kelas ini sudah final pada 18 Des 2026 14.05. Nilai terkunci."

### 6.2 Batalkan finalisasi
1. Tombol **Batalkan Finalisasi** (varian outline/merah) di tab Input Nilai & tab Rapor & Cetak.
2. Dialog peringatan: "Nilai akan dapat diedit kembali. Rapor yang sudah dicetak mungkin berbeda dengan rapor setelah koreksi." Field **Alasan** wajib (10–300 karakter), mis. "Koreksi nilai Speaking Kevin". 
3. Konfirmasi PIN.
4. Server (`FinalizationService::unfinalize`): tulis `finalizations` (`action=unfinalize`, `reason`), set kelas `draft`, **snapshot lama tetap disimpan** (`is_current` tetap true sampai finalisasi berikutnya, sehingga dashboard/riwayat masih bisa menampilkan hasil final terakhir dengan label "sedang dikoreksi"). Log `class.unfinalized`.
5. UI: banner kuning "Finalisasi dibatalkan pada … Alasan: …. Finalkan kembali setelah koreksi."
6. Riwayat finalisasi kelas dapat dilihat di tab "Riwayat" halaman kelas (timeline: Final → Dibatalkan (alasan) → Final …).

### 6.3 Aturan saat Final
- Semua endpoint ubah nilai/komentar/data kelas/enrollment mengembalikan **423 Locked**.
- Rapor & PDF diambil dari `final_results` + `config_snapshot` (bukan dari pengaturan terkini).
- Ekspor diambil dari snapshot.

## 7. Uji paritas dengan Excel (wajib)
File `tests/fixtures/cefr-parity.json` memuat minimal kasus berikut (diambil dari data contoh Excel) dan dijalankan di Pest **dan** Vitest:

| Grade | L | R | W | S | L% | Level L/R/W/S | Overall % | Overall | Status | Terkuat |
|---|---|---|---|---|---|---|---|---|---|---|
| 3 | 16 | 15 | 14 | 15 | 80 | A1/A1/A1/A1 | 75 | A1 | Meeting | Listening |
| 3 | 18 | 17 | 16 | 17 | 90 | A2/A2/A1/A2 | 85 | A2 | Above | Listening |
| 3 | 10 | 11 | 9 | 12 | 50 | Pre-A1/Pre-A1/Pre-A1/A1 | 52,5 | Pre-A1 | Meeting | Speaking |
| 4 | 9 | 10 | 8 | 9 | 45 | Pre-A1/A1/Pre-A1/Pre-A1 | 45 | Pre-A1 | Below | Reading |
| 4 | 16 | 15 | 14 | 16 | 80 | A2/A1/A1/A2 | 76,25 | A1 | Meeting | Listening |
| 5 | 15 | 17 | 11 | 13 | 75 | A2/B1/A2/A2 | 70 | A2 | Meeting | Reading |
| 5 | 18 | 18 | 17 | 18 | 90 | B1/B1/B1/B1 | 88,75 | B1 | Above | Listening |
| 6 | 19 | 19 | 18 | 18 | 95 | B2/B2/B2/B2 | 92,5 | B2 | Above | Listening |
| 6 | 8 | 7 | 6 | 8 | 40 | A1/A1/A1/A1 | 36,25 | A1 | Below | Listening |

Kasus batas tambahan: skor tepat di ambang (G4 L=10 → 50% → A1; G6 S=18 → 90% → B2), skor 0, skor 20, skor 12,5, skor null (overall null, status `incomplete`), bobot tidak sama (mis. 30/30/20/20), ambang diubah pengguna.

## 8. Kinerja & batas
- Tabel 40 siswa harus tetap responsif (input < 16 ms per ketikan) — hitung klien hanya untuk baris yang berubah.
- Server menghitung ulang hanya baris terkait saat autosave; progres kelas dihitung dengan satu query agregat.

## 9. Kriteria penerimaan
- [ ] Mengetik 15 pada Listening siswa Kelas 5 langsung menampilkan badge **A2** sebelum server merespons, dan hasil server identik.
- [ ] Nilai 21, -1, "abc", 12,3 ditolak dengan pesan jelas; 12,5 diterima.
- [ ] Enter/Tab/panah berpindah sel sesuai §4.2; tempel 4×10 sel dari Excel berhasil.
- [ ] Mematikan jaringan, mengetik 3 nilai, menyalakan kembali → ketiganya tersimpan tanpa tindakan pengguna.
- [ ] Mengubah nilai yang sama di dua tab memunculkan dialog konflik (409).
- [ ] Finalkan ditolak bila ada siswa belum lengkap dan menampilkan daftarnya.
- [ ] Setelah final, permintaan ubah nilai ditolak 423 dan UI mode baca.
- [ ] Batalkan finalisasi mewajibkan alasan + PIN, tercatat di riwayat & log, dan kelas dapat diedit lagi.
- [ ] Mengubah ambang batas setelah final tidak mengubah level di rapor kelas final.
- [ ] Seluruh fixture paritas (§7) lulus di PHP dan TypeScript.
