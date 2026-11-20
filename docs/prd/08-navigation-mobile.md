# PRD-08 — Navigasi Sub Menu Kelas & Tampilan Mobile/Responsif

> Tema yang dipakai: **Tema 1 — Navy Akademik** (PRD-07 §2). Dokumen ini **tidak** menambah desain baru di kanvas. Tampilan mobile dibangun dari token dan komponen yang sama, mengikuti spesifikasi di bawah ini.

## 1. Tujuan
1. Guru langsung menuju kelasnya lewat **satu menu Kelas** yang menggabungkan Input Nilai dan Rapor, dengan sub menu kelas per jenjang.
2. Semua fitur tetap dapat dipakai di ponsel dan tablet. Yang paling penting: **input nilai**, **lihat & unduh rapor**, dan **dashboard**.
3. Tampilan tetap formal, jelas terbaca, dan konsisten di semua ukuran layar.

## 2. Sub menu kelas per jenjang

### 2.1 Struktur
Input Nilai dan Rapor **digabung** menjadi satu menu **Kelas** di sidebar yang bisa dibuka-tutup (*collapsible*). Isinya kelas-kelas **periode aktif**, dikelompokkan per jenjang. Mengklik kelas membuka halaman kelas dengan tab **Input Nilai | Rapor & Cetak | Riwayat** (PRD-04 §3); tab bawaan: Draft → Input Nilai, Final → Rapor & Cetak.

```
▦ Kelas                             ▾
   Semua kelas
   KELAS 3                          ▾
     3A          ✓ 25/25
     3B            18/25
   KELAS 4                          ▸   (tertutup)
   KELAS 5                          ▾
     5A          🔒 Final
     5B            22/25
   KELAS 6                          ▸
👥 Siswa
```

| Elemen | Ketentuan |
|---|---|
| "Semua kelas" | Tautan ke halaman kartu semua kelas (`/kelas`) |
| Judul jenjang | "KELAS 3" … "KELAS 6", huruf kapital kecil 12 px, warna `--sidebar-muted`; bisa diklik untuk membuka/menutup kelompok. Jenjang tanpa kelas tidak ditampilkan |
| Item kelas | Nama kelas (15 px), di kanan indikator status: |
| — Draft, belum lengkap | Teks `18/25` (warna `--sidebar-muted`) |
| — Draft, lengkap | Ikon ✓ emas + `25/25` (siap difinalkan) |
| — Final | Ikon gembok + teks "Final" |
| Item aktif | Latar `--sidebar-accent`, teks putih tebal, ikon emas. Kelompok yang berisi kelas aktif otomatis terbuka |
| Urutan | Jenjang 3→6, kelas urut abjad (3A, 3B, 3C) |
| Tooltip item kelas | "5A • Ms. Sarah • 22/25 lengkap • Draft" |

### 2.2 Perilaku
- **Data:** daftar kelas dikirim sebagai *shared prop* Inertia `nav.classes`, berisi `[{id, grade, name, status, complete, total}]`. Prop ini diperbarui lewat *partial reload* setelah autosave, finalisasi, pembatalan finalisasi, dan perubahan kelas, sehingga angka progres di sidebar selalu terkini.
- **Status buka/tutup:** kondisi tiap menu dan jenjang disimpan di `localStorage` (bawaan: menu Kelas terbuka, jenjang kelas aktif terbuka).
- **Pencarian kelas:** jika ada lebih dari 8 kelas, muncul kolom kecil "Cari kelas…" di atas daftar. Ketik "5" untuk menyaring kelas 5A dan 5B.
- **Periode tanpa kelas:** sub menu menampilkan "Belum ada kelas" dengan tautan "Tambah kelas" ke Periode & Kelas.
- **Sidebar diciutkan (72 px):** ikon Kelas membuka *flyout* (popover di samping) berisi kelompok jenjang yang sama.
- **Animasi:** buka/tutup 200 ms (tinggi + fade). Tanpa animasi bila `prefers-reduced-motion`.
- **Aksesibilitas:** pola *disclosure*. Tombol jenjang memakai `aria-expanded` dan `aria-controls`; kelas aktif memakai `aria-current="page"`; panah atas/bawah berpindah antar item dan kiri/kanan menutup/membuka kelompok.

### 2.3 Pemindah kelas di dalam halaman
Selain lewat sidebar, guru bisa berpindah kelas langsung dari halaman kelas (tab apa pun):
- **Judul halaman** "Kelas 5A ▾" berupa *class switcher*: dropdown (Command + Popover) berisi kelas yang dikelompokkan per jenjang, dengan indikator status dan kolom cari.
- Tombol **‹ ›** untuk ke kelas sebelumnya/berikutnya, urut jenjang lalu abjad. Tab yang sedang dibuka dipertahankan (mis. tetap di Rapor & Cetak).
- Jika masih ada antrean autosave saat pindah kelas → dialog "Masih ada 2 nilai yang belum tersimpan" (PRD-04 §4.6).

## 3. Breakpoint & kerangka tata letak

| Lebar layar | Perangkat | Navigasi | Header |
|---|---|---|---|
| **≥ 1280 px** | Laptop/PC | Sidebar terbuka 256 px | Breadcrumb, periode, SaveIndicator, Kunci layar |
| **1024–1279 px** | Laptop kecil | Sidebar terbuka (bisa diciutkan ke 72 px, pilihan disimpan) | Sama |
| **768–1023 px** | Tablet | Sidebar disembunyikan; tombol ☰ membuka **drawer kiri** berisi menu & sub menu kelas lengkap | Breadcrumb diringkas ke judul halaman |
| **< 768 px** | Ponsel | **Top app bar** + **navigasi bawah** (lihat §4) | Top app bar |

Aturan umum:
- Tidak ada scroll horizontal pada halaman. Tabel lebar diletakkan di dalam kotak `overflow-x: auto` dengan kolom pertama *sticky*, atau diubah menjadi kartu (lihat per halaman).
- Kolom grid berkurang otomatis: KPI 4 → 2 → 2 (2×2), kartu dua kolom → satu kolom.
- Padding konten: 32 px (desktop), 24 px (tablet), 16 px (ponsel).
- Judul halaman 30 px → 24 px di ponsel; teks dasar tetap 16 px.
- Dialog → **bottom sheet** (Drawer) di ponsel; Sheet samping → layar penuh di ponsel.
- Tooltip yang berisi informasi penting juga dapat diketuk di layar sentuh (berubah menjadi popover).

## 4. Kerangka navigasi ponsel (< 768 px)

### 4.1 Top app bar (tinggi 56 px, navy)
```
[☰]  ICP CEFR Report            [2026/27 S1]  [🔒]
```
- ☰ membuka drawer kiri berisi menu lengkap. Sub menu kelas per jenjang tampil sebagai *accordion*, sama seperti §2.
- Chip periode aktif (ketuk untuk mengganti periode yang dilihat).
- Tombol Kunci layar.
- Di halaman tabel nilai, area judul diganti nama kelas + SaveIndicator ringkas (ikon ✓ / spinner / ⚠).

### 4.2 Navigasi bawah (tinggi 64 px + `safe-area-inset-bottom`)
| Ikon | Label | Tujuan |
|---|---|---|
| layout-dashboard | Beranda | Dashboard |
| layout-grid | Kelas | Pemilih kelas (§4.3) → halaman kelas dengan tab |
| users | Siswa | Daftar siswa |
| menu | Lainnya | Bottom sheet: Periode & Kelas, Impor/Ekspor, Dokumen, Pengaturan, Log |

Item aktif: ikon emas di atas latar `--sidebar-accent`, label tebal. Navigasi bawah disembunyikan saat keyboard ponsel terbuka (input nilai) agar tidak menutupi kolom isian.

### 4.3 Pemilih kelas (ketuk "Kelas")
Halaman penuh berisi accordion per jenjang:
```
Kelas 3                       2 kelas ▾
 ┌───────────────────────────────┐
 │ 3A   Ms. Rina        ✓ 25/25  │
 │ ███████████████████████ 100%  │
 └───────────────────────────────┘
 ┌───────────────────────────────┐
 │ 3B   Mr. Budi          18/25  │
 │ ████████████████░░░░░░  72%   │
 └───────────────────────────────┘
Kelas 4                       2 kelas ▸
```
Setiap kartu kelas berukuran minimal 64 px dan bisa diketuk di seluruh area. Jenjang dari kelas yang terakhir dibuka terbuka otomatis.

## 5. Tampilan mobile per halaman

### 5.1 Masuk PIN
- Kartu memenuhi lebar layar (margin 16 px), logo di atas.
- Keypad 3×4 dengan tombol **64×64 px**, jarak 12 px. Titik PIN 16 px.
- Mendukung keypad numerik bawaan ponsel (`inputmode="numeric"`, `autocomplete="off"`).
- Hitung mundur penguncian tampil besar di atas keypad.

### 5.2 Dashboard
- Kartu KPI **2×2** (angka 24 px).
- Grafik bertumpuk satu kolom, lebar penuh. Grafik "Sebaran level per kelas" tetap bar horizontal; label kelas di kiri dan jumlah siswa di dalam segmen jika muat (jika tidak muat, lihat tooltip saat diketuk).
- "Progres input kelas" berupa daftar kartu (sama dengan pemilih kelas).
- Filter (periode, jenjang, hanya final) dipindah ke tombol **Filter** yang membuka bottom sheet.
- Grafik dimuat *lazy* saat masuk layar (IntersectionObserver) untuk menghemat data.

### 5.3 Tab Input Nilai (paling penting)
**Daftar siswa** (pengganti tabel):
```
[ Cari siswa…            ] [Belum lengkap ▾]
Lengkap 18/25 • Rata-rata 64,3%
┌─────────────────────────────────────┐
│ 1  Kevin Wijaya           ● A2      │
│    L 15  R 17  W 11  S 13  Meeting  │
└─────────────────────────────────────┘
┌─────────────────────────────────────┐
│ 3  Muhammad Rizki      Belum lengkap│
│    L 8   R 9   W 6   S –            │
└─────────────────────────────────────┘
```
**Form siswa** (layar penuh setelah kartu diketuk):
```
‹ Kembali     Muhammad Rizki (3/25)     ✓ Tersimpan
───────────────────────────────────────────────
Listening   [  8  ]  /20     A1
Reading     [  9  ]  /20     A1
Writing     [  6  ]  /20     A1
Speaking    [     ]  /20     –
───────────────────────────────────────────────
Overall  –   •   Belum lengkap
Komentar guru (opsional)                     ▸
───────────────────────────────────────────────
[ ‹ Sebelumnya ]              [ Berikutnya › ]
```
- Input tinggi **48 px**, font **18 px** (minimal 16 px agar iOS tidak memperbesar layar), `inputmode="decimal"`, tombol "Berikutnya" di keyboard berpindah ke skill berikutnya. Setelah Speaking, fokus pindah ke siswa berikutnya.
- Badge level di samping input berubah langsung (animasi pop).
- Tombol Sebelumnya/Berikutnya *sticky* di bawah. Geser kiri/kanan juga berpindah siswa (dengan tombol sebagai alternatif yang selalu ada).
- Komentar dibuka sebagai bottom sheet.
- Aksi kelas (Impor, Ekspor, **Finalkan**, **Batalkan Finalisasi**) ada di menu ⋮ pada top app bar. Finalkan tetap menampilkan dialog ringkasan + PIN dalam bentuk bottom sheet.
- Kelas final: form hanya-baca, banner hijau "Kelas sudah final".
- Autosave, offline, dan konflik sama persis dengan desktop (PRD-04 §4.3–4.6). Banner offline tampil di bawah top app bar.

**Tablet (768–1023 px):** tabel tetap dipakai. Kolom No & Nama *sticky*; kolom persen disembunyikan bawaan (toggle tersedia); tabel bisa digulir horizontal di dalam kotak.

### 5.4 Rapor & Cetak
- Tab di halaman kelas berupa *segmented control* di bawah top app bar: **Nilai | Rapor | Riwayat**.
- Bilah atas tab: pemilih kertas (F4/A4) dan menu ⋮ berisi **Unduh semua PDF** (satu file / ZIP) dan **Cetak semua**.
- Daftar siswa dalam kartu (nama, badge overall, status) dengan tombol **PDF** dan **Cetak**.
- Pratinjau rapor diskalakan selebar layar (rasio F4 atau A4 sesuai pilihan), bisa dicubit untuk zoom; tombol **Unduh PDF**, **Cetak**, dan **Bagikan** (Web Share API jika tersedia, untuk mengirim PDF via aplikasi lain) di bilah bawah *sticky*.
- Siswa sebelumnya/berikutnya dengan tombol ‹ › di bilah bawah.
- Cetak massal tetap tersedia; dialog progres menjadi bottom sheet. Ada catatan: "Disarankan memakai laptop untuk mencetak massal."

### 5.5 Siswa
- Kolom cari *sticky* di atas, filter dalam bottom sheet.
- Daftar kartu (nama, NIS, kelas, badge level terakhir).
- Tombol tambah melayang (FAB "+") di kanan bawah, di atas navigasi bawah.
- Profil siswa: grafik perkembangan selebar layar, tabel riwayat menjadi kartu per periode.

### 5.6 Periode & Kelas, Pengaturan, Log
- Form satu kolom; label di atas input.
- Tab Pengaturan menjadi *chip* yang bisa digulir horizontal.
- Tabel ambang batas CEFR digulir horizontal dengan kolom Level *sticky*.
- Log aktivitas berupa daftar kartu (waktu, badge kategori, deskripsi).

### 5.7 Impor/Ekspor & Dokumen
- Unggah file tetap bisa dari ponsel (pemilih file bawaan).
- Pratinjau impor: ringkasan status di atas, lalu daftar baris bermasalah sebagai kartu (baris, kolom, pesan). Tabel lengkap hanya di tablet/desktop. Ada catatan: "Pratinjau lengkap lebih nyaman di laptop."
- Dokumen: daftar kartu dengan tombol unduh.

## 6. Ergonomi & teknis
- Target sentuh minimal **44×44 px** (keypad PIN 64 px, item navigasi bawah 64 px).
- Meta viewport: `width=device-width, initial-scale=1, viewport-fit=cover`. Zoom tidak dikunci.
- Padding area aman (`env(safe-area-inset-*)`) untuk iPhone berponi dan Android dengan bilah gestur.
- Tidak ada informasi yang hanya muncul saat hover.
- Gambar/logo memakai `srcset` dan ukuran kecil; Chart.js dimuat *lazy*.
- Orientasi lanskap di ponsel: form nilai dua kolom (Listening/Reading | Writing/Speaking).
- PWA ringan (opsional v1.1): manifest + ikon agar bisa "Tambahkan ke layar utama"; **tanpa** mode offline penuh (data nilai tetap butuh server).

## 7. Perangkat uji
| Perangkat | Ukuran | Browser |
|---|---|---|
| Android kelas menengah | 360×800 | Chrome |
| iPhone | 390×844 | Safari |
| iPad | 768×1024 & 1024×768 | Safari |
| Laptop | 1280×800 | Chrome, Edge |
| PC | 1920×1080 | Chrome, Firefox |

## 8. Kriteria penerimaan
- [ ] Menu Kelas (gabungan Input Nilai & Rapor) menampilkan kelas periode aktif yang dikelompokkan per jenjang, dengan indikator progres/final yang langsung berubah setelah autosave atau finalisasi.
- [ ] Kelompok jenjang dari kelas aktif terbuka otomatis, dan status buka/tutup tersimpan setelah halaman dimuat ulang.
- [ ] Class switcher di halaman kelas memindahkan ke kelas lain tanpa kembali ke daftar kelas dan mempertahankan tab aktif.
- [ ] Membuka kelas Draft langsung ke tab Input Nilai; kelas Final langsung ke tab Rapor & Cetak.
- [ ] Pada lebar 360 px tidak ada scroll horizontal halaman, dan semua menu bisa dijangkau lewat navigasi bawah atau drawer.
- [ ] Guru dapat mengisi 4 nilai satu kelas (25 siswa) di ponsel hanya dengan keypad dan tombol Berikutnya, dan semua nilai tersimpan otomatis.
- [ ] Input di iOS tidak memicu zoom otomatis (font ≥ 16 px).
- [ ] PDF rapor dapat diunduh dan dibagikan dari ponsel.
- [ ] Semua dialog tampil sebagai bottom sheet di < 768 px dan tetap bisa ditutup dengan tombol Batal.
- [ ] Seluruh tampilan memakai token Tema 1 (Navy Akademik) tanpa warna baru di luar PRD-07.
