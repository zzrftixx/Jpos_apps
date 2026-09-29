<?php

namespace Tests\Feature;

use App\Models\Sale;
use Tests\JposTestCase;

class DiskonMaksimalTest extends JposTestCase
{
    public function test_diskon_tidak_boleh_melebihi_subtotal(): void
    {
        $product = $this->makeProduct(['stock' => 10, 'sell_price' => 20000]);

        // Belanja Rp 20.000, diskon Rp 50.000 (tidak valid)
        $response = $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $product->id, 'qty' => 1, 'unit_type' => 'base']],
            'discount' => 50000,
            'paid_amount' => 0,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Diskon tidak boleh melebihi subtotal', $response->json('message') ?? '');
    }

    public function test_diskon_sama_dengan_subtotal_diperbolehkan(): void
    {
        $product = $this->makeProduct(['stock' => 10, 'sell_price' => 20000]);

        // Diskon 100% (gratis): Belanja Rp 20.000, diskon Rp 20.000, total Rp 0
        $response = $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $product->id, 'qty' => 1, 'unit_type' => 'base']],
            'discount' => 20000,
            'paid_amount' => 0,
            'payment_method' => 'cash',
        ]);

        $response->assertOk();
        $sale = Sale::latest('id')->first();
        $this->assertNotNull($sale);
        $this->assertSame(0.0, (float) $sale->total);
    }

    public function test_diskon_pada_update_waiting_list_tidak_boleh_melebihi_subtotal(): void
    {
        $product = $this->makeProduct(['stock' => 10, 'sell_price' => 20000]);

        $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $product->id, 'qty' => 1, 'unit_type' => 'base']],
            'paid_amount' => 5000,
            'payment_method' => 'cash',
            'is_waiting_list' => true,
        ])->assertOk();

        $sale = Sale::latest('id')->first();

        // Update pesanan dengan diskon melebihi subtotal
        $response = $this->actingAs($this->kasir)->putJson("/kasir/waiting-list/{$sale->id}", [
            'items' => [['product_id' => $product->id, 'qty' => 1, 'unit_type' => 'base']],
            'discount' => 35000,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Diskon tidak boleh melebihi subtotal', $response->json('message') ?? '');
    }
}
