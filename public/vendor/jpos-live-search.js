/**
 * Pencarian langsung (live search) untuk daftar berhalaman di JPOS.
 *
 * Kasir mengetik, hasilnya langsung menyesuaikan - tanpa menekan tombol "Cari".
 *
 * CARA KERJANYA: isi tabel hasil ditukar, bukan menampilkan dropdown saran terpisah.
 * Alasannya, yang dicari kasir memang tabel itu sendiri - lengkap dengan kolom harga,
 * stok, dan tombol Edit/Hapus. Dropdown saran justru menambah satu langkah lagi.
 *
 * Halaman dimuat lewat fetch ke URL yang SAMA dengan form aslinya, lalu hanya bagian
 * [data-live-results] yang diambil. Tidak ada endpoint baru, jadi tidak ada aturan hak
 * akses atau validasi baru yang perlu dijaga - halaman ini tetap dilindungi middleware
 * yang sama seperti saat dibuka biasa.
 *
 * Kalau berkas ini gagal dimuat, form kembali menjadi form biasa dan tombol "Cari" tetap
 * bekerja seperti semula.
 */
(function () {
    'use strict';

    // Server PHP bawaan hanya melayani SATU request pada satu waktu. Tanpa jeda ini,
    // mengetik "sabun" mengirim 5 permintaan yang saling mengantre dan aplikasi terasa
    // tersendat. 280 ms cukup cepat terasa langsung, tapi menyatukan ketikan cepat.
    var JEDA_MS = 280;

    function siapkan(form) {
        if (form.__jposLiveSearch) return;
        form.__jposLiveSearch = true;

        var wadah = document.querySelector(form.getAttribute('data-live-search') || '[data-live-results]');
        if (!wadah) return;

        var timer = null;
        var pembatal = null;
        var terakhirDiminta = '';

        function url() {
            var data = new FormData(form);
            var params = new URLSearchParams();

            data.forEach(function (nilai, kunci) {
                // Parameter kosong tidak perlu ikut supaya URL tetap bersih dan
                // pemeriksaan when() di controller tidak menerima string kosong.
                if (String(nilai).trim() !== '') params.append(kunci, nilai);
            });

            var dasar = form.getAttribute('action') || window.location.pathname;
            var q = params.toString();

            return q ? dasar + '?' + q : dasar;
        }

        function tandaiSibuk(sibuk) {
            wadah.style.transition = 'opacity .12s';
            wadah.style.opacity = sibuk ? '0.55' : '';
        }

        function muat(tujuan, dorongRiwayat) {
            // Permintaan sebelumnya dibatalkan supaya server tidak menumpuk pekerjaan
            // yang hasilnya sudah tidak dipakai - penting di server satu-antrean.
            if (pembatal) pembatal.abort();
            pembatal = new AbortController();

            terakhirDiminta = tujuan;
            tandaiSibuk(true);

            fetch(tujuan, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: pembatal.signal,
            })
                .then(function (res) {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.text();
                })
                .then(function (html) {
                    // Hasil yang datang terlambat (bukan permintaan terakhir) diabaikan,
                    // supaya tabel tidak sempat menampilkan hasil ketikan yang sudah lama.
                    if (tujuan !== terakhirDiminta) return;

                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var baru = doc.querySelector('[data-live-results]');

                    if (!baru) {
                        // Balasan tidak seperti yang diharapkan (mis. terlempar ke halaman
                        // login karena sesi habis). Muat ulang biasa supaya pengguna melihat
                        // apa yang sebenarnya terjadi, bukan tabel yang diam-diam kosong.
                        window.location.href = tujuan;
                        return;
                    }

                    wadah.innerHTML = baru.innerHTML;
                    tandaiSibuk(false);

                    if (dorongRiwayat !== false) {
                        window.history.replaceState({}, '', tujuan);
                    }

                    // Halaman yang menyimpan pilihan per baris (mis. centang di Cetak Barcode)
                    // perlu tahu daftarnya baru saja berganti, supaya pilihan lama yang sudah
                    // tidak tampil tidak ikut terbawa diam-diam.
                    wadah.dispatchEvent(new CustomEvent('jpos:hasil-diperbarui', {
                        bubbles: true,
                        detail: { url: tujuan },
                    }));
                })
                .catch(function (e) {
                    if (e.name === 'AbortError') return;   // digantikan ketikan berikutnya
                    tandaiSibuk(false);
                    // Kegagalan jaringan dibiarkan diam: hasil lama tetap tampil, dan kasir
                    // masih bisa menekan "Cari" untuk memuat ulang secara biasa.
                });
        }

        function jadwalkan() {
            clearTimeout(timer);
            timer = setTimeout(function () { muat(url()); }, JEDA_MS);
        }

        // Mengetik di kotak teks -> ditunda; mengubah pilihan/tanggal -> langsung.
        form.addEventListener('input', function (e) {
            var t = e.target;
            if (t.tagName === 'INPUT' && (t.type === 'text' || t.type === 'search')) jadwalkan();
        });

        form.addEventListener('change', function (e) {
            var t = e.target;
            var langsung = t.tagName === 'SELECT'
                || (t.tagName === 'INPUT' && (t.type === 'date' || t.type === 'checkbox' || t.type === 'radio'));

            if (langsung) {
                clearTimeout(timer);
                muat(url());
            }
        });

        // Tombol "Cari" tidak lagi perlu memuat ulang seluruh halaman.
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            clearTimeout(timer);
            muat(url());
        });

        // Tautan paginasi ikut dimuat tanpa meninggalkan halaman, jadi kotak pencarian
        // tetap terfokus dan isinya tidak hilang saat berpindah halaman.
        wadah.addEventListener('click', function (e) {
            var a = e.target.closest ? e.target.closest('a[href]') : null;
            if (!a || !wadah.contains(a)) return;

            var href = a.getAttribute('href');
            if (!href || href.charAt(0) === '#' || a.target === '_blank') return;

            var tujuan;
            try {
                tujuan = new URL(href, window.location.origin);
            } catch (_) { return; }

            // Hanya tautan paginasi di halaman yang sama; tautan lain (Edit, cetak, dsb)
            // dibiarkan berperilaku normal.
            if (tujuan.origin !== window.location.origin) return;
            if (tujuan.pathname !== window.location.pathname) return;
            if (!tujuan.searchParams.has('page')) return;

            e.preventDefault();
            muat(tujuan.pathname + tujuan.search);
        });
    }

    function pasangSemua(akar) {
        var daftar = (akar || document).querySelectorAll('form[data-live-search]');
        Array.prototype.forEach.call(daftar, siapkan);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { pasangSemua(document); });
    } else {
        pasangSemua(document);
    }
})();
