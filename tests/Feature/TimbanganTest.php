<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\Unit;
use App\Support\ProductCatalog;
use Illuminate\Support\Facades\DB;
use Tests\JposTestCase;

/**
 * UAT satuan timbangan.
 *
 * Izin menjual qty pecahan datang dari dua tempat yang berbeda, dan itu disengaja:
 *
 *   - satuan DASAR mengambilnya dari tabel `units`, karena "Kg" memang satuan timbangan
 *     di mana pun ia dipakai;
 *   - satuan TAMBAHAN mengambilnya dari baris product_units, karena "Kg" pada Gula yang
 *     ditimbang boleh pecahan sementara "Kg" pada produk lain yang dijual per karung tidak.
 *
 * Yang dijaga di sini adalah keduanya benar-benar berlaku, dan satuan hitung tetap menolak
 * pecahan - setengah Dus tidak ada wujudnya, dan menerimanya membuat stok toko berisi angka
 * yang tidak mungkin dicocokkan dengan barang di rak.
 */
class TimbanganTest extends JposTestCase
{
    private function jadikanTimbangan(string $nama): Unit
    {
        return tap(Unit::firstOrCreate(['name' => $nama]))->update(['is_weighable' => true]);
    }

    private function jual(Product $produk, float $qty, ?string $unitType = null): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [array_filter([
                'product_id' => $produk->id,
                'qty' => $qty,
                'unit_type' => $unitType,
            ])],
            'paid_amount' => 9999999,
            'payment_method' => 'cash',
        ]);
    }

    public function test_satuan_dasar_timbangan_menerima_qty_pecahan(): void
    {
        $this->jadikanTimbangan('Kg');
        $produk = $this->makeProduct(['unit' => 'Kg', 'stock' => 10, 'sell_price' => 20000]);

        $this->jual($produk, 2.5)->assertOk();

        $this->assertSame(7.5, (float) $produk->fresh()->stock);
        $this->assertSame(2.5, (float) Sale::firstOrFail()->items()->firstOrFail()->qty);
    }

    public function test_satuan_hitung_menolak_qty_pecahan(): void
    {
        $produk = $this->makeProduct(['unit' => 'Pcs', 'stock' => 10, 'sell_price' => 20000]);

        $this->jual($produk, 2.5)
            ->assertStatus(422)
            ->assertSee('harus bilangan bulat', false);

        $this->assertSame(10.0, (float) $produk->fresh()->stock);
        $this->assertSame(0, Sale::count());
    }

    /**
     * Satuan yang sama bisa berbeda perlakuannya antar produk, karena izinnya ditentukan
     * per baris satuan produk - bukan per satuan global.
     */
    public function test_izin_pecahan_satuan_tambahan_ditentukan_per_produk(): void
    {
        $gula = $this->makeProduct(['name' => 'Gula', 'unit' => 'Gram', 'stock' => 100000, 'sell_price' => 20]);
        $kgGula = $this->makeProductUnit($gula, 'Kg', 1000, 20000, ['allow_decimal' => true]);

        $semen = $this->makeProduct(['name' => 'Semen', 'unit' => 'Gram', 'stock' => 100000, 'sell_price' => 5]);
        $kgSemen = $this->makeProductUnit($semen, 'Kg', 1000, 5000, ['allow_decimal' => false]);

        $this->jual($gula, 2.5, 'unit_' . $kgGula->id)->assertOk();
        $this->assertSame(97500.0, (float) $gula->fresh()->stock, '2,5 Kg = 2.500 Gram.');

        $this->jual($semen, 2.5, 'unit_' . $kgSemen->id)
            ->assertStatus(422)
            ->assertSee('harus bilangan bulat', false);
        $this->assertSame(100000.0, (float) $semen->fresh()->stock);
    }

    public function test_katalog_kasir_membawa_penanda_timbangan(): void
    {
        $this->jadikanTimbangan('Kg');
        $produk = $this->makeProduct(['unit' => 'Kg', 'stock' => 10]);
        $this->makeProductUnit($produk, 'Karung', 25, 400000, ['allow_decimal' => false]);

        $katalog = app(ProductCatalog::class)->forCart();
        $baris = collect($katalog)->firstWhere('id', $produk->id);

        $this->assertTrue($baris['is_weighable'], 'Satuan dasar Kg sudah ditandai timbangan.');
        $this->assertFalse($baris['additional_units'][0]['is_weighable'], 'Karung tetap bilangan bulat.');
    }

    /**
     * Penanda timbangan tinggal di tabel units, sedangkan yang dibaca kasir adalah katalog
     * yang di-cache. Tanpa units ikut diperhitungkan, mencentang "Kg" tidak akan pernah
     * terlihat kasir sampai ada produk yang kebetulan diubah.
     */
    public function test_mencentang_satuan_timbangan_langsung_terlihat_kasir(): void
    {
        $produk = $this->makeProduct(['unit' => 'Kg', 'stock' => 10]);
        $kg = Unit::firstOrCreate(['name' => 'Kg']);

        $katalog = app(ProductCatalog::class);
        $this->assertFalse(collect($katalog->forCart())->firstWhere('id', $produk->id)['is_weighable']);

        $kg->update(['is_weighable' => true]);

        $this->assertTrue(
            collect($katalog->forCart())->firstWhere('id', $produk->id)['is_weighable'],
            'Katalog masih menyajikan penanda lama - kasir tidak akan bisa menimbang.'
        );
    }

    /**
     * Katalog dibangun untuk SELURUH produk sekaligus. Membaca penanda timbangan per produk
     * berarti satu query tambahan per produk; pada katalog 2.000 produk itu menahan server
     * yang cuma melayani satu permintaan pada satu waktu.
     */
    public function test_membangun_katalog_tidak_menambah_query_per_produk(): void
    {
        $this->jadikanTimbangan('Kg');
        for ($i = 0; $i < 12; $i++) {
            $this->makeProduct(['unit' => 'Kg', 'stock' => 10]);
        }

        app(ProductCatalog::class)->flush();

        DB::enableQueryLog();
        app(ProductCatalog::class)->forCart();
        $jumlahQuery = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(
            12,
            $jumlahQuery,
            "Jumlah query ikut bertambah mengikuti jumlah produk ({$jumlahQuery} query untuk 12 produk)."
        );
    }

    /**
     * Penanda timbangan harus benar-benar sampai ke browser, dan kotak qty harus menyesuaikan
     * jumlah desimalnya per baris. Kalau salah satunya hilang, kasir tidak bisa mengetik 0,5
     * sama sekali - dan itu tidak menimbulkan error apa pun, cuma tidak bisa dipakai.
     */
    public function test_halaman_kasir_membawa_penanda_timbangan_dan_kotak_qty_menyesuaikan(): void
    {
        $this->jadikanTimbangan('Kg');
        $this->makeProduct(['unit' => 'Kg', 'stock' => 10]);

        $halaman = $this->actingAs($this->admin)->get('/kasir')->assertOk()->getContent();

        $this->assertStringContainsString('"is_weighable":true', $halaman, 'Katalog tidak membawa penanda timbangan ke browser.');
        $this->assertStringContainsString('data-number-decimals="item.is_weighable ? 3 : 0"', $halaman);
        $this->assertStringContainsString('data-number-min="item.is_weighable ? 0.001 : 1"', $halaman);
    }

    public function test_edit_pesanan_mempertahankan_qty_pecahan(): void
    {
        $this->jadikanTimbangan('Kg');
        $produk = $this->makeProduct(['unit' => 'Kg', 'stock' => 10, 'sell_price' => 20000]);

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 2.5]],
            'paid_amount' => 10000,
            'payment_method' => 'cash',
            'is_waiting_list' => true,
        ])->assertOk();

        $sale = Sale::firstOrFail();

        $halaman = $this->actingAs($this->admin)->get("/kasir/waiting-list/{$sale->id}/edit")
            ->assertOk()->getContent();

        $this->assertStringContainsString('"qty":2.5', $halaman, 'Qty pecahan berubah jadi bulat saat pesanan dibuka untuk diedit.');
        $this->assertStringContainsString('"is_weighable":true', $halaman);
    }

    /**
     * Halaman Master Satuan harus benar-benar menampilkan penanda timbangannya, dan bisa
     * dicentang.
     *
     * Ini menangkap kelas kesalahan yang tidak menimbulkan error sama sekali: kolom
     * ditambahkan ke header tabel tapi tidak ke barisnya, sehingga seluruh sel bergeser
     * satu kolom ke kiri dan "Dipakai di Produk" tampil di kolom Timbangan. Halaman tetap
     * terbuka, tidak ada yang gagal, dan tabelnya salah.
     */
    public function test_halaman_satuan_menampilkan_penanda_timbangan(): void
    {
        $this->jadikanTimbangan('Kg');
        Unit::firstOrCreate(['name' => 'Dus']);

        $halaman = $this->actingAs($this->admin)->get('/master/satuan')->assertOk()->getContent();

        $this->assertStringContainsString('Boleh pecahan', $halaman, 'Satuan timbangan tidak diberi penanda.');
        $this->assertStringContainsString('Bilangan bulat', $halaman, 'Satuan hitung tidak diberi penanda.');
        $this->assertStringContainsString('<input type="hidden" name="is_weighable" value="0">', $halaman);

        // Jumlah kolom header harus sama dengan jumlah kolom isi.
        // Polanya <th[spasi atau >] supaya <thead> sendiri tidak ikut terhitung.
        preg_match('/<thead>.*?<\/thead>/s', $halaman, $kepala);
        preg_match('/<tbody>\s*<tr>(.*?)<\/tr>/s', $halaman, $baris);

        $this->assertSame(
            preg_match_all('/<th[\s>]/', $kepala[0] ?? ''),
            preg_match_all('/<td[\s>]/', $baris[1] ?? ''),
            'Jumlah kolom header dan kolom isi tidak sama - tabelnya bergeser.'
        );
    }

    public function test_mencentang_satuan_timbangan_tersimpan_dan_bisa_dilepas(): void
    {
        $unit = Unit::firstOrCreate(['name' => 'Kg']);

        $this->actingAs($this->admin)
            ->put("/master/satuan/{$unit->id}", ['name' => 'Kg', 'is_weighable' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertTrue((bool) $unit->fresh()->is_weighable);

        $this->actingAs($this->admin)
            ->put("/master/satuan/{$unit->id}", ['name' => 'Kg', 'is_weighable' => '0'])
            ->assertSessionHasNoErrors();
        $this->assertFalse((bool) $unit->fresh()->is_weighable, 'Melepas centang tidak tersimpan.');
    }

    public function test_retur_pecahan_dari_satuan_timbangan(): void
    {
        $this->jadikanTimbangan('Kg');
        $produk = $this->makeProduct(['unit' => 'Kg', 'stock' => 10, 'sell_price' => 20000]);

        $this->jual($produk, 2.5)->assertOk();

        $sale = Sale::firstOrFail();
        $item = $sale->items()->firstOrFail();

        $this->actingAs($this->admin)->postJson('/retur', [
            'sale_id' => $sale->id,
            'items' => [['sale_item_id' => $item->id, 'qty' => 0.5]],
            'reason' => 'Kelebihan timbang',
        ])->assertOk();

        $this->assertSame(8.0, (float) $produk->fresh()->stock);
        $this->assertSame(0.5, (float) $item->fresh()->returned_qty);
    }

    public function test_retur_melebihi_sisa_pecahan_ditolak(): void
    {
        $this->jadikanTimbangan('Kg');
        $produk = $this->makeProduct(['unit' => 'Kg', 'stock' => 10, 'sell_price' => 20000]);

        $this->jual($produk, 2.5)->assertOk();
        $item = Sale::firstOrFail()->items()->firstOrFail();

        $this->actingAs($this->admin)->postJson('/retur', [
            'sale_id' => $item->sale_id,
            'items' => [['sale_item_id' => $item->id, 'qty' => 3]],
            'reason' => 'Salah',
        ])->assertStatus(422);

        $this->assertSame(7.5, (float) $produk->fresh()->stock, 'Retur gagal tidak boleh menyentuh stok.');
    }
}
