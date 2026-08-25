<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Support\ProductCatalog;
use Tests\JposTestCase;

/**
 * Katalog produk yang ditanam ke halaman Kasir & Retur di-cache karena membangunnya
 * memakan ~247 ms pada katalog 2.000 produk, diulang setiap kali halaman dibuka.
 *
 * Syarat mutlaknya: hasil dari cache harus sama persis dengan hasil query langsung.
 * Kasir tidak boleh melihat harga atau stok yang basi.
 */
class ProductCatalogCacheTest extends JposTestCase
{
    private function catalog(): ProductCatalog
    {
        return app(ProductCatalog::class);
    }

    private function fresh(): array
    {
        return Product::with('units.unit')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => $p->toCartArray())
            ->values()
            ->all();
    }

    public function test_hasil_cache_sama_persis_dengan_query_langsung(): void
    {
        $product = $this->makeProduct(['name' => 'Kopi Susu']);
        $this->makeProductUnit($product, 'Dus', 12, 100000);

        $pertama = $this->catalog()->forCart();   // mengisi cache
        $kedua = $this->catalog()->forCart();     // membaca dari cache

        $this->assertSame(json_encode($this->fresh()), json_encode($pertama));
        $this->assertSame(json_encode($this->fresh()), json_encode($kedua));
    }

    /**
     * Regresi: `additional_units` sempat berupa Eloquent Collection di dalam array.
     * Sebagai Collection nilai itu tidak selamat melewati serialisasi cache dan dibaca
     * kembali sebagai __PHP_Incomplete_Class, membuat satuan besar hilang dari keranjang.
     */
    public function test_satuan_tambahan_selamat_melewati_cache(): void
    {
        $product = $this->makeProduct(['name' => 'Air Mineral']);
        $this->makeProductUnit($product, 'Dus', 24, 48000);

        $this->catalog()->forCart();
        $dariCache = $this->catalog()->forCart();

        $baris = collect($dariCache)->firstWhere('id', $product->id);

        $this->assertIsArray($baris['additional_units'], 'additional_units harus array biasa, bukan objek.');
        $this->assertCount(1, $baris['additional_units']);
        $this->assertSame('Dus', $baris['additional_units'][0]['unit_name']);
        $this->assertSame(24.0, $baris['additional_units'][0]['conversion']);
    }

    public function test_cache_ikut_berubah_saat_harga_produk_diubah(): void
    {
        $product = $this->makeProduct(['name' => 'Teh Botol', 'sell_price' => 5000]);
        $this->catalog()->forCart();

        $product->update(['sell_price' => 7500]);

        $baris = collect($this->catalog()->forCart())->firstWhere('id', $product->id);
        $this->assertSame(7500.0, $baris['sell_price'], 'Perubahan harga harus langsung terlihat kasir.');
    }

    public function test_cache_ikut_berubah_saat_produk_baru_ditambahkan(): void
    {
        $this->makeProduct(['name' => 'Produk Pertama']);
        $this->assertCount(1, $this->catalog()->forCart());

        $this->makeProduct(['name' => 'Produk Kedua']);
        $this->assertCount(2, $this->catalog()->forCart());
    }

    public function test_cache_ikut_berubah_saat_produk_dinonaktifkan(): void
    {
        $product = $this->makeProduct(['name' => 'Produk Ditarik']);
        $this->assertCount(1, $this->catalog()->forCart());

        $product->update(['is_active' => false]);
        $this->assertCount(0, $this->catalog()->forCart(), 'Produk nonaktif tidak boleh muncul di kasir.');
    }

    public function test_cache_ikut_berubah_saat_satuan_tambahan_diubah(): void
    {
        $product = $this->makeProduct(['name' => 'Rokok']);
        $unit = $this->makeProductUnit($product, 'Slop', 10, 200000);
        $this->catalog()->forCart();

        $unit->update(['price' => 220000]);

        $baris = collect($this->catalog()->forCart())->firstWhere('id', $product->id);
        $this->assertSame(220000.0, $baris['additional_units'][0]['price']);
    }

    public function test_halaman_kasir_menampilkan_produk_terbaru_setelah_perubahan(): void
    {
        $product = $this->makeProduct(['name' => 'Produk Awal']);

        $this->actingAs($this->admin)->get('/kasir')->assertOk()->assertSee('Produk Awal');

        $product->update(['name' => 'Produk Berganti Nama']);

        $this->actingAs($this->admin)->get('/kasir')
            ->assertOk()
            ->assertSee('Produk Berganti Nama')
            ->assertDontSee('Produk Awal');
    }

    /** Filter manual lewat URL tetap dilayani query langsung, bukan katalog penuh. */
    public function test_filter_lewat_url_tetap_berfungsi(): void
    {
        $this->makeProduct(['name' => 'Kopi Hitam']);
        $this->makeProduct(['name' => 'Teh Manis']);

        $html = $this->actingAs($this->admin)->get('/kasir?q=Kopi')->assertOk()->getContent();

        $this->assertStringContainsString('Kopi Hitam', $html);
        $this->assertStringNotContainsString('Teh Manis', $html);
    }
}
