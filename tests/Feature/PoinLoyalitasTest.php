<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use Tests\JposTestCase;

/**
 * Uji fitur Poin Loyalitas & Voucher Belanja Pelanggan.
 */
class PoinLoyalitasTest extends JposTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Aktifkan pengaturan poin loyalitas untuk pengujian
        Setting::set('poin_loyalitas', [
            'enabled' => true,
            'belanja_per_poin' => 10000,
            'target_poin' => 50,
            'nilai_voucher' => 20000,
        ]);
    }

    public function test_pengaturan_poin_loyalitas_dapat_disimpan(): void
    {
        $response = $this->actingAs($this->admin)->post('/pengaturan/poin-loyalitas', [
            'enabled' => '1',
            'belanja_per_poin' => '15000',
            'target_poin' => '100',
            'nilai_voucher' => '50000',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $settings = Setting::poinLoyalitas();
        $this->assertTrue($settings['enabled']);
        $this->assertEquals(15000, $settings['belanja_per_poin']);
        $this->assertEquals(100, $settings['target_poin']);
        $this->assertEquals(50000, $settings['nilai_voucher']);
    }

    public function test_transaksi_kasir_menghasilkan_poin_untuk_pelanggan(): void
    {
        $customer = Customer::create([
            'name' => 'Pelanggan Setia',
            'phone' => '081234567890',
            'customer_type' => 'UMUM',
            'points' => 10,
        ]);

        $product = Product::create([
            'name' => 'Dry Food Kucing 1kg',
            'sku' => 'DF-KCG-1K',
            'barcode' => '899123456701',
            'sell_price' => 55000,
            'cost_price' => 40000,
            'stock' => 20,
            'unit' => 'pcs',
            'is_active' => true,
        ]);

        // Belanja 2 pcs = Rp 110.000. Belanja per poin = 10.000 -> Poin didapat = floor(110.000 / 10.000) = 11 poin
        $res = $this->actingAs($this->kasir)->postJson('/kasir', [
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $product->id, 'qty' => 2, 'unit_type' => 'base'],
            ],
            'paid_amount' => 110000,
            'payment_method' => 'tunai',
        ]);

        $res->assertStatus(200);

        $customer->refresh();
        $this->assertEquals(21, $customer->points); // 10 awal + 11 didapat = 21

        $sale = Sale::latest()->first();
        $this->assertEquals(11, $sale->points_earned);
        $this->assertEquals(0, $sale->points_redeemed);
        $this->assertEquals(0, $sale->points_discount);
    }

    public function test_pelanggan_dapat_menukar_voucher_poin_saat_mencapai_target(): void
    {
        $customer = Customer::create([
            'name' => 'Pelanggan VIP',
            'phone' => '081234567891',
            'customer_type' => 'UMUM',
            'points' => 60, // Target poin = 50, nilai voucher = Rp 20.000
        ]);

        $product = Product::create([
            'name' => 'Pasir Kucing Gumpal 10L',
            'sku' => 'PSR-KCG-10L',
            'barcode' => '899123456702',
            'sell_price' => 70000,
            'cost_price' => 50000,
            'stock' => 15,
            'unit' => 'zak',
            'is_active' => true,
        ]);

        // Belanja Rp 70.000, diskon voucher Rp 20.000 -> Total = Rp 50.000
        $res = $this->actingAs($this->kasir)->postJson('/kasir', [
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $product->id, 'qty' => 1, 'unit_type' => 'base'],
            ],
            'discount' => 20000,
            'use_points_voucher' => true,
            'paid_amount' => 50000,
            'payment_method' => 'tunai',
        ]);

        $res->assertStatus(200);

        $customer->refresh();
        // Poin: 60 - 50 (ditukar) + 5 (didapat dari belanja Rp 50.000 / 10.000) = 15 poin
        $this->assertEquals(15, $customer->points);

        $sale = Sale::latest()->first();
        $this->assertEquals(5, $sale->points_earned);
        $this->assertEquals(50, $sale->points_redeemed);
        $this->assertEquals(20000, $sale->points_discount);
    }

    public function test_pembatalan_transaksi_mengembalikan_poin_pelanggan(): void
    {
        $customer = Customer::create([
            'name' => 'Pelanggan Retur',
            'phone' => '081234567892',
            'customer_type' => 'UMUM',
            'points' => 55,
        ]);

        $product = Product::create([
            'name' => 'Snack Kucing Creamy 4x15g',
            'sku' => 'SNK-CRM-4X',
            'barcode' => '899123456703',
            'sell_price' => 30000,
            'cost_price' => 20000,
            'stock' => 50,
            'unit' => 'pack',
            'is_active' => true,
        ]);

        // Checkout dengan tukar voucher
        $this->actingAs($this->kasir)->postJson('/kasir', [
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $product->id, 'qty' => 1, 'unit_type' => 'base'],
            ],
            'discount' => 20000,
            'use_points_voucher' => true,
            'paid_amount' => 10000,
            'payment_method' => 'tunai',
        ])->assertStatus(200);

        $sale = Sale::latest()->first();
        $customer->refresh();
        $this->assertEquals(6, $customer->points); // 55 - 50 + 1 (dari Rp 10.000) = 6 poin

        // Batalkan transaksi via ReturnController::cancelSale
        $cancelRes = $this->actingAs($this->admin)->post("/retur/{$sale->id}/cancel", [
            'reason' => 'Salah input barang',
        ]);
        $cancelRes->assertRedirect();

        $customer->refresh();
        // Rollback poin: 6 - 1 (points_earned dicabut) + 50 (points_redeemed dikembalikan) = 55 poin
        $this->assertEquals(55, $customer->points);
    }

    public function test_pengaturan_poin_loyalitas_menerima_format_ribuan_indonesia(): void
    {
        $response = $this->actingAs($this->admin)->post('/pengaturan/poin-loyalitas', [
            'enabled' => '1',
            'belanja_per_poin' => '15.000',
            'target_poin' => '1.000',
            'nilai_voucher' => '50.000',
        ]);

        $response->assertSessionHasNoErrors();
        $settings = Setting::poinLoyalitas();
        $this->assertEquals(15000, $settings['belanja_per_poin']);
        $this->assertEquals(1000, $settings['target_poin']);
        $this->assertEquals(50000, $settings['nilai_voucher']);
    }

    public function test_hapus_permanen_transaksi_mengembalikan_poin_pelanggan(): void
    {
        $customer = Customer::create([
            'name' => 'Pelanggan Hapus',
            'phone' => '081234567899',
            'customer_type' => 'UMUM',
            'points' => 55,
        ]);

        $product = Product::create([
            'name' => 'Kalung Kucing Premium',
            'sku' => 'KLG-PRM-01',
            'barcode' => '899123456799',
            'sell_price' => 30000,
            'cost_price' => 20000,
            'stock' => 20,
            'unit' => 'pcs',
            'is_active' => true,
        ]);

        $this->actingAs($this->kasir)->postJson('/kasir', [
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $product->id, 'qty' => 1, 'unit_type' => 'base'],
            ],
            'discount' => 20000,
            'use_points_voucher' => true,
            'paid_amount' => 10000,
            'payment_method' => 'tunai',
        ])->assertStatus(200);

        $sale = Sale::latest()->first();
        $customer->refresh();
        $this->assertEquals(6, $customer->points);

        // Hapus permanen via KasirController::destroy
        $delRes = $this->actingAs($this->admin)->delete("/kasir/waiting-list/{$sale->id}");
        $delRes->assertRedirect();

        $customer->refresh();
        $this->assertEquals(55, $customer->points, 'Poin pelanggan harus dikembalikan saat transaksi dihapus permanen.');
    }
}
