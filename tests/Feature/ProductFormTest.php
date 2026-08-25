<?php

namespace Tests\Feature;

use App\Models\Product;
use Tests\JposTestCase;

class ProductFormTest extends JposTestCase
{
    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'Produk Uji',
            'type' => 'barang',
            'cost_price' => 5000,
            'sell_price' => 9000,
            'stock' => 10,
        ], $override);
    }

    /**
     * Checkbox HTML yang tidak dicentang tidak dikirim browser sama sekali, jadi tanpa
     * hidden input pendamping server tidak pernah bisa membedakan "centang dilepas" dari
     * "field tidak ada". Akibatnya produk tidak pernah benar-benar bisa dinonaktifkan
     * atau dibebaskan dari pajak lewat form, berapa kali pun disimpan.
     */
    public function test_form_produk_punya_hidden_pendamping_untuk_setiap_checkbox(): void
    {
        $this->makeProduct();

        $html = $this->actingAs($this->admin)->get('/master/produk')->assertOk()->getContent();

        $this->assertStringContainsString('<input type="hidden" name="is_active" value="0">', $html);
        $this->assertStringContainsString('<input type="hidden" name="is_taxable" value="0">', $html);
    }

    public function test_produk_bisa_dinonaktifkan(): void
    {
        $product = $this->makeProduct(['is_active' => true]);

        // Persis seperti yang dikirim browser saat checkbox dilepas: hidden value "0".
        $this->actingAs($this->admin)
            ->put('/master/produk/' . $product->id, $this->payload([
                'name' => $product->name,
                'stock' => $product->stock,
                'is_active' => '0',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertFalse($product->fresh()->is_active, 'Produk seharusnya menjadi nonaktif.');
    }

    public function test_produk_bisa_diaktifkan_kembali(): void
    {
        $product = $this->makeProduct(['is_active' => false]);

        $this->actingAs($this->admin)
            ->put('/master/produk/' . $product->id, $this->payload([
                'name' => $product->name,
                'stock' => $product->stock,
                'is_active' => '1',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertTrue($product->fresh()->is_active);
    }

    /** S1: kolom is_taxable dipakai perhitungan PPN tapi tidak pernah ada di form. */
    public function test_produk_bisa_ditandai_bebas_pajak(): void
    {
        $this->actingAs($this->admin)
            ->post('/master/produk', $this->payload(['name' => 'Beras', 'is_taxable' => '0']))
            ->assertSessionHasNoErrors();

        $this->assertFalse(Product::where('name', 'Beras')->firstOrFail()->is_taxable);
    }

    public function test_produk_baru_default_kena_pajak(): void
    {
        // Perilaku lama dipertahankan: produk yang dibuat tanpa menyentuh opsi ini
        // tetap kena pajak, supaya data yang sudah ada tidak berubah artinya.
        $this->actingAs($this->admin)
            ->post('/master/produk', $this->payload(['name' => 'Kopi']))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Product::where('name', 'Kopi')->firstOrFail()->is_taxable);
    }

    public function test_pajak_hanya_dihitung_untuk_produk_yang_kena_pajak(): void
    {
        $this->enableTax(10);

        $kenaPajak = $this->makeProduct(['name' => 'Rokok', 'sell_price' => 10000, 'is_taxable' => true]);
        $bebasPajak = $this->makeProduct(['name' => 'Beras', 'sell_price' => 10000, 'is_taxable' => false]);

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [
                ['product_id' => $kenaPajak->id, 'qty' => 1],
                ['product_id' => $bebasPajak->id, 'qty' => 1],
            ],
            'paid_amount' => 21000,
            'payment_method' => 'cash',
        ])->assertOk();

        $sale = \App\Models\Sale::firstOrFail();

        $this->assertSame(20000.0, (float) $sale->subtotal);
        $this->assertSame(1000.0, (float) $sale->tax_amount, 'Pajak 10% hanya dari Rokok (10.000), bukan dari keduanya.');
        $this->assertSame(21000.0, (float) $sale->total);
    }
}
