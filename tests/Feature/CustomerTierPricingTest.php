<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\Unit;
use Tests\JposTestCase;

/**
 * Uji fitur Kategori Pelanggan (UMUM, Reseller, Grosir) dan Harga Bertingkat (Tier Pricing).
 */
class CustomerTierPricingTest extends JposTestCase
{
    public function test_kategori_pelanggan_validasi_dan_penyimpanan(): void
    {
        // 1. Simpan pelanggan kategori UMUM
        $this->actingAs($this->admin)->post('/master/pelanggan', [
            'name' => 'Budi Santoso',
            'phone' => '08123456781',
            'customer_type' => 'UMUM',
        ])->assertRedirect();

        $cUmum = Customer::where('name', 'Budi Santoso')->firstOrFail();
        $this->assertEquals('UMUM', $cUmum->customer_type);
        $this->assertTrue($cUmum->isUmum());
        $this->assertFalse($cUmum->isReseller());
        $this->assertFalse($cUmum->isGrosir());

        // 2. Simpan pelanggan kategori Reseller
        $this->actingAs($this->admin)->post('/master/pelanggan', [
            'name' => 'Toko Mitra Berkah',
            'phone' => '08123456782',
            'customer_type' => 'Reseller',
        ])->assertRedirect();

        $cReseller = Customer::where('name', 'Toko Mitra Berkah')->firstOrFail();
        $this->assertEquals('Reseller', $cReseller->customer_type);
        $this->assertTrue($cReseller->isReseller());

        // 3. Simpan pelanggan kategori Grosir
        $this->actingAs($this->admin)->post('/master/pelanggan', [
            'name' => 'Grosir Maju Jaya',
            'phone' => '08123456783',
            'customer_type' => 'Grosir',
        ])->assertRedirect();

        $cGrosir = Customer::where('name', 'Grosir Maju Jaya')->firstOrFail();
        $this->assertEquals('Grosir', $cGrosir->customer_type);
        $this->assertTrue($cGrosir->isGrosir());

        // 4. Validasi nilai tidak valid ditolak
        $this->actingAs($this->admin)->post('/master/pelanggan', [
            'name' => 'Kategori Invalid',
            'customer_type' => 'VIP_SUPER',
        ])->assertSessionHasErrors(['customer_type']);
    }

    public function test_produk_dan_satuan_tier_pricing_tersimpan(): void
    {
        $unitDus = Unit::firstOrCreate(['name' => 'DUS']);

        // Simpan produk beserta tier pricing dan satuan tambahan (format ribuan string dari form)
        $response = $this->actingAs($this->admin)->post('/master/produk', [
            'name' => 'Minyak Goreng 1L',
            'type' => 'barang',
            'unit' => 'Pcs',
            'cost_price' => '12.000',
            'sell_price' => '15.000',
            'reseller_price' => '14.000',
            'grosir_price' => '13.500',
            'stock' => 100,
            'is_active' => 1,
            'multi_unit_enabled' => 1,
            'units' => [
                [
                    'unit_id' => $unitDus->id,
                    'conversion' => 12,
                    'ratio_to_previous' => 12,
                    'price' => '170.000',
                    'reseller_price' => '165.000',
                    'grosir_price' => '160.000',
                    'cost_price' => '144.000',
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $produk = Product::where('name', 'Minyak Goreng 1L')->firstOrFail();
        $this->assertEquals(15000, (float) $produk->sell_price);
        $this->assertEquals(14000, (float) $produk->reseller_price);
        $this->assertEquals(13500, (float) $produk->grosir_price);

        $pu = $produk->units()->first();
        $this->assertNotNull($pu);
        $this->assertEquals(170000, (float) $pu->price);
        $this->assertEquals(165000, (float) $pu->reseller_price);
        $this->assertEquals(160000, (float) $pu->grosir_price);
    }

    public function test_kasir_menerapkan_harga_tier_sesuai_kategori_pelanggan(): void
    {
        $produk = $this->makeProduct([
            'name' => 'Kopi Sachet',
            'cost_price' => 1000,
            'sell_price' => 2000,
            'reseller_price' => 1800,
            'grosir_price' => 1500,
            'stock' => 100,
        ]);

        $cUmum = Customer::create(['name' => 'Pelanggan Biasa', 'customer_type' => 'UMUM']);
        $cReseller = Customer::create(['name' => 'Mitra Reseller', 'customer_type' => 'Reseller']);
        $cGrosir = Customer::create(['name' => 'Agen Grosir', 'customer_type' => 'Grosir']);

        // 1. Transaksi untuk Pelanggan UMUM (Mendapatkan harga jual biasa 2.000)
        $resUmum = $this->actingAs($this->admin)->postJson('/kasir', [
            'customer_id' => $cUmum->id,
            'items' => [['product_id' => $produk->id, 'qty' => 5, 'unit_type' => 'base']],
            'paid_amount' => 10000,
            'payment_method' => 'cash',
        ]);
        $resUmum->assertOk();
        $saleUmum = Sale::latest('id')->first();
        $this->assertEquals(10000, (float) $saleUmum->total);
        $itemUmum = $saleUmum->items->first();
        $this->assertEquals(2000, (float) $itemUmum->price);
        $this->assertEquals(10000, (float) $itemUmum->subtotal);
        $this->assertEquals(1000, (float) $itemUmum->cost_price_snapshot); // H6 terjaga

        // 2. Transaksi untuk Pelanggan Reseller (Mendapatkan harga reseller 1.800)
        $resReseller = $this->actingAs($this->admin)->postJson('/kasir', [
            'customer_id' => $cReseller->id,
            'items' => [['product_id' => $produk->id, 'qty' => 5, 'unit_type' => 'base']],
            'paid_amount' => 9000,
            'payment_method' => 'cash',
        ]);
        $resReseller->assertOk();
        $saleReseller = Sale::latest('id')->first();
        $this->assertEquals(9000, (float) $saleReseller->total);
        $itemReseller = $saleReseller->items->first();
        $this->assertEquals(1800, (float) $itemReseller->price);
        $this->assertEquals(9000, (float) $itemReseller->subtotal);

        // 3. Transaksi untuk Pelanggan Grosir (Mendapatkan harga grosir 1.500)
        $resGrosir = $this->actingAs($this->admin)->postJson('/kasir', [
            'customer_id' => $cGrosir->id,
            'items' => [['product_id' => $produk->id, 'qty' => 5, 'unit_type' => 'base']],
            'paid_amount' => 7500,
            'payment_method' => 'cash',
        ]);
        $resGrosir->assertOk();
        $saleGrosir = Sale::latest('id')->first();
        $this->assertEquals(7500, (float) $saleGrosir->total);
        $itemGrosir = $saleGrosir->items->first();
        $this->assertEquals(1500, (float) $itemGrosir->price);
        $this->assertEquals(7500, (float) $itemGrosir->subtotal);
    }

    public function test_fallback_ke_harga_normal_jika_tier_kosong(): void
    {
        // Produk tanpa tier pricing (hanya sell_price)
        $produk = $this->makeProduct([
            'name' => 'Barang Reguler',
            'cost_price' => 5000,
            'sell_price' => 8000,
            'reseller_price' => null,
            'grosir_price' => null,
            'stock' => 50,
        ]);

        $cReseller = Customer::create(['name' => 'Mitra B', 'customer_type' => 'Reseller']);

        $res = $this->actingAs($this->admin)->postJson('/kasir', [
            'customer_id' => $cReseller->id,
            'items' => [['product_id' => $produk->id, 'qty' => 2, 'unit_type' => 'base']],
            'paid_amount' => 16000,
            'payment_method' => 'cash',
        ]);
        $res->assertOk();

        $sale = Sale::latest('id')->first();
        $this->assertEquals(16000, (float) $sale->total);
        $item = $sale->items->first();
        $this->assertEquals(8000, (float) $item->price, 'Harus fallback ke sell_price normal bila tier price null');
    }

    public function test_kasir_menerapkan_harga_tier_pada_multi_satuan(): void
    {
        $unitPak = Unit::firstOrCreate(['name' => 'PAK']);

        $produk = $this->makeProduct([
            'name' => 'Snack Keripik',
            'cost_price' => 2000,
            'sell_price' => 3000,
            'reseller_price' => 2800,
            'grosir_price' => 2500,
            'stock' => 200,
        ]);

        $pu = ProductUnit::create([
            'product_id' => $produk->id,
            'unit_id' => $unitPak->id,
            'conversion' => 10,
            'price' => 28000,
            'reseller_price' => 25000,
            'grosir_price' => 23000,
            'cost_price' => 20000,
        ]);

        $cGrosir = Customer::create(['name' => 'Juragan C', 'customer_type' => 'Grosir']);

        // Beli 2 PAK dengan kategori Grosir -> dapat harga grosir satuan (23.000 / pak)
        $res = $this->actingAs($this->admin)->postJson('/kasir', [
            'customer_id' => $cGrosir->id,
            'items' => [['product_id' => $produk->id, 'qty' => 2, 'unit_type' => 'unit_' . $pu->id]],
            'paid_amount' => 46000,
            'payment_method' => 'cash',
        ]);
        $res->assertOk();

        $sale = Sale::latest('id')->first();
        $this->assertEquals(46000, (float) $sale->total);
        $item = $sale->items->first();
        $this->assertEquals(23000, (float) $item->price);
        $this->assertEquals(46000, (float) $item->subtotal);
        $this->assertEquals(10, (float) $item->unit_conversion);
    }

    public function test_edit_waiting_order_tetap_menjaga_tier_pricing_pelanggan(): void
    {
        $produk = $this->makeProduct([
            'name' => 'Gula Pasir 1Kg',
            'cost_price' => 12000,
            'sell_price' => 16000,
            'reseller_price' => 14500,
            'grosir_price' => 13500,
            'stock' => 100,
        ]);

        $cReseller = Customer::create(['name' => 'Warung Reseller', 'customer_type' => 'Reseller']);

        // 1. Buat pesanan waiting list dengan DP
        $this->actingAs($this->admin)->postJson('/kasir', [
            'customer_id' => $cReseller->id,
            'items' => [['product_id' => $produk->id, 'qty' => 2, 'unit_type' => 'base']],
            'paid_amount' => 10000,
            'payment_method' => 'cash',
            'is_waiting_list' => true,
        ])->assertOk();

        $sale = Sale::latest('id')->first();
        $this->assertEquals('waiting', $sale->order_status);
        $this->assertEquals(29000, (float) $sale->total); // 2 x 14.500

        // 1b. Periksa view edit waiting list menerima customerType
        $viewRes = $this->actingAs($this->admin)->get("/kasir/waiting-list/{$sale->id}/edit");
        $viewRes->assertOk();
        $viewRes->assertViewHas('customerType', 'Reseller');

        // 2. Edit pesanan waiting list: ubah qty menjadi 4
        $res = $this->actingAs($this->admin)->putJson("/kasir/waiting-list/{$sale->id}", [
            'items' => [['product_id' => $produk->id, 'qty' => 4, 'unit_type' => 'base']],
            'discount' => 0,
        ]);
        $res->assertOk();

        $sale->refresh();
        $this->assertEquals(58000, (float) $sale->total); // 4 x 14.500 (harga reseller tetap aktif)
        $item = $sale->items->first();
        $this->assertEquals(14500, (float) $item->price);
        $this->assertEquals(58000, (float) $item->subtotal);
    }

    public function test_retur_tukar_tambah_menerapkan_harga_tier_pelanggan(): void
    {
        $produkLama = $this->makeProduct([
            'name' => 'Barang Lama',
            'cost_price' => 5000,
            'sell_price' => 10000,
            'reseller_price' => 9000,
            'stock' => 50,
        ]);

        $produkTukar = $this->makeProduct([
            'name' => 'Barang Pengganti',
            'cost_price' => 8000,
            'sell_price' => 15000,
            'reseller_price' => 13000,
            'stock' => 50,
        ]);

        $cReseller = Customer::create(['name' => 'Reseller Sejahtera', 'customer_type' => 'Reseller']);

        // 1. Transaksi awal selesai untuk Reseller
        $this->actingAs($this->admin)->postJson('/kasir', [
            'customer_id' => $cReseller->id,
            'items' => [['product_id' => $produkLama->id, 'qty' => 1, 'unit_type' => 'base']],
            'paid_amount' => 9000,
            'payment_method' => 'cash',
        ])->assertOk();

        $sale = Sale::latest('id')->first();
        $saleItem = $sale->items->first();

        // 2. findSale harus menyertakan customer_type di JSON
        $findRes = $this->actingAs($this->admin)->getJson('/retur/find?invoice_no=' . urlencode($sale->invoice_no));
        $findRes->assertOk();
        $this->assertEquals('Reseller', $findRes->json('sale.customer_type'));

        // 3. Simpan retur dengan tukar tambah barang pengganti
        $returRes = $this->actingAs($this->admin)->postJson('/retur', [
            'sale_id' => $sale->id,
            'reason' => 'Tukar tipe barang',
            'items' => [
                ['sale_item_id' => $saleItem->id, 'qty' => 1],
            ],
            'add_items' => [
                ['product_id' => $produkTukar->id, 'qty' => 1, 'unit_type' => 'base'],
            ],
            'additional_payment' => 13000, // 1 x 13.000 (reseller price untuk item tambahan)
            'additional_payment_method' => 'tunai',
        ]);
        $returRes->assertOk();

        // 4. Verifikasi item baru di sale_items mendapat harga reseller (13.000) bukan harga umum (15.000)
        $addedItem = $sale->items()->where('product_id', $produkTukar->id)->first();
        $this->assertNotNull($addedItem);
        $this->assertEquals(13000, (float) $addedItem->price, 'Barang pengganti retur harus menggunakan harga tier pelanggan');
    }
}
