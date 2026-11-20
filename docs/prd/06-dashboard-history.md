# PRD-06 — Dashboard Rekap & Riwayat Perkembangan

## 1. Ringkasan
**Dashboard** memberi gambaran capaian CEFR periode terpilih untuk koordinator, kepala sekolah, dan mitra. **Riwayat** menampilkan perkembangan level setiap siswa dari semester ke semester.

Sumber data:
- Kelas **Final** → `final_results` (`is_current=true`).
- Kelas **Draft** → perhitungan langsung dari `scores` (ditandai "sementara").
- Toggle "Hanya kelas final" (bawaan **mati** di dashboard, **hidup** untuk riwayat).

## 2. User stories
| ID | Sebagai | Saya ingin | Agar |
|---|---|---|---|
| US-06.1 | Kepala sekolah | melihat sebaran level per kelas | menilai efektivitas program |
| US-06.2 | Koordinator | melihat progres input & finalisasi | tahu kelas mana yang tertinggal |
| US-06.3 | Mitra | membandingkan rata-rata per skill | menentukan fokus pembelajaran |
| US-06.4 | Guru | melihat grafik perkembangan satu siswa | bahan diskusi dengan orang tua |
| US-06.5 | Koordinator | membandingkan dua periode | melihat kemajuan angkatan |

## 3. Dashboard (`/`)

### 3.1 Header & filter
- Salam singkat + nama sekolah, periode (dropdown, bawaan periode aktif), filter grade (Semua/3/4/5/6), toggle "Hanya kelas final".
- Tombol **Ekspor rekap periode (.xlsx)**.

### 3.2 Kartu KPI (baris atas, 4 kartu, angka beranimasi count-up)
| Kartu | Isi | Detail |
|---|---|---|
| Siswa dinilai | `128 / 140` | Progress ring persen kelengkapan |
| Kelas final | `4 / 6` | Daftar kelas belum final saat di-hover |
| Rata-rata overall | `66,4%` | Panah naik/turun dibanding periode sebelumnya (mis. ▲ 3,1) |
| Sesuai/di atas harapan | `82%` | Persentase siswa Meeting + Above |

### 3.3 Grafik
| Grafik | Jenis | Keterangan |
|---|---|---|
| **Sebaran level per kelas** | Stacked bar horizontal (100%) | Satu bar per kelas, segmen warna level Pre-A1→B2, label jumlah; klik segmen → daftar siswa level tsb |
| **Sebaran level per grade** | Stacked bar vertikal | Grade 3–6 |
| **Rata-rata per skill** | Grouped bar (4 skill × kelas) atau radar per grade (toggle) | Menunjukkan skill terlemah |
| **Status terhadap harapan** | Doughnut | Below / Meeting / Above / Belum lengkap |
| **Perbandingan periode** | Line/bar | Rata-rata overall per grade untuk periode-periode final (maks 6 periode) |

Semua grafik: tooltip berbahasa Indonesia, legenda dengan warna level, tombol "Unduh PNG", skeleton saat memuat (deferred props Inertia), empty state bila belum ada data ("Belum ada nilai di periode ini — mulai dari menu Kelas").

### 3.4 Tabel progres kelas
Kolom: Kelas • Guru • Siswa • Lengkap (progress bar) • Rata-rata overall • Sebaran mini (5 segmen) • Status (Draft/Final) • Aksi (Isi Nilai / Rapor). Urut bawaan: kelas Draft dengan kelengkapan terendah di atas.

### 3.5 Sorotan (insight otomatis, maks 3 kartu)
Aturan sederhana berbasis data, misalnya:
- "Writing adalah skill dengan rata-rata terendah di Kelas 5 (56%)."
- "9 siswa Kelas 6 berada di bawah level harapan (A2–B1)."
- "Kelas 4B belum diisi sama sekali."
Tiap kartu punya tautan ke halaman terkait.

## 4. Riwayat siswa (`/siswa/{id}`)

### 4.1 Header profil
Nama, NIS, kelas saat ini, badge level terakhir (final), jumlah periode tercatat.

### 4.2 Grafik perkembangan
- **Line chart** Overall % per periode (sumbu X: "2025/26 S1", "2025/26 S2", …), titik berwarna sesuai level, garis putus-putus untuk nilai Draft.
- Pita latar horizontal menunjukkan rentang level (ambang batas berbeda per grade → gunakan **level index** sebagai sumbu kedua; tooltip menampilkan % dan level).
- Toggle: Overall / per skill (4 garis warna skill).

### 4.3 Tabel riwayat
| Periode | Kelas | L | R | W | S | Overall | Status | Rapor |
|---|---|---|---|---|---|---|---|---|
| 2026/27 S1 | 5A | A2 (75%) | B1 | A2 | A2 | **A2** 70% | Meeting | [PDF] |

- Kolom perubahan: panah ↑/↓ level dibanding periode sebelumnya (mis. "A1 → A2 ↑").
- Tombol PDF membuka rapor periode tersebut (dari snapshot).

### 4.4 Ringkasan naratif (otomatis, Bahasa Indonesia)
"Kevin naik dari A1 (Kelas 4, Semester 2) ke A2 (Kelas 5, Semester 1). Skill dengan kenaikan terbesar: Reading (+20 poin persen)."

## 5. Perbandingan antar periode (di Dashboard → tab "Perbandingan")
- Pilih dua periode → tabel per grade: rata-rata overall, % siswa per level, selisih.
- Kohort: "Siswa yang sama" (berdasarkan NIS) vs "Semua siswa".
- Catatan: persentase antar grade tidak sebanding langsung karena tes & ambang berbeda → tampilkan perbandingan **level** sebagai metrik utama, persen sebagai pelengkap. Info ini ditampilkan sebagai tooltip ⓘ.

## 6. Kinerja
- Agregasi dengan query SQL (`GROUP BY classroom_id, overall_level`) pada `final_results`; untuk kelas Draft hitung di PHP lalu cache 60 detik per kelas (invalidasi saat autosave).
- Dashboard dimuat bertahap: KPI dulu (< 500 ms), grafik via deferred props.

## 7. Kasus tepi
| Kasus | Penanganan |
|---|---|
| Periode tanpa data | Empty state dengan ilustrasi & tombol aksi |
| Siswa pindah kelas antar periode | Riwayat tetap utuh karena berbasis `student_id` |
| Kelas dibatalkan finalisasinya | Dashboard memakai snapshot final terakhir dengan label "sedang dikoreksi" bila toggle "Hanya final" aktif; memakai data langsung bila tidak |
| Siswa dengan nilai belum lengkap | Tidak dihitung pada rata-rata/sebaran; dihitung di "Belum lengkap" |

## 8. Kriteria penerimaan
- [ ] Angka KPI & grafik sama dengan rekap ekspor Excel untuk periode yang sama.
- [ ] Klik segmen grafik sebaran membuka daftar siswa yang sesuai.
- [ ] Riwayat siswa menampilkan semua periode final dengan level & tautan PDF yang benar.
- [ ] Dashboard memuat KPI < 1 detik pada data 500 siswa di shared hosting.
