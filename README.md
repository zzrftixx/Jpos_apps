# JPos — Aplikasi Kasir & Manajemen Ritel Offline

Aplikasi Point of Sale (POS) modern, cepat, dan handal berbasis **Laravel 13 + SQLite** yang dirancang khusus untuk berjalan **100% offline di toko ritel aktif**. Tanpa ketergantungan koneksi internet, tanpa CDN eksternal, dan teroptimasi untuk perangkat keras kasir (thermal printer 58mm/80mm & barcode scanner).

---

## 🌟 Fitur Utama

- **Modul Kasir Cepat (High-Speed Checkout):**
  - Pencarian instan dan pemindaian barcode dengan indeks hash map $O(1)$.
  - Tampilan kartu produk responsif dengan pemuatan pintar (*DOM slicing*) untuk katalog ribuan produk.
  - Multi-satuan produk (Pcs, Box, Karung, dll.) dengan konversi otomatis.
  - Skema harga bertingkat (*Tier Pricing*): Umum, Reseller, dan Grosir.
  - Penangguhan transaksi (*Waiting List / Parked Order*) & Retur Penjualan.

- **Promo Diskon & Loyalitas Pelanggan:**
  - Pembuatan promo diskon produk terintegrasi dengan pemindaian barcode rak promo.
  - Sistem poin loyalitas pelanggan otomatis dan penukaran voucher belanja saat checkout.

- **Pembukuan Akuntansi & Pelaporan Presisi:**
  - Pemisahan jelas antara **Laporan Penjualan (Akrual)** dan **Realisasi Kas Masuk/Keluar (Cash Flow)**.
  - Laporan Rekapitulasi Mutasi Stok (Stok Awal, Kulakan Masuk, Penjualan Keluar, Sisa Stok).
  - Ekspor laporan ke format PDF dan Excel rapi dan siap cetak.

- **Hardware & Operasional Toko:**
  - Dukungan pencetakan struk termal 58mm dan 80mm dengan layout tabel presisi anti-terpotong.
  - Standby Launcher otomatis di sistem tray Windows untuk mencegah background freeze.
  - Manajemen Shift Kasir dan audit saldo awal/akhir laci kasir.

- **Ekosistem Kasir Tambahan (Android Tablet & Multi-Device LAN):**
  - Antarmuka khusus tablet kasir (*Tablet Offline POS*) berbasis PWA di `/tablet` dengan penyimpanan lokal IndexedDB.
  - Aplikasi pembungkus Android Kiosk Native (`android/`) dengan fitur cetak Bluetooth ESC/POS.
  - Sinkronisasi transaksi offline aman via token kriptografis LAN (`X-JPOS-TABLET-TOKEN`).

---

## 🛠️ Panduan Pengembangan (Developer Quickstart)

### Prasyarat
- PHP >= 8.2 (ekstensi `pdo_sqlite`, `sqlite3`, `fileinfo`, `mbstring`, `zip`, `gd` aktif)
- Composer
- Node.js (untuk verifikasi pengujian otomatis)
- Web Server lokal (disarankan **Laragon** di Windows)

### Langkah Pemasangan

1. **Clone Repositori:**
   ```bash
   git clone https://github.com/zzrftixx/Jpos_apps.git
   cd Jpos_apps/JPos
   ```

2. **Setup Konfigurasi Environment:**
   ```bash
   cp .env.example .env
   ```

3. **Pasang Dependensi Composer:**
   ```bash
   composer install
   ```

4. **Buat Database SQLite & Application Key:**
   ```bash
   touch database/database.sqlite
   php artisan key:generate
   ```

5. **Jalankan Migrasi & Data Uji (Seeder):**
   ```bash
   php artisan migrate --seed
   ```

6. **Jalankan Server Lokal:**
   ```bash
   php artisan serve
   ```
   Akses di peramban: `http://localhost:8000`  
   *Akun bawaan development: `admin` / `password` atau `kasir1` / `password`.*

---

## 🧪 Gerbang Pengujian Otomatis

Sebelum mengajukan perubahan atau Pull Request, pastikan seluruh gerbang pengujian berstatus hijau:

```bash
# 1. Unit & Feature Test Laravel
php vendor/bin/phpunit

# 2. Pengujian Sintaks Reaktif Alpine.js
node tests/js/alpine-xdata.test.cjs

# 3. Pengujian Modul Format & Presisi Angka
node tests/js/jpos-number.test.cjs

# 4. Pengujian Deteksi Pemindai Barcode
node tests/js/jpos-pemindai.test.cjs
```

---

## 📜 Log Riwayat Pembaruan Berkala

Berikut adalah catatan pembaruan fitur, optimasi, dan pemeliharaan aplikasi JPos:

| Versi | Tanggal | Modul / Area | Deskripsi Pembaruan | Fungsi & Manfaat |
| :--- | :--- | :--- | :--- | :--- |
| **2.15.0** | 19 Okt 2026 | Android & Tablet POS | Integrasi Ekosistem Tablet Offline-First PWA, Kiosk Sync, & Token Pengaman LAN | Mendukung kasir tambahan mobile (tablet Android/iPad) dengan sinkronisasi lokal aman |
| **2.14.3** | 19 Okt 2026 | Hardware & Scanner | Dukungan Barcode Scanner berakhiran Tab/Enter & Standby Tray | Menjamin pembacaan akurat scanner fisik tanpa salah input nominal bayar |
| **2.14.2** | 13 Okt 2026 | Modul Kasir & Barcode | Slicing DOM 60 kartu & Hash Map $O(1)$ Barcode Index | Akselerasi kasir responsif bebas lag untuk katalog besar (>1.800 produk) |
| **2.14.1** | 13 Okt 2026 | Master Produk & Pengaturan | Sakelar fleksibel harga kategori pelanggan & normalisasi input | Memberikan opsi aktivasi harga reseller/grosir sesuai jenis toko |
| **2.14.0** | 12 Okt 2026 | Promo & Pelanggan | Modul Promo Diskon Barcode & Poin Loyalitas Pelanggan | Meningkatkan retensi pelanggan lewat poin belanja dan voucher |
| **2.13.6** | 03 Okt 2026 | Performa & Navigasi | Akselerasi navigasi instan & optimasi SQLite MMAP 256MB | Transisi antar halaman 0ms tanpa kedipan putih peramban |
| **2.13.5** | 03 Okt 2026 | Launcher Windows | Standby launcher POS-grade & perbaikan pembuatan APP_KEY | Mencegah auto-kill service saat peramban masuk mode hemat memori |
| **2.13.4** | 02 Okt 2026 | Struk & Hardware | Penjagaan cetak struk termal 58mm/80mm layout tabel fixed | Hasil cetakan struk presisi, rapi, dan tidak terpotong tepi kertas |
| **2.13.0** | 01 Okt 2026 | Laporan & Mutasi | Laporan Rekap Mutasi Stok (Stok Awal, Masuk, Keluar, Sisa) | Memudahkan audit pergerakan fisik barang antara gudang dan etalase |
| **2.12.0** | 28 Sep 2026 | Shift Kasir & Multi-Satuan | Modul Shift Kasir & Penetapan Harga Multi-Satuan Pelanggan | Kontrol uang laci pergantian kasir dan harga grosir per kemasan |
| **2.11.0** | 15 Sep 2026 | Akuntansi & Kas | Pemisahan Akuntansi Akrual (`sales`) vs Realisasi Kas (`sale_payments`) | Menjamin kesesuaian antara uang setoran fisik dengan catatan sistem |

---

## ⚖️ Lisensi & Hak Cipta

Hak Cipta &copy; 2026 JPos by JaylaTech. Seluruh hak cipta dilindungi undang-undang.
Dirancang khusus untuk kehandalan operasional toko ritel luring.
