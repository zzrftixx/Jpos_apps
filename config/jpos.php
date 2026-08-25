<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Versi Aplikasi
    |--------------------------------------------------------------------------
    |
    | Ditampilkan di Pengaturan > Tentang dan di tooltip system tray JPOS.exe,
    | serta dipakai paket update untuk tahu kapan cache perlu dibangun ulang.
    |
    | DIBACA DARI BERKAS VERSION, satu-satunya sumber kebenaran - berkas yang sama
    | yang dipakai build-exe.ps1 untuk menamai paketnya, dan yang ikut terkirim ke
    | kedua jenis paket. Sebelumnya angkanya ditulis di tiga tempat berbeda dan
    | ketiganya sudah tidak cocok satu sama lain: berkas VERSION 2.1.0, berkas ini
    | 2.0.0, dan halaman Tentang 1.0. Akibatnya client tidak punya cara memastikan
    | versi mana yang sebenarnya sedang berjalan - dan itu justru yang paling
    | dibutuhkan saat ada laporan masalah.
    |
    */

    'version' => env('JPOS_VERSION') ?: (
        is_readable($berkasVersi = dirname(__DIR__) . '/VERSION')
            ? trim(file_get_contents($berkasVersi))
            : 'tidak diketahui'
    ),

    /*
    |--------------------------------------------------------------------------
    | Sumber Aset UI
    |--------------------------------------------------------------------------
    |
    | Seluruh aset (Tailwind, Alpine, Chart.js, JsBarcode, Iconify) dimuat dari
    | folder public/vendor sehingga aplikasi berjalan penuh tanpa internet.
    |
    | tailwind_mode:
    |   'css'  - memakai public/vendor/jpos.css, satu file CSS hasil kompilasi.
    |            Jauh lebih ringan; ini yang dipakai secara normal.
    |   'play' - memakai compiler Tailwind yang berjalan di browser, persis
    |            seperti versi CDN lama. Ini jalur rollback: kalau suatu saat ada
    |            tampilan yang terlihat berbeda di komputer client, ubah satu
    |            baris ini dan tampilan kembali identik dengan versi sebelumnya
    |            tanpa perlu build ulang apa pun.
    |
    */

    'assets' => [
        'tailwind_mode' => env('JPOS_TAILWIND_MODE', 'css'),
    ],

];
