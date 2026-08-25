<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Unit;
use Tests\JposTestCase;

/**
 * Dua utang lama yang dilunasi bersamaan.
 *
 * 1. PRESISI SAAT MEMBATALKAN TRANSAKSI YANG SUDAH SEBAGIAN DIRETUR.
 *
 *    `ReturnController::cancelSale()` menghitung sisa yang perlu dikembalikan ke stok dengan
 *    `$item->qty - $item->returned_qty` - TANPA `Angka::bulat()`. `KasirController` sudah
 *    memakai `Angka::bulat()` untuk perhitungan yang sama persis; baris ini tertinggal.
 *
 *    JUJUR TENTANG APA YANG DIBUKTIKAN TEST DI BAWAH. Test presisi di berkas ini LULUS baik
 *    dengan maupun tanpa `Angka::bulat()` itu - sudah dicoba dengan mengembalikan kodenya.
 *    Sebabnya nilai itu masih melewati DUA pembulatan di hilir sebelum menyentuh kolom stok:
 *
 *        Angka::keSatuanDasar()  -> memanggil Angka::bulat()
 *        Product::ubahStok()     -> memanggil Angka::bulat()
 *
 *    Jadi ini perbaikan KONSISTENSI, bukan perbaikan kerugian yang bisa ditunjukkan. Tetap
 *    dikerjakan karena satu pengecualian HUKUM 1 yang dibiarkan akan disalin ke tempat lain
 *    yang TIDAK punya pembulatan di hilir - dan di sana ia menjadi kebocoran sungguhan.
 *
 *    Yang benar-benar dijaga test di bawah karena itu bukan galat mikronya, melainkan bahwa
 *    jalur pembatalan-setelah-retur mengembalikan stok dengan benar sama sekali - jalur yang
 *    sebelumnya tidak punya test apa pun.
 *
 * 2. NOMOR NOTA RETUR DI ATAS 9.999 PER HARI.
 *
 *    `substr($last->return_no, -4)` memotong empat digit terakhir. Begitu nomornya menjadi
 *    lima digit, pembacaannya berubah jadi "0000" - urutannya kembali ke 1, dan kolom unique
 *    menolaknya dengan galat yang tidak bisa dipahami siapa pun di meja kasir.
 *
 *    Tercatat sebagai utang teknis di BLUEPRINT §14 nomor 3 sejak blueprint pertama ditulis.
 */
class PresisiDanNomorReturTest extends JposTestCase
{
    // -----------------------------------------------------------------
    // Presisi pecahan
    // -----------------------------------------------------------------

    private function produkTimbangan(): Product
    {
        Unit::firstOrCreate(['name' => 'Kg'])->update(['is_weighable' => true]);

        return $this->makeProduct([
            'name' => 'Daging Sapi',
            'unit' => 'Kg',
            'stock' => 10,
            'cost_price' => 80000,
            'sell_price' => 120000,
        ]);
    }

    /**
     * PALING MENENTUKAN: stok setelah pembatalan harus PERSIS kembali ke angka semula.
     *
     * Jual 0,3 Kg -> retur 0,2 Kg -> batalkan transaksinya. Sisa 0,1 Kg yang dikembalikan
     * harus benar-benar 0,1 - bukan 0,09999999999999998 yang menempel selamanya di stok.
     */
    public function test_membatalkan_transaksi_yang_sebagian_diretur_mengembalikan_stok_persis(): void
    {
        $produk = $this->produkTimbangan();
        $stokAwal = (float) $produk->stock;

        $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 0.3]],
            'paid_amount' => 999999,
            'payment_method' => 'cash',
        ])->assertOk();

        $penjualan = Sale::latest('id')->firstOrFail();

        $this->actingAs($this->admin)->postJson('/retur', [
            'sale_id' => $penjualan->id,
            'items' => [['sale_item_id' => $penjualan->items->first()->id, 'qty' => 0.2]],
            'reason' => 'Sebagian rusak',
        ])->assertOk();

        $this->actingAs($this->admin)
            ->post(route('retur.cancel-sale', $penjualan))
            ->assertRedirect();

        $stokAkhir = (float) $produk->fresh()->stock;

        $this->assertSame($stokAwal, $stokAkhir,
            'Stok tidak kembali persis ke angka semula - ada sisa pecahan biner yang menempel. '
            . 'Diharapkan ' . $stokAwal . ', didapat ' . $stokAkhir . '.');
    }

    /** Dan tidak ada pecahan liar yang tersimpan di kolom stok. */
    public function test_stok_tidak_menyimpan_pecahan_di_luar_skala_yang_diizinkan(): void
    {
        $produk = $this->produkTimbangan();

        $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 0.3]],
            'paid_amount' => 999999,
            'payment_method' => 'cash',
        ])->assertOk();

        $penjualan = Sale::latest('id')->firstOrFail();

        $this->actingAs($this->admin)->postJson('/retur', [
            'sale_id' => $penjualan->id,
            'items' => [['sale_item_id' => $penjualan->items->first()->id, 'qty' => 0.2]],
        ])->assertOk();

        $this->actingAs($this->admin)->post(route('retur.cancel-sale', $penjualan))->assertRedirect();

        $stok = (float) $produk->fresh()->stock;

        $this->assertSame(round($stok, 4), $stok,
            'Stok menyimpan pecahan di luar skala 4 desimal (HUKUM 1).');
    }

    /** Pembatalan biasa - tanpa retur sebelumnya - tetap berjalan seperti semula. */
    public function test_pembatalan_tanpa_retur_tetap_mengembalikan_seluruh_stok(): void
    {
        $produk = $this->produkTimbangan();
        $stokAwal = (float) $produk->stock;

        $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 2.5]],
            'paid_amount' => 999999,
            'payment_method' => 'cash',
        ])->assertOk();

        $penjualan = Sale::latest('id')->firstOrFail();

        $this->assertSame($stokAwal - 2.5, (float) $produk->fresh()->stock);

        $this->actingAs($this->admin)->post(route('retur.cancel-sale', $penjualan))->assertRedirect();

        $this->assertSame($stokAwal, (float) $produk->fresh()->stock);
    }

    // -----------------------------------------------------------------
    // Nomor nota retur
    // -----------------------------------------------------------------

    public function test_nomor_retur_berurutan_seperti_biasa(): void
    {
        $prefix = 'RET' . now()->format('ymd');

        $this->assertSame($prefix . '0001', SaleReturn::generateReturnNo());

        $this->buatNomorRetur($prefix . '0001');
        $this->assertSame($prefix . '0002', SaleReturn::generateReturnNo());
    }

    /**
     * INI UTANG §14 NOMOR 3. Di atas 9.999, `substr(-4)` membaca "0000" dan urutannya
     * kembali ke 1 - langsung menabrak nomor yang sudah ada.
     */
    public function test_nomor_retur_tidak_kembali_ke_satu_setelah_melewati_sembilan_ribu_sembilan_ratus_sembilan_puluh_sembilan(): void
    {
        $prefix = 'RET' . now()->format('ymd');

        $this->buatNomorRetur($prefix . '9999');
        $this->assertSame($prefix . '10000', SaleReturn::generateReturnNo(),
            'Nomor tidak melanjutkan ke lima digit.');

        $this->buatNomorRetur($prefix . '10000');
        $this->assertSame($prefix . '10001', SaleReturn::generateReturnNo(),
            'Nomor kembali ke urutan awal setelah lima digit - inilah yang menabrak kolom unique.');
    }

    /**
     * Nomor tertinggi dicari dengan MAX(), bukan dengan baris yang paling baru dibuat.
     * Versi lama memakai orderByDesc('id') - benar hanya selama nomornya kebetulan selalu
     * naik berurutan.
     */
    public function test_nomor_diambil_dari_yang_tertinggi_bukan_dari_baris_terbaru(): void
    {
        $prefix = 'RET' . now()->format('ymd');

        $this->buatNomorRetur($prefix . '0050');
        $this->buatNomorRetur($prefix . '0007'); // dibuat belakangan, tapi nomornya lebih kecil

        $this->assertSame($prefix . '0051', SaleReturn::generateReturnNo(),
            'Nomor diambil dari baris terbaru, bukan dari nomor tertinggi.');
    }

    /** Jaring pengaman terakhir: nomor yang ternyata sudah ada dilewati, bukan dipaksakan. */
    public function test_nomor_yang_sudah_terpakai_dilewati(): void
    {
        $prefix = 'RET' . now()->format('ymd');

        $this->buatNomorRetur($prefix . '0001');
        $this->buatNomorRetur($prefix . '0002');

        $this->assertSame($prefix . '0003', SaleReturn::generateReturnNo());
    }

    /** Nomor hari ini tidak terpengaruh nomor hari lain. */
    public function test_nomor_terpisah_per_hari(): void
    {
        $prefix = 'RET' . now()->format('ymd');

        $this->buatNomorRetur('RET2401019999');

        $this->assertSame($prefix . '0001', SaleReturn::generateReturnNo(),
            'Nomor hari ini terpengaruh nomor hari lain.');
    }

    /**
     * Satu penjualan sungguhan dipakai bersama seluruh baris retur uji: kolom `sale_id`
     * wajib diisi, dan yang diuji di sini penomorannya - bukan isi returnya.
     */
    private ?int $penjualanUjiId = null;

    private function penjualanUji(): Sale
    {
        // Properti instans, BUKAN `static`: RefreshDatabase mengosongkan database di antara
        // test sementara variabel static bertahan sepanjang proses PHP - id yang tersimpan
        // akan menunjuk ke penjualan yang sudah tidak ada.
        if ($this->penjualanUjiId === null) {
            $produk = $this->makeProduct(['name' => 'Barang Uji Nomor', 'stock' => 100]);

            $this->actingAs($this->kasir)->postJson('/kasir', [
                'items' => [['product_id' => $produk->id, 'qty' => 1]],
                'paid_amount' => 999999,
                'payment_method' => 'cash',
            ])->assertOk();

            $this->penjualanUjiId = Sale::latest('id')->value('id');
        }

        return Sale::findOrFail($this->penjualanUjiId);
    }

    private function buatNomorRetur(string $nomor): void
    {
        SaleReturn::create([
            'return_no' => $nomor,
            'sale_id' => $this->penjualanUji()->id,
            'user_id' => $this->admin->id,
            'total' => 0,
            'reason' => 'uji nomor',
        ]);
    }
}
