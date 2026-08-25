/**
 * Input angka berformat Indonesia untuk JPOS.
 *
 * Dua hal yang dikerjakan berkas ini:
 *   1. Nilai 0 hilang begitu kotak diklik, jadi kasir tidak perlu menghapusnya dulu.
 *   2. Ribuan diberi pemisah titik SAAT DIKETIK, jadi "1.500.000" langsung terbaca.
 *
 * Kenapa bukan <input type="number">: browser menolak karakter non-numerik, jadi mustahil
 * menampilkan pemisah ribuan di dalamnya. Karena itu inputnya type="text" dengan
 * inputmode yang tepat supaya papan tombol angka tetap muncul di tablet kasir.
 *
 * Kenapa TIDAK memakai input tersembunyi berpasangan: kalau berkas ini gagal dimuat, input
 * tersembunyi akan tetap mengirim nilai awalnya dan apa pun yang diketik kasir hilang tanpa
 * jejak. Di sini inputnya tunggal - kalau JS mati, kotaknya jadi kotak teks biasa dan server
 * tetap membaca "1.500.000" dengan benar (lihat App\Http\Middleware\NormalizeNumericInput).
 *
 * Locale Indonesia: "." pemisah ribuan, "," pemisah desimal.
 */
(function () {
    'use strict';

    var PEMISAH_RIBUAN = '.';
    var PEMISAH_DESIMAL = ',';

    // ---------------------------------------------------------------- inti angka

    /**
     * Baca opsi dari atribut data-* pada elemen.
     *
     * data-number-decimals : jumlah maksimum angka desimal (0 = bilangan bulat saja)
     * data-number-empty    : "nol" (kosong dianggap 0) atau "kosong" (kosong dibiarkan null)
     * data-number-group    : "0" untuk mematikan pemisah ribuan
     */
    function bacaOpsi(el) {
        var d = el.dataset || {};

        return {
            desimal: Math.max(0, parseInt(d.numberDecimals || '0', 10) || 0),
            kosongJadiNol: (d.numberEmpty || 'nol') === 'nol',
            pakaiPemisah: d.numberGroup !== '0',
        };
    }

    /**
     * Teks apa pun -> angka. Mengembalikan null kalau tidak ada angka sama sekali.
     *
     * Tahan terhadap tempelan seperti "Rp 1.500.000,-" karena semua karakter selain digit
     * dan pemisah desimal dibuang lebih dulu.
     */
    function keAngka(teks, maksDesimal) {
        if (teks === null || teks === undefined) return null;
        if (typeof teks === 'number') return isFinite(teks) ? teks : null;

        var bersih = String(teks).replace(/[^0-9.,]/g, '');
        if (bersih === '') return null;

        // Titik selalu pemisah ribuan (locale Indonesia), jadi dibuang.
        bersih = bersih.split(PEMISAH_RIBUAN).join('');

        var bagian = bersih.split(PEMISAH_DESIMAL);
        var bulat = bagian.shift().replace(/[^0-9]/g, '');
        var pecahan = bagian.join('').replace(/[^0-9]/g, '');

        if (bulat === '' && pecahan === '') return null;

        if (maksDesimal > 0 && pecahan !== '') {
            pecahan = pecahan.slice(0, maksDesimal);
            var angka = parseFloat((bulat || '0') + '.' + pecahan);
            return isFinite(angka) ? angka : null;
        }

        var bulatSaja = parseInt(bulat || '0', 10);
        return isFinite(bulatSaja) ? bulatSaja : null;
    }

    /**
     * Nilai yang datang dari SERVER atau state, bukan yang diketik manusia.
     *
     * INI BERBEDA DARI keAngka() DAN PERBEDAANNYA PENTING. keAngka() membaca teks yang
     * diketik kasir, di mana titik berarti pemisah RIBUAN. Sedangkan Laravel mengirim
     * kolom decimal sebagai string di mana titik berarti pemisah DESIMAL:
     *
     *     cost_price 1500 di database  ->  JSON "1500.00"
     *
     * Memakai keAngka() untuk nilai seperti itu membuang titiknya dan menghasilkan
     * 150000 - harga naik seratus kali lipat setiap kali produk dibuka di form Edit,
     * dan berlipat lagi setiap kali disimpan ulang.
     */
    function keAngkaMesin(nilai) {
        if (typeof nilai === 'number') return isFinite(nilai) ? nilai : null;
        if (nilai === null || nilai === undefined) return null;

        var teks = String(nilai).trim();
        if (teks === '') return null;

        // Format tampilan Indonesia selalu memakai koma untuk desimal; format mesin tidak
        // pernah. Jadi kehadiran koma adalah penanda yang bisa diandalkan.
        if (teks.indexOf(PEMISAH_DESIMAL) !== -1) return keAngka(teks, 10);

        var angka = parseFloat(teks.replace(/[^0-9.\-]/g, ''));
        return isFinite(angka) ? angka : null;
    }

    function kelompokkanRibuan(digit, pakaiPemisah) {
        if (!pakaiPemisah) return digit;
        return digit.replace(/\B(?=(\d{3})+(?!\d))/g, PEMISAH_RIBUAN);
    }

    /**
     * Angka -> teks tampilan. Desimal nol di ujung dibuang, jadi 1500 tampil "1.500"
     * dan 1500.5 tampil "1.500,5" - tanpa memaksa ",00" yang cuma bikin ramai.
     */
    function keTampilan(angka, opsi) {
        if (angka === null || angka === undefined || !isFinite(angka)) return '';

        var negatif = angka < 0;
        var absolut = Math.abs(angka);
        var teks;

        if (opsi.desimal > 0) {
            teks = absolut.toFixed(opsi.desimal).replace(/0+$/, '').replace(/\.$/, '');
        } else {
            teks = String(Math.round(absolut));
        }

        var bagian = teks.split('.');
        var hasil = kelompokkanRibuan(bagian[0], opsi.pakaiPemisah);
        if (bagian[1]) hasil += PEMISAH_DESIMAL + bagian[1];

        return (negatif ? '-' : '') + hasil;
    }

    /**
     * Format teks yang SEDANG diketik.
     *
     * Beda dari keTampilan(): di sini koma yang menggantung dan nol di ujung desimal harus
     * DIPERTAHANKAN, kalau tidak kasir tidak akan pernah bisa mengetik "1.500,05" - komanya
     * ikut terhapus begitu diketik.
     */
    function formatSaatMengetik(teks, opsi) {
        var bersih = String(teks).replace(/[^0-9.,]/g, '').split(PEMISAH_RIBUAN).join('');

        var idxKoma = opsi.desimal > 0 ? bersih.indexOf(PEMISAH_DESIMAL) : -1;
        var bulat, pecahan, adaKoma;

        if (idxKoma === -1) {
            bulat = bersih.replace(/[^0-9]/g, '');
            pecahan = '';
            adaKoma = false;
        } else {
            bulat = bersih.slice(0, idxKoma).replace(/[^0-9]/g, '');
            pecahan = bersih.slice(idxKoma + 1).replace(/[^0-9]/g, '').slice(0, opsi.desimal);
            adaKoma = true;
        }

        // Nol di depan dirapikan, tapi "0," tetap sah supaya "0,5" bisa diketik.
        if (bulat.length > 1) bulat = bulat.replace(/^0+/, '');
        if (bulat === '' && adaKoma) bulat = '0';

        var hasil = kelompokkanRibuan(bulat, opsi.pakaiPemisah);
        if (adaKoma) hasil += PEMISAH_DESIMAL + pecahan;

        return hasil;
    }

    // ---------------------------------------------------------------- kursor

    function jumlahDigit(teks) {
        var n = 0;
        for (var i = 0; i < teks.length; i++) {
            if (teks[i] >= '0' && teks[i] <= '9') n++;
        }
        return n;
    }

    /**
     * Letakkan kursor setelah digit ke-n.
     *
     * Tanpa ini, kursor melompat ke ujung setiap kali pemisah titik disisipkan - kasir yang
     * mengetik cepat akan memasukkan angka di posisi yang salah, dan itu berarti uang salah.
     */
    function posisiSetelahDigit(teks, targetDigit) {
        if (targetDigit <= 0) return 0;

        var n = 0;
        for (var i = 0; i < teks.length; i++) {
            if (teks[i] >= '0' && teks[i] <= '9') {
                n++;
                if (n === targetDigit) return i + 1;
            }
        }
        return teks.length;
    }

    // ---------------------------------------------------------------- perilaku input

    /**
     * Pasang perilaku format pada satu elemen input.
     *
     * @param {HTMLInputElement} el
     * @param {?function(number|null)} tulisKeModel  dipanggil setiap nilai berubah
     */
    function pasang(el, tulisKeModel) {
        if (el.__jposNumber) return el.__jposNumber;

        var opsi = bacaOpsi(el);
        var kendali = { opsi: opsi, el: el, sedangDiisiUlang: false };

        // Kunci lebar papan tombol yang benar untuk layar sentuh.
        if (!el.getAttribute('inputmode')) {
            el.setAttribute('inputmode', opsi.desimal > 0 ? 'decimal' : 'numeric');
        }
        if (!el.getAttribute('autocomplete')) el.setAttribute('autocomplete', 'off');

        function kirim(nilai) {
            if (typeof tulisKeModel === 'function') tulisKeModel(nilai);
        }

        function tanganiInput(ev) {
            if (kendali.sedangDiisiUlang) return;

            // Papan tombol angka (numpad, dan keypad numerik di tablet) hanya punya tombol
            // TITIK, tidak ada koma. Untuk kotak yang menerima desimal, titik yang DIKETIK
            // karena itu diperlakukan sebagai koma desimal.
            //
            // Tanpa ini, kasir yang mengetik "11.5" pada persen pajak akan mendapat 115 -
            // salah sepuluh kali lipat - karena titik dianggap pemisah ribuan lalu dibuang.
            //
            // Hanya berlaku untuk ketikan satu karakter. TEMPELAN sengaja tidak diubah,
            // supaya "Rp 1.500.000" yang ditempel tetap terbaca 1.500.000 dan bukan 1,5.
            if (opsi.desimal > 0 && ev && ev.inputType === 'insertText' && ev.data === '.') {
                var titik = el.selectionStart;
                var sebelum = el.value.slice(0, titik - 1);
                var sesudah = el.value.slice(titik);

                if ((sebelum + sesudah).indexOf(PEMISAH_DESIMAL) === -1) {
                    el.value = sebelum + PEMISAH_DESIMAL + sesudah;
                    try { el.setSelectionRange(titik, titik); } catch (e) { /* diabaikan */ }
                }
            }

            var posisi = el.selectionStart === null ? el.value.length : el.selectionStart;
            var digitSebelumKursor = jumlahDigit(el.value.slice(0, posisi));

            var tampil = formatSaatMengetik(el.value, opsi);

            if (tampil !== el.value) {
                el.value = tampil;
                var baru = posisiSetelahDigit(tampil, digitSebelumKursor);
                try { el.setSelectionRange(baru, baru); } catch (e) { /* input tersembunyi */ }
            }

            kirim(keAngka(tampil, opsi.desimal));
        }

        function tanganiFokus() {
            // Inilah yang diminta: 0 langsung hilang saat diklik.
            //
            // Modelnya sengaja TIDAK diubah ke null di sini. Kalau diubah, perhitungan yang
            // memakai nilai ini (total, kembalian) akan menghasilkan NaN selama kotak kosong.
            // Jadi hanya tampilannya yang dikosongkan; nilainya tetap 0 sampai kasir mengetik.
            var sekarang = keAngka(el.value, opsi.desimal);
            if (sekarang === 0) {
                el.value = '';
            } else {
                // Pilih semua supaya mengetik langsung menimpa, bukan menyambung.
                try { el.select(); } catch (e) { /* diabaikan */ }
            }
        }

        function tanganiBlur() {
            if (el.value.trim() === '') {
                if (opsi.kosongJadiNol) {
                    el.value = keTampilan(0, opsi);
                    kirim(0);
                } else {
                    el.value = '';
                    kirim(null);
                }
                return;
            }

            // Rapikan sisa pengetikan, mis. "1.500," -> "1.500"
            var nilai = keAngka(el.value, opsi.desimal);
            el.value = keTampilan(nilai, opsi);
            kirim(nilai);
        }

        el.addEventListener('input', tanganiInput);
        el.addEventListener('focus', tanganiFokus);
        el.addEventListener('blur', tanganiBlur);

        // Tombol atas/bawah tidak ada lagi karena bukan type="number"; dikembalikan manual
        // supaya kebiasaan lama (mis. menaikkan qty pakai panah) tetap jalan.
        el.addEventListener('keydown', function (ev) {
            if (ev.key !== 'ArrowUp' && ev.key !== 'ArrowDown') return;
            ev.preventDefault();

            var langkah = opsi.desimal > 0 ? 1 : 1;
            var nilai = keAngka(el.value, opsi.desimal) || 0;
            nilai = ev.key === 'ArrowUp' ? nilai + langkah : nilai - langkah;

            var batasBawah = el.dataset.numberMin !== undefined ? parseFloat(el.dataset.numberMin) : 0;
            if (isFinite(batasBawah) && nilai < batasBawah) nilai = batasBawah;

            el.value = keTampilan(nilai, opsi);
            kirim(nilai);
        });

        /**
         * Baca ulang opsi dari atribut data-*.
         *
         * Diperlukan karena sebagian kotak menentukan opsinya secara dinamis - kotak qty di
         * keranjang memakai `:data-number-decimals="item.is_weighable ? 3 : 0"`, dan Alpine
         * baru mengisi atribut itu SETELAH elemennya dipasang. Tanpa pembacaan ulang, baris
         * satuan timbangan akan memakai aturan "tanpa desimal" milik pembacaan pertama, dan
         * kasir tidak bisa mengetik 0,5 sama sekali.
         *
         * Objek opsi diubah di tempat (bukan diganti) karena seluruh penangan peristiwa di
         * atas menutup (closure) variabel yang sama.
         */
        kendali.segarkanOpsi = function () {
            var baru = bacaOpsi(el);
            var berubah = baru.desimal !== opsi.desimal
                || baru.kosongJadiNol !== opsi.kosongJadiNol
                || baru.pakaiPemisah !== opsi.pakaiPemisah;

            if (!berubah) return false;

            opsi.desimal = baru.desimal;
            opsi.kosongJadiNol = baru.kosongJadiNol;
            opsi.pakaiPemisah = baru.pakaiPemisah;

            el.setAttribute('inputmode', opsi.desimal > 0 ? 'decimal' : 'numeric');

            return true;
        };

        /** Isi ulang tampilan dari luar (dipakai saat model berubah secara programatik). */
        kendali.tampilkan = function (nilai) {
            kendali.segarkanOpsi();

            kendali.sedangDiisiUlang = true;

            // keAngkaMesin (BUKAN keAngka): nilai di sini berasal dari server/state, di
            // mana titik adalah pemisah desimal. Lihat catatan di keAngkaMesin().
            var teksBaru = (nilai === null || nilai === undefined || nilai === '')
                ? (opsi.kosongJadiNol ? keTampilan(0, opsi) : '')
                : keTampilan(keAngkaMesin(nilai), opsi);

            var sedangDifokus = document.activeElement === el;

            // Saat kotak sedang difokus dan isinya sengaja dikosongkan (perilaku klik-hapus-nol),
            // jangan paksa "0" muncul kembali - kasir sedang bersiap mengetik.
            var sedangFokusDanKosong = sedangDifokus && el.value === '' && keAngka(teksBaru, opsi.desimal) === 0;

            // Pertahanan berlapis: jangan menimpa apa yang SEDANG diketik selama angkanya
            // belum berubah.
            //
            // Pada kolom dua arah, mengetik "1000," membuat model bernilai 1000 dan memicu
            // effect Alpine, yang secara teori bisa menulis ulang tampilan menjadi "1.000"
            // dan menghapus koma yang baru saja diketik. Pada pengujian, penjadwalan effect
            // Alpine ternyata membuat hal itu tidak terjadi - jadi ini bukan perbaikan atas
            // kerusakan yang teramati, melainkan penjaga agar perilakunya tidak bergantung
            // pada urutan penjadwalan. Penulisan dari luar (tombol nominal cepat, clampQty)
            // tetap lolos karena angkanya memang berbeda.
            var angkaSama = sedangDifokus && keAngka(el.value, opsi.desimal) === keAngkaMesin(nilai);

            if (!sedangFokusDanKosong && !angkaSama && el.value !== teksBaru) {
                var posisi = el.selectionStart;
                var digitSebelumKursor = document.activeElement === el && posisi !== null
                    ? jumlahDigit(el.value.slice(0, posisi))
                    : null;

                el.value = teksBaru;

                if (digitSebelumKursor !== null) {
                    var baru = posisiSetelahDigit(teksBaru, digitSebelumKursor);
                    try { el.setSelectionRange(baru, baru); } catch (e) { /* diabaikan */ }
                }
            }

            kendali.sedangDiisiUlang = false;
        };

        kendali.nilai = function () {
            return keAngka(el.value, opsi.desimal);
        };

        el.__jposNumber = kendali;
        return kendali;
    }

    // ---------------------------------------------------------------- tanpa Alpine

    /**
     * Input yang tidak terikat state Alpine (form Produk, Kas, Pengaturan).
     *
     * Nilainya dikirim apa adanya dalam bentuk berformat; server yang menormalkannya. Itu
     * disengaja - lihat catatan di kepala berkas.
     */
    function tingkatkanInputBiasa(akar) {
        var daftar = (akar || document).querySelectorAll('input[data-jpos-number]:not([x-number])');

        Array.prototype.forEach.call(daftar, function (el) {
            if (el.__jposNumber) return;

            var kendali = pasang(el, null);
            // Rapikan nilai awal dari server, mis. value="1500000" -> "1.500.000"
            kendali.tampilkan(el.value === '' ? null : el.value);
        });
    }

    // ---------------------------------------------------------------- Alpine

    function daftarkanDirectiveAlpine(Alpine) {
        /**
         * x-number="ekspresi" - pengganti x-model.number untuk input berformat.
         *
         * Mengetik  -> ekspresi diperbarui sebagai Number
         * Ekspresi berubah dari mana pun (tombol nominal cepat, tombol persentase DP,
         * clampQty, reset setelah checkout) -> tampilan berformat ikut diperbarui.
         *
         * x-number.oneway="ekspresi" - untuk kotak yang TIDAK punya state Alpine dan hanya
         * mengandalkan atribut name= saat submit (form Produk memakai pola :value="editItem
         * ? editItem.cost_price : 0"). Arahnya cuma ekspresi -> tampilan; apa yang diketik
         * kasir tidak ditulis balik ke mana pun, persis seperti :value sebelumnya.
         *
         * Mode ini WAJIB ada: menulis el.value lewat :value tidak memicu event 'input'
         * maupun mengubah atribut, jadi baik addEventListener maupun MutationObserver tidak
         * akan pernah tahu nilainya berganti saat tombol Edit ditekan - dan kotak akan
         * menampilkan "1500000" polos alih-alih "1.500.000".
         */
        Alpine.directive('number', function (el, meta, util) {
            var expression = meta.expression;
            var satuArah = (meta.modifiers || []).indexOf('oneway') !== -1;
            var evaluate = util.evaluate;
            var evaluateLater = util.evaluateLater;
            var effect = util.effect;

            var ambilNilai = evaluateLater(expression);

            var kendali = pasang(el, satuArah ? null : function (nilai) {
                evaluate(expression + ' = __jposNilai', { scope: { __jposNilai: nilai } });
            });

            effect(function () {
                ambilNilai(function (nilai) {
                    kendali.tampilkan(nilai);
                });
            });
        });
    }

    // ---------------------------------------------------------------- pemasangan

    function siap() {
        tingkatkanInputBiasa(document);

        // Baris "Satuan Tambahan" di form produk dibuat setelah halaman dimuat lewat x-for,
        // begitu juga baris keranjang. MutationObserver menangkap semuanya tanpa perlu
        // memanggil apa pun dari Blade.
        if (typeof MutationObserver !== 'undefined') {
            new MutationObserver(function (perubahan) {
                for (var i = 0; i < perubahan.length; i++) {
                    var ditambah = perubahan[i].addedNodes;
                    for (var j = 0; j < ditambah.length; j++) {
                        var n = ditambah[j];
                        if (n.nodeType !== 1) continue;
                        if (n.matches && n.matches('input[data-jpos-number]')) tingkatkanInputBiasa(n.parentNode || document);
                        else tingkatkanInputBiasa(n);
                    }
                }
            }).observe(document.documentElement, { childList: true, subtree: true });
        }
    }

    if (window.Alpine) {
        daftarkanDirectiveAlpine(window.Alpine);
    } else {
        document.addEventListener('alpine:init', function () {
            daftarkanDirectiveAlpine(window.Alpine);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', siap);
    } else {
        siap();
    }

    // Dipakai juga oleh komponen Alpine di Blade untuk menampilkan angka
    // (menggantikan formatNumber yang dulu diduplikasi di tiga berkas).
    window.JposNumber = {
        /**
         * Angka (dari server/state) -> teks berformat Indonesia untuk ditampilkan.
         * null/kosong menghasilkan "0", sama seperti formatNumber lama yang digantikan
         * helper ini, supaya tidak ada tampilan yang berubah jadi kosong.
         */
        format: function (angka, desimal) {
            var n = keAngkaMesin(angka);

            return keTampilan(n === null ? 0 : n, {
                desimal: desimal || 0,
                pakaiPemisah: true,
                kosongJadiNol: true,
            });
        },
        /**
         * Kuantitas untuk ditampilkan - desimal hanya muncul kalau memang ada.
         *
         * Kuantitas boleh pecahan sejak barang timbangan bisa dijual per Kg atau per
         * Gram, tapi sebagian besar penjualan tetap bilangan bulat dan "10,0000 Pcs"
         * cuma membuat layar kasir sulit dibaca. Padanan Angka::qty() di sisi PHP;
         * keduanya harus menghasilkan teks yang sama persis, karena struk dicetak
         * server sedangkan keranjang digambar di sini.
         */
        formatQty: function (angka, maksDesimal) {
            var n = keAngkaMesin(angka);
            if (n === null) n = 0;

            var maks = typeof maksDesimal === 'number' ? maksDesimal : 4;
            var desimal = maks;

            for (var i = 0; i <= maks; i++) {
                var faktor = Math.pow(10, i);
                if (Math.round(n * faktor) / faktor === n) {
                    desimal = i;
                    break;
                }
            }

            return keTampilan(n, {
                desimal: desimal,
                pakaiPemisah: true,
                kosongJadiNol: true,
            });
        },
        /** Teks yang DIKETIK kasir (titik = ribuan) -> angka. */
        parse: function (teks, desimal) {
            return keAngka(teks, desimal || 0);
        },
        /** Nilai dari server/state (titik = desimal) -> angka. */
        parseMesin: function (nilai) {
            return keAngkaMesin(nilai);
        },
    };
})();
