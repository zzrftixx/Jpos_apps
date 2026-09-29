/**
 * Unit test logika pencarian dan pencocokan barcode pada modul kasir.
 */
let lolos = 0;
let gagal = 0;

function cek(nama, syarat) {
    if (syarat) { lolos++; return; }
    gagal++;
    console.error('  GAGAL: ' + nama);
}

function cocokBarcode(kode, term) {
    if (!kode || !term) return false;
    const k = String(kode).trim().toLowerCase();
    const t = String(term).trim().toLowerCase();
    if (k.includes(t) || t.includes(k)) return true;
    const kTanpaNol = k.replace(/^0+/, '');
    const tTanpaNol = t.replace(/^0+/, '');
    return Boolean(kTanpaNol && tTanpaNol && (kTanpaNol.includes(tTanpaNol) || tTanpaNol.includes(kTanpaNol)));
}

function samaBarcode(a, b) {
    if (!a || !b) return false;
    const strA = String(a).trim().toLowerCase();
    const strB = String(b).trim().toLowerCase();
    if (strA === strB) return true;
    const aTanpaNol = strA.replace(/^0+/, '');
    const bTanpaNol = strB.replace(/^0+/, '');
    return aTanpaNol !== '' && aTanpaNol === bTanpaNol;
}

// 1. Uji samaBarcode
cek('samaBarcode: barcode identik cocok', samaBarcode('8991234567890', '8991234567890'));
cek('samaBarcode: toleransi leading zero EAN-13 vs UPC-A (katalog tanpa nol, scanner bawa nol)',
    samaBarcode('899123456789', '0899123456789'));
cek('samaBarcode: toleransi leading zero EAN-13 vs UPC-A (katalog bawa nol, scanner tanpa nol)',
    samaBarcode('0899123456789', '899123456789'));
cek('samaBarcode: toleransi spasi / trim', samaBarcode(' 8991234567890 ', '8991234567890'));
cek('samaBarcode: case insensitive untuk barcode alfanumerik', samaBarcode('abc123X', 'ABC123x'));
cek('samaBarcode: barcode berbeda tidak boleh cocok', !samaBarcode('899111', '899222'));
cek('samaBarcode: input null / kosong tidak melempar error dan bernilai false',
    !samaBarcode(null, '899') && !samaBarcode('', '899') && !samaBarcode('899', null));

// 2. Uji cocokBarcode (pencarian parsial & toleran leading zero)
cek('cocokBarcode: kecocokan persis', cocokBarcode('8991234567890', '8991234567890'));
cek('cocokBarcode: substring awal', cocokBarcode('8991234567890', '899123'));
cek('cocokBarcode: substring tengah', cocokBarcode('8991234567890', '12345'));
cek('cocokBarcode: term dengan leading 0 menemukan barcode tanpa leading 0',
    cocokBarcode('899123456789', '0899123456789'));
cek('cocokBarcode: barcode dengan leading 0 ditemukan term tanpa leading 0',
    cocokBarcode('0899123456789', '899123456789'));
cek('cocokBarcode: kode kosong / null mengembalikan false',
    !cocokBarcode(null, '899') && !cocokBarcode('899', ''));

// 3. Uji simulasi filter katalog kasir
const sampleProducts = [
    {
        id: 1,
        name: 'Aqua 600ml',
        barcode: '8991234567890',
        sku: 'SKU-AQUA',
        category_id: 1, // Minuman
        additional_units: [
            { id: 10, unit_name: 'Dus', barcode: '8999999000001' }
        ]
    },
    {
        id: 2,
        name: 'Keripik Tempe',
        barcode: '0899777888999',
        sku: 'SKU-TEMPE',
        category_id: 2, // Makanan
        additional_units: []
    }
];

function saring(products, search, categoryId, topIds = []) {
    const hasSearch = (search || '').trim().length > 0;
    const term = (search || '').trim().toLowerCase();

    return products.filter(p => {
        const matchSearch = !hasSearch ||
            (p.name && p.name.toLowerCase().includes(term)) ||
            (p.barcode && cocokBarcode(p.barcode, term)) ||
            (p.sku && String(p.sku).toLowerCase().includes(term)) ||
            (p.additional_units && p.additional_units.some(u => u.barcode && cocokBarcode(u.barcode, term)));

        let matchCat = true;
        if (!hasSearch) {
            if (categoryId === 'terlaris') {
                matchCat = topIds.includes(p.id);
            } else if (categoryId !== '') {
                matchCat = String(p.category_id) === String(categoryId);
            }
        }
        return matchSearch && matchCat;
    });
}

// Simulasi kasus user:
// Kasir sedang klik kategori "Makanan" (id: 2), lalu menembak barcode "Aqua" (kategori: 1, id: 1)
const hasilScanBedaKategori = saring(sampleProducts, '8991234567890', '2');
cek('filter kasir: scan barcode produk beda kategori tetap ditemukan (tidak terblokir chip kategori)',
    hasilScanBedaKategori.length === 1 && hasilScanBedaKategori[0].id === 1);

// Scan barcode satuan kemasan (Dus Aqua) saat kategori Makanan aktif
const hasilScanSatuan = saring(sampleProducts, '8999999000001', '2');
cek('filter kasir: scan barcode multi-unit tetap ditemukan di katalog',
    hasilScanSatuan.length === 1 && hasilScanSatuan[0].id === 1);

// Scan barcode dengan leading zero tolerance saat filter kosong
const hasilScanLeadingZero = saring(sampleProducts, '899777888999', '');
cek('filter kasir: scan tanpa leading zero menemukan produk yang di-input dengan leading zero',
    hasilScanLeadingZero.length === 1 && hasilScanLeadingZero[0].id === 2);

// Tanpa pencarian, chip kategori tetap berfungsi normal
const saringMakanan = saring(sampleProducts, '', '2');
cek('filter kasir: tanpa search, filter kategori tetap berlaku (hanya kategori 2)',
    saringMakanan.length === 1 && saringMakanan[0].id === 2);

console.log(`\n  Test logika pencarian barcode kasir: ${lolos} lolos, ${gagal} gagal\n`);

if (gagal > 0) {
    process.exit(1);
}
