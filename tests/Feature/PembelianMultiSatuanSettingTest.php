<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Unit;
use Tests\JposTestCase;

class PembelianMultiSatuanSettingTest extends JposTestCase
{
    public function test_pembelian_dengan_multi_satuan_sub_baris_dapat_memperbarui_semua_satuan(): void
    {
        $supplier = Supplier::create(['name' => 'Distributor Kopi Nasional']);
        $renceng = Unit::firstOrCreate(['name' => 'Renceng']);
        $karton = Unit::firstOrCreate(['name' => 'Karton']);

        $produk = $this->makeProduct([
            'name' => 'Kopi ABC Susu',
            'stock' => 0,
            'cost_price' => 1200,
            'sell_price' => 1800,
            'multi_unit_enabled' => true,
        ]);

        $uRenceng = $produk->units()->create([
            'unit_id' => $renceng->id,
            'conversion' => 10,
            'cost_price' => 12000,
            'price' => 17000,
        ]);

        $uKarton = $produk->units()->create([
            'unit_id' => $karton->id,
            'conversion' => 120,
            'cost_price' => 140000,
            'price' => 195000,
        ]);

        // Skenario: Beli 2 Karton (@ 144.000). Satuan Pcs dan Renceng tidak dibeli (qty 0),
        // tapi harga jual & modal proporsionalnya diatur di baris multi-satuan.
        $postData = [
            'supplier_id' => $supplier->id,
            'supplier_invoice_no' => 'INV-ABC-99',
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $produk->id,
                    'qty' => 2,
                    'unit_type' => 'unit_' . $uKarton->id,
                    'price' => 144000,
                    'sell_price' => 200000,
                ],
            ],
            'other_unit_prices' => [
                [
                    'product_id' => $produk->id,
                    'unit_type' => 'base',
                    'sell_price' => 2000,
                    'cost_price' => 1200,
                ],
                [
                    'product_id' => $produk->id,
                    'unit_type' => 'unit_' . $uRenceng->id,
                    'sell_price' => 18500,
                    'cost_price' => 12000,
                ],
            ],
            'bayar' => 'tunai',
        ];

        $response = $this->actingAs($this->admin)->post('/pembelian', $postData);
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $produk->refresh();
        $uRenceng->refresh();
        $uKarton->refresh();

        // 1. Stok produk bertambah sesuai konversi 2 Karton x 120 = 240 Pcs
        $this->assertEquals(240, (float) $produk->stock);

        // 2. Harga jual satuan dasar (Pcs) diperbarui dari baris setting satuan lain
        $this->assertEquals(2000, (float) $produk->sell_price);

        // 3. Harga jual & modal satuan Renceng diperbarui dari baris setting satuan lain
        $this->assertEquals(18500, (float) $uRenceng->price);
        $this->assertEquals(12000, (float) $uRenceng->cost_price);

        // 4. Satuan utama yang dibeli (Karton) diperbarui
        $this->assertEquals(200000, (float) $uKarton->price);
        $this->assertEquals(144000, (float) $uKarton->cost_price);

        // 5. Total faktur hanya menghitung item yang dibeli (2 x 144.000 = 288.000)
        $purchase = Purchase::where('supplier_invoice_no', 'INV-ABC-99')->firstOrFail();
        $this->assertEquals(288000, (float) $purchase->total);
        $this->assertCount(1, $purchase->items);
    }

    public function test_pembelian_dengan_dua_satuan_sekaligus_pada_produk_multi_satuan(): void
    {
        $supplier = Supplier::create(['name' => 'Agen Sembako Makmur']);
        $dusUnit = Unit::firstOrCreate(['name' => 'Dus']);

        $produk = $this->makeProduct([
            'name' => 'Mie Instan Goreng',
            'stock' => 10,
            'cost_price' => 2500,
            'sell_price' => 3200,
            'multi_unit_enabled' => true,
        ]);

        $uDus = $produk->units()->create([
            'unit_id' => $dusUnit->id,
            'conversion' => 40,
            'cost_price' => 100000,
            'price' => 125000,
        ]);

        // Skenario: Beli 5 Dus (@ 102.000) DAN 10 Pcs eceran (@ 2.600) sekaligus
        $postData = [
            'supplier_id' => $supplier->id,
            'supplier_invoice_no' => 'INV-MIE-02',
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $produk->id,
                    'qty' => 5,
                    'unit_type' => 'unit_' . $uDus->id,
                    'price' => 102000,
                    'sell_price' => 130000,
                ],
                [
                    'product_id' => $produk->id,
                    'qty' => 10,
                    'unit_type' => 'base',
                    'price' => 2600,
                    'sell_price' => 3500,
                ],
            ],
            'bayar' => 'tunai',
        ];

        $response = $this->actingAs($this->admin)->post('/pembelian', $postData);
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $produk->refresh();
        $uDus->refresh();

        // 5 Dus x 40 = 200 + 10 Pcs = 210 Pcs masuk. Stok awal 10 -> Total 220 Pcs
        $this->assertEquals(220, (float) $produk->stock);

        // Harga jual diperbarui untuk kedua satuan
        $this->assertEquals(3500, (float) $produk->sell_price);
        $this->assertEquals(130000, (float) $uDus->price);

        // Total faktur = (5 x 102.000) + (10 x 2.600) = 510.000 + 26.000 = 536.000
        $purchase = Purchase::where('supplier_invoice_no', 'INV-MIE-02')->firstOrFail();
        $this->assertEquals(536000, (float) $purchase->total);
        $this->assertCount(2, $purchase->items);
    }
}
