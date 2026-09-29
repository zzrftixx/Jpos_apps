<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Facades\Cache;
use Tests\JposTestCase;

class KategoriTerlarisKasirTest extends JposTestCase
{
    public function test_kategori_terlaris_tampil_paling_depan_di_modul_kasir(): void
    {
        $p1 = $this->makeProduct(['name' => 'Kopi Robusta Super', 'stock' => 50, 'sell_price' => 15000]);
        $p2 = $this->makeProduct(['name' => 'Teh Celup Melati', 'stock' => 50, 'sell_price' => 8000]);

        // Simulasikan transaksi completed untuk p1
        $sale = Sale::create([
            'invoice_no' => 'INV-TEST-001',
            'user_id' => $this->kasir->id,
            'subtotal' => 45000,
            'total' => 45000,
            'paid_amount' => 45000,
            'change_amount' => 0,
            'payment_method' => 'tunai',
            'status' => 'completed',
            'order_status' => 'completed',
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $p1->id,
            'product_name' => $p1->name,
            'price' => 15000,
            'cost_price_snapshot' => 10000,
            'qty' => 3,
            'unit_label' => 'pcs',
            'unit_conversion' => 1,
            'subtotal' => 45000,
        ]);

        Cache::forget('jpos:top_product_ids');

        $response = $this->actingAs($this->kasir)->get(route('kasir.index'));
        $response->assertOk();

        $html = $response->getContent();

        // 1. Ambil bar navigasi kategori pada body HTML (bukan dari blok style CSS)
        $catBar = strstr($html, '<div class="kasir-cat-bar">');
        $this->assertNotFalse($catBar, 'Bar kategori tidak ditemukan.');

        // 2. Tombol Kategori Terlaris hadir
        $this->assertStringContainsString('kat-theme-terlaris', $catBar);
        $this->assertStringContainsString('Terlaris', $catBar);

        // 3. Tombol Terlaris diposisikan PALING DEPAN (sebelum "Semua Kategori")
        $posTerlaris = strpos($catBar, 'kat-theme-terlaris');
        $posSemua = strpos($catBar, 'kat-btn-all');
        $this->assertNotFalse($posTerlaris, 'Tombol Terlaris tidak ditemukan dalam bar.');
        $this->assertNotFalse($posSemua, 'Tombol Semua Kategori tidak ditemukan dalam bar.');
        $this->assertLessThan($posSemua, $posTerlaris, 'Tombol Terlaris harus berada di depan tombol Semua Kategori.');

        // 4. ID produk p1 masuk ke dalam topProductIds
        $this->assertStringContainsString((string) $p1->id, $html);

        // 5. Badge Terlaris hadir di kartu produk
        $this->assertStringContainsString('topProductIds.includes(p.id)', $html);

        // 6. Saat pertama dibuka dan ada produk terlaris, kategori terlaris aktif secara default
        $this->assertStringContainsString('categoryId: "terlaris"', $html);
    }

    public function test_filter_kategori_terlaris_lewat_url(): void
    {
        $p1 = $this->makeProduct(['name' => 'Kopi Robusta Super', 'stock' => 50, 'sell_price' => 15000]);
        $p2 = $this->makeProduct(['name' => 'Barang Kurang Laris', 'stock' => 50, 'sell_price' => 8000]);

        $sale = Sale::create([
            'invoice_no' => 'INV-TEST-002',
            'user_id' => $this->kasir->id,
            'subtotal' => 30000,
            'total' => 30000,
            'paid_amount' => 30000,
            'change_amount' => 0,
            'payment_method' => 'tunai',
            'status' => 'completed',
            'order_status' => 'completed',
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $p1->id,
            'product_name' => $p1->name,
            'price' => 15000,
            'cost_price_snapshot' => 10000,
            'qty' => 2,
            'unit_label' => 'pcs',
            'unit_conversion' => 1,
            'subtotal' => 30000,
        ]);

        Cache::forget('jpos:top_product_ids');

        $response = $this->actingAs($this->kasir)->get(route('kasir.index', ['category_id' => 'terlaris']));
        $response->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('Kopi Robusta Super', $html);
        $this->assertStringNotContainsString('Barang Kurang Laris', $html);
    }
}
