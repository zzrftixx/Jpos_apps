/**
 * Menjaga posisi gulir menu samping saat berpindah halaman.
 *
 * MASALAHNYA. JPos memuat ulang halaman secara penuh setiap kali menu ditekan - tidak ada
 * router sisi peramban. Daftar menunya panjang (Laporan, Manajemen, dan Pengaturan ada di
 * bagian bawah), jadi setiap kali pemilik toko membuka salah satunya, menu itu melompat
 * balik ke paling atas dan ia harus menggulir turun lagi. Untuk orang yang bolak-balik
 * antar halaman Pengaturan seharian, itu gulir yang sama diulang berpuluh kali.
 *
 * KENAPA BUKAN DIBUAT TANPA MUAT ULANG SAMA SEKALI. Itu berarti menambahkan router sisi
 * peramban (Turbo/HTMX/SPA) ke seluruh aplikasi - perubahan besar yang menyentuh setiap
 * halaman, setiap komponen Alpine, dan setiap formulir, demi masalah yang bisa
 * diselesaikan dengan mengingat satu angka. Aplikasi ini dipakai di toko sungguhan; risiko
 * sebesar itu tidak sebanding dengan hasilnya.
 *
 * KENAPA sessionStorage, BUKAN localStorage. Posisi gulir hanya bermakna dalam satu sesi
 * pemakaian. localStorage akan membuat menu terbuka pada posisi kemarin - membingungkan,
 * dan pada komputer kasir yang dipakai bergantian, itu jejak orang sebelumnya.
 *
 * WAKTU PEMULIHAN. Berkas ini dimuat TANPA `defer` dan ditempatkan setelah elemen menu,
 * sehingga berjalan sebelum peramban menggambar - posisinya sudah benar sejak kedipan
 * pertama, tanpa lompatan yang terlihat.
 */
(function () {
    'use strict';

    var KUNCI = 'jpos:gulir-menu';

    function menu() {
        return document.querySelector('[data-sidebar-nav]');
    }

    var siapSimpan = false;

    function tersimpan() {
        try {
            var nilai = parseInt(window.sessionStorage.getItem(KUNCI), 10);

            return isNaN(nilai) ? 0 : nilai;
        } catch (e) {
            return 0; // Mode privat / storage diblokir: fitur ini dilewati, bukan menggagalkan halaman.
        }
    }

    /**
     * Dijalankan DUA KALI, dan itu disengaja.
     *
     * Aturan gaya menu (.nav-link dan kawan-kawan) berada di blok <style> di AKHIR body,
     * sesudah skrip ini. Pada panggilan pertama, gaya itu belum berlaku sehingga isi menu
     * masih jauh lebih pendek dari semestinya - peramban menjepit scrollTop ke tinggi yang
     * ada saat itu, dan posisinya meleset. Terukur saat pengembangan: diminta 545, yang
     * benar-benar terpasang 77.
     *
     * Panggilan pertama tetap ada supaya tidak ada kedipan pada halaman yang tinggi menunya
     * memang sudah final; panggilan kedua di DOMContentLoaded yang memastikan angkanya benar.
     */
    function pulihkan() {
        var nav = menu();
        var posisi = tersimpan();

        if (!nav || posisi <= 0) return;

        // Sengaja TIDAK dijepit sendiri: peramban menjepitnya ke batas yang berlaku saat itu,
        // dan menjepit dengan angka yang belum final justru mengunci posisi yang salah.
        nav.scrollTop = posisi;
    }

    function simpan() {
        var nav = menu();

        // Selama pemulihan belum selesai, nilai yang terbaca masih hasil jepitan sementara.
        // Menyimpannya akan menimpa posisi yang benar dengan posisi yang meleset - dan sejak
        // itu posisi aslinya hilang untuk seterusnya.
        if (!nav || !siapSimpan) return;

        try {
            window.sessionStorage.setItem(KUNCI, String(nav.scrollTop));
        } catch (e) {
            // Diabaikan: gagal menyimpan posisi gulir tidak boleh mengganggu apa pun.
        }
    }

    pulihkan();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            pulihkan();
            siapSimpan = true;
        });
    } else {
        siapSimpan = true;
    }

    var nav = menu();
    if (!nav) return;

    // Disimpan saat menggulir (diredam), BUKAN cuma saat menu ditekan: halaman juga
    // berpindah lewat tombol Kembali, kiriman formulir, dan pengalihan sesudah menyimpan.
    var jeda = null;
    nav.addEventListener('scroll', function () {
        if (jeda) return;
        jeda = window.setTimeout(function () {
            jeda = null;
            simpan();
        }, 120);
    }, { passive: true });

    // pagehide menangkap kepergian halaman yang tidak selalu memicu beforeunload
    // (mis. peramban seluler dan navigasi bfcache).
    window.addEventListener('pagehide', simpan);
    window.addEventListener('beforeunload', simpan);
})();
