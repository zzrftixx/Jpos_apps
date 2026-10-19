<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\Akuntansi;
use Tests\JposTestCase;

class PerbaikiHppAbnormalTest extends JposTestCase
{
    public function test_perbaiki_hpp_menyesuaikan_produk_yang_cocok_nama_dan_id(): void
    {
        // 1. Buat produk ID 1500 dengan nama yang cocok (Beras Lombok) dan modal abnormal (200.000)
        $product = Product::forceCreate([
            'id' => 1500,
            'name' => 'Beras Lombok Super 25kg',
            'sku' => 'BRS-1500',
            'barcode' => '8991500',
            'cost_price' => 200000,
            'sell_price' => 250000,
            'stock' => 10,
            'unit' => 'Kg',
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'invoice_no' => 'INV-TEST-HPP-1',
            'user_id' => $this->admin->id,
            'subtotal' => 250000,
            'discount' => 0,
            'tax_amount' => 0,
            'total' => 250000,
            'paid_amount' => 250000,
            'change_amount' => 0,
            'payment_method' => 'cash',
            'status' => 'completed',
            'order_status' => 'completed',
        ]);

        $item = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 250000,
            'cost_price_snapshot' => 200000, // abnormal
            'qty' => 1,
            'unit_label' => 'Kg',
            'unit_conversion' => 1,
            'subtotal' => 250000,
        ]);

        $hasil = Akuntansi::perbaikiHppAbnormal();

        $this->assertGreaterThanOrEqual(1, $hasil['produk_count']);
        $this->assertGreaterThanOrEqual(1, $hasil['items_count']);

        $product->refresh();
        $this->assertEquals(14500.0, (float) $product->cost_price);

        $item->refresh();
        $this->assertEquals(14500.0, (float) $item->cost_price_snapshot);
    }

    public function test_perbaiki_hpp_menolak_update_jika_nama_produk_berbeda_mencegah_salah_timpa_toko_lain(): void
    {
        // Buat produk ID 1519 tetapi bukan 'teh gelas' melainkan produk lain di toko berbeda
        $product = Product::forceCreate([
            'id' => 1519,
            'name' => 'Susu Formula Bayi Premium',
            'sku' => 'SSU-1519',
            'barcode' => '8991519',
            'cost_price' => 175000,
            'sell_price' => 200000,
            'stock' => 5,
            'unit' => 'Klg',
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'invoice_no' => 'INV-TEST-HPP-2',
            'user_id' => $this->admin->id,
            'subtotal' => 200000,
            'discount' => 0,
            'tax_amount' => 0,
            'total' => 200000,
            'paid_amount' => 200000,
            'change_amount' => 0,
            'payment_method' => 'cash',
            'status' => 'completed',
            'order_status' => 'completed',
        ]);

        $item = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 200000,
            'cost_price_snapshot' => 175000,
            'qty' => 1,
            'unit_label' => 'Klg',
            'unit_conversion' => 1,
            'subtotal' => 200000,
        ]);

        Akuntansi::perbaikiHppAbnormal();

        // Biaya modal TIDAK boleh ditimpa menjadi modal teh gelas (760.42)!
        $product->refresh();
        $this->assertEquals(175000.0, (float) $product->cost_price);

        $item->refresh();
        $this->assertEquals(175000.0, (float) $item->cost_price_snapshot);
    }
}
