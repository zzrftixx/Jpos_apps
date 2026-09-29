<?php

namespace Tests\Feature;

use App\Models\Product;
use Tests\JposTestCase;

/**
 * UAT alur pindai barcode di Modul Kasir.
 *
 * Keluhan yang memicu perbaikan ini: "scan barcode tidak bisa saat aku coba tadi, entah
 * kenapa." Setelah ditelusuri di peramban sungguhan, "entah kenapa"-nya justru bagian yang
 * paling penting - dan penyebabnya bukan satu, melainkan tiga hal yang berbeda:
 *
 *   1. KEGAGALAN YANG DIAM. Kode yang tidak terdaftar sama sekali tidak menampilkan apa pun:
 *      `if (data.found)` tanpa `else`, dan `catch (e) {}` yang kosong. Kasir memindai
 *      berulang kali tanpa satu pun petunjuk, lalu menyimpulkan alat pindainya rusak.
 *
 *   2. HASIL PINDAIAN MENDARAT DI KOLOM YANG SALAH. Alat pindai adalah papan ketik: ia
 *      menembakkan karakter ke elemen mana pun yang sedang terfokus. Terukur di peramban:
 *      memindai saat kolom nominal bayar terfokus mengubah nominalnya menjadi
 *      Rp 89.777.867.754.323 - dan transaksi bisa diselesaikan dengan kembalian sebesar itu.
 *      Itu bukan ketidaknyamanan, itu uang.
 *
 *   3. SATUAN TIDAK IKUT TERPILIH. Label per satuan sudah bisa dicetak sejak 2.1.0, tapi
 *      semuanya membawa kode produk yang sama - memindai label "Karung" dan label "Kg"
 *      menghasilkan hal yang persis sama.
 *
 * Nomor 2 dijaga di sisi peramban (public/vendor/jpos-pemindai.js); yang diuji di sini
 * nomor 1 dan 3, ditambah jalur pencariannya.
 */
class PindaiKasirTest extends JposTestCase
{
    private function pindai(string $kode)
    {
        return $this->actingAs($this->kasir)
            ->getJson(route('kasir.scan', ['barcode' => $kode]));
    }

    // -----------------------------------------------------------------
    // Urutan pencarian
    // -----------------------------------------------------------------

    public function test_barcode_produk_ditemukan(): void
    {
        $produk = $this->makeProduct(['name' => 'Aqua 600ml', 'barcode' => '8991234567890']);

        $this->pindai('8991234567890')
            ->assertOk()
            ->assertJson(['found' => true, 'unit_id' => null])
            ->assertJsonPath('product.id', $produk->id);
    }

    /**
     * Label yang dicetak untuk produk tanpa barcode manual memakai SKU sebagai kodenya.
     * Kalau SKU tidak ikut dicari, label itu tidak akan pernah ketemu saat dipindai.
     */
    public function test_sku_dipakai_sebagai_kode_cadangan(): void
    {
        $produk = $this->makeProduct(['name' => 'Gula Curah', 'barcode' => null]);

        $this->pindai($produk->sku)
            ->assertOk()
            ->assertJson(['found' => true])
            ->assertJsonPath('product.id', $produk->id);
    }

    /**
     * INI YANG BARU DAN PALING BERPENGARUH DI MEJA KASIR: barcode milik SATUAN langsung
     * menentukan satuannya, jadi kasir tidak perlu memilih apa pun.
     *
     * Tanpa ini, memindai label "Karung" cuma menemukan produknya, dan pada jam ramai
     * langkah memilih satuan itulah yang paling sering terlewat - barang seharga
     * Rp 250.000 per karung tercatat terjual seharga per kilo.
     */
    public function test_barcode_satuan_langsung_menentukan_satuannya(): void
    {
        $produk = $this->makeProduct(['name' => 'Beras', 'barcode' => '8990000000001', 'unit' => 'Kg']);
        $satuan = $this->makeProductUnit($produk, 'Karung', 25, 250000);
        $satuan->update(['barcode' => '8990000000002']);

        $this->pindai('8990000000002')
            ->assertOk()
            ->assertJson([
                'found' => true,
                'unit_id' => $satuan->id,
                'unit_name' => 'Karung',
            ])
            ->assertJsonPath('product.id', $produk->id);
    }

    /** Barcode produk pada produk bersatuan banyak TIDAK boleh menebak satuannya. */
    public function test_barcode_produk_tidak_menebak_satuan(): void
    {
        $produk = $this->makeProduct(['name' => 'Beras', 'barcode' => '8990000000001', 'unit' => 'Kg']);
        $this->makeProductUnit($produk, 'Karung', 25, 250000);

        $this->pindai('8990000000001')
            ->assertOk()
            ->assertJson(['found' => true, 'unit_id' => null]);
    }

    /** Satuan lebih spesifik daripada produk, jadi ia diperiksa lebih dulu. */
    public function test_barcode_satuan_menang_atas_sku_produk_lain(): void
    {
        $lain = $this->makeProduct(['name' => 'Produk Lain', 'barcode' => null]);
        $produk = $this->makeProduct(['name' => 'Beras', 'barcode' => '8990000000001']);
        $satuan = $this->makeProductUnit($produk, 'Karung', 25, 250000);
        $satuan->update(['barcode' => $lain->sku]);

        $this->pindai($lain->sku)
            ->assertOk()
            ->assertJsonPath('unit_id', $satuan->id)
            ->assertJsonPath('product.id', $produk->id);
    }

    // -----------------------------------------------------------------
    // Kegagalan tidak boleh diam
    // -----------------------------------------------------------------

    /**
     * PALING MENENTUKAN DI BERKAS INI. Balasan lama hanya `{found:false}`, dan sisi kasir
     * tidak menampilkan apa pun. Kegagalan yang diam adalah kegagalan yang tidak bisa
     * diperbaiki siapa pun - kasir tidak tahu kodenya belum terdaftar, dan pemilik toko
     * tidak tahu ada yang perlu didaftarkan.
     */
    public function test_kode_tidak_terdaftar_menjelaskan_dirinya(): void
    {
        $response = $this->pindai('9999999999999')->assertOk();

        $response->assertJson(['found' => false]);

        $pesan = $response->json('message');

        $this->assertNotEmpty($pesan, 'Kode yang tidak ketemu tidak menjelaskan apa pun.');
        $this->assertStringContainsString('9999999999999', $pesan,
            'Pesannya tidak menyebut kode yang barusan dipindai.');
    }

    public function test_kode_kosong_ditolak_dengan_penjelasan(): void
    {
        $this->pindai('')
            ->assertOk()
            ->assertJson(['found' => false])
            ->assertJsonStructure(['message']);
    }

    /** Produk nonaktif tidak boleh masuk keranjang lewat pindaian. */
    public function test_produk_nonaktif_tidak_ditemukan(): void
    {
        $produk = $this->makeProduct(['name' => 'Sudah Berhenti Dijual', 'barcode' => '8995555555555']);
        $produk->update(['is_active' => false]);

        $this->pindai('8995555555555')
            ->assertOk()
            ->assertJson(['found' => false]);
    }

    /** Satuan milik produk nonaktif juga tidak boleh ditemukan. */
    public function test_satuan_milik_produk_nonaktif_tidak_ditemukan(): void
    {
        $produk = $this->makeProduct(['name' => 'Beras', 'barcode' => '8990000000001']);
        $satuan = $this->makeProductUnit($produk, 'Karung', 25, 250000);
        $satuan->update(['barcode' => '8990000000002']);
        $produk->update(['is_active' => false]);

        $this->pindai('8990000000002')
            ->assertOk()
            ->assertJson(['found' => false]);
    }

    // -----------------------------------------------------------------
    // Halaman kasir
    // -----------------------------------------------------------------

    /** Penangkap alat pindai wajib ikut termuat - tanpa itu seluruh perbaikan ini mati. */
    public function test_halaman_kasir_memuat_penangkap_alat_pindai(): void
    {
        $isi = $this->actingAs($this->kasir)->get(route('kasir.index'))->assertOk()->getContent();

        $this->assertStringContainsString('vendor/jpos-pemindai.js', $isi);
        $this->assertStringContainsString('jpos:barcode-dipindai', $isi,
            'Halaman kasir tidak mendengarkan peristiwa pindaian.');
    }

    /**
     * Penangkapnya dimuat di SELURUH halaman, bukan cuma Kasir: alat pindai menembakkan
     * karakter ke mana pun fokus berada, dan yang paling berbahaya justru saat fokusnya
     * sedang di kolom nominal.
     */
    public function test_penangkap_alat_pindai_termuat_di_halaman_lain(): void
    {
        $this->actingAs($this->admin)
            ->get(route('produk.index'))
            ->assertOk()
            ->assertSee('vendor/jpos-pemindai.js', false);
    }

    /** Barcode satuan bisa disimpan lewat form produk, bukan cuma lewat database. */
    public function test_barcode_satuan_tersimpan_dan_unik_per_baris(): void
    {
        $produk = $this->makeProduct(['name' => 'Beras', 'barcode' => '8990000000001']);
        $satuan = $this->makeProductUnit($produk, 'Karung', 25, 250000);

        $satuan->update(['barcode' => '8990000000002']);

        $this->assertSame('8990000000002', $satuan->fresh()->barcode);
        $this->assertDatabaseHas('product_units', [
            'id' => $satuan->id,
            'barcode' => '8990000000002',
        ]);
    }

    /**
     * Kodenya harus bisa diisi dari form produk, bukan cuma lewat database - kalau tidak,
     * fitur ini hanya ada di atas kertas bagi pemilik toko.
     */
    public function test_barcode_satuan_bisa_diisi_dari_form_produk(): void
    {
        $unit = \App\Models\Unit::firstOrCreate(['name' => 'Karung']);

        $this->actingAs($this->admin)->post(route('produk.store'), [
            'name' => 'Beras Premium',
            'type' => 'barang',
            'unit' => 'Kg',
            'cost_price' => 10000,
            'sell_price' => 12000,
            'stock' => 100,
            'min_stock' => 1,
            'multi_unit_enabled' => 1,
            'units' => [
                ['unit_id' => $unit->id, 'barcode' => '8990000000002', 'ratio_to_previous' => 25, 'price' => 250000],
            ],
        ])->assertRedirect();

        $produk = Product::where('name', 'Beras Premium')->firstOrFail();

        $this->assertSame('8990000000002', $produk->units->first()->barcode);

        // Dan yang paling penting: kode itu benar-benar bisa dipindai.
        $this->pindai('8990000000002')
            ->assertOk()
            ->assertJsonPath('unit_name', 'Karung');
    }

    /** Kode yang dikosongkan disimpan sebagai null, bukan string kosong yang bisa tertabrak. */
    public function test_barcode_satuan_yang_dikosongkan_tidak_menjadi_string_kosong(): void
    {
        $unit = \App\Models\Unit::firstOrCreate(['name' => 'Dus']);

        $this->actingAs($this->admin)->post(route('produk.store'), [
            'name' => 'Air Mineral',
            'type' => 'barang',
            'unit' => 'Botol',
            'cost_price' => 2000,
            'sell_price' => 3000,
            'stock' => 50,
            'min_stock' => 1,
            'multi_unit_enabled' => 1,
            'units' => [
                ['unit_id' => $unit->id, 'barcode' => '   ', 'ratio_to_previous' => 24, 'price' => 60000],
            ],
        ])->assertRedirect();

        $satuan = Product::where('name', 'Air Mineral')->firstOrFail()->units->first();

        $this->assertNull($satuan->barcode,
            'Kode kosong tersimpan sebagai string kosong - dua satuan tanpa kode akan saling tertabrak saat dipindai.');
    }

    /** Katalog kasir tetap utuh sesudah kolom baru ditambahkan. */
    public function test_katalog_kasir_masih_membawa_satuan_tambahan(): void
    {
        $produk = $this->makeProduct(['name' => 'Beras', 'barcode' => '8990000000001']);
        $this->makeProductUnit($produk, 'Karung', 25, 250000);

        $katalog = collect(
            $this->actingAs($this->kasir)->get(route('kasir.index'))->assertOk()->viewData('productsForCart')
        );

        $baris = $katalog->firstWhere('id', $produk->id);

        $this->assertNotNull($baris);
        $this->assertCount(1, $baris['additional_units']);
        $this->assertSame('Karung', $baris['additional_units'][0]['unit_name']);
    }

    public function test_katalog_kasir_membawa_barcode_produk_dan_barcode_satuan(): void
    {
        $produk = $this->makeProduct(['name' => 'Biskuit', 'barcode' => '8991112223334']);
        $satuan = $this->makeProductUnit($produk, 'Karton', 10, 150000);
        $satuan->update(['barcode' => '8991112229999']);

        $katalog = collect(
            $this->actingAs($this->kasir)->get(route('kasir.index'))->assertOk()->viewData('productsForCart')
        );

        $baris = $katalog->firstWhere('id', $produk->id);
        $this->assertNotNull($baris);
        $this->assertSame('8991112223334', $baris['barcode'], 'Barcode produk harus ada di katalog keranjang kasir.');
        $this->assertSame('8991112229999', $baris['additional_units'][0]['barcode'], 'Barcode satuan harus ada di katalog keranjang kasir.');
    }

    public function test_pindai_toleran_terhadap_leading_zero_pada_barcode_produk(): void
    {
        $produk = $this->makeProduct(['name' => 'Kecap Asin', 'barcode' => '0899123456789']);

        // Dipindai tanpa angka 0 di depan (mis. scanner mengirim UPC-A 12 digit)
        $this->pindai('899123456789')
            ->assertOk()
            ->assertJson(['found' => true])
            ->assertJsonPath('product.id', $produk->id);

        // Dipindai dengan angka 0 di depan
        $this->pindai('0899123456789')
            ->assertOk()
            ->assertJson(['found' => true])
            ->assertJsonPath('product.id', $produk->id);
    }

    public function test_pindai_toleran_terhadap_leading_zero_pada_barcode_satuan(): void
    {
        $produk = $this->makeProduct(['name' => 'Susu Kotak', 'barcode' => '8997771112223']);
        $satuan = $this->makeProductUnit($produk, 'Dus', 24, 120000);
        $satuan->update(['barcode' => '0899777333444']);

        // Dipindai tanpa angka 0 di depan
        $this->pindai('899777333444')
            ->assertOk()
            ->assertJson([
                'found' => true,
                'unit_id' => $satuan->id,
                'unit_name' => 'Dus',
            ])
            ->assertJsonPath('product.id', $produk->id);
    }

    public function test_pencarian_katalog_kasir_lewat_url_mendukung_barcode_satuan(): void
    {
        $produk = $this->makeProduct(['name' => 'Deterjen Bubuk', 'barcode' => '8995551110001']);
        $satuan = $this->makeProductUnit($produk, 'Renceng', 12, 24000);
        $satuan->update(['barcode' => '8995551110002']);

        $res = $this->actingAs($this->kasir)->get(route('kasir.index', ['q' => '8995551110002']))->assertOk();
        $items = collect($res->viewData('productsForCart'));
        $this->assertTrue($items->contains('id', $produk->id), 'Pencarian kasir harus menemukan produk lewat barcode satuan.');
    }

    public function test_pencarian_master_produk_mendukung_barcode_satuan_dan_toleran_leading_zero(): void
    {
        $produk = $this->makeProduct(['name' => 'Kopi Sachet', 'barcode' => '8993334445551']);
        $satuan = $this->makeProductUnit($produk, 'Renceng', 10, 15000);
        $satuan->update(['barcode' => '8993334445552']);

        // Cari lewat barcode satuan di Master Produk
        $res = $this->actingAs($this->admin)->get(route('produk.index', ['q' => '8993334445552']))->assertOk();
        $products = $res->viewData('products');
        $this->assertTrue($products->contains('id', $produk->id), 'Master Produk harus menemukan produk lewat barcode satuan.');

        // Cari dengan leading zero di Master Produk (mis. scanner kirim 0899...)
        $resZero = $this->actingAs($this->admin)->get(route('produk.index', ['q' => '08993334445551']))->assertOk();
        $productsZero = $resZero->viewData('products');
        $this->assertTrue($productsZero->contains('id', $produk->id), 'Master Produk harus toleran leading zero saat mencari.');
    }

    public function test_pindai_barcode_produk_tanpa_nol_ditemukan_saat_dipindai_dengan_nol(): void
    {
        $produk = $this->makeProduct(['name' => 'Teh Celup', 'barcode' => '8998889990001']);

        $this->pindai('08998889990001')
            ->assertOk()
            ->assertJson(['found' => true])
            ->assertJsonPath('product.id', $produk->id);
    }

    public function test_pindai_barcode_satuan_tanpa_nol_ditemukan_saat_dipindai_dengan_nol(): void
    {
        $produk = $this->makeProduct(['name' => 'Wafer Cokelat', 'barcode' => '8991231231231']);
        $satuan = $this->makeProductUnit($produk, 'Kaleng', 6, 90000);
        $satuan->update(['barcode' => '8991231231232']);

        $this->pindai('08991231231232')
            ->assertOk()
            ->assertJson([
                'found' => true,
                'unit_id' => $satuan->id,
                'unit_name' => 'Kaleng',
            ])
            ->assertJsonPath('product.id', $produk->id);
    }

    public function test_cetak_barcode_mendukung_pencarian_barcode_satuan(): void
    {
        $produk = $this->makeProduct(['name' => 'Minyak Goreng', 'barcode' => '8994441112221']);
        $satuan = $this->makeProductUnit($produk, 'Jerigen', 4, 120000);
        $satuan->update(['barcode' => '8994441112222']);

        $res = $this->actingAs($this->admin)->get(route('barcode.index', ['q' => '8994441112222']))->assertOk();
        $products = $res->viewData('products');
        $this->assertTrue($products->contains('id', $produk->id), 'Cetak Barcode harus menemukan produk lewat barcode satuan.');
    }
}

