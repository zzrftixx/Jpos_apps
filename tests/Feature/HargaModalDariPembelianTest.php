<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Unit;
use Tests\JposTestCase;

/**
 * UAT "hitungkan harga modal dari harga beli".
 *
 * Untuk pemilik toko yang tidak mau repot menghitung HPP: yang dia tahu cuma dua angka di
 * nota - harga beli satu Dus, dan ongkirnya. Berapa harga modal per Pcs adalah pekerjaan
 * aplikasi.
 *
 * YANG PALING MENENTUKAN di berkas ini adalah harga modal SATUAN DASAR. Harga modal per
 * satuan tambahan tidak dipakai perhitungan apa pun di aplikasi ini; yang menggerakkan
 * Laporan Laba dan Nilai Stok adalah products.cost_price. Kalau angka itu tidak ikut
 * diturunkan, pemilik toko sudah mengisi seluruh rincian pembeliannya tapi laporan labanya
 * tetap salah - dan tidak ada satu pun tanda bahwa ada yang keliru.
 */
class HargaModalDariPembelianTest extends JposTestCase
{
    private function unitId(string $nama): int
    {
        return Unit::firstOrCreate(['name' => $nama])->id;
    }

    private function payload(array $units, array $tambahan = []): array
    {
        return array_merge([
            'name' => 'Makanan Kucing',
            'type' => 'barang',
            'unit' => 'Pcs',
            'cost_price' => 0,
            'sell_price' => 5000,
            'stock' => 100,
            'min_stock' => 0,
            'is_active' => '1',
            'is_taxable' => '1',
            'multi_unit_enabled' => '1',
            'hpp_calc_enabled' => '1',
            'units' => $units,
        ], $tambahan);
    }

    public function test_harga_modal_satuan_dasar_diturunkan_dari_harga_beli(): void
    {
        // Beli 1 Dus isi 40 Pcs seharga 100.000, ongkir 10.000.
        $this->actingAs($this->admin)->post('/master/produk', $this->payload([
            ['unit_id' => $this->unitId('Dus'), 'ratio_to_previous' => 40, 'price' => 200000,
             'modal_total' => 100000, 'biaya_lain' => 10000],
        ]))->assertSessionHasNoErrors();

        $produk = Product::where('name', 'Makanan Kucing')->firstOrFail();

        $this->assertSame(
            2750.0,
            (float) $produk->cost_price,
            '110.000 untuk 40 Pcs = 2.750 per Pcs. Inilah angka yang dipakai Laporan Laba.'
        );
    }

    public function test_harga_modal_satuan_itu_sendiri_ikut_tersimpan(): void
    {
        $this->actingAs($this->admin)->post('/master/produk', $this->payload([
            ['unit_id' => $this->unitId('Dus'), 'ratio_to_previous' => 40, 'price' => 200000,
             'modal_total' => 100000, 'biaya_lain' => 10000],
        ]))->assertSessionHasNoErrors();

        $baris = ProductUnit::firstOrFail();

        $this->assertSame(110000.0, (float) $baris->cost_price, 'Harga modal 1 Dus = beli + biaya lain.');
    }

    /**
     * Tanpa ini, angka yang diketik pemilik toko lenyap begitu form ditutup. Saat produknya
     * dibuka lagi untuk dikoreksi, dia cuma melihat hasil akhirnya dan harus menghitung
     * mundur sendiri untuk tahu mana harga beli dan mana ongkir - justru yang mau dihindari.
     */
    public function test_rincian_harga_beli_tersimpan_dan_muncul_lagi_saat_diedit(): void
    {
        $this->actingAs($this->admin)->post('/master/produk', $this->payload([
            ['unit_id' => $this->unitId('Dus'), 'ratio_to_previous' => 40, 'price' => 200000,
             'modal_total' => 100000, 'biaya_lain' => 10000],
        ]))->assertSessionHasNoErrors();

        $baris = ProductUnit::firstOrFail();
        $this->assertSame(100000.0, (float) $baris->modal_total);
        $this->assertSame(10000.0, (float) $baris->biaya_lain);

        // Halaman Master Produk menanam data produk ke tombol Edit; rinciannya harus ikut.
        $halaman = $this->actingAs($this->admin)->get('/master/produk')->assertOk()->getContent();
        $this->assertStringContainsString('"modal_total":"100000.00"', $halaman);
        $this->assertStringContainsString('"biaya_lain":"10000.00"', $halaman);
        $this->assertStringContainsString('"hpp_calc_enabled":true', $halaman);
    }

    /** Rantai berjenjang: harga beli diisi di satuan terbesar, dibagi ke satuan dasar. */
    public function test_rantai_berjenjang_membagi_sampai_satuan_dasar(): void
    {
        $this->actingAs($this->admin)->post('/master/produk', $this->payload([
            ['unit_id' => $this->unitId('Pack'), 'ratio_to_previous' => 10, 'price' => 50000],
            ['unit_id' => $this->unitId('Dus'), 'ratio_to_previous' => 4, 'price' => 190000,
             'modal_total' => 160000, 'biaya_lain' => 0],
        ]))->assertSessionHasNoErrors();

        $produk = Product::where('name', 'Makanan Kucing')->firstOrFail();

        // 1 Dus = 4 Pack = 40 Pcs. 160.000 / 40 = 4.000 per Pcs.
        $this->assertSame(4000.0, (float) $produk->cost_price);
    }

    /** Baris terisi PERTAMA yang dipakai, supaya hasilnya bisa ditebak. */
    public function test_baris_terisi_pertama_yang_menentukan(): void
    {
        $this->actingAs($this->admin)->post('/master/produk', $this->payload([
            ['unit_id' => $this->unitId('Pack'), 'ratio_to_previous' => 10, 'price' => 30000,
             'modal_total' => 25000, 'biaya_lain' => 0],
            ['unit_id' => $this->unitId('Dus'), 'ratio_to_previous' => 4, 'price' => 120000,
             'modal_total' => 999999, 'biaya_lain' => 0],
        ]))->assertSessionHasNoErrors();

        $produk = Product::where('name', 'Makanan Kucing')->firstOrFail();

        $this->assertSame(2500.0, (float) $produk->cost_price, '25.000 / 10 Pcs, bukan dari baris Dus.');
    }

    /** Mematikan kalkulator mengembalikan harga modal ke angka yang diketik sendiri. */
    public function test_tanpa_kalkulator_harga_modal_dipakai_apa_adanya(): void
    {
        $this->actingAs($this->admin)->post('/master/produk', $this->payload([
            ['unit_id' => $this->unitId('Dus'), 'ratio_to_previous' => 40, 'price' => 200000,
             'cost_price' => 111000],
        ], ['hpp_calc_enabled' => '0', 'cost_price' => 3000]))->assertSessionHasNoErrors();

        $produk = Product::where('name', 'Makanan Kucing')->firstOrFail();

        $this->assertSame(3000.0, (float) $produk->cost_price, 'Angka yang diketik tidak boleh ditimpa.');
        $this->assertSame(111000.0, (float) ProductUnit::firstOrFail()->cost_price);
    }

    /** Rincian kosong tidak boleh menimpa harga modal dengan nol. */
    public function test_rincian_kosong_tidak_menimpa_harga_modal(): void
    {
        $this->actingAs($this->admin)->post('/master/produk', $this->payload([
            ['unit_id' => $this->unitId('Dus'), 'ratio_to_previous' => 40, 'price' => 200000],
        ], ['cost_price' => 3000]))->assertSessionHasNoErrors();

        $this->assertSame(3000.0, (float) Product::where('name', 'Makanan Kucing')->firstOrFail()->cost_price);
    }

    /**
     * Angka datang dari kotak berformat Indonesia. Kalau titik ribuan tidak dinormalkan,
     * "100.000" terbaca 100 dan harga modal meleset seribu kali lipat.
     */
    public function test_angka_berformat_ribuan_dinormalkan(): void
    {
        $this->actingAs($this->admin)->post('/master/produk', $this->payload([
            ['unit_id' => $this->unitId('Dus'), 'ratio_to_previous' => '40', 'price' => '200.000',
             'modal_total' => '100.000', 'biaya_lain' => '10.000'],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(2750.0, (float) Product::where('name', 'Makanan Kucing')->firstOrFail()->cost_price);
        $this->assertSame(110000.0, (float) ProductUnit::firstOrFail()->cost_price);
    }

    /**
     * Bug penggandaan x100 saat Edit pernah terjadi di form ini. Menyimpan ulang tanpa
     * mengubah apa pun harus menghasilkan angka yang persis sama, berapa kali pun.
     */
    public function test_simpan_ulang_berulang_tidak_menggeser_angka(): void
    {
        $this->actingAs($this->admin)->post('/master/produk', $this->payload([
            ['unit_id' => $this->unitId('Dus'), 'ratio_to_previous' => 40, 'price' => 200000,
             'modal_total' => 100000, 'biaya_lain' => 10000],
        ]))->assertSessionHasNoErrors();

        $produk = Product::where('name', 'Makanan Kucing')->firstOrFail();

        for ($ke = 1; $ke <= 4; $ke++) {
            $baris = $produk->fresh()->units()->firstOrFail();

            $this->actingAs($this->admin)->put("/master/produk/{$produk->id}", $this->payload([
                [
                    'unit_id' => $baris->unit_id,
                    'ratio_to_previous' => number_format((float) $baris->ratio_to_previous, 0, ',', '.'),
                    'price' => number_format((float) $baris->price, 0, ',', '.'),
                    'modal_total' => number_format((float) $baris->modal_total, 0, ',', '.'),
                    'biaya_lain' => number_format((float) $baris->biaya_lain, 0, ',', '.'),
                ],
            ]))->assertSessionHasNoErrors();

            $this->assertSame(2750.0, (float) $produk->fresh()->cost_price, "Bergeser pada simpan ke-{$ke}.");
            $this->assertSame(110000.0, (float) $produk->fresh()->units()->firstOrFail()->cost_price);
            $this->assertSame(40.0, (float) $produk->fresh()->units()->firstOrFail()->conversion);
        }
    }

    public function test_harga_modal_turunan_masuk_ke_nilai_stok(): void
    {
        $this->actingAs($this->admin)->post('/master/produk', $this->payload([
            ['unit_id' => $this->unitId('Dus'), 'ratio_to_previous' => 40, 'price' => 200000,
             'modal_total' => 100000, 'biaya_lain' => 10000],
        ], ['stock' => 200]))->assertSessionHasNoErrors();

        // 200 Pcs x 2.750 = 550.000
        $this->actingAs($this->admin)->get('/master/produk')
            ->assertOk()
            ->assertSee('Rp 550.000', false);
    }

    public function test_produk_jasa_tidak_pernah_memakai_kalkulator(): void
    {
        $this->actingAs($this->admin)->post('/master/produk', $this->payload([], [
            'type' => 'jasa',
            'cost_price' => 1000,
        ]))->assertSessionHasNoErrors();

        $produk = Product::where('name', 'Makanan Kucing')->firstOrFail();

        $this->assertFalse((bool) $produk->hpp_calc_enabled);
        $this->assertSame(1000.0, (float) $produk->cost_price);
    }
}
