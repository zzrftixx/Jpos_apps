<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\JposTestCase;

class SalePaymentReturTest extends JposTestCase
{
    use RefreshDatabase;

    public function test_additional_payment_pada_retur_tercatat_di_sale_payments(): void
    {
        $produkLama = $this->makeProduct(['sell_price' => 10000, 'stock' => 10]);
        $produkBaru = $this->makeProduct(['sell_price' => 15000, 'stock' => 10]);

        $sale = Sale::create([
            'invoice_no' => 'TEST-001',
            'user_id' => $this->admin->id,
            'subtotal' => 10000,
            'total' => 10000,
            'paid_amount' => 10000,
            'change_amount' => 0,
            'payment_method' => 'tunai',
            'order_status' => 'completed',
        ]);

        $itemLama = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $produkLama->id,
            'product_name' => $produkLama->name,
            'qty' => 1,
            'price' => 10000,
            'subtotal' => 10000,
            'cost_price_snapshot' => $produkLama->cost_price,
            'returned_qty' => 0,
        ]);

        SalePayment::create([
            'sale_id' => $sale->id,
            'method' => 'tunai',
            'amount' => 10000,
            'kind' => 'bayar',
            'user_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->postJson('/retur', [
            'sale_id' => $sale->id,
            'reason' => 'Tukar tambah',
            'items' => [
                ['sale_item_id' => $itemLama->id, 'qty' => 1],
            ],
            'add_items' => [
                ['product_id' => $produkBaru->id, 'qty' => 1],
            ],
            'additional_payment' => 15000,
        ]);
        $response->assertOk();

        $sale->refresh();
        $this->assertEquals(25000, $sale->total);
        $this->assertEquals(25000, $sale->paid_amount);

        $this->assertDatabaseHas('sale_payments', [
            'sale_id' => $sale->id,
            'amount' => 15000,
            'kind' => 'bayar',
        ]);

        $this->assertEquals(25000, SalePayment::where('sale_id', $sale->id)->sum('amount'));
    }

    public function test_additional_payment_berlebih_dicatat_nilai_bersihnya(): void
    {
        $produkLama = $this->makeProduct(['sell_price' => 10000, 'stock' => 10]);
        $produkBaru = $this->makeProduct(['sell_price' => 15000, 'stock' => 10]);

        $sale = Sale::create([
            'invoice_no' => 'TEST-002',
            'user_id' => $this->admin->id,
            'subtotal' => 10000,
            'total' => 10000,
            'paid_amount' => 10000,
            'change_amount' => 0,
            'payment_method' => 'tunai',
            'order_status' => 'completed',
        ]);

        $itemLama = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $produkLama->id,
            'product_name' => $produkLama->name,
            'qty' => 1,
            'price' => 10000,
            'subtotal' => 10000,
            'cost_price_snapshot' => $produkLama->cost_price,
            'returned_qty' => 0,
        ]);

        SalePayment::create([
            'sale_id' => $sale->id,
            'method' => 'tunai',
            'amount' => 10000,
            'kind' => 'bayar',
            'user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->postJson('/retur', [
            'sale_id' => $sale->id,
            'reason' => 'Tukar tambah',
            'items' => [
                ['sale_item_id' => $itemLama->id, 'qty' => 1],
            ],
            'add_items' => [
                ['product_id' => $produkBaru->id, 'qty' => 1],
            ],
            // Selisih yang wajib ditutup adalah HARGA PENUH item baru (Rp 15.000).
            // Retur item lama tidak menguranginya - itu perilaku yang sudah ada, dan versi
            // pertama test ini keliru mengira selisihnya Rp 5.000 sehingga server benar
            // menolaknya dengan 422. Di sini pembeli menyerahkan LEBIH: Rp 20.000.
            'additional_payment' => 20000,
            'additional_payment_method' => 'qris',
        ])->assertOk();

        $sale->refresh();
        $this->assertEquals(25000, $sale->total);
        $this->assertEquals(30000, $sale->paid_amount);
        $this->assertEquals(5000, $sale->change_amount, 'Kembaliannya Rp 5.000.');

        // Yang MASUK LACI hanya Rp 15.000 - kembaliannya kembali ke pembeli.
        $this->assertDatabaseHas('sale_payments', [
            'sale_id' => $sale->id,
            'amount' => 15000,
            'method' => 'qris',
        ]);

        $this->assertEquals(25000, SalePayment::where('sale_id', $sale->id)->sum('amount'),
            'Rp 10.000 pembayaran awal + Rp 15.000 bersih dari tukar tambah.');
    }

}
