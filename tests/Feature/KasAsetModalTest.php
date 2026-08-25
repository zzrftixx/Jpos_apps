<?php

namespace Tests\Feature;

use App\Models\CashTransaction;
use App\Models\FixedAsset;
use App\Models\Setting;
use App\Support\Akuntansi;
use Tests\JposTestCase;

/**
 * UAT: seluruh transaksi tangan dicatat lewat menu Kas.
 *
 * Sejak 2.3.0, menu Kas adalah SATU-SATUNYA tempat transaksi dimasukkan tangan - setoran
 * modal, pengambilan pribadi (prive), dan pembelian peralatan. Neraca hanya MENAMPILKAN.
 *
 * Alasannya bukan kerapian tampilan. Satu jenis transaksi yang bisa dimasukkan dari dua
 * tempat cepat atau lambat akan diperlakukan berbeda di salah satunya, dan selisih neraca
 * yang muncul karenanya nyaris mustahil ditelusuri berbulan-bulan kemudian.
 *
 * Yang paling dijaga di sini: membeli peralatan menggerakkan DUA sisi neraca sekaligus -
 * uang keluar dan aset bertambah. Keduanya harus hidup dan mati bersama.
 */
class KasAsetModalTest extends JposTestCase
{
    private function mulaiPembukuan(float $modal = 1000000, float $kas = 1000000): void
    {
        Setting::set('pembukuan', [
            'tanggal_mulai' => now()->toDateString(),
            'saldo_awal_kas' => $kas,
            'modal_awal' => $modal,
        ]);
    }

    private function seimbang(string $kenapa): object
    {
        $posisi = Akuntansi::posisiPada(now()->toDateString());

        $this->assertSame(0.0, $posisi->selisih, sprintf(
            "%s\nAset %s = Kewajiban %s + Modal %s, selisih %s",
            $kenapa,
            number_format($posisi->total_aset), number_format($posisi->total_kewajiban),
            number_format($posisi->total_modal), number_format($posisi->selisih)
        ));

        return $posisi;
    }

    private function beliPeralatan(array $ubah = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->post('/kas', array_merge([
            'type' => 'out',
            'category' => 'aset_tetap',
            'amount' => 400000,
            'note' => 'Etalase untuk pajangan depan',
            'nama_aset' => 'Etalase kaca',
            'acquired_at' => now()->toDateString(),
            'useful_life_months' => 60,
        ], $ubah));
    }

    /* -------------------------------------------------- beli peralatan lewat kas */

    /** INI YANG PALING MENENTUKAN: satu langkah menggerakkan dua sisi neraca. */
    public function test_beli_peralatan_dari_menu_kas_membuat_aset_dan_mengeluarkan_uang(): void
    {
        $this->mulaiPembukuan();

        $this->beliPeralatan()->assertSessionHasNoErrors();

        $this->assertSame(1, FixedAsset::count(), 'Asetnya tidak terbentuk.');
        $this->assertSame(1, CashTransaction::count(), 'Kas keluarnya tidak tercatat.');

        $aset = FixedAsset::firstOrFail();
        $kas = CashTransaction::firstOrFail();

        $this->assertSame('Etalase kaca', $aset->name);
        $this->assertSame(400000.0, (float) $aset->acquisition_cost, 'Harga perolehan diambil dari jumlah kas.');
        $this->assertSame(60, $aset->useful_life_months);
        $this->assertSame($aset->id, $kas->fixed_asset_id, 'Kas dan aset tidak terhubung.');
        $this->assertSame('out', $kas->type);

        $posisi = $this->seimbang('Beli peralatan lewat menu Kas membuat neraca timpang.');

        $this->assertSame(600000.0, $posisi->kas, 'Uangnya keluar dari kas.');
        $this->assertSame(400000.0, $posisi->aset_tetap);
        $this->assertSame(0.0, $posisi->laba_ditahan, 'Membeli peralatan bukan kerugian bulan ini.');
    }

    /** Menghapus kasnya harus ikut menghapus asetnya, kalau tidak neracanya timpang. */
    public function test_menghapus_kas_pembelian_peralatan_ikut_menghapus_asetnya(): void
    {
        $this->mulaiPembukuan();
        $this->beliPeralatan()->assertSessionHasNoErrors();

        $kas = CashTransaction::firstOrFail();

        $this->actingAs($this->admin)->delete("/kas/{$kas->id}")->assertSessionHasNoErrors();

        $this->assertSame(0, CashTransaction::count());
        $this->assertSame(0, FixedAsset::count(), 'Peralatannya tertinggal, seolah didapat gratis.');

        $posisi = $this->seimbang('Menghapus kas pembelian peralatan membuat neraca timpang.');
        $this->assertSame(1000000.0, $posisi->kas);
        $this->assertSame(0.0, $posisi->aset_tetap);
    }

    /**
     * Peralatan hanya bisa DIBELI. Membiarkan kategori ini dipakai untuk kas MASUK akan
     * membuat aset bertambah sementara kas juga bertambah - dua-duanya di sisi kiri neraca,
     * tanpa pasangan di sisi kanan.
     */
    public function test_beli_peralatan_lewat_kas_masuk_ditolak(): void
    {
        $this->mulaiPembukuan();

        $this->beliPeralatan(['type' => 'in'])->assertSessionHasErrors('category');

        $this->assertSame(0, FixedAsset::count());
        $this->assertSame(0, CashTransaction::count());
    }

    public function test_beli_peralatan_tanpa_nama_ditolak(): void
    {
        $this->mulaiPembukuan();

        $this->beliPeralatan(['nama_aset' => ''])->assertSessionHasErrors('nama_aset');

        $this->assertSame(0, FixedAsset::count());
        $this->assertSame(0, CashTransaction::count(), 'Kas terlanjur tercatat walau asetnya gagal.');
    }

    /** Tanggal perolehan masa depan ditolak - sama alasannya dengan nota pembelian. */
    public function test_peralatan_bertanggal_masa_depan_ditolak(): void
    {
        $this->mulaiPembukuan();

        $this->beliPeralatan(['acquired_at' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors('acquired_at');

        $this->assertSame(0, FixedAsset::count());
    }

    /** Umur manfaat boleh kosong - etalase kaca memang tidak menyusut berarti. */
    public function test_peralatan_tanpa_umur_manfaat_diterima(): void
    {
        $this->mulaiPembukuan();

        $this->beliPeralatan(['useful_life_months' => null])->assertSessionHasNoErrors();

        $aset = FixedAsset::firstOrFail();
        $this->assertNull($aset->useful_life_months);
        $this->assertSame(0.0, $aset->penyusutanSampai(now()));

        $this->seimbang('Peralatan tanpa umur manfaat membuat neraca timpang.');
    }

    /* ---------------------------------------------------- modal & prive lewat kas */

    public function test_setoran_modal_lewat_kas_menambah_modal_bukan_laba(): void
    {
        $this->mulaiPembukuan();

        $this->actingAs($this->admin)->post('/kas', [
            'type' => 'in', 'category' => 'modal_tambahan', 'amount' => 500000, 'note' => 'Tambah modal',
        ])->assertSessionHasNoErrors();

        $posisi = $this->seimbang('Setoran modal lewat kas membuat neraca timpang.');

        $this->assertSame(1500000.0, $posisi->kas);
        $this->assertSame(500000.0, $posisi->tambahan_modal);
        $this->assertSame(0.0, $posisi->laba_ditahan, 'Menyetor uang sendiri bukan keuntungan.');
    }

    public function test_prive_lewat_kas_mengurangi_modal_bukan_laba(): void
    {
        $this->mulaiPembukuan();

        $this->actingAs($this->admin)->post('/kas', [
            'type' => 'out', 'category' => 'ambil_pribadi', 'amount' => 200000, 'note' => 'Belanja rumah',
        ])->assertSessionHasNoErrors();

        $posisi = $this->seimbang('Prive lewat kas membuat neraca timpang.');

        $this->assertSame(800000.0, $posisi->kas);
        $this->assertSame(200000.0, $posisi->prive);
        $this->assertSame(0.0, $posisi->laba_ditahan, 'Prive tidak boleh muncul sebagai kerugian.');
    }

    /* ------------------------------------------------------- neraca hanya menampilkan */

    /**
     * Neraca tidak boleh lagi punya jalan masuk sendiri untuk aset tetap. Dua jalan masuk
     * untuk satu jenis transaksi adalah bagaimana selisih neraca lahir tanpa jejak.
     */
    public function test_neraca_tidak_lagi_punya_formulir_aset_tetap(): void
    {
        $this->mulaiPembukuan();

        $this->actingAs($this->admin)->post('/laporan/neraca/aset', [
            'name' => 'Lewat jalur lama',
            'acquired_at' => now()->toDateString(),
            'acquisition_cost' => 500000,
        ])->assertNotFound();

        $this->assertSame(0, FixedAsset::count());
    }

    public function test_halaman_neraca_menampilkan_aset_dan_mengarahkan_ke_kas(): void
    {
        $this->mulaiPembukuan();
        $this->beliPeralatan()->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->get('/laporan/neraca')
            ->assertOk()
            ->assertSee('Etalase kaca')
            ->assertSee('Catat lewat menu Kas')
            ->assertDontSee('Tambah Aset');
    }

    /* ------------------------------------------------------------------- halaman kas */

    public function test_halaman_kas_menyediakan_isian_aset_tetap(): void
    {
        $this->actingAs($this->admin)->get('/kas')
            ->assertOk()
            ->assertSee('Beli Peralatan / Aset Tetap')
            ->assertSee('Nama Peralatan')
            ->assertSee('Umur Manfaat (bulan)');
    }

    public function test_kasir_tidak_bisa_mencatat_pembelian_peralatan(): void
    {
        $this->actingAs($this->kasir)->post('/kas', [
            'type' => 'out', 'category' => 'aset_tetap', 'amount' => 100000,
            'nama_aset' => 'Rak', 'acquired_at' => now()->toDateString(),
        ])->assertForbidden();

        $this->assertSame(0, FixedAsset::count());
    }
}
