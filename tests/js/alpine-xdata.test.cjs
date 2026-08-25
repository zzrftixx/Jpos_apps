/**
 * Memeriksa setiap ekspresi x-data di seluruh Blade benar-benar JavaScript yang sah.
 *
 * KENAPA TEST INI ADA. Seluruh test PHP hanya MERENDER halaman; tidak satu pun menjalankan
 * JavaScript-nya. Kalau ada satu tanda kurung yang salah di dalam x-data, Alpine gagal
 * mengevaluasi seluruh komponen dan setiap tombol di halaman itu berhenti bekerja - modal
 * tidak terbuka, keranjang tidak bertambah - sementara HTML-nya tetap terkirim rapi dengan
 * status 200 dan seluruh test PHP tetap hijau.
 *
 * Itu persis yang terjadi: tombol "+ Tambah Produk" tidak membuka apa-apa, dan tidak ada
 * satu pun test yang menyadarinya.
 *
 * Yang diperiksa di sini murni SINTAKS - bahwa ekspresinya bisa diurai. Perilakunya tidak
 * bisa diuji tanpa browser, tapi sintaks rusak adalah kegagalan yang paling sering dan
 * paling mematikan, dan itu bisa ditangkap dengan murah.
 */

const fs = require('fs');
const path = require('path');

const AKAR = path.join(__dirname, '..', '..', 'resources', 'views');

function semuaBlade(dir) {
    return fs.readdirSync(dir, { withFileTypes: true }).flatMap((e) => {
        const p = path.join(dir, e.name);
        if (e.isDirectory()) return semuaBlade(p);
        return e.name.endsWith('.blade.php') ? [p] : [];
    });
}

/**
 * Mengambil isi atribut x-data="..." beserta posisinya.
 *
 * Nilainya dibatasi tanda kutip ganda, dan isi Alpine memakai kutip tunggal untuk string -
 * jadi pencarian kutip ganda penutup pertama sudah cukup dan tidak perlu mengurai HTML.
 */
function ambilXData(isi) {
    const hasil = [];
    const pola = /x-data="/g;
    let m;

    while ((m = pola.exec(isi)) !== null) {
        const mulai = m.index + m[0].length;
        const akhir = isi.indexOf('"', mulai);
        if (akhir === -1) continue;

        const baris = isi.slice(0, mulai).split('\n').length;
        hasil.push({ baris, ekspresi: isi.slice(mulai, akhir) });
    }

    return hasil;
}

/**
 * Menetralkan sintaks Blade supaya yang tersisa bisa diurai sebagai JavaScript.
 *
 * Ekspresi seperti {{ $produkMode }} atau @js($settings['layout']) dievaluasi server dan
 * menghasilkan literal; di sini cukup diganti dengan literal yang bentuknya setara.
 */
function tanpaBlade(ekspresi) {
    return ekspresi
        // {{ Illuminate\Support\Js::from(...) }} dan {{ $var }} -> literal
        .replace(/\{\{[^}]*\}\}/g, '0')
        .replace(/@js\([^)]*\)/g, '0')
        .replace(/@json\([^)]*\)/g, '0')
        // Entitas HTML yang muncul kalau nilainya sempat di-escape.
        .replace(/&quot;/g, '"')
        .replace(/&#0?39;/g, "'")
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>');
}

/**
 * Seluruh atribut yang isinya dievaluasi Alpine sebagai JavaScript: x-*, @event, dan :bind.
 * Semuanya dibatasi tanda kutip ganda, jadi semuanya rentan pada masalah yang sama.
 */
const ATRIBUT_ALPINE = /\s(x-[a-z-]+|@[a-zA-Z][a-zA-Z0-9.:-]*|:[a-zA-Z][a-zA-Z0-9.-]*)="/g;

let lolos = 0;
const gagal = [];

for (const berkas of semuaBlade(AKAR)) {
    const isi = fs.readFileSync(berkas, 'utf8');
    const nama = path.relative(AKAR, berkas).split(path.sep).join('/');

    // --- x-data: harus terurai sebagai OBJEK -----------------------------------------
    for (const { baris, ekspresi } of ambilXData(isi)) {
        const kode = tanpaBlade(ekspresi);

        try {
            // Membungkusnya dalam tanda kurung memaksa penguraian sebagai objek, bukan blok.
            new Function('return (' + kode + ');');
            lolos++;
        } catch (e) {
            gagal.push(
                `${nama}:${baris} [x-data] ${e.message}` +
                (kode.includes('"') ? '  <- ada tanda kutip GANDA di dalam x-data; itu memutus atributnya di tengah jalan' : '')
            );
        }
    }

    // --- atribut Alpine lainnya: ekspresi ATAU pernyataan -----------------------------
    let m;
    ATRIBUT_ALPINE.lastIndex = 0;

    while ((m = ATRIBUT_ALPINE.exec(isi)) !== null) {
        if (m[1] === 'x-data') continue;   // sudah diperiksa di atas

        const mulai = m.index + m[0].length;
        const akhir = isi.indexOf('"', mulai);
        if (akhir === -1) continue;

        const kode = tanpaBlade(isi.slice(mulai, akhir));
        if (kode.trim() === '') continue;

        try {
            new Function('return (' + kode + ');');
            lolos++;
        } catch (e1) {
            // @click="a = 1; b()" berisi PERNYATAAN, bukan ekspresi - itu sah.
            try {
                new Function(kode);
                lolos++;
            } catch (e2) {
                const baris = isi.slice(0, mulai).split('\n').length;
                gagal.push(`${nama}:${baris} [${m[1]}] ${e2.message} :: ${kode.slice(0, 70)}`);
            }
        }
    }
}

console.log(`\n  Test atribut Alpine: ${lolos} lolos, ${gagal.length} gagal\n`);

if (gagal.length > 0) {
    gagal.forEach((g) => console.error('  GAGAL ' + g));
    process.exit(1);
}
