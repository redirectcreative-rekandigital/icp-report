# PRD-02 — Akses PIN, Sesi & Keamanan

## 1. Ringkasan
Aplikasi **tidak memakai login/akun**. Seluruh akses memakai **satu PIN sekolah 4 digit**. Karena PIN pendek dan dipakai bersama, keamanan ditopang oleh: pembatasan percobaan, penguncian, sesi yang berakhir otomatis, konfirmasi ulang PIN untuk aksi sensitif, kode pemulihan, dan log aktivitas.

## 2. User stories
| ID | Sebagai | Saya ingin | Agar |
|---|---|---|---|
| US-02.1 | Koordinator | membuat PIN saat pertama kali membuka aplikasi | aplikasi langsung terlindungi |
| US-02.2 | Guru | memasukkan PIN dengan cepat (keypad) | bisa langsung bekerja |
| US-02.3 | Koordinator | mengganti PIN | PIN lama yang bocor tidak berlaku |
| US-02.4 | Koordinator | mereset PIN yang terlupa dengan kode pemulihan | tidak terkunci dari aplikasi |
| US-02.5 | Guru | mengunci layar saat meninggalkan komputer | orang lain tidak bisa mengubah nilai |
| US-02.6 | Koordinator | melihat log aktivitas | tahu kapan & dari perangkat mana data diubah |

## 3. Setup awal (`/setup`)
Muncul **hanya** jika `pin_hash` belum ada. Setelah selesai, `/setup` mengembalikan 404.

Langkah (wizard 3 langkah, dengan indikator langkah):
1. **Identitas sekolah**: nama sekolah (wajib), nama program (bawaan "International Class Program"), nama mitra, kota.
2. **Buat PIN**: masukkan PIN 4 digit dua kali. Tolak PIN lemah: `0000`, `1111`…`9999`, `1234`, `4321`, `1212`, `2580`, dan tahun `19xx/20xx` → pesan "PIN terlalu mudah ditebak. Pilih kombinasi lain."
3. **Kode pemulihan**: sistem menampilkan kode 12 karakter (mis. `K7QM-4XPA-9RTE`), tombol **Salin** dan **Unduh .txt**. Checkbox wajib "Saya sudah menyimpan kode pemulihan" sebelum tombol **Selesai** aktif.

Kriteria penerimaan:
- Given PIN belum dibuat, When membuka URL apa pun, Then diarahkan ke `/setup`.
- Given setup selesai, When membuka `/setup`, Then 404.
- PIN & kode pemulihan disimpan sebagai **hash bcrypt**, tidak pernah dalam teks biasa atau log.

## 4. Masuk PIN (`/pin`)

### 4.1 Tampilan
- Kartu di tengah layar: logo & nama sekolah, judul "Masukkan PIN", **4 kotak titik** (●○○○), **keypad numerik** 3×4 (1–9, hapus, 0, masuk) yang juga bisa diketik dengan keyboard fisik.
- Otomatis mengirim ketika digit ke-4 diisi.
- Tautan kecil "Lupa PIN?" → `/pin/recover`.

### 4.2 Perilaku
| Kondisi | Respons UI |
|---|---|
| PIN benar | Animasi centang singkat → masuk ke halaman tujuan semula (intended URL) atau Dashboard |
| PIN salah | Titik bergetar (shake 300 ms) + warna merah, pesan "PIN salah. Sisa percobaan: N" |
| 5 kali salah dalam 15 menit (per IP) | Keypad dinonaktifkan, hitung mundur "Coba lagi dalam 14:59" (HTTP 429) |
| 20 kali salah dalam 1 jam (global semua IP) | Penguncian global 30 menit + pesan; tercatat di log sebagai `pin.global_lock` |
| Server error / offline | Pesan "Tidak dapat terhubung. Periksa koneksi lalu coba lagi." + tombol Coba lagi |

Teknis:
- `RateLimiter` Laravel dengan kunci `pin:{ip}` (5/15 menit) dan `pin:global` (20/60 menit).
- Setelah berhasil: `session()->regenerate()`, set `pin_verified_at` dan `last_activity_at` di sesi.
- Setiap percobaan gagal & berhasil dicatat ke `activity_logs` (`pin.failed`, `pin.success`) beserta IP & perangkat.

## 5. Sesi
- **Idle timeout** bawaan 60 menit (dapat diubah 15–240 menit di Pengaturan → Keamanan). Middleware memperbarui `last_activity_at` pada setiap permintaan; jika melewati batas → sesi dihapus, redirect ke `/pin` dengan pesan "Sesi berakhir karena tidak ada aktivitas."
- **Peringatan**: 2 menit sebelum habis, muncul dialog "Sesi akan berakhir dalam 2:00 — Tetap masuk?" (tombol Tetap masuk memanggil endpoint ringan `/ping`).
- **Batas absolut** sesi: 12 jam sejak PIN dimasukkan.
- **Kunci layar**: tombol di header (ikon gembok) dan pintasan keyboard `Alt + L` (tidak bentrok dengan pintasan browser) → logout sesi.
- Jika sesi berakhir saat ada autosave tertunda: nilai yang belum tersimpan disimpan sementara di `sessionStorage`, lalu setelah PIN dimasukkan kembali, sistem menawarkan "Ada 3 perubahan yang belum tersimpan. Simpan sekarang?" (lihat PRD-04 §4.6).

## 6. Konfirmasi PIN untuk aksi sensitif
Aksi berikut memerlukan PIN dimasukkan dalam **5 menit terakhir** (middleware `pin.recent`). Jika belum, klien menampilkan **dialog PIN** (komponen `PinConfirmDialog`), lalu mengulang aksi secara otomatis.

- Finalisasi kelas & **Batalkan finalisasi**
- Mengubah ambang batas, bobot, maksimum skor, level harapan
- Menghapus siswa/kelas/periode, mengosongkan nilai kelas
- Ganti PIN, buat ulang kode pemulihan
- Unduh/pulihkan cadangan data
- Impor yang menimpa nilai yang sudah ada

## 7. Ganti PIN (Pengaturan → Keamanan)
Form: PIN lama, PIN baru, ulangi PIN baru. Aturan PIN lemah sama dengan setup. Setelah berhasil:
- Semua sesi lain diakhiri (hapus baris di tabel `sessions` kecuali sesi saat ini).
- Toast "PIN berhasil diganti. Perangkat lain perlu memasukkan PIN baru."
- Log `pin.changed`.

## 8. Lupa PIN (`/pin/recover`)
- Masukkan **kode pemulihan** + PIN baru (2×).
- Rate limit 5 percobaan/jam per IP.
- Berhasil → PIN diganti, **kode pemulihan baru** dibuat & ditampilkan (kode lama tidak berlaku), semua sesi diakhiri.
- Cadangan darurat (untuk pengembang): perintah `php artisan pin:reset` (membutuhkan akses server).

## 9. Log aktivitas (`/log`)
- Tabel: waktu, aksi (badge berwarna per kategori: Akses, Nilai, Finalisasi, Data, Pengaturan), deskripsi Bahasa Indonesia (mis. "Nilai Listening Kevin Wijaya (5A) diubah 13 → 15"), perangkat ("Chrome • Windows"), IP, kode sesi pendek.
- Filter: rentang tanggal, kategori, kelas; pencarian teks.
- Paginasi 50 baris; ekspor CSV.
- Nilai yang diubah dicatat **per autosave**, tetapi beruntun pada sel yang sama dalam 60 detik dari sesi yang sama digabung menjadi satu entri (nilai awal → nilai akhir).
- Retensi 365 hari (pembersihan terjadwal/oportunistik).

## 10. Keamanan tambahan
- Halaman `/pin` dan seluruh aplikasi mengirim `X-Robots-Tag: noindex`.
- Tidak ada data siswa di URL selain ID numerik.
- Respons error tidak membocorkan detail teknis (hanya kode referensi).
- File cadangan & PDF sementara disimpan di storage privat, tautan unduh hanya berlaku untuk sesi yang memintanya dan kedaluwarsa 1 jam.
- Catatan kebijakan (ditampilkan di Pengaturan → Keamanan): "PIN dipakai bersama. Ganti PIN setiap semester dan setiap kali ada perubahan personel."

## 11. Kasus tepi
| Kasus | Penanganan |
|---|---|
| Dua tab terbuka, satu tab dikunci | Tab lain mendapat 401/redirect pada permintaan berikutnya → dialog "Sesi telah dikunci. Masukkan PIN." tanpa kehilangan isian |
| Jam server berbeda | Semua perhitungan waktu memakai waktu server; tampilan memakai zona `Asia/Jakarta` |
| Cookie diblokir | Pesan di `/pin`: "Browser menolak cookie. Aktifkan cookie untuk situs ini." |
| Token CSRF kedaluwarsa (419) | Klien memuat ulang token secara diam-diam dan mengulang permintaan sekali; jika gagal → dialog PIN |

## 12. Kriteria penerimaan
- [ ] Tanpa PIN terverifikasi, semua rute selain `/pin`, `/setup`, `/pin/recover` diarahkan ke `/pin`.
- [ ] Percobaan ke-6 dalam 15 menit dari IP yang sama ditolak 429 dengan hitung mundur.
- [ ] Aksi sensitif tanpa PIN dalam 5 menit terakhir memunculkan dialog PIN, dan aksi berlanjut setelah PIN benar.
- [ ] Ganti PIN mengakhiri sesi di perangkat lain.
- [ ] PIN/kode pemulihan tidak pernah muncul di log, respons, atau database dalam bentuk asli.
