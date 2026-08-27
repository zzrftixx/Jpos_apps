<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use Tests\JposTestCase;

/**
 * Harga grosir untuk produk JASA.
 *
 * JPos dipakai lini usaha yang sangat berbeda-beda - sembako, bengkel motor, petshop,
 * fotokopi - dan itulah sebabnya "jasa tidak punya grosir" pernah terasa benar. Toko
 * fotokopi membuktikan sebaliknya: harga per lembar turun begitu jumlahnya banyak (cetak
 * skripsi, jilid borongan), dan itu persis aturan yang sama dengan grosir barang.
 *
 * Yang menahannya selama ini cuma EMPAT BARIS di ProductController yang menolkan grosir
 * untuk jasa. Mesin harganya sendiri (ResolvesUnitPricing) tidak pernah peduli tipe.
 *
 * Yang paling dijaga di sini: toko yang TIDAK menyalakannya tidak boleh merasakan apa pun.
 */
class GrosirJasaTest extends JposTestCase
{
    private function aturMode(bool $grosirJasa): void
    {
        Setting::set('produk_mode', ['mode' => 'sederhana', 'grosir_jasa' => $grosirJasa]);
    }

    private function simpanJasa(array $ubah = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->post('/master/produk', array_merge([
            'name' => 'Fotokopi A4',
            'type' => 'jasa',
            'unit' => 'Lembar',
            'cost_price' => 200,
            'sell_price' => 500,
            'is_active' => 1,
            'wholesale_price' => 300,
            'wholesale_min_qty' => 100,
        ], $ubah));
    }

    // -----------------------------------------------------------------
    // Mati secara bawaan
    // -----------------------------------------------------------------

    /** PALING MENENTUKAN: toko yang tidak menyalakannya tetap seperti sebelumnya. */
    public function test_mati_secara_bawaan_grosir_jasa_tetap_ditolak_server(): void
    {
        $this->aturMode(false);

        $this->simpanJasa()->assertSessionHasNoErrors();

        $p = Product::where('name', 'Fotokopi A4')->firstOrFail();

        $this->assertNull($p->wholesale_price, 'Grosir jasa tersimpan padahal fiturnya mati.');
        $this->assertNull($p->wholesale_min_qty);
    }

    public function test_mati_secara_bawaan_blok_grosir_tersembunyi_untuk_jasa(): void
    {
        $this->aturMode(false);

        $html = $this->actingAs($this->admin)->get('/master/produk')->assertOk()->getContent();

        $this->assertStringContainsString('grosirJasa: false', $html);
        $this->assertStringContainsString(
            'x-show="type === \'barang\' || grosirJasa"', $html,
            'Blok grosir tidak lagi mengikuti pengaturan.'
        );
    }

    // -----------------------------------------------------------------
    // Dinyalakan
    // -----------------------------------------------------------------

    public function test_dinyalakan_grosir_jasa_tersimpan(): void
    {
        $this->aturMode(true);

        $this->simpanJasa()->assertSessionHasNoErrors();

        $p = Product::where('name', 'Fotokopi A4')->firstOrFail();

        $this->assertSame('jasa', $p->type);
        $this->assertEqualsWithDelta(300, (float) $p->wholesale_price, 0.01);
        $this->assertSame(100, $p->wholesale_min_qty);
    }

    /**
     * Yang dinyalakan HANYA grosir. Stok dan satuan tambahan tetap dipaksa kosong untuk
     * jasa - jasa tidak punya persediaan, dan itu tidak berubah karena harganya bertingkat.
     */
    public function test_dinyalakan_tidak_membuka_stok_dan_satuan_untuk_jasa(): void
    {
        $this->aturMode(true);

        $this->simpanJasa(['stock' => 500, 'min_stock' => 10, 'multi_unit_enabled' => '1'])
            ->assertSessionHasNoErrors();

        $p = Product::where('name', 'Fotokopi A4')->firstOrFail();

        $this->assertEqualsWithDelta(0, (float) $p->stock, 0.001, 'Produk jasa punya stok.');
        $this->assertEqualsWithDelta(0, (float) $p->min_stock, 0.001);
        $this->assertFalse((bool) $p->multi_unit_enabled);
        $this->assertCount(0, $p->units);
    }

    // -----------------------------------------------------------------
    // Harganya benar-benar dipakai saat transaksi
    // -----------------------------------------------------------------

    /**
     * Ini yang membuat fiturnya nyata, bukan cuma dua kolom yang bisa diisi.
     *
     * 150 lembar melewati minimum 100, jadi SELURUH 150 lembar dihargai Rp 300 - bukan
     * 100 lembar pertama Rp 500 lalu sisanya Rp 300. Itu aturan grosir yang sudah berlaku
     * untuk barang sejak dulu, dan sengaja tidak dibuat berbeda di sini: kasir yang harus
     * mengingat dua aturan berbeda akan salah pada salah satunya.
     */
    public function test_harga_grosir_jasa_dipakai_di_kasir(): void
    {
        $this->aturMode(true);
        $this->simpanJasa()->assertSessionHasNoErrors();

        $p = Product::where('name', 'Fotokopi A4')->firstOrFail();

        $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $p->id, 'qty' => 150]],
            'paid_amount' => 999999,
            'payment_method' => 'cash',
        ])->assertOk();

        $baris = \App\Models\SaleItem::latest('id')->firstOrFail();

        $this->assertEqualsWithDelta(300, (float) $baris->price, 0.01,
            'Harga grosir jasa tidak dipakai di kasir.');
        $this->assertEqualsWithDelta(45000, (float) $baris->subtotal, 0.01);
    }

    /** Di bawah minimum, harga satuannya yang dipakai. */
    public function test_di_bawah_minimum_memakai_harga_satuan(): void
    {
        $this->aturMode(true);
        $this->simpanJasa()->assertSessionHasNoErrors();

        $p = Product::where('name', 'Fotokopi A4')->firstOrFail();

        $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $p->id, 'qty' => 20]],
            'paid_amount' => 999999,
            'payment_method' => 'cash',
        ])->assertOk();

        $baris = \App\Models\SaleItem::latest('id')->firstOrFail();

        $this->assertEqualsWithDelta(500, (float) $baris->price, 0.01);
        $this->assertEqualsWithDelta(10000, (float) $baris->subtotal, 0.01);
    }

    /** Jasa tetap tidak memotong stok, berapa pun jumlahnya. */
    public function test_jasa_bergrosir_tetap_tidak_memotong_stok(): void
    {
        $this->aturMode(true);
        $this->simpanJasa()->assertSessionHasNoErrors();

        $p = Product::where('name', 'Fotokopi A4')->firstOrFail();

        $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $p->id, 'qty' => 500]],
            'paid_amount' => 999999,
            'payment_method' => 'cash',
        ])->assertOk();

        $this->assertEqualsWithDelta(0, (float) $p->fresh()->stock, 0.001);
    }

    // -----------------------------------------------------------------
    // Pengaturan
    // -----------------------------------------------------------------

    public function test_pengaturan_bisa_dinyalakan_dan_dimatikan(): void
    {
        $this->actingAs($this->admin)->post('/pengaturan/mode-produk', [
            'mode' => 'sederhana',
            'grosir_jasa' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Setting::get('produk_mode', [])['grosir_jasa']);

        // Centang dilepas -> checkbox tidak terkirim sama sekali.
        $this->actingAs($this->admin)->post('/pengaturan/mode-produk', [
            'mode' => 'sederhana',
        ])->assertSessionHasNoErrors();

        $this->assertFalse(Setting::get('produk_mode', [])['grosir_jasa']);
    }

    /** Produk BARANG tidak terpengaruh sama sekali oleh pengaturan ini. */
    public function test_grosir_barang_tidak_terpengaruh_pengaturan_ini(): void
    {
        $this->aturMode(false);

        $this->actingAs($this->admin)->post('/master/produk', [
            'name' => 'Beras Premium',
            'type' => 'barang',
            'unit' => 'Kg',
            'cost_price' => 10000,
            'sell_price' => 14000,
            'stock' => 100,
            'min_stock' => 5,
            'is_active' => 1,
            'wholesale_price' => 12500,
            'wholesale_min_qty' => 25,
        ])->assertSessionHasNoErrors();

        $p = Product::where('name', 'Beras Premium')->firstOrFail();

        $this->assertEqualsWithDelta(12500, (float) $p->wholesale_price, 0.01,
            'Grosir barang ikut dimatikan oleh pengaturan yang seharusnya cuma soal jasa.');
        $this->assertSame(25, $p->wholesale_min_qty);
    }
}
