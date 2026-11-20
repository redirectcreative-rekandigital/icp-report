# PRD-05 — Rapor, Pengaturan Layout, PDF & Cetak

## 1. Ringkasan
Menghasilkan **English Proficiency Progress Report** (bahasa Inggris) **satu halaman per siswa** untuk **SD Muhammadiyah Wonorejo**. Desain bawaan sama dengan rapor Excel: kop berwarna, informasi siswa, level overall, peta level CEFR, tabel skor + grafik radar, deskripsi kemampuan & saran, komentar guru, dan tanda tangan.

Rapor diakses dari **menu Kelas → tab Rapor & Cetak** (PRD-04 §3). Fitur:
- **Pratinjau** di layar.
- **Unduh PDF per siswa** dan **unduh PDF semua siswa** (satu file gabungan atau ZIP).
- **Cetak** per siswa dan **cetak semua**.
- Ukuran kertas **F4 (bawaan)** atau **A4**, dipilih saat mengunduh/mencetak.
- **Pengaturan layout** berbasis preset & sakelar (Pengaturan → Rapor), termasuk logo Muhammadiyah opsional dan penanda tangan dengan **NBM**.

## 2. User stories
| ID | Sebagai | Saya ingin | Agar |
|---|---|---|---|
| US-05.1 | Wali kelas | melihat pratinjau rapor sebelum mencetak | memastikan data benar |
| US-05.2 | Wali kelas | mengunduh PDF satu siswa | mencetak ulang bila perlu |
| US-05.3 | Koordinator | mengunduh PDF semua siswa satu kelas | mencetak/mengarsip sekaligus |
| US-05.4 | Wali kelas | langsung mencetak rapor dari web | tidak perlu membuka file manual |
| US-05.5 | Koordinator | memilih kertas F4 atau A4 | sesuai kertas yang tersedia di sekolah |
| US-05.6 | Koordinator | mengatur bagian rapor, kop, logo, dan penanda tangan | rapor sesuai identitas sekolah Muhammadiyah |

## 3. Tab Rapor & Cetak (`/kelas/{kelas}/rapor`)

### 3.1 Bilah aksi
```
Kertas: [ F4 ▾ ]     [ ⬇ Unduh semua PDF ▾ ]   [ 🖨 Cetak semua ]   [ Batalkan Finalisasi ]
```
- **Pemilih kertas**: F4 (bawaan) / A4. Pilihan terakhir diingat per perangkat (`localStorage`); bawaan diambil dari Pengaturan → Rapor.
- **Unduh semua PDF ▾**: menu dengan pilihan **Satu file PDF** (bawaan) dan **ZIP (satu PDF per siswa)**.
- **Cetak semua**: membuat PDF gabungan lalu membukanya untuk dicetak (§7.3).
- Bila ada siswa yang dicentang, tombol berubah menjadi **Unduh terpilih (5)** dan **Cetak terpilih (5)**.

### 3.2 Daftar siswa
Tabel (desktop) / kartu (ponsel): ☐ • No • Nama • Overall (badge) • Status • aksi per siswa:
- **Pratinjau** (ikon mata)
- **PDF** (unduh)
- **Cetak** (ikon printer)

Siswa dengan nilai belum lengkap: aksi nonaktif dengan tooltip "Nilai belum lengkap", dan tidak ikut di "semua siswa" (disebutkan di ringkasan).

### 3.3 Kelas Draft
Tab tetap bisa dibuka. Banner kuning: "Kelas belum final. Rapor yang diunduh/dicetak akan bertanda **DRAFT**." Watermark DRAFT tampil di pratinjau dan PDF.

## 4. Pratinjau rapor (`/kelas/{kelas}/rapor/{siswa}`)
- Rapor dirender sebagai komponen Vue (`ReportSheet.vue`) dengan rasio kertas yang dipilih (F4 215×330 mm / A4 210×297 mm), di tengah layar dengan bayangan kertas; zoom 75/100/125%.
- Toolbar: ‹ Siswa sebelumnya | Siswa berikutnya › | Kertas F4/A4 | **Unduh PDF** | **Cetak** | Edit nilai (ke tab Input Nilai, jika Draft).
- Data dari `ReportDataBuilder` (sama untuk pratinjau & PDF): kelas Final → snapshot (termasuk layout saat finalisasi); kelas Draft → data & layout terkini.

## 5. Pengaturan layout rapor (Pengaturan → tab **Rapor**)

Tata letak halaman: **form di kiri, pratinjau langsung di kanan** (memakai data siswa contoh atau siswa yang dipilih). Pratinjau diperbarui 500 ms setelah perubahan. Tombol **Simpan** dan **Kembalikan ke bawaan**. Menyimpan perubahan memerlukan PIN dalam 5 menit terakhir (PRD-02 §6).

### 5.1 Preset tata letak
| Preset | Isi | Cocok untuk |
|---|---|---|
| **Lengkap** (bawaan) | Semua bagian seperti rapor Excel | Rapor resmi semester |
| **Ringkas** | Tanpa blok can-do & next steps; tabel skor dan peta level lebih lega | Rapor tengah semester |
| **Minimal** | Kop, info siswa, tabel skor + level, komentar, tanda tangan | Laporan singkat |

Memilih preset mengatur sakelar §5.3 secara otomatis; sakelar masih bisa diubah sesudahnya (label preset berubah menjadi "Kustom").

### 5.2 Kop & identitas
| Field | Keterangan |
|---|---|
| Logo sekolah | PNG/JPG, maks 1 MB, disarankan persegi ≥ 300 px. Tampil di kiri kop |
| **Logo Muhammadiyah** | **Opsional** (sakelar "Tampilkan logo Muhammadiyah"). File diunggah sekolah sendiri; posisi kanan kop |
| Logo mitra ICP | Opsional; bila logo Muhammadiyah aktif, logo mitra pindah ke baris program di bawah judul (ukuran kecil) |
| Baris kop tambahan | Opsional, 1 baris, maks 80 karakter (mis. nama majelis/cabang). Tampil di atas nama sekolah |
| Nama sekolah | Dari Pengaturan → Sekolah (bawaan **SD Muhammadiyah Wonorejo**), ditampilkan huruf kapital |
| Alamat | Dari Pengaturan → Sekolah |
| Judul rapor | Bawaan "ENGLISH PROFICIENCY PROGRESS REPORT" (maks 60 karakter) |
| Subjudul | Bawaan "CEFR-Aligned Assessment • Academic Year {tahun} • {semester}" (variabel otomatis) |

### 5.3 Sakelar bagian (on/off)
| Bagian | Bawaan (Lengkap) |
|---|---|
| Peta level CEFR (Level Map) | On |
| Label setara Cambridge di peta level | On |
| Grafik radar (Skill Profile) | On |
| Kolom persentase + bar di tabel skor | On |
| Blok "What the student can do & Next steps" | On |
| Kalimat "Strongest skill this term" di komentar otomatis | On |
| Status terhadap level harapan (Below/Meeting/Above) | On |
| Catatan kaki penjelasan CEFR | On |
| Kolom tanda tangan orang tua | On |

Bila bagian dimatikan, ruangnya dibagikan ke bagian lain (bukan dibiarkan kosong). Setiap kombinasi sakelar harus tetap muat **satu halaman** di F4 maupun A4.

### 5.4 Warna kop
- Preset: **Navy** (bawaan, sama dengan web: `#1F3A68` + aksen emas `#E3A82B`), **Hijau Muhammadiyah** (hijau tua + aksen kuning), atau **Kustom** (pilih warna utama & aksen).
- Warna kustom divalidasi kontrasnya terhadap teks putih (≥ 4,5:1). Bila kurang, muncul peringatan dan tombol Simpan nonaktif.
- **Warna level CEFR dan status tidak ikut berubah** (tetap sama di web dan rapor).

### 5.5 Tanda tangan
| Field | Keterangan |
|---|---|
| Penanda tangan | Pilih & urutkan 2–4 dari: Orang Tua/Wali, Wali Kelas, Guru Bahasa Inggris (ICP), Kepala Sekolah. Bawaan: keempatnya |
| Label jabatan (EN) | Bisa diubah, mis. "Parent / Guardian", "Homeroom Teacher", "English Teacher (ICP)", "Principal" |
| Nomor induk | Label bawaan **NBM** (pilihan: NBM / NIP / tidak ditampilkan). Nomor kepala sekolah diisi di Pengaturan → Sekolah; nomor wali kelas & guru opsional per kelas (Periode & Kelas) |
| Tempat & tanggal | "{Kota}, {tanggal rapor periode}" format Inggris (mis. "{Kota}, 18 December 2026"). Kota dari Pengaturan → Sekolah |

### 5.6 Kertas
- Bawaan sekolah: **F4** (dapat diganti ke A4). Pengguna tetap bisa memilih per unduhan/cetak (§3.1).
- Margin bawaan 12 mm (8–20 mm dapat diatur).

### 5.7 Penyimpanan & snapshot
- Disimpan di `settings.report_layout` (json):
```json
{
  "preset": "lengkap",
  "paper_default": "f4",
  "margin_mm": 12,
  "theme": { "preset": "navy", "primary": "#1F3A68", "accent": "#E3A82B" },
  "header": { "extra_line": "", "show_muhammadiyah_logo": false, "title": "ENGLISH PROFICIENCY PROGRESS REPORT" },
  "sections": { "level_map": true, "cambridge": true, "radar": true, "percent_bar": true, "can_do": true, "strongest_skill": true, "status": true, "footnote": true, "parent_signature": true },
  "signatures": [
    { "role": "parent", "label": "Parent / Guardian" },
    { "role": "homeroom", "label": "Homeroom Teacher" },
    { "role": "english", "label": "English Teacher (ICP)" },
    { "role": "principal", "label": "Principal" }
  ],
  "id_label": "NBM"
}
```
- Saat kelas **difinalkan**, layout ikut disalin ke `finalizations.config_snapshot`. Mengubah layout kemudian **tidak** mengubah rapor kelas final. Pratinjau kelas final menampilkan info "Memakai layout saat finalisasi (18 Des 2026)" dengan tombol **Pakai layout terbaru**, yang memerlukan Batalkan Finalisasi → Finalkan ulang.
- Ukuran kertas **tidak** dikunci oleh snapshot. Rapor final tetap bisa diunduh di F4 atau A4.

## 6. Desain rapor (preset Lengkap)

| # | Blok | Isi |
|---|---|---|
| 1 | Strip aksen atas | Garis tebal warna aksen |
| 2 | **Kop** (warna utama) | Logo sekolah (kiri), logo Muhammadiyah (kanan, opsional). Tengah: baris kop tambahan (opsional), **SD MUHAMMADIYAH WONOREJO**, alamat, "INTERNATIONAL CLASS PROGRAM • in partnership with {Mitra}", **judul rapor**, subjudul |
| 3 | Student Information | Student Name, Student ID, Class / Grade, Semester, Homeroom Teacher, English Teacher |
| 4 | Overall | Kotak berwarna level berisi kode level besar, nama level, "Score: 70.0%" |
| 5 | CEFR Level Map | 5 kotak Pre-A1 → B2, label Cambridge, penanda "▲ CURRENT LEVEL" & "expected range", pita status |
| 6 | Skill Results | Skill • Score (15 / 20) • Percentage + bar • CEFR Level; baris OVERALL |
| 7 | Skill Profile | Grafik radar 4 skill |
| 8 | Can-do & Next steps | 4 baris per skill |
| 9 | Teacher's Comment | Komentar guru/otomatis |
| 10 | Tanda tangan | Tempat & tanggal; 2–4 kolom penanda tangan; nama + **NBM** di bawah nama kepala sekolah |
| 11 | Catatan kaki | Penjelasan level CEFR |
| 12 | Strip warna utama bawah | |

**Penyesuaian F4 vs A4**: F4 lebih tinggi ± 33 mm. Ruang tambahan dipakai untuk jarak antarblok dan area tanda tangan yang lebih lega; ukuran font sama. A4 memakai jarak minimum yang tetap terbaca (≥ 7 pt).

Warna level: Pre-A1 `#F4A261`, A1 `#E9C46A` (teks gelap `#3B2A12`), A2 `#1F7A70`, B1 `#457B9D`, B2 `#6A4C93` (teks putih).

Tipografi PDF: font sans yang di-embed (Plus Jakarta Sans/Inter, cadangan DejaVu Sans), 7–16 pt. Nama sekolah > 40 karakter dan nama siswa > 32 karakter diperkecil otomatis.

## 7. Pembuatan PDF & cetak

### 7.1 PDF per siswa
- **DomPDF** dengan template Blade `resources/views/pdf/report.blade.php`. Template membaca `report_layout` (atau snapshot) dan ukuran kertas.
- Ukuran kertas DomPDF: A4 = `'a4'`; **F4 = `[0, 0, 609.45, 935.43]` pt** (215 × 330 mm).
- Radar dibuat `RadarChartRenderer` (GD → PNG); bar persentase dibuat dengan `div` lebar persen.
- Nama file: `Report_{NIS}_{Nama}_{2026-2027}_S1_F4.pdf`.
- Target ≤ 3 detik per rapor, ≤ 300 KB.

### 7.2 PDF semua siswa (tanpa queue)
1. **Unduh semua PDF** → `POST /kelas/{kelas}/rapor/batch` (`paper`, `mode`: merged/zip, `enrollment_ids` opsional) → `batch_id`.
2. **BatchProgressDialog**: "Membuat rapor 12 dari 30…", nama siswa, estimasi waktu. Klien memanggil `POST /rapor/batch/{id}/next` berulang; tiap panggilan membuat maks. 5 PDF (atau berhenti di 20 detik).
3. Langkah terakhir: gabung dengan **FPDI** (satu PDF, satu halaman per siswa, urut nomor) atau ZIP.
4. Selesai → unduhan otomatis + ringkasan "30 rapor berhasil". Siswa gagal → daftar + "Coba ulang yang gagal".
5. Ketahanan: batch dapat dilanjutkan setelah tab ditutup (banner "Ada proses yang belum selesai (12/30). Lanjutkan / Batalkan"), `next` idempoten, coba ulang otomatis 3×, file kedaluwarsa 24 jam, maks 1 batch berjalan per kelas, maks 45 rapor per batch.

### 7.3 Cetak
- **Cetak per siswa**: membuka PDF satu siswa (`inline=1`) di tab baru dan memanggil dialog cetak browser otomatis (PDF dimuat di `iframe` tersembunyi lalu `contentWindow.print()`; bila browser memblokir, PDF dibuka di tab baru dengan petunjuk "Tekan Ctrl+P").
- **Cetak semua / cetak terpilih**: menjalankan proses §7.2 (mode merged), lalu membuka hasilnya untuk dicetak dengan cara yang sama.
- Mencetak selalu dari **PDF**, bukan dari halaman web, agar hasil cetak identik dengan file unduhan.
- Petunjuk cetak (tooltip & dialog sebelum cetak pertama, bisa disembunyikan): "Pilih ukuran kertas **F4/Folio** (atau A4) sesuai pilihan, skala **100% / Actual size**, matikan header/footer browser." Bila printer tidak punya ukuran F4, sarankan menambah ukuran kertas kustom 215 × 330 mm atau memakai A4.

## 8. Kasus tepi
| Kasus | Penanganan |
|---|---|
| Kelas Draft | Rapor bertanda DRAFT; "semua siswa" menampilkan peringatan dulu |
| Siswa belum lengkap | Tidak bisa diunduh/dicetak; dikecualikan dari "semua siswa" |
| Komentar sangat panjang | Maks 600 karakter; font komentar mengecil hingga 8 pt agar tetap 1 halaman |
| Logo Muhammadiyah aktif tetapi file belum diunggah | Pratinjau menampilkan kotak peringatan "Logo Muhammadiyah belum diunggah"; di PDF tidak dicetak |
| Logo sangat lebar | Diskalakan proporsional ke kotak 70×70 px |
| Kombinasi sakelar membuat ruang kosong | Jarak antarblok dibagi merata |
| Pengaturan/identitas diubah setelah final | Rapor final tetap memakai snapshot; info ditampilkan di pratinjau |
| Popup/iframe cetak diblokir | Buka PDF di tab baru + petunjuk Ctrl+P |
| DomPDF kehabisan memori | Batch memakai potongan lebih kecil (3), pesan error dengan kode referensi |

## 9. Kriteria penerimaan
- [ ] Bawaan kertas **F4**; F4 dan A4 sama-sama menghasilkan **tepat satu halaman per siswa** untuk semua preset, semua kombinasi sakelar, dan komentar maksimum.
- [ ] Unduh PDF per siswa, unduh semua siswa (satu PDF & ZIP), cetak per siswa, dan cetak semua berfungsi dari tab Rapor & Cetak dan dari ponsel.
- [ ] Pengaturan → Rapor menampilkan pratinjau langsung; preset Lengkap/Ringkas/Minimal mengubah sakelar sesuai §5.1.
- [ ] Logo Muhammadiyah hanya tampil bila sakelarnya aktif dan file tersedia.
- [ ] Label nomor induk kepala sekolah tampil sebagai **NBM** secara bawaan.
- [ ] Cetak semua 40 siswa berhasil di shared hosting dengan `max_execution_time=30`.
- [ ] Mengubah layout/identitas setelah final tidak mengubah PDF kelas final; ukuran kertas tetap bisa dipilih.
- [ ] Isi PDF identik dengan pratinjau dan dengan hasil Excel untuk data contoh.
