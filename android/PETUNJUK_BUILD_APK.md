# 📱 PANDUAN LENGKAP MEMBUAT FILE APK KASIR TABLET ANDROID (OFFLINE POS)

Proyek ini telah dilengkapi dengan arsitektur **Offline-First Tablet POS**:
- Menggunakan database lokal tablet (**IndexedDB/SQLite**) &mdash; bisa transaksi 100% offline tanpa koneksi PC/internet.
- Sinkronisasi otomatis ke PC Server (`/api/tablet/*`) saat tersambung Wi-Fi.
- Mendukung **Cetak Struk Bluetooth Thermal (ESC/POS)** langsung dari tablet.

Berikut 3 cara mudah untuk mendapatkan/memasang aplikasi di tablet Android Anda:

---

## ⚡ CARA 1: Generate File APK Siap Pakai via PWABuilder (1 Menit, Tanpa Install Android Studio)
Ini adalah cara resmi standar Google/Microsoft untuk mengubah Web PWA menjadi berkas **`.apk`** murni:

1. Pastikan server JPOS Anda sedang menyala.
2. Buka situs gratis: **[https://www.pwabuilder.com/](https://www.pwabuilder.com/)**
3. Masukkan alamat tablet Anda, misalnya: `http://seraya.local:8000/tablet` (atau domain Anda).
4. Klik **Start** &rarr; lalu klik **Generate APK** (pilih platform **Android**).
5. Unduh file **`.apk`** yang dihasilkan, lalu pindahkan dan install di tablet Android Anda!

---

## 🛠️ CARA 2: Build File APK Menggunakan Android Studio (Folder `android/`)
Jika Anda atau programmer Anda ingin mem-build APK secara mandiri:

1. Buka aplikasi **Android Studio**.
2. Pilih **Open an Existing Project** &rarr; arahkan ke folder:
   `/Applications/XAMPP/xamppfiles/htdocs/Jpos/android`
3. Tunggu proses sinkronisasi Gradle selesai.
4. Klik menu **Build** &rarr; **Build Bundle(s) / APK(s)** &rarr; **Build APK(s)**.
5. Android Studio akan menghasilkan file `app-debug.apk` di folder `android/app/build/outputs/apk/debug/`.
6. Kirim file APK tersebut ke tablet dan instal!

---

## 📲 CARA 3: Instal Langsung di Layar Tablet via Google Chrome (WebAPK - Tanpa Perlu Build APK)
Android memiliki fitur resmi **WebAPK** yang langsung mengubah halaman web menjadi aplikasi Android native:

1. Di tablet Android Anda, buka **Google Chrome**.
2. Masukkan alamat: `http://seraya.local:8000/tablet`
3. Klik tombol **titik tiga (⋮)** di kanan atas Chrome &rarr; pilih **"Instal Aplikasi"** (atau *"Tambahkan ke Layar Utama"*).
4. Selesai! Ikon **JPOS Kasir Tablet** akan muncul di menu aplikasi tablet Anda, dan saat dibuka berjalan **Layar Penuh (Full Screen)** tanpa bar browser!

---

### 🔄 Cara Menjalankan Kasir Tablet:
1. **Buka Aplikasi Kasir** di tablet Anda.
2. Klik tombol **"Sinkron Data"** untuk mengunduh seluruh produk dari komputer PC utama ke memori tablet.
3. Tablet sekarang **BISA DIGUNAKAN TRANSAKSI BEBAS** bahkan jika koneksi Wi-Fi toko mati total (Offline).
4. Hubungkan printer thermal via menu **Pengaturan &rarr; Sambungkan Printer Bluetooth**.
5. Saat toko tutup atau Wi-Fi tersambung kembali, klik **"Sinkron Data"** &mdash; seluruh transaksi di tablet otomatis masuk ke laporan omset dan memotong stok di PC utama!
