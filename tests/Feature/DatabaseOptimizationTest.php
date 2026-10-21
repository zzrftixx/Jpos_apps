<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\JposTestCase;

class DatabaseOptimizationTest extends JposTestCase
{
    public function test_optimasi_database_memerlukan_login(): void
    {
        $response = $this->post(route('pengaturan.database.optimize'));
        $response->assertRedirect(route('login'));
    }

    public function test_optimasi_database_berhasil_membersihkan_cache_dan_menjaga_data_utuh(): void
    {
        $product = Product::create([
            'name' => 'Produk Uji Optimasi',
            'sku' => 'SKU-OPT-1',
            'barcode' => '8999991234567',
            'cost_price' => 5000,
            'sell_price' => 10000,
            'stock' => 50,
            'unit' => 'Pcs',
            'is_active' => true,
        ]);

        // Simpan data di cache
        Cache::put('katalog_lama', ['dummy' => 'data'], 3600);
        $this->assertTrue(Cache::has('katalog_lama'));

        $response = $this->actingAs($this->admin)->post(route('pengaturan.database.optimize'));

        $response->assertSessionHas('success');
        $response->assertRedirect();

        // Cache dibersihkan
        $this->assertFalse(Cache::has('katalog_lama'));

        // Produk tetap 100% utuh
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Produk Uji Optimasi',
        ]);
    }

    public function test_tampilan_master_produk_memiliki_catatan_stok_minimum_dan_backdrop_statis(): void
    {
        $response = $this->actingAs($this->admin)->get(route('produk.index'));
        $response->assertOk();

        // 1. Memiliki pengingat bahwa stok minimum bukan untuk menambah stok
        $response->assertSee('Batas Pengingat Stok Menipis');
        $response->assertSee('Kolom ini <em>bukan</em> untuk menambah stok produk', false);

        // 2. Modal tidak memiliki @click.outside="showModal=false" agar tidak menutup sendiri
        $response->assertDontSee('@click.outside="showModal=false"', false);

        // 3. Search input memiliki ID untuk scanner
        $response->assertSee('id="produk-search-input"', false);
    }
}
