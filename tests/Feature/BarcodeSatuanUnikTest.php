<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Unit;
use Tests\JposTestCase;

/**
 * Keunikan barcode lintas produk DAN satuan.
 *
 * Barcode produk dan barcode satuan hidup di SATU ruang nama yang sama - itu bukan pilihan
 * gaya, melainkan kenyataan yang dipaksakan oleh kasir. `KasirController::findByBarcode()`
 * mencari ke `product_units.barcode` lebih dulu, baru ke `products.barcode`. Satu kode yang
 * sama di dua tempat berarti yang satu MENUTUPI yang lain, selamanya, tanpa pesan apa pun.
 *
 * Sampai versi ini, sisi satuannya tidak dijaga sama sekali:
 *
 *     products      : CREATE UNIQUE INDEX products_barcode_unique
 *     product_units : CREATE INDEX product_units_barcode_index   <- tidak unik
 *
 * dan validasinya cuma ['nullable','string','max:64'].
 *
 * Yang paling berbahaya bukan tabrakan satuan-vs-produk, melainkan satuan-vs-satuan:
 * `findByBarcode()` memakai `->first()`, jadi dua satuan berkode sama membuat pemindaian
 * memilih salah satunya SEMBARANG. Karung Rp 250.000 dan Kg Rp 12.000 bisa tertukar tanpa
 * ada yang menyadarinya sampai tutup buku.
 */
class BarcodeSatuanUnikTest extends JposTestCase
{
    private function unit(string $nama): Unit
    {
        return Unit::firstOrCreate(['name' => $nama]);
    }

    /** @return array<string,mixed> */
    private function formProduk(array $ubah = []): array
    {
        return array_merge([
            'name' => 'Produk Uji',
            'type' => 'barang',
            'unit' => 'Pcs',
            'cost_price' => 1000,
            'sell_price' => 1500,
            'stock' => 10,
            'min_stock' => 1,
            'multi_unit_enabled' => 1,
        ], $ubah);
    }

    private function barisSatuan(string $unitNama, ?string $barcode, float $harga = 25000): array
    {
        return [
            'unit_id' => $this->unit($unitNama)->id,
            'barcode' => $barcode,
            'ratio_to_previous' => 12,
            'price' => $harga,
        ];
    }

    // -----------------------------------------------------------------
    // Tabrakan antar satuan
    // -----------------------------------------------------------------

    /**
     * PALING MENENTUKAN: dua satuan berkode sama membuat pemindaian memilih sembarang -
     * dan tiap satuan punya harganya sendiri.
     */
    public function test_dua_satuan_dalam_satu_produk_tidak_boleh_berkode_sama(): void
    {
        $this->actingAs($this->admin)
            ->post(route('produk.store'), $this->formProduk([
                'units' => [
                    $this->barisSatuan('Lusin', '8990000000001', 18000),
                    $this->barisSatuan('Karung', '8990000000001', 250000),
                ],
            ]))
            ->assertSessionHasErrors('units.1.barcode');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_satuan_tidak_boleh_memakai_kode_satuan_produk_lain(): void
    {
        $this->actingAs($this->admin)->post(route('produk.store'), $this->formProduk([
            'name' => 'Beras',
            'units' => [$this->barisSatuan('Karung', '8990000000002', 250000)],
        ]))->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post(route('produk.store'), $this->formProduk([
                'name' => 'Gula',
                'units' => [$this->barisSatuan('Karung', '8990000000002', 300000)],
            ]))
            ->assertSessionHasErrors('units.0.barcode');

        $this->assertSame(1, Product::count(), 'Produk kedua tersimpan padahal kodenya bentrok.');
    }

    // -----------------------------------------------------------------
    // Tabrakan satuan dengan produk
    // -----------------------------------------------------------------

    public function test_satuan_tidak_boleh_memakai_barcode_produk_lain(): void
    {
        $this->makeProduct(['name' => 'Air Mineral', 'barcode' => '8991111111111']);

        $this->actingAs($this->admin)
            ->post(route('produk.store'), $this->formProduk([
                'name' => 'Beras',
                'units' => [$this->barisSatuan('Karung', '8991111111111')],
            ]))
            ->assertSessionHasErrors('units.0.barcode');
    }

    /**
     * Satuan menang atas produknya sendiri saat dipindai, jadi kode yang sama berarti satuan
     * dasar tidak akan pernah terpilih.
     */
    public function test_satuan_tidak_boleh_memakai_barcode_produk_induknya_sendiri(): void
    {
        $this->actingAs($this->admin)
            ->post(route('produk.store'), $this->formProduk([
                'barcode' => '8992222222222',
                'units' => [$this->barisSatuan('Karung', '8992222222222')],
            ]))
            ->assertSessionHasErrors('units.0.barcode');
    }

    /**
     * Arah sebaliknya juga dijaga: `unique:products,barcode` hanya melihat tabel products,
     * jadi tanpa pemeriksaan tambahan, produk baru bisa mengambil kode milik satuan - dan
     * karena satuan dicari lebih dulu, produk itu tidak akan pernah ketemu saat dipindai.
     */
    public function test_barcode_produk_tidak_boleh_menabrak_barcode_satuan(): void
    {
        $this->actingAs($this->admin)->post(route('produk.store'), $this->formProduk([
            'name' => 'Beras',
            'units' => [$this->barisSatuan('Karung', '8993333333333')],
        ]))->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post(route('produk.store'), $this->formProduk([
                'name' => 'Produk Baru',
                'barcode' => '8993333333333',
                'multi_unit_enabled' => 0,
            ]))
            ->assertSessionHasErrors('barcode');
    }

    // -----------------------------------------------------------------
    // Yang HARUS tetap boleh
    // -----------------------------------------------------------------

    /** Satuan tanpa kode sendiri ikut kode produknya - dan itu boleh berkali-kali. */
    public function test_barcode_satuan_boleh_dikosongkan_berkali_kali(): void
    {
        $this->actingAs($this->admin)
            ->post(route('produk.store'), $this->formProduk([
                'units' => [
                    $this->barisSatuan('Lusin', null, 18000),
                    $this->barisSatuan('Karung', '', 250000),
                    $this->barisSatuan('Pack', '   ', 9000),
                ],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(3, Product::first()->units()->count());
        $this->assertSame(0, Product::first()->units()->whereNotNull('barcode')->count());
    }

    /** Menyimpan ulang produk yang sama tanpa mengubah apa pun tidak boleh menabrak dirinya sendiri. */
    public function test_menyimpan_ulang_produk_yang_sama_tidak_dianggap_bentrok(): void
    {
        $this->actingAs($this->admin)->post(route('produk.store'), $this->formProduk([
            'name' => 'Beras',
            'barcode' => '8994444444444',
            'units' => [$this->barisSatuan('Karung', '8994444444444-K')],
        ]))->assertSessionHasNoErrors();

        $produk = Product::firstOrFail();

        $this->actingAs($this->admin)
            ->put(route('produk.update', $produk), $this->formProduk([
                'name' => 'Beras',
                'barcode' => '8994444444444',
                'units' => [$this->barisSatuan('Karung', '8994444444444-K')],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('8994444444444-K', $produk->fresh()->units->first()->barcode);
    }

    /** Kode yang berbeda tetap diterima - penjagaan ini tidak boleh menghalangi pekerjaan wajar. */
    public function test_kode_yang_berbeda_tetap_diterima(): void
    {
        $this->actingAs($this->admin)
            ->post(route('produk.store'), $this->formProduk([
                'barcode' => '8995000000000',
                'units' => [
                    $this->barisSatuan('Lusin', '8995000000001', 18000),
                    $this->barisSatuan('Karung', '8995000000002', 250000),
                ],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Product::first()->units()->whereNotNull('barcode')->count());
    }

    /** Dan yang paling penting: sesudah tersimpan, tiap kode benar-benar menunjuk ke satu satuan. */
    public function test_tiap_kode_menunjuk_tepat_satu_satuan_saat_dipindai(): void
    {
        $this->actingAs($this->admin)->post(route('produk.store'), $this->formProduk([
            'name' => 'Beras',
            'units' => [
                $this->barisSatuan('Lusin', '8996000000001', 18000),
                $this->barisSatuan('Karung', '8996000000002', 250000),
            ],
        ]))->assertSessionHasNoErrors();

        $this->actingAs($this->kasir)
            ->getJson(route('kasir.scan', ['barcode' => '8996000000002']))
            ->assertOk()
            ->assertJsonPath('unit_name', 'Karung');

        $this->actingAs($this->kasir)
            ->getJson(route('kasir.scan', ['barcode' => '8996000000001']))
            ->assertOk()
            ->assertJsonPath('unit_name', 'Lusin');
    }
}
