# PRD-03 — Data Master, Impor/Ekspor Excel & Dokumen

## 1. Ringkasan
Mengelola data yang menjadi dasar input nilai: **tahun ajaran & semester (periode)**, **kelas** beserta wali kelas & guru bahasa Inggris, **siswa** dan keanggotaannya di kelas. Data dapat diisi manual atau **diimpor dari Excel**. Rekap nilai dapat **diekspor ke Excel**. Menu **Dokumen** (opsional) menyimpan file instrumen tes.

## 2. User stories
| ID | Sebagai | Saya ingin | Agar |
|---|---|---|---|
| US-03.1 | Koordinator | membuat periode baru dan menjadikannya aktif | input nilai semester baru terpisah dari semester lalu |
| US-03.2 | Koordinator | membuat kelas 3A–6B dengan wali kelas & guru | rapor menampilkan nama yang benar |
| US-03.3 | Koordinator | mengimpor daftar siswa dari Excel | tidak mengetik ulang ratusan nama |
| US-03.4 | Koordinator | menyalin siswa dari periode sebelumnya dan menaikkan kelasnya | persiapan semester/tahun baru cepat |
| US-03.5 | Guru | mengimpor nilai dari template Excel | bisa mengisi nilai offline |
| US-03.6 | Koordinator | mengekspor rekap nilai | melapor ke mitra/kepala sekolah |
| US-03.7 | Guru | mengunduh file tes kelas tertentu | mencetak soal |

## 3. Periode (`/periode`)

### 3.1 Tahun ajaran & semester
- Daftar tahun ajaran (kartu), masing-masing berisi Semester 1 & 2 dengan badge **Aktif**, jumlah kelas, jumlah siswa, status finalisasi ("4/6 kelas final").
- **Tambah tahun ajaran**: nama format `YYYY/YYYY` (validasi: tahun kedua = tahun pertama + 1), tanggal mulai/selesai opsional.
- **Tambah semester**: pilih 1 atau 2, tanggal rapor (dipakai di tanda tangan rapor).
- **Jadikan aktif**: hanya satu periode aktif. Dialog konfirmasi: "Periode aktif akan berganti ke 2026/2027 Semester 2. Menu Kelas akan menampilkan kelas periode ini."
- Saat semester dibuat → sistem membuat `scoring_configs` grade 3–6 dengan **menyalin** dari periode terakhir (atau bawaan). Ditampilkan toast: "Pengaturan penilaian disalin dari 2026/2027 Semester 1."
- Hapus periode hanya jika belum ada nilai; jika ada → tombol nonaktif dengan tooltip penjelasan.

### 3.2 Kelas (di dalam halaman periode)
Tabel: Kelas | Grade | Wali Kelas | Guru B. Inggris | Jumlah Siswa | Status (Draft/Final) | Aksi.

Form kelas (Sheet samping):
| Field | Aturan |
|---|---|
| Grade | Pilih 3, 4, 5, 6 (wajib) |
| Nama kelas | Wajib, maks 10 karakter, unik dalam periode (mis. "3A"). Saran otomatis berdasarkan grade |
| Wali kelas | Teks, maks 100 |
| NBM wali kelas | Opsional, angka/teks maks 30 (tampil di tanda tangan rapor) |
| Guru bahasa Inggris (ICP) | Teks, maks 100 |
| Nomor induk guru ICP | Opsional, maks 30 |

- **Salin kelas dari periode lain**: pilih periode sumber → centang kelas → opsi "Naikkan grade (+1) untuk tahun ajaran baru" (6 tidak ikut) → opsi "Ikutkan siswa". Pratinjau hasil sebelum disimpan.
- Edit grade tidak diizinkan bila kelas sudah memiliki nilai (karena ambang batas berbeda) — tampilkan alasan.
- Kelas berstatus **Final** tetap bisa diubah nama wali/guru? → **Tidak**; harus batalkan finalisasi dulu (agar snapshot rapor konsisten). Pesan: "Kelas sudah final. Batalkan finalisasi untuk mengubah data kelas."

## 4. Siswa (`/siswa`)

### 4.1 Daftar
- Tabel dengan pencarian (nama/NIS, debounce 300 ms), filter: periode, kelas, status aktif. Kolom: NIS, Nama, L/P, Kelas (periode aktif), Level terakhir (badge), Aksi.
- Paginasi 25/50/100. Pilih massal → "Pindahkan ke kelas…", "Keluarkan dari kelas", "Nonaktifkan".
- Klik nama → profil & riwayat (PRD-06).

### 4.2 Form siswa
| Field | Aturan |
|---|---|
| NIS | Wajib, 3–20 karakter alfanumerik, unik |
| Nama lengkap | Wajib, 2–100 karakter, huruf/spasi/apostrof/titik/tanda hubung |
| Jenis kelamin | Opsional (L/P) |
| Kelas (periode aktif) | Opsional; daftar kelas periode aktif |
| Catatan | Opsional, maks 300 |

Aturan:
- Satu siswa hanya boleh di **satu kelas per periode** → pesan "Siswa ini sudah terdaftar di 4B pada periode ini."
- Nomor urut (`roll_no`) diisi otomatis berurutan nama (A–Z) dan bisa diurutkan ulang dengan drag & drop di halaman kelas.
- Menghapus siswa yang sudah punya nilai final → tidak diizinkan; pilihannya "Nonaktifkan".
- Siswa dikeluarkan dari kelas berstatus Final → tidak diizinkan.

## 5. Impor Excel (`/impor`)

### 5.1 Jenis impor
| Jenis | Template | Kolom |
|---|---|---|
| **Siswa** | `template_siswa.xlsx` | NIS*, Nama*, L/P, Kelas* (mis. 3A — harus ada di periode aktif) |
| **Nilai kelas** | `template_nilai_{kelas}.xlsx` (dibuat per kelas, sudah berisi daftar siswa) | No, NIS*, Nama (info), Listening, Reading, Writing, Speaking, Komentar |
| **Dari file Excel lama** | `ICP_English_Progress_Report.xlsx` | Membaca sheet "Grade 3"–"Grade 6" (kolom B–I baris 6–45) — memudahkan migrasi data yang sudah diisi di Excel |

Template diunduh dari halaman Impor; template nilai berisi validasi data Excel (0–20) dan petunjuk singkat di baris atas.

### 5.2 Alur dua tahap
1. **Unggah** (drag & drop atau pilih file; `.xlsx`, `.xls`, `.csv`; maks 2 MB; maks 1.000 baris).
2. **Pratinjau** (tanpa menyimpan): tabel hasil baca dengan status per baris:
   - ✅ **Baru** (hijau) — akan ditambahkan
   - 🔄 **Diperbarui** (biru) — data/nilai berubah; tampilkan nilai lama → baru
   - ⏭️ **Tidak berubah** (abu-abu)
   - ⚠️ **Peringatan** (kuning) — mis. nama di file berbeda dengan nama di sistem untuk NIS yang sama (dipakai nama sistem)
   - ❌ **Galat** (merah) — tidak akan diimpor; alasan jelas per kolom
   Ringkasan di atas tabel: "48 baris • 40 baru • 5 diperbarui • 3 galat". Filter per status. Tombol **Unduh laporan galat (.xlsx)**.
3. **Konfirmasi**: tombol "Impor 45 baris" (nonaktif jika 0 baris valid). Opsi untuk nilai: "Timpa nilai yang sudah ada" (bawaan **mati**; jika dinyalakan → perlu PIN ulang).
4. **Hasil**: toast + kartu ringkasan, tautan ke kelas terkait. Proses dalam satu transaksi DB — jika gagal di tengah, semua dibatalkan.

### 5.3 Aturan validasi impor (pesan Bahasa Indonesia)
| Masalah | Pesan |
|---|---|
| Header tidak sesuai | "Kolom wajib tidak ditemukan: NIS, Nama. Gunakan template terbaru." |
| NIS kosong | "Baris 7: NIS wajib diisi." |
| NIS ganda dalam file | "Baris 12: NIS 5004 muncul lebih dari sekali (baris 9 dan 12)." |
| Kelas tidak ada | "Baris 15: Kelas '5C' tidak ada di periode 2026/2027 Semester 1." |
| Nilai bukan angka | "Baris 20: Listening 'lima belas' bukan angka." |
| Nilai di luar rentang | "Baris 21: Reading 22 melebihi nilai maksimum 20." |
| Bukan kelipatan 0,5 | "Baris 22: Writing 12,3 harus bulat atau kelipatan 0,5." |
| Siswa tidak terdaftar di kelas | "Baris 25: NIS 6010 tidak terdaftar di kelas 6A." |
| Kelas sudah final | Seluruh impor nilai ditolak: "Kelas 5A sudah final. Batalkan finalisasi untuk mengimpor nilai." |
| File rusak/terenkripsi | "File tidak dapat dibaca. Pastikan file Excel tidak dikunci kata sandi." |

Angka desimal dengan koma (`12,5`) maupun titik (`12.5`) diterima. Spasi di awal/akhir dibuang. Sel kosong pada kolom nilai = tidak mengubah nilai yang ada (kecuali opsi timpa aktif dan sel berisi tanda `-` untuk mengosongkan).

## 6. Ekspor Excel
| Ekspor | Isi |
|---|---|
| **Rekap kelas** | Sama dengan sheet "Grade X" di Excel: No, NIS, Nama, Kelas, skor 4 skill, komentar, persen 4 skill, level 4 skill, Overall %, Overall Level, Status, Skill terkuat; plus blok ringkasan kelas. Sel level berwarna sesuai level. |
| **Rekap periode** | Satu sheet per kelas + sheet "Ringkasan" (sebaran level per kelas, rata-rata per skill) |
| **Template nilai kelas** | Lihat §5.1 |
| **Daftar siswa** | NIS, Nama, L/P, Kelas |

Nama file: `Rekap_{Kelas}_{2026-2027}_S1.xlsx`. Kelas Final diekspor dari snapshot (`final_results`), kelas Draft dari perhitungan langsung — ditandai di judul sheet "(DRAFT)".

## 7. Dokumen (`/dokumen`) — opsional
- Kartu per kategori: **Instrumen Tes**, **Pedoman Penskoran**, **Lainnya**; filter grade 3–6.
- Unggah: PDF/DOCX/XLSX, maks 10 MB per file; judul wajib; grade opsional.
- Aksi: Unduh, Ganti file, Hapus (dengan konfirmasi).
- Bawaan saat setup (opsional dari seeder): 4 file tes dan pedoman penskoran yang sudah dibuat.
- Menu dapat disembunyikan dari sidebar lewat Pengaturan (toggle "Tampilkan menu Dokumen").

## 8. Kasus tepi
| Kasus | Penanganan |
|---|---|
| Belum ada periode aktif | Menu Kelas menampilkan *empty state* "Belum ada periode aktif" + tombol "Buat periode" |
| Periode aktif tanpa kelas | Empty state "Belum ada kelas di periode ini" + tombol "Tambah kelas" / "Salin dari periode lalu" |
| Nama siswa sangat panjang | Dipotong dengan elipsis di tabel (tooltip nama lengkap); di rapor diperkecil otomatis hingga muat |
| Unggah file > batas | Ditolak di klien sebelum dikirim: "Ukuran file 3,4 MB melebihi batas 2 MB." |
| Koneksi putus saat unggah | Progress bar berhenti, pesan + tombol Coba lagi; tidak ada data tersimpan setengah |

## 9. Kriteria penerimaan
- [ ] Membuat semester baru otomatis membuat konfigurasi penilaian grade 3–6 hasil salinan.
- [ ] Hanya satu periode aktif pada satu waktu.
- [ ] Impor siswa 40 baris dengan 3 galat menampilkan pratinjau, mengimpor 37 baris, dan laporan galat dapat diunduh.
- [ ] Impor nilai tidak menimpa nilai yang ada kecuali opsi timpa diaktifkan dengan PIN.
- [ ] Impor dari file Excel lama membaca nilai sheet Grade 3–6 dengan benar.
- [ ] Ekspor rekap kelas menghasilkan nilai & level identik dengan tampilan web.
