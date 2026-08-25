/*!
 * jpos-sesi.js - memberi tahu JPOS.exe bahwa jendela aplikasi masih terbuka.
 *
 * Jendela kasir dibuka sebagai proses browser TERPISAH dari JPOS.exe, jadi launcher tidak
 * punya cara langsung untuk tahu jendelanya sudah ditutup. Dulu akibatnya server PHP terus
 * hidup di system tray, dan membuka pintasan lagi cuma memunculkan peringatan
 * "JPOS sudah berjalan".
 *
 * Dua sinyal dikirim dari sini:
 *
 *   1. DETAK berkala selama halaman hidup. Ini bukti "masih ada yang membuka aplikasi".
 *   2. SINYAL TUTUP saat halaman ditinggalkan.
 *
 * Sinyal tutup sengaja tidak langsung mematikan server, karena berpindah halaman
 * (mis. Dashboard -> Kasir) memicu peristiwa yang sama persis dengan menutup jendela dan
 * browser tidak menyediakan cara untuk membedakannya. Launcher menunggu sebentar setelah
 * sinyal tutup; kalau detak dari halaman berikutnya keburu datang, berarti tadi cuma
 * pindah halaman.
 *
 * Skrip ini murni penanda waktu - tidak menyentuh transaksi, keranjang, atau database.
 * Kalau permintaannya gagal sekalipun, aplikasi tetap berjalan normal.
 */
(function () {
    'use strict';

    // Cukup rapat untuk mendeteksi tutup dengan cepat, cukup jarang untuk tidak
    // membebani server PHP yang cuma melayani satu permintaan pada satu waktu.
    var JEDA_DETAK = 10000;

    var urlDetak = '/jpos-detak';
    var urlTutup = '/jpos-tutup';

    var pengatur = null;
    var sudahPamit = false;

    function kirim(url) {
        try {
            fetch(url, {
                method: 'GET',
                cache: 'no-store',
                credentials: 'same-origin',
                // Supaya permintaan tetap diselesaikan browser meski halaman sedang ditutup.
                keepalive: true,
            }).catch(function () {
                // Server sudah mati atau sedang sibuk; tidak ada yang perlu dilakukan.
            });
        } catch (e) {
            // Browser lama tanpa fetch: launcher masih punya ambang batas cadangan.
        }
    }

    function detak() {
        sudahPamit = false;
        kirim(urlDetak);
    }

    function pamit() {
        if (sudahPamit) {
            return;
        }
        sudahPamit = true;
        kirim(urlTutup);
    }

    detak();
    pengatur = setInterval(detak, JEDA_DETAK);

    // Browser memperlambat timer di tab yang tidak terlihat sampai sekitar satu kali per
    // menit. Detak tambahan saat jendela kembali aktif membuat jeda panjang itu langsung
    // terputus, bukan menunggu giliran timer berikutnya.
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            detak();
        }
    });

    // pagehide menangani menutup jendela, refresh, dan berpindah halaman sekaligus, dan
    // tetap terpicu di halaman yang masuk cache maju-mundur - tidak seperti unload.
    window.addEventListener('pagehide', pamit);

    // Jaring pengaman untuk browser yang tidak memicu pagehide saat proses ditutup.
    window.addEventListener('beforeunload', pamit);

    // Halaman dipulihkan dari cache maju-mundur: sinyal tutup tadi harus dibatalkan.
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            detak();
        }
    });

    window.addEventListener('unload', function () {
        if (pengatur) {
            clearInterval(pengatur);
        }
    });
})();
