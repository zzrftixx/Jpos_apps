<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Tests\JposTestCase;

/**
 * UAT rantai satuan berjenjang.
 *
 * Pemilik toko sekarang cuma menyebut isi tiap satuan terhadap satuan di bawahnya -
 * "1 Pack = 10 Pcs", "1 Dus = 4 Pack" - dan server yang melipatnya menjadi konversi ke
 * satuan dasar. Kolom `conversion` tetap satu-satunya sumber kebenaran untuk Kasir, struk,
 * dan laporan, jadi yang perlu dijaga di sini adalah: hasil pelipatan itu benar, DAN data
 * toko yang sudah berjalan tidak berubah nilainya saat ikut terbawa ke bentuk baru.
 */
class RantaiSatuanTest extends JposTestCase
{
    private function unitId(string $nama): int
    {
        return Unit::firstOrCreate(['name' => $nama])->id;
    }

    private function payloadProduk(array $units, array $tambahan = []): array
    {
        return array_merge([
            'name' => 'Produk Rantai',
            'type' => 'barang',
            'unit' => 'Pcs',
            'cost_price' => 1000,
            'sell_price' => 2000,
            'stock' => 100,
            'min_stock' => 0,
            'is_active' => '1',
            'is_taxable' => '1',
            'multi_unit_enabled' => '1',
            'units' => $units,
        ], $tambahan);
    }

    public function test_rantai_tiga_tingkat_dilipat_menjadi_konversi_ke_satuan_dasar(): void
    {
        $this->actingAs($this->admin)->post('/master/produk', $this->payloadProduk([
            ['unit_id' => $this->unitId('Botol'), 'ratio_to_previous' => 12, 'price' => 24000],
            ['unit_id' => $this->unitId('Pack'), 'ratio_to_previous' => 6, 'price' => 140000],
            ['unit_id' => $this->unitId('Dus'), 'ratio_to_previous' => 4, 'price' => 550000],
        ]))->assertRedirect();

        $produk = Product::where('name', 'Produk Rantai')->firstOrFail();
        $konversi = $produk->units()->orderBy('sort_order')->pluck('conversion')->map(fn ($c) => (float) $c)->all();

        // 12 Pcs -> 12 x 6 = 72 Pcs -> 72 x 4 = 288 Pcs
        $this->assertSame([12.0, 72.0, 288.0], $konversi);
    }

    public function test_urutan_rantai_mengikuti_urutan_yang_dikirim_form(): void
    {
        $this->actingAs($this->admin)->post('/master/produk', $this->payloadProduk([
            ['unit_id' => $this->unitId('Lusin'), 'ratio_to_previous' => 12, 'price' => 24000],
            ['unit_id' => $this->unitId('Kodi'), 'ratio_to_previous' => 20, 'price' => 400000],
        ]))->assertRedirect();

        $produk = Product::where('name', 'Produk Rantai')->firstOrFail();
        $baris = $produk->units()->orderBy('sort_order')->with('unit')->get();

        $this->assertSame(['Lusin', 'Kodi'], $baris->pluck('unit.name')->all());
        $this->assertSame([0, 1], $baris->pluck('sort_order')->all());
        $this->assertSame(240.0, (float) $baris[1]->conversion, '1 Kodi = 20 Lusin = 240 Pcs.');
    }

    /**
     * INI YANG PALING PENTING DI BERKAS INI.
     *
     * Toko yang sudah berjalan punya satuan tambahan dengan konversi langsung ke satuan
     * dasar - mis. Dus=24 dan Lusin=12 pada produk ber-satuan Pcs. Data itu ikut dibawa ke
     * bentuk rantai oleh migrasi.
     *
     * Godaan yang salah adalah menyalin `conversion` apa adanya ke `ratio_to_previous`.
     * Nilainya terlihat benar sampai produknya dibuka lalu disimpan kembali TANPA diubah
     * sama sekali: server melipat rasio secara kumulatif, dan Lusin berubah dari 12 menjadi
     * 24 x 12 = 288. Sejak saat itu harga, potongan stok, dan seluruh laporan salah tanpa
     * ada yang menyadarinya.
     */
    public function test_produk_lama_tidak_berubah_nilainya_setelah_disimpan_ulang(): void
    {
        $produk = $this->makeProduct(['name' => 'Produk Warisan', 'unit' => 'Pcs', 'stock' => 500]);
        $lusin = $this->makeProductUnit($produk, 'Lusin', 12, 24000);
        $dus = $this->makeProductUnit($produk, 'Dus', 24, 46000);

        // Bentuk data sebelum migrasi: rantai belum ada sama sekali.
        DB::table('product_units')->whereIn('id', [$lusin->id, $dus->id])
            ->update(['ratio_to_previous' => null, 'sort_order' => 0]);
        DB::table('products')->where('id', $produk->id)->update(['multi_unit_enabled' => false]);

        $this->jalankanBackfillRantai();

        $sesudahMigrasi = ProductUnit::where('product_id', $produk->id)
            ->orderBy('sort_order')->get();

        $this->assertTrue(
            (bool) $produk->fresh()->multi_unit_enabled,
            'Produk yang sudah punya satuan tambahan tidak boleh kehilangan fiturnya setelah pembaruan.'
        );
        $this->assertSame([12.0, 24.0], $sesudahMigrasi->pluck('conversion')->map(fn ($c) => (float) $c)->all());
        $this->assertSame(
            [12.0, 2.0],
            $sesudahMigrasi->pluck('ratio_to_previous')->map(fn ($r) => (float) $r)->all(),
            '1 Lusin = 12 Pcs, lalu 1 Dus = 2 Lusin. Bukan 12 dan 24.'
        );

        // Kasir membuka produk itu lalu menekan Simpan tanpa mengubah apa pun.
        $this->actingAs($this->admin)->put("/master/produk/{$produk->id}", $this->payloadProduk(
            $sesudahMigrasi->map(fn ($pu) => [
                'unit_id' => $pu->unit_id,
                'ratio_to_previous' => (float) $pu->ratio_to_previous,
                'price' => (float) $pu->price,
            ])->all(),
            ['name' => 'Produk Warisan', 'stock' => 500]
        ))->assertRedirect();

        $this->assertSame(
            [12.0, 24.0],
            ProductUnit::where('product_id', $produk->id)->orderBy('sort_order')
                ->pluck('conversion')->map(fn ($c) => (float) $c)->all(),
            'Menyimpan ulang tanpa perubahan tidak boleh menggandakan konversi apa pun.'
        );
    }

    /** Satuan yang lebih KECIL dari satuan dasar (Gram terhadap Kg) juga harus terjaga. */
    public function test_satuan_lebih_kecil_dari_satuan_dasar_ikut_terjaga(): void
    {
        $produk = $this->makeProduct(['name' => 'Gula', 'unit' => 'Kg', 'stock' => 50]);
        $gram = $this->makeProductUnit($produk, 'Gram', 0.001, 20);

        DB::table('product_units')->where('id', $gram->id)
            ->update(['ratio_to_previous' => null, 'sort_order' => 0]);

        $this->jalankanBackfillRantai();

        $this->assertSame(0.001, (float) $gram->fresh()->ratio_to_previous);
        $this->assertSame(0.001, (float) $gram->fresh()->conversion);
    }

    public function test_mematikan_multi_satuan_menghapus_seluruh_satuan_tambahan(): void
    {
        $produk = $this->makeProduct(['name' => 'Produk Rantai', 'unit' => 'Pcs']);
        $this->makeProductUnit($produk, 'Dus', 12, 24000);

        $this->actingAs($this->admin)->put("/master/produk/{$produk->id}", $this->payloadProduk([], [
            'name' => 'Produk Rantai',
            'multi_unit_enabled' => '0',
        ]))->assertRedirect();

        $this->assertSame(0, $produk->fresh()->units()->count());
    }

    /**
     * Menjalankan bagian backfill dari migrasi rantai terhadap data yang sudah ada.
     * RefreshDatabase menjalankan seluruh migrasi sebelum data test dibuat, jadi backfill-nya
     * perlu dipanggil ulang di sini supaya benar-benar menghadapi data warisan.
     */
    private function jalankanBackfillRantai(): void
    {
        $migrasi = require database_path('migrations/2025_01_01_000230_add_multi_unit_chain_columns.php');

        $metode = new \ReflectionMethod($migrasi, 'isiRantaiUntukDataLama');
        $metode->setAccessible(true);
        $metode->invoke($migrasi);
    }
}
