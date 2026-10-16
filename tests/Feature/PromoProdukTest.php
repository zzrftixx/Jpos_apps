<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Models\Sale;
use Tests\JposTestCase;

/**
 * Uji fitur Menu Promo Produk & Cetak Barcode Promo.
 */
class PromoProdukTest extends JposTestCase
{
    public function test_promo_crud_berjalan_dengan_baik(): void
    {
        $product1 = Product::create([
            'name' => 'Whiskas Ocean Fish 1.2kg',
            'sku' => 'WHK-OF-12',
            'barcode' => '8999999001',
            'sell_price' => 75000,
            'cost_price' => 60000,
            'stock' => 30,
            'unit' => 'pack',
            'is_active' => true,
        ]);

        $product2 = Product::create([
            'name' => 'Royal Canin Mother & Babycat 400g',
            'sku' => 'RC-MB-400',
            'barcode' => '8999999002',
            'sell_price' => 85000,
            'cost_price' => 70000,
            'stock' => 20,
            'unit' => 'pack',
            'is_active' => true,
        ]);

        // 1. Simpan promo baru
        $storeRes = $this->actingAs($this->admin)->post('/promo', [
            'name' => 'Promo Spesial Weekend Makanan Kucing',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'is_active' => '1',
            'description' => 'Diskon khusus pakan kucing akhir pekan',
            'items' => [
                [
                    'product_id' => $product1->id,
                    'promo_price' => 65000, // diskon dari 75.000 -> 65.000
                    'promo_barcode' => '', // otomatis
                ],
                [
                    'product_id' => $product2->id,
                    'promo_price' => 78000, // diskon dari 85.000 -> 78.000
                    'promo_barcode' => 'PRM-RC-SPECIAL',
                ],
            ],
        ]);

        $storeRes->assertRedirect(route('promo.index'));
        $storeRes->assertSessionHas('success');

        $promo = Promotion::where('name', 'Promo Spesial Weekend Makanan Kucing')->firstOrFail();
        $this->assertEquals(2, $promo->items()->count());

        $item1 = $promo->items()->where('product_id', $product1->id)->first();
        $this->assertEquals(65000, $item1->promo_price);
        $this->assertNotEmpty($item1->promo_barcode);
        $this->assertStringStartsWith('PRM-', $item1->promo_barcode);

        $item2 = $promo->items()->where('product_id', $product2->id)->first();
        $this->assertEquals(78000, $item2->promo_price);
        $this->assertEquals('PRM-RC-SPECIAL', $item2->promo_barcode);

        // 2. Toggle status promo
        $toggleRes = $this->actingAs($this->admin)->post("/promo/{$promo->id}/toggle");
        $toggleRes->assertRedirect();
        $promo->refresh();
        $this->assertFalse($promo->is_active);

        $this->actingAs($this->admin)->post("/promo/{$promo->id}/toggle");
        $promo->refresh();
        $this->assertTrue($promo->is_active);

        // 3. Update promo
        $updateRes = $this->actingAs($this->admin)->put("/promo/{$promo->id}", [
            'name' => 'Promo Spesial Weekend Makanan Kucing (Update)',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'is_active' => '1',
            'description' => 'Update deskripsi promo',
            'items' => [
                [
                    'product_id' => $product1->id,
                    'promo_price' => 63000,
                    'promo_barcode' => 'PRM-WHK-CUSTOM',
                ],
            ],
        ]);

        $updateRes->assertRedirect(route('promo.index'));
        $promo->refresh();
        $this->assertEquals('Promo Spesial Weekend Makanan Kucing (Update)', $promo->name);
        $this->assertEquals(1, $promo->items()->count());
        $this->assertEquals(63000, $promo->items()->first()->promo_price);

        // 4. Hapus promo
        $deleteRes = $this->actingAs($this->admin)->delete("/promo/{$promo->id}");
        $deleteRes->assertRedirect(route('promo.index'));
        $this->assertDatabaseMissing('promotions', ['id' => $promo->id]);
        $this->assertDatabaseMissing('promotion_items', ['promotion_id' => $promo->id]);
    }

    public function test_scan_barcode_promo_mengembalikan_harga_promo_di_kasir(): void
    {
        $product = Product::create([
            'name' => 'Shampoo Kucing Flea & Tick 250ml',
            'sku' => 'SHP-FL-250',
            'barcode' => '8999999003',
            'sell_price' => 45000,
            'cost_price' => 30000,
            'stock' => 15,
            'unit' => 'botol',
            'is_active' => true,
        ]);

        $promo = Promotion::create([
            'name' => 'Promo Mandi Sehat Kucing',
            'is_active' => true,
        ]);

        $promoItem = PromotionItem::create([
            'promotion_id' => $promo->id,
            'product_id' => $product->id,
            'promo_price' => 38000,
            'promo_barcode' => 'PRM-SHP-FLEA',
        ]);

        // Pindai dengan barcode promo rak 'PRM-SHP-FLEA'
        $scanRes = $this->actingAs($this->kasir)->getJson("/kasir/scan?barcode=PRM-SHP-FLEA");

        $scanRes->assertStatus(200);
        $scanRes->assertJson([
            'found' => true,
            'promo_active' => true,
            'promo_price' => 38000,
            'promo_name' => 'Promo Mandi Sehat Kucing',
        ]);
        $this->assertEquals($product->id, $scanRes->json('product.id'));
    }

    public function test_checkout_produk_promo_menerapkan_harga_promo_otomatis(): void
    {
        $product = Product::create([
            'name' => 'Kalung Kucing Lonceng Motif',
            'sku' => 'KLG-KCG-01',
            'barcode' => '8999999004',
            'sell_price' => 20000,
            'cost_price' => 12000,
            'stock' => 25,
            'unit' => 'pcs',
            'is_active' => true,
        ]);

        $promo = Promotion::create([
            'name' => 'Promo Aksesoris Gemoy',
            'is_active' => true,
        ]);

        PromotionItem::create([
            'promotion_id' => $promo->id,
            'product_id' => $product->id,
            'promo_price' => 15000, // Diskon jadi 15.000
            'promo_barcode' => 'PRM-KLG-01',
        ]);

        // Beli 2 pcs. Harga normal 20.000 x 2 = 40.000, karena promo aktif harga jadi 15.000 x 2 = 30.000
        $res = $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [
                ['product_id' => $product->id, 'qty' => 2, 'unit_type' => 'base'],
            ],
            'paid_amount' => 30000,
            'payment_method' => 'tunai',
        ]);

        $res->assertStatus(200);

        $sale = Sale::with('items')->latest()->first();
        $this->assertEquals(30000, $sale->subtotal);
        $this->assertEquals(30000, $sale->total);

        $saleItem = $sale->items->first();
        $this->assertEquals(15000, $saleItem->price);
        $this->assertEquals(30000, $saleItem->subtotal);

        // Stok berkurang 2 dari 25 -> 23
        $product->refresh();
        $this->assertEquals(23, $product->stock);
    }

    public function test_cetak_barcode_label_rak_promo_menampilkan_view(): void
    {
        $product = Product::create([
            'name' => 'Snack Kucing Me-O Creamy Treat 4x15g',
            'sku' => 'MEO-CRM-4X',
            'barcode' => '8999999005',
            'sell_price' => 28000,
            'cost_price' => 20000,
            'stock' => 40,
            'unit' => 'pack',
            'is_active' => true,
        ]);

        $promo = Promotion::create([
            'name' => 'Promo Treat Spesial',
            'is_active' => true,
        ]);

        PromotionItem::create([
            'promotion_id' => $promo->id,
            'product_id' => $product->id,
            'promo_price' => 22000,
            'promo_barcode' => 'PRM-MEO-001',
        ]);

        $res = $this->actingAs($this->admin)->get("/promo/{$promo->id}/cetak-barcode?qty=2");
        $res->assertStatus(200);
        $res->assertSee('Cetak Barcode Label Rak Promo');
        $res->assertSee('PROMO SPESIAL');
        $res->assertSee('PRM-MEO-001');
        $res->assertSee('Rp 22.000');
        $res->assertSee('Hemat Rp 6.000');
    }

    public function test_promo_menerima_harga_format_ribuan_dan_menolak_produk_duplikat(): void
    {
        $product = Product::create([
            'name' => 'bolt Dry Food 1kg',
            'sku' => 'BLT-1KG',
            'barcode' => '8999999099',
            'sell_price' => 25000,
            'cost_price' => 18000,
            'stock' => 50,
            'unit' => 'pack',
            'is_active' => true,
        ]);

        // 1. Duplikat produk dalam 1 promo harus ditolak validasi (bukan 500 QueryException)
        $dupRes = $this->actingAs($this->admin)->post('/promo', [
            'name' => 'Promo Duplikat',
            'is_active' => '1',
            'items' => [
                ['product_id' => $product->id, 'promo_price' => '20.000', 'promo_barcode' => ''],
                ['product_id' => $product->id, 'promo_price' => '19.000', 'promo_barcode' => ''],
            ],
        ]);
        $dupRes->assertSessionHasErrors(['items.1.product_id']);

        // 2. Harga promo berformat ribuan Indonesia ('21.500') harus tersimpan sebagai 21500
        $okRes = $this->actingAs($this->admin)->post('/promo', [
            'name' => 'Promo Format Ribuan',
            'is_active' => '1',
            'items' => [
                ['product_id' => $product->id, 'promo_price' => '21.500', 'promo_barcode' => ''],
            ],
        ]);
        $okRes->assertSessionHasNoErrors();

        $promo = Promotion::where('name', 'Promo Format Ribuan')->firstOrFail();
        $this->assertEquals(21500, (float) $promo->items()->first()->promo_price);
    }
}
