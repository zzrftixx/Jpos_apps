<?php

namespace Tests\Feature;

use App\Models\Product;
use Tests\JposTestCase;

/**
 * Halaman Master Produk setelah rantai satuan masuk.
 *
 * Yang dijaga di sini bukan tampilannya, tapi hal-hal yang kalau hilang tidak menimbulkan
 * error apa pun - cuma diam-diam berhenti bekerja: penanda pencarian langsung, hidden
 * pendamping checkbox, dan sumber gambar yang harus lewat MediaController.
 */
class FormProdukRantaiTest extends JposTestCase
{
    public function test_halaman_merender_rantai_satuan_dengan_stok_setara(): void
    {
        $produk = $this->makeProduct(['name' => 'Kopi Sachet', 'unit' => 'Pcs', 'stock' => 288]);
        $this->makeProductUnit($produk, 'Botol', 12, 24000);
        $this->makeProductUnit($produk, 'Dus', 288, 550000);

        $respons = $this->actingAs($this->admin)->get('/master/produk')->assertOk();

        // Satu baris anak per satuan tambahan, dengan stok setara masing-masing.
        $respons->assertSee('&#8618; Botol', false);
        $respons->assertSee('&#8618; Dus', false);
        $respons->assertSee('>24</span> Botol', false);   // 288 Pcs / 12
        $respons->assertSee('>1</span> Dus', false);      // 288 Pcs / 288
    }

    /**
     * Satuan yang lebih KECIL dari satuan dasar punya konversi di bawah 1. Membulatkannya
     * ke integer lebih dulu - seperti `intdiv($stock, (int) round($conversion))` - membuat
     * hasilnya meleset 1.000 kali lipat karena pembaginya menjadi 0 lalu dipaksa 1.
     */
    public function test_satuan_lebih_kecil_dari_satuan_dasar_menampilkan_stok_setara_yang_benar(): void
    {
        $produk = $this->makeProduct(['name' => 'Gula Pasir', 'unit' => 'Kg', 'stock' => 9.6]);
        $this->makeProductUnit($produk, 'Gram', 0.001, 20);

        $this->actingAs($this->admin)->get('/master/produk')
            ->assertOk()
            ->assertSee('>9.600</span> Gram', false)
            ->assertDontSee('>9,6</span> Gram', false);
    }

    public function test_penanda_pencarian_langsung_masih_terpasang(): void
    {
        $this->makeProduct();

        $this->actingAs($this->admin)->get('/master/produk')
            ->assertOk()
            ->assertSee('data-live-search', false)
            ->assertSee('data-live-results', false);
    }

    /**
     * Checkbox yang tidak dicentang tidak dikirim browser sama sekali. Tanpa hidden
     * pendamping, melepas centang tidak pernah sampai ke server - dan produk tidak akan
     * pernah bisa dinonaktifkan lewat form.
     */
    public function test_checkbox_punya_hidden_pendamping(): void
    {
        $this->makeProduct();

        $halaman = $this->actingAs($this->admin)->get('/master/produk')->assertOk()->getContent();

        foreach (['is_active', 'is_taxable', 'multi_unit_enabled'] as $field) {
            $this->assertStringContainsString(
                '<input type="hidden" name="' . $field . '" value="0">',
                $halaman,
                "Hidden pendamping untuk {$field} hilang - melepas centangnya tidak akan tersimpan."
            );
        }
    }

    public function test_melepas_centang_aktif_benar_benar_menonaktifkan_produk(): void
    {
        $produk = $this->makeProduct(['is_active' => true]);

        $this->actingAs($this->admin)->put("/master/produk/{$produk->id}", [
            'name' => $produk->name,
            'type' => 'barang',
            'unit' => $produk->unit,
            'cost_price' => 6000,
            'sell_price' => 10000,
            'stock' => 100,
            'is_active' => '0',
            'is_taxable' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertFalse((bool) $produk->fresh()->is_active);
    }

    /**
     * Kalkulator harga modal dan harga otomatis dari isi harus benar-benar terpasang di
     * form. Keduanya tidak menimbulkan error kalau hilang - cuma tidak ada, dan pemilik toko
     * kembali menghitung sendiri.
     */
    public function test_kalkulator_harga_modal_dan_harga_otomatis_terpasang(): void
    {
        $this->makeProduct();

        $halaman = $this->actingAs($this->admin)->get('/master/produk')->assertOk()->getContent();

        // Kalkulator harga modal beserta hidden pendampingnya.
        $this->assertStringContainsString('<input type="hidden" name="hpp_calc_enabled" value="0">', $halaman);
        $this->assertStringContainsString('Hitungkan harga modal dari harga beli', $halaman);
        $this->assertStringContainsString('hargaModalDasarTurunan()', $halaman);

        // Harga otomatis berantai per baris satuan.
        $this->assertStringContainsString('x-model="row.auto_price"', $halaman);
        $this->assertStringContainsString('rowEffectivePrice(row, index)', $halaman);
    }

    /**
     * Seluruh kotak nominal di form ini WAJIB memakai direktif x-number. Mekanisme lain
     * (kelas .money-input-auto yang dipakai proyek asal fitur ini) membuang desimal lewat
     * replace(/\D/g,'') dan mengulang persis bug penggandaan harga x100 saat Edit.
     */
    public function test_form_produk_tidak_memakai_mekanisme_angka_yang_lain(): void
    {
        $this->makeProduct();

        $halaman = $this->actingAs($this->admin)->get('/master/produk')->assertOk()->getContent();

        $this->assertStringNotContainsString('money-input-auto', $halaman);
        $this->assertStringContainsString('data-jpos-number', $halaman);
    }

    public function test_gambar_produk_dilayani_lewat_media_bukan_symlink_storage(): void
    {
        $produk = $this->makeProduct();
        $produk->forceFill(['image' => 'products/contoh.png'])->save();

        $this->assertStringContainsString('/media/products/contoh.png', $produk->fresh()->image_url);

        // $appends wajib memuat image_url, kalau tidak tombol Edit mengirim data produk
        // tanpa gambar dan kotak pratinjau selalu kosong.
        $this->assertArrayHasKey('image_url', Product::findOrFail($produk->id)->toArray());
    }
}
