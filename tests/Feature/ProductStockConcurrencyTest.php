<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use Tests\JposTestCase;

class ProductStockConcurrencyTest extends JposTestCase
{
    public function test_edit_produk_tanpa_ubah_stok_tidak_menimpa_penjualan_kasir(): void
    {
        $product = $this->makeProduct([
            'stock' => 50,
            'cost_price' => 5000,
            'sell_price' => 10000,
        ]);

        // Simulasikan form dibuka saat stok 50 (initial_stock = 50).
        // Lalu terjadi transaksi kasir yang memotong stok menjadi 45 sebelum form disimpan.
        $product->update(['stock' => 45]);
        StockMovement::create([
            'product_id' => $product->id,
            'type' => 'sale',
            'qty' => -5,
            'stock_after' => 45,
            'note' => 'Penjualan kasir saat form admin terbuka',
            'user_id' => $this->kasir->id,
        ]);

        // Admin menyimpan form (hanya ubah nama produk, nilai stock tetap 50 seperti saat form dibuka)
        $response = $this->actingAs($this->admin)->put(route('produk.update', $product), [
            'name' => 'Nama Produk Baru',
            'type' => 'barang',
            'unit' => 'Pcs',
            'cost_price' => 5000,
            'sell_price' => 10000,
            'stock' => 50,
            'initial_stock' => 50,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $product->refresh();

        // Stok HARUS tetap 45 (tidak boleh tertimpa kembali ke 50)
        $this->assertEquals(45, $product->stock, 'Penjualan kasir tertimpa oleh form admin (stale read)');

        // Tidak boleh ada StockMovement adjustment palsu yang dibuat
        $this->assertDatabaseMissing('stock_movements', [
            'product_id' => $product->id,
            'type' => 'adjustment',
        ]);
    }

    public function test_edit_produk_dengan_ubah_stok_tetap_memperbarui_dan_mencatat_adjustment(): void
    {
        $product = $this->makeProduct([
            'stock' => 50,
            'cost_price' => 5000,
            'sell_price' => 10000,
        ]);

        // Admin sengaja mengubah stok dari 50 ke 60 (opname fisik)
        $response = $this->actingAs($this->admin)->put(route('produk.update', $product), [
            'name' => $product->name,
            'type' => 'barang',
            'unit' => 'Pcs',
            'cost_price' => 5000,
            'sell_price' => 10000,
            'stock' => 60,
            'initial_stock' => 50,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $product->refresh();

        $this->assertEquals(60, $product->stock);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'adjustment',
            'qty' => 10,
            'stock_after' => 60,
        ]);
    }
}
