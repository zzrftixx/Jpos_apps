/**
 * Test modul format angka (public/vendor/jpos-number.js).
 *
 * Dijalankan otomatis oleh build-exe.ps1 sebelum paket dikemas:
 *     node tests/js/jpos-number.test.cjs
 *
 * Modul ini hidup di browser dan tidak terjangkau phpunit, padahal di sinilah bug
 * penggandaan nominal terjadi: nilai dari server ("1500.00", titik = DESIMAL) dibaca
 * dengan parser teks ketikan (titik = RIBUAN), sehingga harga produk naik seratus kali
 * lipat setiap kali form Edit dibuka.
 */
const fs = require('fs');
const path = require('path');

// --- Tiruan lingkungan browser secukupnya agar modul IIFE bisa dievaluasi.
global.window = {};
global.document = {
    readyState: 'complete',
    addEventListener() {},
    documentElement: {},
    querySelectorAll: () => [],
    activeElement: null,
};
global.MutationObserver = function () { this.observe = function () {}; };

const berkas = path.resolve(__dirname, '../../public/vendor/jpos-number.js');
eval(fs.readFileSync(berkas, 'utf8'));

const J = global.window.JposNumber;

let lolos = 0;
const gagal = [];

function uji(nama, didapat, diharapkan) {
    if (Object.is(didapat, diharapkan)) {
        lolos++;
    } else {
        gagal.push(`${nama}\n      didapat   : ${JSON.stringify(didapat)}\n      diharapkan: ${JSON.stringify(diharapkan)}`);
    }
}

// =====================================================================
// Nilai dari SERVER: titik adalah pemisah DESIMAL.
// Laravel mengirim kolom decimal sebagai string - inilah sumber bugnya.
// =====================================================================
uji('server "1500.00" (harga produk)',        J.format('1500.00', 2), '1.500');
uji('server "2000.00"',                        J.format('2000.00', 2), '2.000');
uji('server "150000.00"',                      J.format('150000.00', 2), '150.000');
uji('server "1000.0000" (konversi decimal:4)', J.format('1000.0000', 4), '1.000');
uji('server "57.5" (lebar kertas)',            J.format('57.5', 1), '57,5');
uji('server "11.5" (persen pajak)',            J.format('11.5', 2), '11,5');
uji('server "1500.75" (harga berdesimal)',     J.format('1500.75', 2), '1.500,75');
uji('server angka 1500000',                    J.format(1500000, 0), '1.500.000');
uji('server angka 100',                        J.format(100, 0), '100');
uji('server null -> "0"',                      J.format(null, 0), '0');
uji('server "" -> "0"',                        J.format('', 0), '0');

// parseMesin mengembalikan angka apa adanya
uji('parseMesin "1500.00"',   J.parseMesin('1500.00'), 1500);
uji('parseMesin "1000.0000"', J.parseMesin('1000.0000'), 1000);
uji('parseMesin 2500',        J.parseMesin(2500), 2500);
uji('parseMesin null',        J.parseMesin(null), null);
// Nilai berformat Indonesia tetap terbaca benar walau lewat jalur mesin,
// karena koma adalah penanda yang tidak pernah muncul di format mesin.
uji('parseMesin "1.500,75"',  J.parseMesin('1.500,75'), 1500.75);

// =====================================================================
// Teks yang DIKETIK kasir: titik adalah pemisah RIBUAN.
// =====================================================================
uji('ketikan "1.500.000"',      J.parse('1.500.000', 0), 1500000);
uji('ketikan "1.500"',          J.parse('1.500', 0), 1500);
uji('ketikan "1.500,75"',       J.parse('1.500,75', 2), 1500.75);
uji('ketikan "Rp 2.750.000,-"', J.parse('Rp 2.750.000,-', 0), 2750000);
uji('ketikan ""',               J.parse('', 0), null);
uji('ketikan "abc"',            J.parse('abc', 0), null);

// =====================================================================
// Bolak-balik: format lalu baca lagi harus menghasilkan angka yang sama.
// Inilah yang dilanggar bug penggandaan.
// =====================================================================
[1500, 2000, 150000, 1500000, 12500, 999, 1].forEach((n) => {
    uji(`bolak-balik ${n}`, J.parse(J.format(n, 0), 0), n);
});

// Simulasi siklus buat -> edit -> simpan berulang seperti keluhan pengguna:
// nilai dari database diformat ke layar, dibaca lagi saat submit, disimpan, dan
// dikirim balik oleh server sebagai string decimal. Lima putaran harus stabil.
let nilai = 1500;
for (let i = 1; i <= 5; i++) {
    const diLayar = J.format(nilai.toFixed(2), 2);   // server -> layar
    nilai = J.parse(diLayar, 2);                      // layar -> dikirim
    uji(`siklus edit-simpan ke-${i}`, nilai, 1500);
}

// =====================================================================
// Kuantitas pecahan: desimal hanya muncul kalau memang ada.
//
// Harus menghasilkan teks yang SAMA PERSIS dengan Angka::qty() di sisi PHP -
// struk dicetak server sedangkan keranjang digambar di browser, dan angka yang
// sama tidak boleh tampil berbeda di keduanya.
// =====================================================================
uji('qty bulat 10',            J.formatQty(10), '10');
uji('qty 0,5',                 J.formatQty(0.5), '0,5');
uji('qty 0,4',                 J.formatQty(0.4), '0,4');
uji('qty 2,5',                 J.formatQty(2.5), '2,5');
uji('qty 9,6',                 J.formatQty(9.6), '9,6');
uji('qty 0,001',               J.formatQty(0.001), '0,001');
uji('qty ribuan 12500',        J.formatQty(12500), '12.500');
uji('qty 1.500,25',            J.formatQty(1500.25), '1.500,25');
uji('qty nol',                 J.formatQty(0), '0');
uji('qty null jadi 0',         J.formatQty(null), '0');

// Nilai dari server datang sebagai STRING decimal bertitik. Titik di situ berarti
// desimal, bukan ribuan - jalur keAngkaMesin. Kalau tertukar, 0,4 Kg akan tampil
// sebagai 4.000 Kg di layar kasir.
uji('qty dari server "0.4000"', J.formatQty('0.4000'), '0,4');
uji('qty dari server "9.60"',   J.formatQty('9.60'), '9,6');
uji('qty dari server "10.0000"', J.formatQty('10.0000'), '10');

// =====================================================================
console.log(`\n  Test modul angka: ${lolos} lolos, ${gagal.length} gagal\n`);

if (gagal.length > 0) {
    gagal.forEach((g) => console.error('  GAGAL ' + g));
    process.exit(1);
}
