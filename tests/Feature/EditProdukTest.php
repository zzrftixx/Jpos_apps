<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Unit;
use Tests\JposTestCase;

/**
 * Regresi untuk bug penggandaan nominal saat produk diedit.
 *
 * Laravel mengirim kolom decimal sebagai STRING: cost_price 1500 menjadi "1500.00".
 * Modul format angka memperlakukan titik sebagai pemisah RIBUAN - benar untuk teks yang
 * diketik kasir, salah untuk nilai dari server. Akibatnya form Edit menampilkan 150.000
 * untuk produk seharga 1.500, dan nominalnya berlipat lagi setiap kali disimpan ulang.
 *
 * Test di sini menjaga sisi server: data yang dikirim form Edit harus tersimpan apa adanya,
 * dan menyimpan berulang kali tidak boleh mengubah nilainya. Sisi tampilan dijaga oleh
 * tests/js/jpos-number.test.cjs.
 */
class EditProdukTest extends JposTestCase
{
    private function payload(Product $p, array $override = []): array
    {
        // Meniru apa yang dikirim browser: nilai berformat Indonesia dari kotak input.
        return array_merge([
            'name' => $p->name,
            'type' => $p->type,
            'unit' => $p->unit,
            'cost_price' => number_format((float) $p->cost_price, 0, ',', '.'),
            'sell_price' => number_format((float) $p->sell_price, 0, ',', '.'),
            'stock' => number_format($p->stock, 0, ',', '.'),
            'is_active' => '1',
            'is_taxable' => '1',
        ], $override);
    }

    public function test_simpan_ulang_tanpa_mengubah_apa_pun_tidak_menggandakan_nominal(): void
    {
        $produk = $this->makeProduct(['cost_price' => 1500, 'sell_price' => 2000, 'stock' => 100]);

        // Disimpan ulang lima kali berturut-turut, persis seperti kasir yang bolak-balik
        // membuka Edit lalu menekan Simpan.
        for ($i = 1; $i <= 5; $i++) {
            $segar = $produk->fresh();

            $this->actingAs($this->admin)
                ->put('/master/produk/' . $produk->id, $this->payload($segar))
                ->assertSessionHasNoErrors();

            $sesudah = $produk->fresh();

            $this->assertSame(1500.0, (float) $sesudah->cost_price, "Harga modal berubah pada simpan ke-{$i}.");
            $this->assertSame(2000.0, (float) $sesudah->sell_price, "Harga jual berubah pada simpan ke-{$i}.");
            $this->assertSame(100.0, (float) $sesudah->stock, "Stok berubah pada simpan ke-{$i}.");
        }
    }

    public function test_nilai_berdesimal_tidak_berubah_saat_disimpan_ulang(): void
    {
        $produk = $this->makeProduct(['cost_price' => 1500.75, 'sell_price' => 2000.25, 'stock' => 10]);

        for ($i = 1; $i <= 3; $i++) {
            $segar = $produk->fresh();

            $this->actingAs($this->admin)->put('/master/produk/' . $produk->id, $this->payload($segar, [
                'cost_price' => number_format((float) $segar->cost_price, 2, ',', '.'),
                'sell_price' => number_format((float) $segar->sell_price, 2, ',', '.'),
            ]))->assertSessionHasNoErrors();

            $this->assertSame(1500.75, (float) $produk->fresh()->cost_price, "Berubah pada simpan ke-{$i}.");
            $this->assertSame(2000.25, (float) $produk->fresh()->sell_price, "Berubah pada simpan ke-{$i}.");
        }
    }

    public function test_satuan_tambahan_tidak_tergandakan_saat_disimpan_ulang(): void
    {
        $produk = $this->makeProduct(['cost_price' => 1000, 'sell_price' => 1500]);
        $unit = Unit::firstOrCreate(['name' => 'Dus']);
        $this->makeProductUnit($produk, 'Dus', 1000, 24000);

        for ($i = 1; $i <= 3; $i++) {
            $baris = $produk->fresh()->units()->firstOrFail();

            $this->actingAs($this->admin)->put('/master/produk/' . $produk->id, $this->payload($produk->fresh(), [
                'multi_unit_enabled' => '1',
                'units' => [[
                    'unit_id' => $unit->id,
                    // Rasio bertipe decimal:4 - inilah yang paling parah tergandakan
                    // (10.000 kali lipat) sebelum diperbaiki. Untuk rantai satu baris,
                    // rasio ke satuan sebelumnya sama dengan konversi ke satuan dasar.
                    'ratio_to_previous' => number_format((float) $baris->ratio_to_previous, 0, ',', '.'),
                    'price' => number_format((float) $baris->price, 0, ',', '.'),
                ]],
            ]))->assertSessionHasNoErrors();

            $sesudah = $produk->fresh()->units()->firstOrFail();
            $this->assertSame(1000.0, (float) $sesudah->conversion, "Konversi berubah pada simpan ke-{$i}.");
            $this->assertSame(24000.0, (float) $sesudah->price, "Harga satuan berubah pada simpan ke-{$i}.");
        }
    }

    /**
     * Regresi: image_url adalah accessor, jadi tidak ikut saat model diubah ke JSON kecuali
     * didaftarkan di $appends. Tanpa itu, kotak pratinjau gambar di form Edit selalu kosong
     * walaupun produknya punya gambar.
     */
    public function test_data_produk_untuk_form_edit_memuat_url_gambar(): void
    {
        $produk = $this->makeProduct(['image' => 'products/contoh.jpg']);

        $json = $produk->toArray();

        $this->assertArrayHasKey('image_url', $json, 'Form Edit butuh image_url untuk pratinjau.');
        $this->assertStringContainsString('products/contoh.jpg', $json['image_url']);
    }

    public function test_produk_tanpa_gambar_memakai_gambar_pengganti(): void
    {
        $json = $this->makeProduct(['image' => null])->toArray();

        $this->assertArrayHasKey('image_url', $json);
        $this->assertStringContainsString('no-image', $json['image_url']);
    }

    /** Halaman daftar produk menanamkan data ini ke tombol Edit; harus ikut membawa image_url. */
    public function test_halaman_daftar_produk_menanamkan_url_gambar(): void
    {
        $this->makeProduct(['name' => 'Produk Bergambar', 'image' => 'products/contoh.jpg']);

        $html = $this->actingAs($this->admin)->get('/master/produk')->assertOk()->getContent();

        $this->assertStringContainsString('image_url', $html);
    }

    public function test_mengubah_harga_lewat_form_tetap_tersimpan_benar(): void
    {
        $produk = $this->makeProduct(['cost_price' => 1500, 'sell_price' => 2000]);

        $this->actingAs($this->admin)->put('/master/produk/' . $produk->id, $this->payload($produk, [
            'cost_price' => '12.500',
            'sell_price' => '17.900',
        ]))->assertSessionHasNoErrors();

        $sesudah = $produk->fresh();
        $this->assertSame(12500.0, (float) $sesudah->cost_price);
        $this->assertSame(17900.0, (float) $sesudah->sell_price);
    }

    /** Pengaturan juga memuat nilai berdesimal ke form; harus stabil saat disimpan ulang. */
    public function test_pengaturan_printer_stabil_saat_disimpan_ulang(): void
    {
        $this->actingAs($this->admin)->post('/pengaturan/printer-struk', [
            'profile' => 'custom', 'custom_width' => '57,5', 'margin' => '1,5', 'font_size' => '11',
        ])->assertSessionHasNoErrors();

        for ($i = 1; $i <= 3; $i++) {
            $now = \App\Models\Setting::get('printer_struk');

            $this->actingAs($this->admin)->post('/pengaturan/printer-struk', [
                'profile' => 'custom',
                'custom_width' => str_replace('.', ',', (string) $now['paper_size']),
                'margin' => str_replace('.', ',', (string) $now['margin']),
                'font_size' => (string) $now['font_size'],
            ])->assertSessionHasNoErrors();

            $sesudah = \App\Models\Setting::get('printer_struk');
            $this->assertSame(57.5, (float) $sesudah['paper_size'], "Lebar kertas berubah pada simpan ke-{$i}.");
            $this->assertSame(1.5, (float) $sesudah['margin'], "Margin berubah pada simpan ke-{$i}.");
        }
    }

    /**
     * Produk yang stoknya masih direservasi pesanan belum lunas tidak boleh dihapus:
     * barisnya jadi yatim, pesanan tetap menagih barang yang datanya sudah hilang, dan
     * pembatalan pesanan tidak bisa lagi mengembalikan stoknya.
     */
    public function test_produk_yang_dipakai_pesanan_belum_lunas_tidak_bisa_dihapus(): void
    {
        $produk = $this->makeProduct(['name' => 'Produk Dipesan', 'stock' => 10, 'sell_price' => 5000]);

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 2]],
            'paid_amount' => 2000,
            'payment_method' => 'cash',
            'is_waiting_list' => true,
        ])->assertOk();

        $this->actingAs($this->admin)
            ->delete('/master/produk/' . $produk->id)
            ->assertSessionHas('error');

        $this->assertDatabaseHas('products', ['id' => $produk->id]);
    }

    public function test_produk_tanpa_pesanan_aktif_tetap_bisa_dihapus(): void
    {
        $produk = $this->makeProduct(['name' => 'Produk Bebas']);

        $this->actingAs($this->admin)
            ->delete('/master/produk/' . $produk->id)
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('products', ['id' => $produk->id]);
    }

    public function test_produk_dengan_transaksi_lunas_tetap_bisa_dihapus(): void
    {
        $produk = $this->makeProduct(['name' => 'Produk Terjual', 'stock' => 10, 'sell_price' => 5000]);

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 1]],
            'paid_amount' => 5000,
            'payment_method' => 'cash',
        ])->assertOk();

        $this->actingAs($this->admin)
            ->delete('/master/produk/' . $produk->id)
            ->assertSessionHas('success');
    }
}
