<?php

namespace Tests\Feature;

use Tests\JposTestCase;

/**
 * Cetak label barcode per satuan.
 *
 * Produk yang dijual dalam beberapa satuan butuh label yang berbeda untuk tiap satuan -
 * label rak berharga per Kg tidak boleh menempel di kemasan yang dijual per Gram. Label
 * yang salah harga adalah kesalahan yang baru ketahuan di depan pelanggan.
 */
class CetakBarcodeSatuanTest extends JposTestCase
{
    public function test_label_satuan_tambahan_memakai_harga_satuan_itu(): void
    {
        $produk = $this->makeProduct(['name' => 'Gula', 'unit' => 'Gram', 'sell_price' => 20, 'barcode' => '899123']);
        $kg = $this->makeProductUnit($produk, 'Kg', 1000, 25000);

        $this->actingAs($this->admin)
            ->get('/barcode/print?ids=' . $produk->id . ':' . $kg->id . '&qty=1')
            ->assertOk()
            ->assertSee('Gula <span style="font-weight:normal;">(Kg)</span>', false)
            ->assertSee('Rp 25.000<span style="font-weight:normal;">/Kg</span>', false)
            ->assertDontSee('Rp 20 ', false);
    }

    public function test_label_satuan_dasar_memakai_harga_jual_produk(): void
    {
        $produk = $this->makeProduct(['name' => 'Gula', 'unit' => 'Gram', 'sell_price' => 20, 'barcode' => '899123']);
        $this->makeProductUnit($produk, 'Kg', 1000, 25000);

        $this->actingAs($this->admin)
            ->get('/barcode/print?ids=' . $produk->id . '&qty=1')
            ->assertOk()
            ->assertSee('Rp 20', false)
            ->assertDontSee('<span style="font-weight:normal;">(Kg)</span>', false);
    }

    /**
     * Produk tanpa barcode manual sebelumnya menghasilkan label tanpa garis sama sekali -
     * tercetak, tertempel, lalu tidak bisa dipindai di kasir.
     */
    public function test_produk_tanpa_barcode_memakai_sku_pada_label(): void
    {
        $produk = $this->makeProduct(['name' => 'Tanpa Barcode', 'barcode' => null]);

        $this->actingAs($this->admin)
            ->get('/barcode/print?ids=' . $produk->id . '&qty=1')
            ->assertOk()
            ->assertSee('data-code="' . $produk->sku . '"', false);
    }

    /** Label hasil cetak itu harus benar-benar bisa dipindai kembali di halaman Kasir. */
    public function test_memindai_label_ber_sku_menemukan_produknya(): void
    {
        $produk = $this->makeProduct(['name' => 'Tanpa Barcode', 'barcode' => null]);

        $this->actingAs($this->admin)
            ->getJson('/kasir/scan?barcode=' . $produk->sku)
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('product.id', $produk->id);
    }

    public function test_memindai_barcode_manual_tetap_bekerja(): void
    {
        $produk = $this->makeProduct(['barcode' => '8991234567890']);

        $this->actingAs($this->admin)
            ->getJson('/kasir/scan?barcode=8991234567890')
            ->assertOk()
            ->assertJsonPath('found', true);
    }

    public function test_jumlah_cetak_menggandakan_tiap_label(): void
    {
        $produk = $this->makeProduct(['name' => 'Produk Ganda', 'barcode' => '111']);

        $isi = $this->actingAs($this->admin)
            ->get('/barcode/print?ids=' . $produk->id . '&qty=3')
            ->assertOk()->getContent();

        $this->assertSame(3, substr_count($isi, 'class="label"'));
    }

    /**
     * Halaman cetak dibuka di jendela baru untuk langsung dicetak. Satu baris basi tidak
     * boleh menggagalkan seluruh cetakan.
     */
    public function test_satuan_yang_sudah_dihapus_dilewati_tanpa_menggagalkan_cetakan(): void
    {
        $produk = $this->makeProduct(['name' => 'Masih Ada', 'barcode' => '222']);

        $this->actingAs($this->admin)
            ->get('/barcode/print?ids=' . $produk->id . ',' . $produk->id . ':99999&qty=1')
            ->assertOk()
            ->assertSee('Masih Ada', false);
    }

    public function test_halaman_pilih_label_masih_punya_pencarian_langsung(): void
    {
        $produk = $this->makeProduct(['name' => 'Kopi']);
        $this->makeProductUnit($produk, 'Dus', 12, 24000);

        $this->actingAs($this->admin)->get('/barcode')
            ->assertOk()
            ->assertSee('data-live-search', false)
            ->assertSee('data-live-results', false)
            // Satu baris yang bisa dicentang per satuan.
            ->assertSee('value="' . $produk->id . '"', false)
            ->assertSee('value="' . $produk->id . ':' . $produk->units()->first()->id . '"', false);
    }
}
