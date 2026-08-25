/**
 * Penangkap alat pindai barcode.
 *
 * ================================================================================
 * MASALAH YANG DIPECAHKAN, DAN KENAPA BUKAN SEKADAR "TARUH FOKUS DI KOLOM CARI"
 * ================================================================================
 *
 * Alat pindai barcode adalah PAPAN KETIK. Ia tidak punya cara memberi tahu aplikasi bahwa
 * yang mengetik adalah alat, bukan orang - ia hanya menembakkan karakter ke elemen mana pun
 * yang sedang terfokus, lalu menekan Enter.
 *
 * Sebelum berkas ini ada, hasil pindaian hanya tertangkap kalau kolom cari kebetulan sedang
 * terfokus. Padahal kasir terus-menerus menyentuh hal lain: mengubah jumlah, mengisi nominal
 * bayar, menekan tombol. Sekali fokusnya pindah, pindaian berikutnya mendarat di tempat lain.
 *
 * Yang paling berbahaya bukan pindaian yang hilang, melainkan pindaian yang MENDARAT DI
 * KOLOM NOMINAL BAYAR. Ini terukur saat pengembangan, bukan kekhawatiran teoretis: memindai
 * saat kolom bayar terfokus mengubah nominal bayar menjadi Rp 89.777.867.754.323, dan kasir
 * bisa menyelesaikan transaksi dengan kembalian sebesar itu. Itu bukan ketidaknyamanan -
 * itu uang.
 *
 * ================================================================================
 * CARA MEMBEDAKAN ALAT PINDAI DARI ORANG YANG MENGETIK
 * ================================================================================
 *
 * Dua-duanya mengirim keydown yang sama persis. Yang membedakan cuma RITME:
 *
 *   - Alat pindai mengirim seluruh kode dalam belasan milidetik antar karakter, lalu Enter.
 *   - Orang mengetik jauh lebih lambat, dan jarang menekan Enter tepat setelah karakter
 *     terakhir.
 *
 * Jadi keputusannya diambil dari jeda antar tombol, bukan dari isi kodenya. Ambang 35 ms
 * sengaja longgar: pengetik tercepat sekalipun ada di kisaran 80-150 ms per karakter, jadi
 * jarak antara keduanya lebar - tidak ada kasir yang pengetikannya akan disangka pindaian.
 *
 * ================================================================================
 * KENAPA ISI KOLOM DIPULIHKAN, BUKAN DICEGAH SEJAK AWAL
 * ================================================================================
 *
 * Kita baru tahu sesuatu adalah pindaian SETELAH beberapa karakter cepat berurutan - saat
 * itu karakter pertamanya sudah terlanjur masuk ke kolom yang terfokus, dan preventDefault
 * tidak bisa berlaku surut.
 *
 * Karena itu nilai kolom yang terfokus difoto saat rentetan dimulai, lalu dikembalikan utuh
 * begitu rentetan itu terbukti pindaian. Hasil akhirnya sama dengan mencegah sejak awal:
 * tidak ada satu karakter pun yang tertinggal di kolom yang salah.
 */
(function () {
    'use strict';

    /** Jeda antar tombol paling lama yang masih dianggap berasal dari alat pindai (ms). */
    var JEDA_MAKS = 35;

    /** Kode yang lebih pendek dari ini diabaikan - terlalu mudah tertukar dengan ketikan. */
    var PANJANG_MIN = 4;

    /** Rentetan yang menggantung lebih lama dari ini dibuang (mis. alat dicabut di tengah). */
    var UMUR_MAKS = 900;

    var sangga = '';
    var waktuTerakhir = 0;
    var elemenAsal = null;
    var nilaiAsal = null;
    var mulaiAsal = null;
    var akhirAsal = null;

    function kolomTeks(el) {
        if (!el) return false;

        var tag = el.tagName;

        return tag === 'INPUT' || tag === 'TEXTAREA' || el.isContentEditable;
    }

    /**
     * Kolom yang MEMANG untuk diisi hasil pindaian - jangan disentuh sama sekali.
     *
     * Di form Tambah/Edit Produk, kasir memindai barcode barangnya supaya kodenya masuk ke
     * kolom Barcode. Tanpa penjagaan ini, penangkap di bawah memperlakukannya seperti
     * pindaian di kasir: isinya dikembalikan ke keadaan semula, jadi kodenya tidak pernah
     * muncul dan tidak bisa didaftarkan sama sekali.
     *
     * Dikenali dari nama kolomnya, bukan dari atribut penanda: kolom barcode ada di produk
     * (`barcode`) dan di tiap baris satuan (`units[0][barcode]`), dan yang berikutnya tidak
     * perlu diingat-ingat untuk ditandai.
     */
    function kolomBarcode(el) {
        if (!kolomTeks(el)) return false;

        return /barcode/i.test((el.getAttribute('name') || '') + ' ' + (el.id || ''));
    }

    function fotoKolom() {
        var el = document.activeElement;

        elemenAsal = kolomTeks(el) ? el : null;
        nilaiAsal = elemenAsal && 'value' in elemenAsal ? elemenAsal.value : null;

        // Posisi kursor ikut difoto supaya pemulihan tidak memindahkan kursor kasir yang
        // sedang di tengah mengetik sesuatu.
        mulaiAsal = elemenAsal && 'selectionStart' in elemenAsal ? elemenAsal.selectionStart : null;
        akhirAsal = elemenAsal && 'selectionEnd' in elemenAsal ? elemenAsal.selectionEnd : null;
    }

    function pulihkanKolom() {
        if (!elemenAsal || nilaiAsal === null) return;

        elemenAsal.value = nilaiAsal;

        // Wajib: banyak kolom di aplikasi ini terikat ke Alpine lewat x-model, dan Alpine
        // hanya tahu nilainya berubah kalau event input dikirim. Tanpa ini, layar kembali
        // benar tapi angka di dalam Alpine tetap berisi barcode.
        elemenAsal.dispatchEvent(new Event('input', { bubbles: true }));

        try {
            if (mulaiAsal !== null && 'setSelectionRange' in elemenAsal) {
                elemenAsal.setSelectionRange(mulaiAsal, akhirAsal);
            }
        } catch (e) {
            // Sebagian jenis input tidak mendukung setSelectionRange; diabaikan.
        }
    }

    function bersihkan() {
        sangga = '';
        elemenAsal = null;
        nilaiAsal = null;
        mulaiAsal = null;
        akhirAsal = null;
    }

    document.addEventListener('keydown', function (e) {
        // Fokus sedang di kolom barcode: biarkan mengetik apa adanya. Lihat kolomBarcode().
        if (kolomBarcode(document.activeElement)) {
            bersihkan();

            return;
        }

        // Kombinasi tombol (Ctrl+C dan kawan-kawan) tidak pernah berasal dari alat pindai.
        if (e.ctrlKey || e.altKey || e.metaKey) {
            bersihkan();

            return;
        }

        var sekarang = e.timeStamp || Date.now();
        var jeda = sekarang - waktuTerakhir;

        waktuTerakhir = sekarang;

        if (e.key === 'Enter') {
            var kode = sangga;

            if (kode.length < PANJANG_MIN) {
                bersihkan();

                return;
            }

            // Sampai di sini rentetannya terbukti berasal dari alat pindai. Isi kolom yang
            // kebetulan terfokus dikembalikan utuh SEBELUM Enter sempat memicu apa pun -
            // inilah yang mencegah barcode menjadi nominal bayar.
            //
            // URUTANNYA PENTING: pulihkan DULU, baru bersihkan. Versi pertama berkas ini
            // memanggil bersihkan() lebih dulu, sehingga foto kolomnya sudah terhapus saat
            // hendak dipakai - pindaiannya tertangkap dengan benar, tapi kolom nominal bayar
            // tetap rusak. Terlihat di UAT: 50.000 menjadi 50.000.089.777.867.750.000.
            pulihkanKolom();
            bersihkan();

            e.preventDefault();
            e.stopPropagation();

            document.dispatchEvent(new CustomEvent('jpos:barcode-dipindai', {
                bubbles: true,
                detail: { kode: kode },
            }));

            return;
        }

        // Hanya karakter tunggal yang dikumpulkan; Shift, F1, panah, dan kawan-kawan dilewati
        // tanpa memutus rentetan - sebagian alat pindai mengirim Shift untuk huruf besar.
        if (e.key.length !== 1) {
            if (e.key !== 'Shift') bersihkan();

            return;
        }

        if (jeda > JEDA_MAKS || jeda > UMUR_MAKS) {
            // Terlalu lambat: ini orang mengetik. Rentetan dimulai ulang dari karakter ini,
            // dan kolom yang terfokus difoto lagi - kalau ternyata karakter-karakter
            // berikutnya datang secepat alat pindai, fotonya sudah siap.
            sangga = e.key;
            fotoKolom();

            return;
        }

        if (sangga === '') fotoKolom();

        sangga += e.key;
    }, true); // fase capture: didengar lebih dulu daripada penangan milik kolom mana pun
})();
