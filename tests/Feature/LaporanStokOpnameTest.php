<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Rack;
use App\Models\Setting;
use Tests\JposTestCase;

class LaporanStokOpnameTest extends JposTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('store_profile', [
            'name' => 'Toko Jayla Makmur',
            'address' => 'Jl. Merdeka No. 17, Semarang',
            'phone' => '0812-3456-7890',
        ]);
    }

    public function test_halaman_stok_opname_dapat_diakses(): void
    {
        $response = $this->actingAs($this->admin)->get(route('laporan.stok-opname'));
        $response->assertOk();
        $response->assertSee('Lembar Cek Stok Opname');
        $response->assertSee('Total Jenis Produk');
        $response->assertSee('Cetak Lembar Kerja');
    }

    public function test_tampilkan_sku_dan_barcode_lengkap_dengan_modal_dan_jual(): void
    {
        $category = Category::create(['name' => 'Makanan Kucing', 'slug' => 'makanan-kucing']);

        $product = $this->makeProduct([
            'name' => 'Whiskas Ocean Fish 1kg',
            'sku' => 'CAT-WHS-001',
            'barcode' => '8999991234567',
            'category_id' => $category->id,
            'cost_price' => 45000,
            'sell_price' => 60000,
            'stock' => 25,
            'unit' => 'Bungkus',
        ]);

        $response = $this->actingAs($this->admin)->get(route('laporan.stok-opname'));
        $response->assertOk();
        $response->assertSee('CAT-WHS-001');
        $response->assertSee('8999991234567');
        $response->assertSee('Whiskas Ocean Fish 1kg');
        $response->assertSee('Makanan Kucing');
        $response->assertSee('45.000');
        $response->assertSee('60.000');
        $response->assertSee('25');
    }

    public function test_pencarian_dan_filter_stok_opname_bekerja(): void
    {
        $catA = Category::create(['name' => 'Dry Food', 'slug' => 'dry-food']);
        $catB = Category::create(['name' => 'Wet Food', 'slug' => 'wet-food']);

        $prodA = $this->makeProduct([
            'name' => 'Royal Canin Mother & Babycat',
            'sku' => 'RC-MB-400',
            'barcode' => '1111222233334',
            'category_id' => $catA->id,
            'stock' => 10,
        ]);

        $prodB = $this->makeProduct([
            'name' => 'Pro Plan Delicate Adult',
            'sku' => 'PP-DA-150',
            'barcode' => '5555666677778',
            'category_id' => $catB->id,
            'stock' => 5,
        ]);

        // Cari berdasarkan SKU
        $resSku = $this->actingAs($this->admin)->get(route('laporan.stok-opname', ['q' => 'RC-MB-400']));
        $resSku->assertOk();
        $resSku->assertSee('Royal Canin Mother & Babycat');
        $resSku->assertDontSee('Pro Plan Delicate Adult');

        // Filter berdasarkan kategori
        $resCat = $this->actingAs($this->admin)->get(route('laporan.stok-opname', ['category_id' => $catB->id]));
        $resCat->assertOk();
        $resCat->assertSee('Pro Plan Delicate Adult');
        $resCat->assertDontSee('Royal Canin Mother & Babycat');
    }

    public function test_halaman_stok_biasa_juga_menampilkan_sku_barcode_modal_jual(): void
    {
        $product = $this->makeProduct([
            'name' => 'Me-O Creamy Treats 60g',
            'sku' => 'MEO-TRT-060',
            'barcode' => '8888999900001',
            'cost_price' => 18000,
            'sell_price' => 25000,
            'stock' => 40,
        ]);

        $response = $this->actingAs($this->admin)->get(route('laporan.stok'));
        $response->assertOk();
        $response->assertSee('MEO-TRT-060');
        $response->assertSee('8888999900001');
        $response->assertSee('Me-O Creamy Treats 60g');
        $response->assertSee('18.000');
        $response->assertSee('25.000');
        $response->assertSee('Buka Lembar Cek Stok Opname');
    }

    public function test_ekspor_stok_opname_pdf_dan_excel(): void
    {
        $this->makeProduct([
            'name' => 'Bolt Salmon 1kg',
            'sku' => 'BLT-SLM-001',
            'barcode' => '1234567890123',
            'cost_price' => 20000,
            'sell_price' => 28000,
            'stock' => 50,
        ]);

        // Unduh PDF
        $resPdf = $this->actingAs($this->admin)->get(route('laporan.ekspor', ['jenis' => 'stok-opname', 'format' => 'pdf']));
        $resPdf->assertOk();
        $this->assertStringStartsWith('%PDF', $resPdf->streamedContent());

        // Unduh Excel
        $resXls = $this->actingAs($this->admin)->get(route('laporan.ekspor', ['jenis' => 'stok-opname', 'format' => 'xlsx']));
        $resXls->assertOk();
        $this->assertStringStartsWith('PK', $resXls->streamedContent());
    }
}
