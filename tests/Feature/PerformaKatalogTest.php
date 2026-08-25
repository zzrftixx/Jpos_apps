<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Unit;
use App\Support\ProductCatalog;
use Illuminate\Support\Facades\DB;
use Tests\JposTestCase;

/**
 * Batas performa halaman Kasir pada katalog besar.
 *
 * Server bawaan Windows melayani SATU permintaan pada satu waktu. Setiap milidetik yang
 * dipakai membangun katalog bukan cuma memperlambat halaman itu - ia menahan seluruh
 * permintaan lain di belakangnya, termasuk membuka struk di tab baru.
 *
 * Yang dijaga di sini adalah hal-hal yang memburuk secara diam-diam seiring katalog toko
 * bertambah: jumlah query yang ikut tumbuh mengikuti jumlah produk (N+1), dan cache yang
 * tanpa sadar berhenti bekerja.
 */
class PerformaKatalogTest extends JposTestCase
{
    private const JUMLAH_PRODUK = 300;

    /** Membuat katalog besar lewat query mentah supaya penyiapannya sendiri tidak lambat. */
    private function buatKatalogBesar(?int $jumlah = null, string $awalan = 'Produk Massal'): void
    {
        $jumlah ??= self::JUMLAH_PRODUK;

        Unit::firstOrCreate(['name' => 'Kg'])->update(['is_weighable' => true]);
        $dus = Unit::firstOrCreate(['name' => 'Dus'])->id;
        $waktu = now()->toDateTimeString();

        $produk = [];
        for ($i = 1; $i <= $jumlah; $i++) {
            $produk[] = [
                'name' => $awalan . ' ' . $i,
                'type' => 'barang',
                'sku' => 'SKU-' . substr(md5($awalan), 0, 6) . '-' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'unit' => $i % 3 === 0 ? 'Kg' : 'Pcs',
                'cost_price' => 5000,
                'sell_price' => 8000,
                'stock' => 100,
                'min_stock' => 5,
                'is_taxable' => true,
                'is_active' => true,
                'multi_unit_enabled' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ];
        }
        foreach (array_chunk($produk, 100) as $bagian) {
            DB::table('products')->insert($bagian);
        }

        $satuan = [];
        foreach (DB::table('products')->where('name', 'like', $awalan . ' %')->pluck('id') as $id) {
            $satuan[] = [
                'product_id' => $id, 'unit_id' => $dus,
                'conversion' => 12, 'ratio_to_previous' => 12, 'sort_order' => 0,
                'price' => 90000, 'allow_decimal' => false,
                'created_at' => $waktu, 'updated_at' => $waktu,
            ];
        }
        foreach (array_chunk($satuan, 100) as $bagian) {
            DB::table('product_units')->insert($bagian);
        }

        app(ProductCatalog::class)->flush();
    }

    /** Menghitung query untuk satu kali membangun katalog dari nol. */
    private function hitungQueryBangunKatalog(): int
    {
        app(ProductCatalog::class)->flush();

        // flushQueryLog WAJIB: menonaktifkan lalu mengaktifkan kembali log TIDAK
        // mengosongkan isinya, sehingga pengukuran kedua diam-diam ikut menghitung
        // query pengukuran pertama - dan terbaca persis seperti N+1 yang tidak ada.
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(ProductCatalog::class)->forCart();
        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        return $jumlah;
    }

    /**
     * INI YANG PALING MENENTUKAN. Jumlah query membangun katalog harus TETAP, tidak peduli
     * ada 3 produk atau 3.000. Kalau ikut tumbuh, toko yang katalognya berkembang akan
     * melambat sendiri tanpa ada yang mengubah apa pun.
     */
    public function test_jumlah_query_tidak_tumbuh_mengikuti_jumlah_produk(): void
    {
        // Kedua katalog sama-sama berisi produk BERSATUAN TAMBAHAN. Membandingkan katalog
        // bersatuan dengan katalog tanpa satuan akan menyesatkan: yang tanpa satuan
        // melewatkan satu eager-load, dan selisih tetap satu query itu terbaca seperti N+1.
        $this->buatKatalogBesar(10);
        $queryKecil = $this->hitungQueryBangunKatalog();

        $this->buatKatalogBesar(self::JUMLAH_PRODUK, 'Gelombang Dua');
        $queryBesar = $this->hitungQueryBangunKatalog();

        $this->assertSame(
            $queryKecil,
            $queryBesar,
            "Jumlah query berubah dari {$queryKecil} (10 produk) menjadi {$queryBesar} (" .
            (self::JUMLAH_PRODUK + 10) . " produk) - ada N+1."
        );
    }

    /**
     * Cache katalog harus benar-benar terpakai. Kalau kuncinya berubah tiap permintaan,
     * cache-nya tidak pernah kena dan seluruh biayanya diulang setiap kali halaman dibuka -
     * tanpa gejala apa pun selain lambat.
     */
    public function test_membuka_kasir_dua_kali_tidak_menembak_katalog_dua_kali(): void
    {
        $this->buatKatalogBesar();

        $katalog = app(ProductCatalog::class);
        $katalog->forCart();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $katalog->forCart();
        $queryKedua = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        // Panggilan kedua hanya boleh membaca sidik jari cache, bukan membangun ulang.
        $this->assertLessThanOrEqual(
            5,
            $queryKedua,
            "Panggilan kedua menembak {$queryKedua} query - cache katalog tidak terpakai."
        );
    }

    /** Mengubah satu produk harus langsung terlihat kasir - harga basi berarti uang yang salah. */
    public function test_mengubah_produk_langsung_menyegarkan_katalog(): void
    {
        $this->buatKatalogBesar();
        $katalog = app(ProductCatalog::class);

        $sebelum = collect($katalog->forCart())->firstWhere('name', 'Produk Massal 1');
        $this->assertSame(8000.0, $sebelum['sell_price']);

        Product::where('name', 'Produk Massal 1')->firstOrFail()->update(['sell_price' => 9500]);

        $sesudah = collect($katalog->forCart())->firstWhere('name', 'Produk Massal 1');
        $this->assertSame(9500.0, $sesudah['sell_price'], 'Kasir masih melihat harga lama.');
    }

    /**
     * Halaman Kasir memuat SELURUH katalog aktif ke dalam HTML. Ukurannya harus tetap wajar,
     * karena seluruh isinya juga harus diurai Alpine di komputer kasir yang tidak kencang.
     */
    public function test_halaman_kasir_terbuka_pada_katalog_besar(): void
    {
        $this->buatKatalogBesar();

        // Permintaan pertama dibuang: di dalamnya termasuk mengkompilasi seluruh template
        // Blade. Di aplikasi sungguhan itu sudah dikerjakan `jpos:prepare` saat start, jadi
        // yang dialami kasir adalah permintaan kedua dan seterusnya.
        $this->actingAs($this->kasir)->get('/kasir')->assertOk();

        $mulai = microtime(true);
        $halaman = $this->actingAs($this->kasir)->get('/kasir')->assertOk()->getContent();
        $lama = (microtime(true) - $mulai) * 1000;

        $kb = round(strlen($halaman) / 1024);

        // Ambang yang longgar: mesin uji jauh lebih lambat daripada komputer kasir yang
        // memakai OPcache dan view yang sudah dikompilasi. Yang dijaga di sini adalah
        // pembengkakan besar, bukan selisih puluhan milidetik.
        $this->assertLessThan(
            1500,
            $lama,
            sprintf('Halaman Kasir butuh %d ms untuk %d produk.', $lama, self::JUMLAH_PRODUK)
        );

        // Angka ini bukan patokan mutlak, tapi lonjakan besar berarti ada yang ikut
        // terserialisasi ke halaman padahal tidak dipakai.
        $this->assertLessThan(
            2048,
            $kb,
            "Halaman Kasir {$kb} KB untuk " . self::JUMLAH_PRODUK . " produk - ada data berlebih yang ikut tertanam."
        );
    }

    /** Laporan stok memindai seluruh produk; indeks dan paginasi harus menahannya tetap wajar. */
    public function test_laporan_stok_terbuka_pada_katalog_besar(): void
    {
        $this->buatKatalogBesar();

        $this->actingAs($this->admin)->get('/laporan/stok')->assertOk();

        $mulai = microtime(true);
        $this->actingAs($this->admin)->get('/laporan/stok')->assertOk();
        $lama = (microtime(true) - $mulai) * 1000;

        $this->assertLessThan(1500, $lama, sprintf('Laporan stok butuh %d ms.', $lama));
    }

    /**
     * Halaman-halaman yang ditambahkan belakangan juga wajib tidak tumbuh mengikuti katalog.
     *
     * Neraca menjumlahkan nilai seluruh persediaan, Planogram menggambar kisi berisi produk,
     * dan Pembelian menanam daftar produk ke formulirnya. Ketiganya berpeluang menembak satu
     * query per produk kalau ditulis dengan lengah - dan gejalanya cuma "aplikasi makin
     * lambat" seiring toko bertambah barang, tanpa satu pun pesan galat.
     */
    public function test_halaman_baru_tidak_tumbuh_mengikuti_jumlah_produk(): void
    {
        $halaman = ['/laporan/neraca', '/pembelian', '/master/planogram', '/laporan/laba'];

        $this->buatKatalogBesar(30, 'Katalog Kecil');
        $kecil = [];
        foreach ($halaman as $url) {
            $kecil[$url] = $this->hitungQuery($url);
        }

        $this->buatKatalogBesar(300, 'Katalog Besar');
        foreach ($halaman as $url) {
            $besar = $this->hitungQuery($url);

            $this->assertLessThanOrEqual(
                $kecil[$url] + 3,
                $besar,
                sprintf(
                    'Halaman %s: %d query pada 30 produk, %d query pada 330 produk. '
                    . 'Jumlahnya ikut tumbuh - itu tanda satu query per produk.',
                    $url,
                    $kecil[$url],
                    $besar
                )
            );
        }
    }

    private function hitungQuery(string $url): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->admin)->get($url)->assertOk();

        $jumlah = count(DB::getRawQueryLog());
        DB::disableQueryLog();

        return $jumlah;
    }

    /**
     * Cache katalog itu yang menahan halaman Kasir tetap ringan. Kalau suatu saat ia
     * berhenti bekerja, gejalanya cuma "aplikasi terasa berat" - tidak ada error apa pun.
     * Angka ini yang membuat kehilangan itu terlihat.
     */
    public function test_cache_katalog_jauh_lebih_cepat_daripada_membangun_ulang(): void
    {
        $this->buatKatalogBesar();
        $katalog = app(ProductCatalog::class);

        $katalog->flush();
        $mulai = microtime(true);
        $katalog->forCart();
        $bangun = (microtime(true) - $mulai) * 1000;

        $mulai = microtime(true);
        $katalog->forCart();
        $dariCache = (microtime(true) - $mulai) * 1000;

        $this->assertLessThan(
            $bangun / 3,
            $dariCache,
            sprintf(
                'Membaca dari cache (%.1f ms) tidak jauh lebih cepat daripada membangun ulang (%.1f ms) - cache katalog kemungkinan tidak bekerja.',
                $dariCache,
                $bangun
            )
        );
    }

    /** Katalog besar harus tetap selamat melewati serialisasi cache. */
    public function test_katalog_besar_selamat_melewati_cache(): void
    {
        $this->buatKatalogBesar();

        $katalog = unserialize(serialize(app(ProductCatalog::class)->forCart()));

        $this->assertCount(self::JUMLAH_PRODUK, $katalog);

        $baris = collect($katalog)->firstWhere('name', 'Produk Massal 1');
        $this->assertIsArray($baris['additional_units']);
        $this->assertSame('Dus', $baris['additional_units'][0]['unit_name']);
        $this->assertArrayHasKey('is_weighable', $baris);
    }

    public function test_indeks_dipakai_saat_menyaring_laporan_penjualan(): void
    {
        $rencana = DB::select(
            "EXPLAIN QUERY PLAN SELECT * FROM sales WHERE created_at BETWEEN ? AND ? AND order_status = ?",
            ['2026-01-01', '2026-12-31', 'completed']
        );

        $teks = collect($rencana)->pluck('detail')->implode(' ');

        $this->assertStringContainsString(
            'sales_created_at_order_status_index',
            $teks,
            "Laporan penjualan tidak memakai indeks - rencananya: {$teks}"
        );
    }

    public function test_indeks_dipakai_saat_membaca_rantai_satuan(): void
    {
        $rencana = DB::select("EXPLAIN QUERY PLAN SELECT * FROM product_units WHERE product_id = 1 ORDER BY sort_order");
        $teks = collect($rencana)->pluck('detail')->implode(' ');

        $this->assertStringContainsString('product_units_product_id_sort_order_index', $teks, "Rencananya: {$teks}");
    }
}
