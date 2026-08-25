<?php

namespace Tests\Feature;

use App\Models\CashTransaction;
use App\Models\Product;
use App\Models\Sale;
use Tests\JposTestCase;

/**
 * UAT Laporan Laba Rugi.
 *
 * Laporan ini yang dibaca pemilik toko untuk memutuskan hal-hal besar: menaikkan harga,
 * menambah karyawan, mengambil untung. Kalau angkanya salah, keputusannya ikut salah - dan
 * tidak akan ada yang menyadarinya, karena tidak ada pembanding.
 *
 * Empat hal yang dijaga di sini, semuanya pernah salah:
 *
 *   1. Menyetor modal BUKAN untung, dan mengambil uang pribadi BUKAN rugi. Keduanya
 *      memindahkan uang antara pemilik dan tokonya, bukan menghasilkan atau menghabiskan.
 *   2. Barang yang diretur tidak jadi terjual, jadi omset dan HPP-nya harus ikut batal.
 *   3. Laba bulan lalu tidak boleh berubah gara-gara harga modal hari ini diubah.
 *   4. Satu aplikasi hanya boleh punya satu definisi "omset".
 */
class AkuntansiTest extends JposTestCase
{
    private function jual(Product $produk, float $qty, float $bayar): Sale
    {
        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => $qty]],
            'paid_amount' => $bayar,
            'payment_method' => 'cash',
        ])->assertOk();

        return Sale::latest('id')->firstOrFail();
    }

    private function ringkasanLaba(): object
    {
        $respons = $this->actingAs($this->admin)->get('/laporan/laba')->assertOk();

        return $respons->viewData('summary');
    }

    /**
     * Uang yang disetor pemilik ke tokonya bukan hasil berdagang. Menghitungnya sebagai laba
     * membuat toko yang merugi terlihat untung persis sebesar uang yang baru saja disuntikkan.
     */
    public function test_menyetor_modal_tidak_menaikkan_laba(): void
    {
        $produk = $this->makeProduct(['cost_price' => 6000, 'sell_price' => 10000, 'stock' => 100]);
        $this->jual($produk, 1, 10000);

        $sebelum = $this->ringkasanLaba()->laba_bersih;

        CashTransaction::create([
            'type' => 'in',
            'category' => 'modal_tambahan',
            'amount' => 5000000,
            'note' => 'Setor modal',
            'user_id' => $this->admin->id,
        ]);

        $this->assertSame(
            $sebelum,
            $this->ringkasanLaba()->laba_bersih,
            'Menyetor modal 5 juta menaikkan "laba bersih" - padahal tidak ada satu barang pun terjual.'
        );
    }

    /**
     * Kebalikannya sama menyesatkan: pemilik yang mengambil uang untuk keperluan pribadi akan
     * melihat tokonya seolah merugi, lalu mengambil keputusan berdasarkan kerugian yang tidak
     * pernah terjadi.
     */
    public function test_mengambil_uang_pribadi_tidak_menurunkan_laba(): void
    {
        $produk = $this->makeProduct(['cost_price' => 6000, 'sell_price' => 10000, 'stock' => 100]);
        $this->jual($produk, 10, 100000);

        $sebelum = $this->ringkasanLaba()->laba_bersih;

        CashTransaction::create([
            'type' => 'out',
            'category' => 'ambil_pribadi',
            'amount' => 30000,
            'note' => 'Belanja rumah',
            'user_id' => $this->admin->id,
        ]);

        $this->assertSame(
            $sebelum,
            $this->ringkasanLaba()->laba_bersih,
            'Mengambil uang pribadi menurunkan laba - padahal itu mengurangi modal, bukan untung.'
        );
    }

    /** Beban yang sesungguhnya - listrik, gaji, sewa - memang harus mengurangi laba. */
    public function test_beban_operasional_tetap_mengurangi_laba(): void
    {
        $produk = $this->makeProduct(['cost_price' => 6000, 'sell_price' => 10000, 'stock' => 100]);
        $this->jual($produk, 10, 100000);

        $sebelum = $this->ringkasanLaba()->laba_bersih;

        CashTransaction::create([
            'type' => 'out',
            'category' => 'gaji',
            'amount' => 20000,
            'note' => 'Gaji harian',
            'user_id' => $this->admin->id,
        ]);

        $this->assertSame(
            $sebelum - 20000.0,
            $this->ringkasanLaba()->laba_bersih,
            'Beban gaji harus mengurangi laba bersih tepat sebesar nominalnya.'
        );
    }

    /**
     * Barang yang kembali ke rak tidak jadi terjual. Kalau omsetnya tetap dihitung, toko
     * yang seluruh penjualannya diretur akan tetap terlihat untung.
     */
    public function test_retur_mengurangi_omset_dan_hpp(): void
    {
        $produk = $this->makeProduct(['cost_price' => 6000, 'sell_price' => 10000, 'stock' => 100]);
        $sale = $this->jual($produk, 5, 50000);
        $item = $sale->items()->firstOrFail();

        $sebelum = $this->ringkasanLaba();
        $this->assertSame(50000.0, $sebelum->omset);
        $this->assertSame(30000.0, $sebelum->hpp);

        $this->actingAs($this->admin)->postJson('/retur', [
            'sale_id' => $sale->id,
            'items' => [['sale_item_id' => $item->id, 'qty' => 2]],
            'reason' => 'Rusak',
        ])->assertOk();

        $sesudah = $this->ringkasanLaba();

        $this->assertSame(30000.0, $sesudah->omset, '2 dari 5 diretur, jadi omset tinggal 3 x 10.000.');
        $this->assertSame(18000.0, $sesudah->hpp, 'HPP juga tinggal 3 x 6.000 - barangnya kembali ke rak.');
    }

    /**
     * INI YANG PALING HALUS. Harga modal berubah setiap kali pemilik toko membeli dengan
     * harga baru. Kalau laporan mengambil harga modal HARI INI, laba seluruh bulan lampau
     * ikut bergerak - dan laporan yang sudah dicetak, dilaporkan ke pasangan, atau dipakai
     * menghitung bonus karyawan tidak akan pernah bisa direproduksi.
     */
    public function test_mengubah_harga_modal_tidak_mengubah_laba_yang_sudah_lewat(): void
    {
        $produk = $this->makeProduct(['cost_price' => 6000, 'sell_price' => 10000, 'stock' => 100]);
        $this->jual($produk, 10, 100000);

        $sebelum = $this->ringkasanLaba();
        $this->assertSame(60000.0, $sebelum->hpp);

        // Pemasok menaikkan harga; pemilik toko memperbarui harga modalnya.
        $produk->update(['cost_price' => 9000]);

        $this->assertSame(
            60000.0,
            $this->ringkasanLaba()->hpp,
            'HPP transaksi yang sudah lewat ikut berubah saat harga modal produk diperbarui.'
        );
    }

    /**
     * Dua halaman yang sama-sama berjudul "omset" harus menampilkan angka yang sama. Kalau
     * berbeda, pemilik toko tidak punya cara tahu mana yang benar.
     */
    public function test_omset_di_laporan_omset_sama_dengan_di_laba_rugi(): void
    {
        $this->enableTax(11);
        $produk = $this->makeProduct(['cost_price' => 6000, 'sell_price' => 10000, 'stock' => 100, 'is_taxable' => true]);
        $this->jual($produk, 10, 200000);

        $dariLaba = $this->ringkasanLaba()->omset;

        $dariOmset = $this->actingAs($this->admin)->get('/laporan/omset')->assertOk()->viewData('summary');

        $this->assertSame(
            $dariLaba,
            (float) $dariOmset->total_omset,
            'Halaman Omset dan Laba Rugi menampilkan angka omset yang berbeda.'
        );
    }

    /** Pesanan yang belum lunas belum jadi penjualan, jadi belum boleh diakui sebagai omset. */
    public function test_pesanan_dp_belum_dihitung_sebagai_omset(): void
    {
        $produk = $this->makeProduct(['cost_price' => 6000, 'sell_price' => 10000, 'stock' => 100]);

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 5]],
            'paid_amount' => 20000,
            'payment_method' => 'cash',
            'is_waiting_list' => true,
        ])->assertOk();

        $this->assertSame(0.0, (float) $this->ringkasanLaba()->omset);
    }
}
