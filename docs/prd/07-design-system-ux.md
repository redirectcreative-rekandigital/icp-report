# PRD-07 — Design System, UX, Animasi & Error Handling

## 1. Prinsip desain
1. **Formal & jelas** — tampilan resmi sekolah, teks mudah dibaca, tidak ramai.
2. **Warna bermakna** — warna dipakai untuk level CEFR, status, dan skill; selalu didampingi teks/ikon (tidak hanya warna).
3. **Selalu ada umpan balik** — setiap klik menghasilkan respons visual: loading, sukses, atau gagal dengan solusi.
4. **Cepat untuk guru** — keyboard-first di tabel nilai, aksi utama selalu terlihat.
5. **Konsisten** — komponen shadcn-vue + token desain yang sama di semua halaman, dan sama dengan rapor.

## 2. Token warna

> **Tema terpilih: Tema 1 — Navy Akademik** (kanvas desain: "ICP CEFR Report — 3 Pilihan Tema", artboard *Tema 1 — Navy Akademik*). Tema 2 dan 3 hanya arsip pembanding dan **tidak** diimplementasikan.

### 2.1 Warna merek (Tailwind CSS v4 `@theme`, mode terang)
| Token | Hex | Pemakaian |
|---|---|---|
| `--primary` (Navy) | `#1F3A68` | Header, tombol utama, sidebar aktif |
| `--primary-foreground` | `#FFFFFF` | Teks di atas primary |
| `--accent` (Emas) | `#E3A82B` | Aksen, garis header, highlight |
| `--secondary` (Teal) | `#1F7A70` | Tombol sekunder, progress, judul bagian (kontras teks putih 5,2:1) |
| `--background` | `#F5F7FB` | Latar halaman |
| `--card` | `#FFFFFF` | Kartu, tabel |
| `--foreground` | `#111827` | Teks utama (kontras 16:1) |
| `--muted-foreground` | `#4B5563` | Teks sekunder (kontras 7,6:1) |
| `--border` | `#E2E8F0` | Garis |
| `--input-editable` | `#FFF8E1` | Latar sel yang dapat diedit |
| `--input-computed` | `#F3F4F6` | Latar sel hasil hitung |
| `--ring` | `#1F3A68` | Fokus (ring 2 px + offset putih 2 px) |
| `--sidebar` | `#1F3A68` | Latar sidebar & top app bar mobile |
| `--sidebar-foreground` | `#E5ECF6` | Teks menu sidebar |
| `--sidebar-muted` | `#AFC0DA` | Ikon & teks sekunder sidebar (kontras 6:1 di navy) |
| `--sidebar-accent` | `#2D4F86` | Latar item menu aktif / hover |
| `--sidebar-accent-icon` | `#E3A82B` | Ikon item aktif (emas) |
| `--kpi-icon-bg` | `#E8EEF8` | Latar ikon kartu KPI & avatar kelas |
| `--tag` / `--tag-foreground` | `#FFF4D6` / `#7A5300` | Chip penanda (mis. "Data contoh", "Draft") |
| `--radius` | `10px` | Sudut kartu, tombol, input (badge level & status: pil penuh) |

Mode gelap: **tidak** di v1 (fokus mode terang yang formal); token disiapkan agar mudah ditambah.

### 2.2 Warna level CEFR (dipakai di badge, grafik, rapor)
| Level | Latar | Teks | Pastel (latar lembut) |
|---|---|---|---|
| Pre-A1 | `#F4A261` | `#3B2A12` | `#FDE6D3` |
| A1 | `#E9C46A` | `#3B2A12` | `#FAF0D2` |
| A2 | `#1F7A70` | `#FFFFFF` | `#D4EDEA` |
| B1 | `#457B9D` | `#FFFFFF` | `#D9E5EF` |
| B2 | `#6A4C93` | `#FFFFFF` | `#E3DCEE` |

### 2.3 Warna status
| Status | Latar | Teks | Ikon |
|---|---|---|---|
| Below Expected | `#FDECEA` | `#B42318` | `arrow-down` |
| Meeting Expected | `#E7F6EC` | `#1E7B43` | `check` |
| Above Expected | `#E6F0FA` | `#1F5E99` | `arrow-up` |
| Belum lengkap | `#FFF4E5` | `#9A5B00` | `clock` |
| Draft | `#F1F5F9` | `#475569` | `pencil` |
| Final | `#E7F6EC` | `#1E7B43` | `lock` |

### 2.4 Warna skill (grafik & ikon)
| Skill | Warna | Ikon Lucide |
|---|---|---|
| Listening | `#2563EB` | `headphones` |
| Reading | `#059669` | `book-open` |
| Writing | `#D97706` | `pen-line` |
| Speaking | `#DB2777` | `mic` |

Warna skill dipakai untuk garis grafik, ikon, dan aksen (kontras non-teks ≥ 3 : 1), **bukan** sebagai latar teks kecil.

Semua kombinasi teks/latar wajib kontras **≥ 4,5 : 1** (WCAG AA); diuji di CI dengan skrip pemeriksa kontras token. Catatan: hijau tosca A2 di rapor Excel (`#2A9D8F`) hanya 3,3 : 1 dengan teks putih, sehingga di web dan PDF diganti `#1F7A70` (5,2 : 1).

## 3. Tipografi
- **UI**: `Plus Jakarta Sans` (formal, sangat terbaca, dukungan angka bagus) — fallback `Inter, system-ui, sans-serif`. Di-host lokal (tanpa Google Fonts CDN) agar cepat & privat.
- **Angka di tabel**: `font-variant-numeric: tabular-nums`.
- Skala: 12 (keterangan) • 14 (tabel, label) • **16 (teks dasar)** • 18 • 20 • 24 • 30 (judul halaman; 24 di mobile). Berat: 400, 500, 600, 700 (judul), 800 (nama aplikasi di sidebar).
- Tinggi baris 1,5 untuk teks, 1,25 untuk judul. Panjang baris paragraf maks ± 75 karakter.

## 4. Tata letak
- **Sidebar** kiri navy (lebar 256 px, bisa diciutkan ke 72 px ikon saja) berisi logo sekolah (kotak emas), nama aplikasi + nama sekolah, menu berikon dengan menu **Kelas** dengan **sub menu kelas per jenjang** (Input Nilai & Rapor digabung sebagai tab di halaman kelas), dan kartu periode aktif di bawah. Detail navigasi & perilaku di layar kecil: **PRD-08**.
- **Header** atas: breadcrumb, periode aktif (badge), SaveIndicator (di halaman nilai), tombol Kunci layar.
- Konten maks 1440 px; jarak dasar 4 px (spacing Tailwind); kartu `rounded-xl`, bayangan halus `shadow-sm`.
- Breakpoint: `sm 640`, `md 768`, `lg 1024`, `xl 1280`. Tata letak per breakpoint dan tampilan mobile setiap halaman: **PRD-08**.

## 5. Komponen

### 5.1 Dari shadcn-vue
Button, Input, Textarea, Label, Select, Combobox (Command + Popover), Checkbox, Switch, RadioGroup, Tabs, Card, Badge, Table, Dialog, AlertDialog, Sheet, DropdownMenu, Tooltip, Popover, Progress, Skeleton, Sonner (Toast), Alert, Breadcrumb, Sidebar (dengan `SidebarMenuSub` + `Collapsible`), Drawer (bottom sheet mobile, `vaul-vue`), Pagination, Separator, Avatar, ScrollArea, Form (vee-validate + zod), Calendar/DatePicker, Stepper (setup wizard).

### 5.2 Komponen aplikasi (custom)
| Komponen | Fungsi | State yang wajib ada |
|---|---|---|
| `LevelBadge` | Badge level CEFR berwarna, ukuran sm/md/lg/xl | normal, kosong ("–"), animasi pop saat berubah |
| `StatusBadge` | Below/Meeting/Above/Belum lengkap/Draft/Final | dengan ikon |
| `ScoreCell` | Sel input nilai tabel | idle, fokus, mengetik, tidak valid, menyimpan, tersimpan, gagal, terkunci |
| `SaveIndicator` | Status autosave global | tersimpan, menyimpan, menunggu (offline), gagal |
| `CefrLadder` | Peta 5 level dengan penanda level & rentang harapan | |
| `SkillRadar` | Radar 4 skill (Chart.js) | loading, kosong |
| `ClassCard` | Kartu kelas (progres, status, mini distribusi) | skeleton |
| `StatCard` | Kartu KPI dengan count-up & tren | skeleton |
| `LevelDistributionBar` | Bar 5 segmen warna level | kosong |
| `PinPad` | Keypad PIN 4 digit | idle, error (shake), terkunci (countdown), sukses |
| `PinConfirmDialog` | Dialog PIN untuk aksi sensitif | |
| `ImportDropzone` + `ImportPreviewTable` | Unggah & pratinjau impor | drag-over, membaca, galat |
| `BatchProgressDialog` | Progres PDF semua siswa / cetak semua | berjalan, jeda/gagal, selesai |
| `ReportSheet` | Pratinjau rapor F4/A4 sesuai layout | watermark DRAFT, kertas F4/A4 |
| `ReportLayoutEditor` | Form pengaturan rapor + pratinjau langsung (PRD-05 §5) | menyimpan, kontras warna kurang, logo belum diunggah |
| `ClassTabs` | Tab Input Nilai / Rapor & Cetak / Riwayat di halaman kelas | badge progres, tab bawaan sesuai status |
| `EmptyState` | Ilustrasi + judul + deskripsi + tombol aksi | |
| `ErrorState` | Pesan error + tombol coba lagi + kode referensi | |
| `OfflineBanner` | Banner koneksi | |
| `SessionTimeoutDialog` | Peringatan sesi habis | |

Semua komponen didokumentasikan di halaman internal `/_ui` (hanya di lingkungan lokal) yang menampilkan setiap varian & state.

## 6. Animasi & interaksi mikro
Durasi standar: **150 ms** (hover/fokus), **200–250 ms** (masuk/keluar elemen), **300 ms** (dialog/sheet). Easing `cubic-bezier(0.2, 0, 0, 1)`. **Semua animasi nonaktif bila `prefers-reduced-motion: reduce`.**

| Momen | Animasi |
|---|---|
| Pindah halaman (Inertia) | Fade + geser 8 px ke atas; progress bar tipis warna emas di atas layar (Inertia progress) |
| Kartu & baris tabel muncul | Stagger fade-in 30 ms per item (maks 10 item pertama) |
| Badge level berubah | Scale 0,9 → 1,05 → 1 + transisi warna |
| Simpan berhasil | Ikon centang menggambar diri (stroke) di SaveIndicator |
| Angka KPI | Count-up 600 ms |
| Progress bar | Transisi lebar halus |
| PIN salah | Shake horizontal 300 ms |
| Finalisasi berhasil | Ikon gembok menutup + konfeti ringan (≤ 1 detik) |
| Hover kartu kelas | Naik 2 px + bayangan sedikit lebih tebal |
| Dialog/Sheet | Fade + scale 0,97 → 1 / slide dari kanan |

## 7. Loading
| Situasi | Pola |
|---|---|
| Muat halaman pertama/ganti halaman | Progress bar Inertia (muncul setelah 250 ms) |
| Konten data (tabel, kartu, grafik) | **Skeleton** sesuai bentuk konten (bukan spinner penuh) |
| Tombol aksi | Spinner kecil di dalam tombol + teks berubah ("Menyimpan…", "Memfinalkan…"); tombol nonaktif untuk mencegah klik ganda |
| Autosave | SaveIndicator + titik berdenyut di sel |
| Unggah file | Progress bar persentase unggah, lalu "Membaca file…" |
| Proses panjang (cetak massal) | Dialog progres dengan langkah & estimasi waktu |
| Grafik | Skeleton kotak dengan kilau (shimmer) |

Aturan: jangan tampilkan loader untuk proses < 300 ms (hindari kedip); bila > 10 detik tampilkan pesan tambahan "Masih memproses, mohon tunggu…".

## 8. Error handling

### 8.1 Format respons error server (JSON)
```json
{
  "message": "Nilai tidak valid.",
  "code": "VALIDATION_FAILED",
  "errors": { "listening": ["Nilai harus antara 0 dan 20."] },
  "ref": "ERR-7F3K2"
}
```
`ref` dicatat di log server bersama stack trace, sehingga pengguna bisa melaporkan kode ini.

### 8.2 Matriks penanganan
| Kode HTTP / situasi | Contoh | UI |
|---|---|---|
| **422** Validasi | Field form salah | Pesan di bawah field (merah, ikon), fokus ke field pertama yang salah, ringkasan di atas form bila > 3 galat |
| **401** Belum PIN / sesi habis | Sesi idle | `PinConfirmDialog` di atas halaman; setelah PIN benar, permintaan diulang otomatis; isian tidak hilang |
| **419** CSRF kedaluwarsa | Tab dibiarkan lama | Ambil token baru & ulang sekali secara diam-diam; jika gagal → dialog PIN |
| **403** Aksi tidak diizinkan | — | Toast merah + penjelasan |
| **404** Tidak ditemukan | Siswa terhapus | Halaman 404 ramah: "Data tidak ditemukan" + tombol kembali ke Dashboard |
| **409** Konflik versi | Nilai diubah di perangkat lain | Dialog pilihan (PRD-04 §4.4) |
| **413** File terlalu besar | Unggah > batas | Pesan di dropzone |
| **423** Terkunci (final) | Edit nilai kelas final | Toast + muat ulang ke mode baca |
| **429** Terlalu banyak percobaan | PIN salah 5× | Countdown di PinPad |
| **500** Error server | Bug | Toast/ErrorState: "Terjadi kesalahan di server. Coba lagi. Bila berulang, laporkan kode ERR-7F3K2." + tombol Salin kode |
| **503** Mode pemeliharaan | Saat update | Halaman pemeliharaan dengan logo sekolah |
| Jaringan putus / timeout | Wi-Fi sekolah | OfflineBanner, coba ulang otomatis (backoff 1–16 detik), antrean autosave |
| Error JavaScript tak terduga | Komponen gagal render | Error boundary per halaman (`onErrorCaptured`) → ErrorState dengan tombol "Muat ulang halaman"; error dikirim ke endpoint `/client-errors` (dibatasi laju) |

### 8.3 Pedoman penulisan pesan
- Bahasa Indonesia baku, sopan, singkat; **apa yang terjadi + apa yang bisa dilakukan**.
  - ✅ "Nilai Reading 22 melebihi batas 20. Masukkan nilai 0–20."
  - ❌ "Validation error: reading max 20"
- Tanpa istilah teknis (SQL, exception, token) di UI.
- Toast sukses 3 detik; toast error tetap tampil hingga ditutup bila butuh tindakan.
- Aksi berbahaya memakai **AlertDialog** dengan tombol merah dan teks tombol spesifik ("Hapus kelas 5A", bukan "OK").

## 9. Empty state
| Halaman | Judul | Aksi |
|---|---|---|
| Dashboard tanpa data | "Belum ada nilai di periode ini" | Buka menu Kelas |
| Menu Kelas tanpa kelas | "Belum ada kelas di periode aktif" | Tambah kelas / Salin dari periode lalu |
| Kelas tanpa siswa | "Kelas 5A belum memiliki siswa" | Tambah siswa / Impor dari Excel |
| Pencarian kosong | "Tidak ada siswa bernama 'Rizky'" | Hapus filter |
| Dokumen kosong | "Belum ada dokumen" | Unggah dokumen |

Ilustrasi sederhana berbasis ikon (Lucide besar dalam lingkaran pastel) — konsisten & ringan.

## 10. Aksesibilitas
- Semua elemen interaktif dapat dijangkau keyboard; urutan fokus logis; ring fokus terlihat jelas (2 px `--ring`, offset 2 px).
- Label untuk setiap input; sel tabel nilai memiliki `aria-label` "Listening, Kevin Wijaya".
- Perubahan status autosave diumumkan lewat `aria-live="polite"`; error lewat `aria-live="assertive"`.
- Ukuran target sentuh minimal 40×40 px (keypad PIN 56×56 px).
- Tidak mengandalkan warna saja: badge level selalu berisi teks kode level; status berikon.
- Mendukung zoom browser 200% tanpa kehilangan fungsi.

## 11. Bahasa & format
- UI Bahasa Indonesia; rapor Bahasa Inggris (konten rapor tidak diterjemahkan).
- Angka: format Indonesia di UI (`66,4%`, `1.250`); format Inggris di rapor (`66.4%`).
- Tanggal UI: `18 Des 2026`, `18 Desember 2026 14.05`; rapor: `18 December 2026`.
- Zona waktu: `Asia/Jakarta`.

## 12. Pintasan keyboard
| Pintasan | Aksi |
|---|---|
| `/` | Fokus pencarian |
| `Alt + L` | Kunci layar |
| `Alt + S` | Simpan form (di form) |
| `[` / `]` | Siswa sebelumnya/berikutnya (panel siswa & pratinjau rapor) |
| `?` | Tampilkan daftar pintasan |

## 13. Kriteria penerimaan
- [ ] Semua token warna lulus uji kontras WCAG AA.
- [ ] Setiap tombol aksi async memiliki state loading & mencegah klik ganda.
- [ ] Setiap halaman data memiliki skeleton, empty state, dan error state.
- [ ] Mematikan jaringan pada halaman mana pun memunculkan OfflineBanner dalam ≤ 2 detik.
- [ ] Respons 401/419/409/423/429/500 ditangani sesuai matriks §8.2 (diuji dengan feature test & uji manual).
- [ ] Dengan `prefers-reduced-motion`, tidak ada animasi gerak.
- [ ] Seluruh alur input nilai dapat diselesaikan hanya dengan keyboard.
