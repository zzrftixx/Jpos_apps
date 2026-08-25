<?php

namespace Tests\Feature;

use App\Models\Rack;
use App\Models\RackSlot;
use App\Support\ProductCatalog;
use Tests\JposTestCase;

/**
 * UAT Planogram - peta rak toko.
 *
 * Yang dijaga di sini bukan tampilannya, tapi satu janji: pertanyaan "barang ini ada di
 * mana?" harus selalu punya SATU jawaban. Kalau satu produk bisa muncul di dua kotak, peta
 * ini justru lebih menyesatkan daripada tidak punya peta sama sekali.
 */
class PlanogramTest extends JposTestCase
{
    private function buatRak(int $rows = 4, int $cols = 6): Rack
    {
        return Rack::create(['name' => 'Rak A', 'rows' => $rows, 'cols' => $cols]);
    }

    private function taruh(Rack $rak, int $row, int $col, int $productId, int $facings = 1)
    {
        return $this->actingAs($this->admin)->post("/master/planogram/{$rak->id}/slot", [
            'row' => $row,
            'col' => $col,
            'product_id' => $productId,
            'facings' => $facings,
        ]);
    }

    public function test_produk_bisa_ditaruh_di_kotak(): void
    {
        $rak = $this->buatRak();
        $produk = $this->makeProduct(['name' => 'Kopi Sachet']);

        $this->taruh($rak, 1, 2, $produk->id, 3)->assertSessionHasNoErrors();

        $slot = RackSlot::firstOrFail();

        $this->assertSame(1, $slot->row);
        $this->assertSame(2, $slot->col);
        $this->assertSame(3, $slot->facings);
        // Baris & kolom ditampilkan mulai 1 - yang membaca ini orang yang berdiri di depan rak.
        $this->assertSame('Rak A 2-3', $slot->label());
    }

    /** Satu kotak satu produk: menaruh produk lain di kotak yang sama menggantikan isinya. */
    public function test_kotak_yang_sama_tidak_bisa_berisi_dua_produk(): void
    {
        $rak = $this->buatRak();
        $lama = $this->makeProduct(['name' => 'Produk Lama']);
        $baru = $this->makeProduct(['name' => 'Produk Baru']);

        $this->taruh($rak, 0, 0, $lama->id)->assertSessionHasNoErrors();
        $this->taruh($rak, 0, 0, $baru->id)->assertSessionHasNoErrors();

        $this->assertSame(1, RackSlot::count(), 'Kotak yang sama menyimpan dua baris.');
        $this->assertSame($baru->id, RackSlot::firstOrFail()->product_id);
    }

    /**
     * Satu produk satu tempat. Kalau produk yang sama bisa ada di dua kotak, peta ini justru
     * menyesatkan - karyawan yang mencari akan menemukan rak yang kosong.
     */
    public function test_produk_yang_dipindah_tidak_meninggalkan_kotak_lama(): void
    {
        $rak = $this->buatRak();
        $produk = $this->makeProduct();

        $this->taruh($rak, 0, 0, $produk->id)->assertSessionHasNoErrors();
        $this->taruh($rak, 3, 5, $produk->id)->assertSessionHasNoErrors();

        $this->assertSame(1, RackSlot::count(), 'Produk yang sama menempati dua kotak sekaligus.');

        $slot = RackSlot::firstOrFail();
        $this->assertSame(3, $slot->row);
        $this->assertSame(5, $slot->col);
    }

    public function test_kotak_bisa_dikosongkan(): void
    {
        $rak = $this->buatRak();
        $produk = $this->makeProduct();

        $this->taruh($rak, 1, 1, $produk->id)->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post("/master/planogram/{$rak->id}/slot", [
            'row' => 1, 'col' => 1, 'product_id' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, RackSlot::count());
    }

    public function test_kotak_di_luar_ukuran_rak_ditolak(): void
    {
        $rak = $this->buatRak(2, 2);
        $produk = $this->makeProduct();

        $this->taruh($rak, 5, 0, $produk->id)->assertSessionHasErrors('row');
        $this->taruh($rak, 0, 9, $produk->id)->assertSessionHasErrors('col');

        $this->assertSame(0, RackSlot::count());
    }

    /**
     * Mengecilkan rak akan membuat kotak di luar batas tidak terlihat namun tetap tersimpan -
     * produknya seolah lenyap tanpa jejak. Lebih baik ditolak.
     */
    public function test_mengecilkan_rak_yang_masih_ada_isinya_ditolak(): void
    {
        $rak = $this->buatRak(4, 6);
        $produk = $this->makeProduct();

        $this->taruh($rak, 3, 5, $produk->id)->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->put("/master/planogram/{$rak->id}", [
            'name' => 'Rak A', 'rows' => 2, 'cols' => 2,
        ])->assertSessionHasErrors('rows');

        $this->assertSame(4, $rak->fresh()->rows, 'Ukurannya tidak boleh berubah saat ditolak.');
        $this->assertSame(1, RackSlot::count(), 'Isinya harus utuh.');
    }

    public function test_mengecilkan_rak_yang_kosong_boleh(): void
    {
        $rak = $this->buatRak(4, 6);

        $this->actingAs($this->admin)->put("/master/planogram/{$rak->id}", [
            'name' => 'Rak A', 'rows' => 2, 'cols' => 3,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $rak->fresh()->rows);
        $this->assertSame(3, $rak->fresh()->cols);
    }

    /** Produk dihapus -> kotaknya ikut hilang, bukan menyisakan kotak berisi produk hantu. */
    public function test_menghapus_produk_melepas_kotaknya(): void
    {
        $rak = $this->buatRak();
        $produk = $this->makeProduct();

        $this->taruh($rak, 0, 0, $produk->id)->assertSessionHasNoErrors();

        $produk->delete();

        $this->assertSame(0, RackSlot::count());
    }

    public function test_menghapus_rak_melepas_seluruh_kotaknya(): void
    {
        $rak = $this->buatRak();
        $this->taruh($rak, 0, 0, $this->makeProduct()->id)->assertSessionHasNoErrors();
        $this->taruh($rak, 0, 1, $this->makeProduct()->id)->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->delete("/master/planogram/{$rak->id}")->assertSessionHasNoErrors();

        $this->assertSame(0, RackSlot::count());
    }

    /* ------------------------------------------------- lokasi muncul di tempat berguna */

    /**
     * INI TUJUAN UTAMANYA: kasir bisa menjawab "ada di mana?" tanpa mencari ke belakang.
     *
     * Katalog kasir disimpan di cache, jadi yang diuji sekaligus adalah cache-nya ikut
     * dibangun ulang - tanpa itu kasir masih melihat lokasi yang lama sampai ada produk yang
     * kebetulan diubah.
     */
    public function test_lokasi_rak_ikut_ke_katalog_kasir(): void
    {
        $rak = $this->buatRak();
        $produk = $this->makeProduct(['name' => 'Kopi Sachet']);

        // Katalog dibangun DULU tanpa lokasi, supaya benar-benar ada isi cache yang basi.
        $sebelum = collect(app(ProductCatalog::class)->forCart())->firstWhere('id', $produk->id);
        $this->assertNull($sebelum['rack_location'], 'Produk yang belum ditaruh di rak tidak punya lokasi.');

        $this->taruh($rak, 1, 2, $produk->id)->assertSessionHasNoErrors();

        $sesudah = collect(app(ProductCatalog::class)->forCart())->firstWhere('id', $produk->id);
        $this->assertSame('Rak A 2-3', $sesudah['rack_location'], 'Kasir masih melihat katalog yang basi.');
    }

    public function test_lokasi_rak_muncul_di_laporan_stok(): void
    {
        $rak = $this->buatRak();
        $produk = $this->makeProduct(['name' => 'Kopi Sachet']);

        $this->taruh($rak, 0, 0, $produk->id)->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->get('/laporan/stok')
            ->assertOk()
            ->assertSee('Rak A 1-1');
    }

    /**
     * Halaman penyunting kisi benar-benar dibuka - bukan cuma rutenya yang ditembak.
     *
     * Test sebelumnya menguji SIMPAN dan CETAK, tapi tidak pernah membuka halaman
     * penyuntingnya. Akibatnya cacat ini lolos: variabel kotak di dalam kisi diberi nama
     * `$produk`, sama dengan daftar produk untuk dropdown di bawahnya, sehingga kisi
     * menimpanya. Kalau kotak terakhir kosong - keadaan paling lazim - dropdown itu
     * mencoba mengulang null dan SELURUH HALAMAN gagal dimuat dengan galat 500.
     *
     * Karena itu di sini rak sengaja dibuat lebih besar daripada isinya.
     */
    public function test_halaman_penyunting_kisi_terbuka_walau_ada_kotak_kosong(): void
    {
        $rak = $this->buatRak(3, 4);
        $terisi = $this->makeProduct(['name' => 'Kopi Sachet']);
        $lain = $this->makeProduct(['name' => 'Gula Pasir']);

        // Kotak pertama diisi, sisanya - termasuk yang terakhir - dibiarkan kosong.
        $this->taruh($rak, 0, 0, $terisi->id)->assertSessionHasNoErrors();

        $respons = $this->actingAs($this->admin)->get("/master/planogram/{$rak->id}");

        $respons->assertOk()
            ->assertSee('Kopi Sachet')          // isi kotak
            ->assertSee('Gula Pasir')           // pilihan di dropdown
            ->assertSee('Kosongkan kotak ini');
    }

    /** Rak yang seluruh kotaknya terisi juga harus terbuka. */
    public function test_halaman_penyunting_kisi_terbuka_saat_semua_kotak_terisi(): void
    {
        $rak = $this->buatRak(1, 2);

        $this->taruh($rak, 0, 0, $this->makeProduct(['name' => 'Barang Satu'])->id)->assertSessionHasNoErrors();
        $this->taruh($rak, 0, 1, $this->makeProduct(['name' => 'Barang Dua'])->id)->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->get("/master/planogram/{$rak->id}")
            ->assertOk()
            ->assertSee('Barang Satu')
            ->assertSee('Barang Dua');
    }

    /** Rak yang benar-benar kosong tetap harus bisa dibuka untuk diisi pertama kali. */
    public function test_halaman_penyunting_kisi_terbuka_saat_rak_masih_kosong(): void
    {
        $rak = $this->buatRak(2, 2);
        $this->makeProduct(['name' => 'Belum Ditaruh']);

        $this->actingAs($this->admin)->get("/master/planogram/{$rak->id}")
            ->assertOk()
            ->assertSee('Belum Ditaruh');
    }

    public function test_halaman_cetak_peta_memuat_seluruh_kotak(): void
    {
        $rak = $this->buatRak(2, 2);
        $produk = $this->makeProduct(['name' => 'Kopi Sachet']);

        $this->taruh($rak, 0, 1, $produk->id)->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->get("/master/planogram/{$rak->id}/cetak")
            ->assertOk()
            ->assertSee('Kopi Sachet')
            // Kolom isian tangan - inilah alasan utama peta ini dicetak.
            ->assertSee('Fisik:');
    }

    /* -------------------------------------------------------------------------- izin */

    public function test_kasir_tidak_bisa_membuka_planogram(): void
    {
        $this->actingAs($this->kasir)->get('/master/planogram')->assertForbidden();
    }

    public function test_kunci_izin_planogram_terdaftar(): void
    {
        $this->assertArrayHasKey('planogram', \App\Models\Role::allMenuKeys());
    }
}
