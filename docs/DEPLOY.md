# Panduan Deploy ke Shared Hosting (cPanel)

Aplikasi tidak membutuhkan Node.js, queue worker, maupun Chrome di server (PRD-01 §1). Semua aset dibangun di komputer lokal.

## 1. Persiapan di komputer lokal (Laragon)

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build                 # menghasilkan public/build
```

Siapkan file `.env` untuk produksi (salin dari `.env.example`):

```dotenv
APP_NAME="ICP CEFR Report"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://rapor.namasekolah.sch.id
APP_TIMEZONE=Asia/Jakarta

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=namauser_icp
DB_USERNAME=namauser_icp
DB_PASSWORD=********

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=sync

# Isi string acak panjang SEMENTARA untuk installer web; kosongkan lagi setelah selesai.
INSTALL_TOKEN=ganti-dengan-string-acak-panjang
```

`APP_KEY`: jalankan `php artisan key:generate --show` di lokal lalu salin hasilnya ke `.env` produksi.

Kompres proyek **tanpa** folder `node_modules`, `.git`, `tests`, dan `docs/source`.

## 2. Di hosting

1. **Database**: buat database & user MySQL di cPanel (MySQL Databases), beri semua hak akses.
2. **PHP**: di "Select PHP Version" pilih **PHP 8.2 atau lebih baru**. Aktifkan ekstensi:
   `pdo_mysql, mbstring, gd, zip, xml, fileinfo, intl, bcmath`.
3. **Unggah aplikasi** ke folder **di luar** `public_html`, mis. `~/icp-app`, lalu ekstrak.
4. **Folder publik** — pilih salah satu:
   - *Disarankan (subdomain)*: arahkan document root subdomain `rapor.namasekolah.sch.id` ke `~/icp-app/public`.
   - *Alternatif*: salin isi `~/icp-app/public/` ke `public_html/` (atau folder subdomain), lalu ubah dua baris di `public_html/index.php`:
     ```php
     require __DIR__.'/../icp-app/vendor/autoload.php';
     $app = require_once __DIR__.'/../icp-app/bootstrap/app.php';
     ```
     dan pastikan `public_html/build` berisi hasil `npm run build`.
5. **Izin folder**: `storage/` dan `bootstrap/cache/` harus dapat ditulis (755/775).
6. **Pasang database** — pilih salah satu:
   - *Ada Terminal/SSH*:
     ```bash
     cd ~/icp-app
     php artisan migrate --force && php artisan db:seed --force && php artisan optimize
     ```
   - *Tanpa SSH*: buka `https://rapor.namasekolah.sch.id/install?token=ISI_INSTALL_TOKEN`.
     Halaman memeriksa versi PHP, ekstensi, izin folder, dan koneksi database, lalu tombol **Pasang sekarang** menjalankan migrasi + data awal (level CEFR & 20 deskriptor).
     Installer otomatis tidak bisa dipakai lagi setelah tabel terbentuk.
7. **Kosongkan `INSTALL_TOKEN`** di `.env` setelah selesai.
8. Buka aplikasi → **Setup awal**: identitas sekolah, buat PIN, simpan kode pemulihan.

## 3. Cron (opsional)

Tambahkan cron setiap menit:

```
* * * * * php ~/icp-app/artisan schedule:run >> /dev/null 2>&1
```

Tanpa cron, pembersihan log (> 1 tahun) dan file cetak massal yang kedaluwarsa (> 24 jam) tetap berjalan otomatis maksimal sekali per jam saat ada yang masuk dengan PIN.

## 4. Memperbarui ke versi baru

1. Di lokal: `composer install --no-dev -o` dan `npm run build`.
2. **Unduh cadangan** dulu di aplikasi: Pengaturan → Cadangan.
3. Unggah file yang berubah (folder `app`, `bootstrap`, `config`, `database`, `resources`, `routes`, `vendor`, `public/build`).
4. Jalankan migrasi:
   - SSH: `php artisan migrate --force && php artisan optimize`
   - Tanpa SSH: isi `INSTALL_TOKEN` sementara, buka `/install?token=…`, masukkan **PIN sekolah**, klik **Perbarui database**, lalu kosongkan lagi `INSTALL_TOKEN`.

## 5. Keamanan & operasional

- Wajib HTTPS (`APP_URL=https://…`, `SESSION_SECURE_COOKIE=true`). Aktifkan AutoSSL di cPanel.
- `APP_DEBUG=false` di produksi. Error dicatat di `storage/logs/laravel.log` dengan kode `ERR-XXXXX` yang juga ditampilkan ke pengguna.
- File logo, dokumen, snapshot rapor, dan PDF sementara disimpan di `storage/app/private` (tidak dapat diakses langsung dari web).
- **Cadangan**: unduh setiap akhir semester (Pengaturan → Cadangan). Pulihkan mengganti seluruh data dan mengembalikan PIN ke PIN saat cadangan dibuat. Batas unggah mengikuti `upload_max_filesize` / `post_max_size` PHP (maks. 50 MB di aplikasi).
- **PIN terlupa & kode pemulihan hilang**: lewat SSH jalankan `php artisan pin:reset`.
- Ganti PIN setiap semester dan setiap ada pergantian personel.

## 6. Cetak

- Uji cetak di printer sekolah dengan kertas **F4 (215 × 330 mm)**: skala **100% / Actual size**, header & footer browser dimatikan.
- Bila printer tidak memiliki ukuran F4, tambahkan ukuran kustom 215 × 330 mm atau pakai A4 (Pengaturan → Rapor → Kertas bawaan).
