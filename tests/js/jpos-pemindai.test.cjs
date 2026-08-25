/**
 * Penangkap alat pindai: kapan ia menangkap, dan kapan ia HARUS diam.
 *
 * Kolom barcode di form Tambah Produk memang untuk diisi hasil pindaian. Sebelum penjagaan
 * ini ada, penangkapnya memperlakukan pindaian di sana sama seperti di kasir - isinya
 * dikembalikan ke keadaan semula, jadi kodenya tidak pernah muncul dan produk baru tidak
 * bisa didaftarkan barcodenya sama sekali.
 */
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const berkas = path.join(__dirname, '..', '..', 'public', 'vendor', 'jpos-pemindai.js');

let lolos = 0;
let gagal = 0;

function cek(nama, syarat) {
    if (syarat) { lolos++; return; }
    gagal++;
    console.error('  GAGAL: ' + nama);
}

/** DOM sekadarnya - cukup untuk menjalankan berkasnya, tanpa memasang pustaka apa pun. */
function jalankanDenganKolom(atribut) {
    const pendengar = [];
    const kolom = {
        tagName: 'INPUT',
        isContentEditable: false,
        value: 'ISI-SEMULA',
        selectionStart: 0,
        selectionEnd: 0,
        id: atribut.id || '',
        getAttribute: (n) => (n === 'name' ? (atribut.name || null) : null),
        setSelectionRange() {},
        dispatchEvent() { return true; },
    };

    const peristiwa = [];
    const dokumen = {
        activeElement: kolom,
        addEventListener: (jenis, fn) => { if (jenis === 'keydown') pendengar.push(fn); },
        dispatchEvent: (ev) => { peristiwa.push(ev); return true; },
    };

    const konteks = {
        document: dokumen,
        window: {},
        Date,
        CustomEvent: class { constructor(t, o) { this.type = t; this.detail = (o || {}).detail; } },
        Event: class { constructor(t) { this.type = t; } },
    };

    vm.createContext(konteks);
    vm.runInContext(fs.readFileSync(berkas, 'utf8'), konteks);

    // Tembakkan rentetan cepat lalu Enter - persis ritme alat pindai.
    let waktu = 1000;
    const kirim = (key) => {
        waktu += 10;
        let dicegah = false;
        const ev = {
            key, timeStamp: waktu,
            ctrlKey: false, altKey: false, metaKey: false,
            preventDefault() { dicegah = true; },
            stopPropagation() {},
        };
        pendengar.forEach((fn) => fn(ev));
        return dicegah;
    };

    '8991234567890'.split('').forEach((c) => { kirim(c); kolom.value += c; });
    const enterDicegah = kirim('Enter');

    return { peristiwa, enterDicegah, nilaiKolom: kolom.value };
}

// --- Kolom biasa (mis. nominal bayar di kasir): pindaian DITANGKAP -----------------
const biasa = jalankanDenganKolom({ name: 'paid_amount' });

cek('kolom biasa: peristiwa pindaian dikirim',
    biasa.peristiwa.length === 1 && biasa.peristiwa[0].type === 'jpos:barcode-dipindai');
cek('kolom biasa: kodenya ikut terbawa',
    biasa.peristiwa[0] && biasa.peristiwa[0].detail.kode === '8991234567890');
cek('kolom biasa: Enter dicegah supaya tidak memicu apa pun',
    biasa.enterDicegah === true);
cek('kolom biasa: isi kolom dikembalikan (barcode tidak jadi nominal)',
    biasa.nilaiKolom === 'ISI-SEMULA');

// --- Kolom barcode produk: pindaian DIBIARKAN -------------------------------------
const produk = jalankanDenganKolom({ name: 'barcode' });

cek('kolom barcode: TIDAK mengirim peristiwa pindaian',
    produk.peristiwa.length === 0);
cek('kolom barcode: Enter tidak dicegah',
    produk.enterDicegah === false);
cek('kolom barcode: kode tetap tertulis di kolom',
    produk.nilaiKolom === 'ISI-SEMULA8991234567890');

// --- Kolom barcode per satuan: units[0][barcode] ----------------------------------
const satuan = jalankanDenganKolom({ name: 'units[0][barcode]' });

cek('kolom barcode satuan: TIDAK mengirim peristiwa pindaian',
    satuan.peristiwa.length === 0);
cek('kolom barcode satuan: kode tetap tertulis',
    satuan.nilaiKolom === 'ISI-SEMULA8991234567890');

console.log('');
console.log('  Test penangkap pindai: ' + lolos + ' lolos, ' + gagal + ' gagal');
console.log('');

process.exit(gagal === 0 ? 0 : 1);
